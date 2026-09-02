<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\FxRevaluation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Period-end foreign-currency revaluation (STEP 19).
 *
 * For every foreign currency with an open posted position, the carrying value
 * (base amounts already in the ledger) is compared against the closing-rate
 * value of the open foreign amount. The difference is posted as an
 * `adjustment` journal through JournalPostingService:
 *
 *   asset position (net debit, e.g. AR):
 *     gain → Dr receivable account / Cr unrealized FX gain
 *     loss → Dr unrealized FX loss  / Cr receivable account
 *   liability position (net credit, e.g. AP):
 *     gain → Dr payable account     / Cr unrealized FX gain
 *     loss → Dr unrealized FX loss  / Cr payable account
 *
 * Idempotency: one row per (institute, branch, fiscal year, period, currency,
 * as-of date). Re-running while `posted` returns the existing record without
 * posting again; re-running a `reversed` record posts a fresh adjustment and
 * restores the row. Source transactions are never rewritten — corrections use
 * the journal reversal convention. Closed/locked periods are rejected.
 */
class FxRevaluationService
{
    public function __construct(
        private readonly JournalPostingService $posting,
        private readonly AccountingAuditService $audit,
        private readonly FxConversionService $fx,
        private readonly ExchangeRateService $rates,
        private readonly AccountingSetupService $settings,
    ) {}

    /**
     * Run the revaluation.
     *
     * @param  array<string, mixed>  $options  as_of_date, currency_id, closing_rate
     *
     * @return array<int, FxRevaluation>
     */
    public function run(int $instituteId, ?int $branchId, array $options = [], ?int $actorId = null): array
    {
        $asOfDate = isset($options['as_of_date'])
            ? \Illuminate\Support\Carbon::parse($options['as_of_date'])->toDateString()
            : now()->toDateString();

        $fiscalYear = $this->resolveFiscalYear($instituteId, $branchId, $asOfDate);
        $period = $this->resolveOpenPeriod($instituteId, $branchId, $fiscalYear, $asOfDate);
        $baseCurrencyId = $this->fx->baseCurrencyId($instituteId, $branchId);

        $positions = $this->openPositions($instituteId, $branchId, $baseCurrencyId, $asOfDate);

        if (filled($options['currency_id'] ?? null)) {
            $positions = $positions->filter(fn ($row) => (int) $row->currency_id === (int) $options['currency_id'])->values();
        }

        if ($positions->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($positions as $position) {
            $closingRate = $this->resolveClosingRate(
                $instituteId,
                $branchId,
                (int) $position->currency_id,
                $baseCurrencyId,
                $asOfDate,
                $options['closing_rate'] ?? null,
            );

            $results[] = $this->revaluateCurrency(
                $instituteId,
                $branchId,
                $fiscalYear,
                $period,
                $position,
                $closingRate,
                $asOfDate,
                $actorId,
            );
        }

        return $results;
    }

    /**
     * Reverse a posted revaluation through the journal reversal convention.
     */
    public function reverse(FxRevaluation $revaluation, int $instituteId, ?int $actorId = null, ?string $reason = null): FxRevaluation
    {
        if ((int) $revaluation->institute_id !== $instituteId) {
            throw new \LogicException('This revaluation does not belong to the given institute.');
        }

        if ($revaluation->isReversed()) {
            throw ValidationException::withMessages([
                'revaluation' => 'This revaluation is already reversed.',
            ]);
        }

        if ($revaluation->journal_id === null) {
            throw ValidationException::withMessages([
                'revaluation' => 'This revaluation has no adjustment journal to reverse.',
            ]);
        }

        DB::transaction(function () use ($revaluation, $instituteId, $actorId, $reason) {
            $this->posting->reverse($revaluation->journal, $instituteId, $actorId, $reason ?? 'FX revaluation reversed');

            $revaluation->forceFill([
                'status' => 'reversed',
                'updated_by' => $actorId,
            ])->save();

            $this->audit->log($instituteId, [
                'branch_id' => $revaluation->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'reverse',
                'entity_type' => 'fx_revaluation',
                'entity_id' => $revaluation->id,
                'after_payload' => [
                    'currency_id' => $revaluation->currency_id,
                    'as_of_date' => $revaluation->as_of_date?->toDateString(),
                    'reason' => $reason,
                ],
            ]);
        });

        return $revaluation;
    }

    // ------------------------------------------------------------- Internals

    private function revaluateCurrency(
        int $instituteId,
        ?int $branchId,
        FiscalYear $fiscalYear,
        ?AccountingPeriod $period,
        object $position,
        string $closingRate,
        string $asOfDate,
        ?int $actorId,
    ): FxRevaluation {
        $existing = FxRevaluation::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('period_id', $period?->id)
            ->where('currency_id', (int) $position->currency_id)
            ->whereDate('as_of_date', $asOfDate)
            ->first();

        // Idempotent: an already-posted revaluation for this business key is
        // returned as-is — no duplicate accounting effect.
        if ($existing !== null && ! $existing->isReversed()) {
            return $existing;
        }

        $carrying = round((float) $position->carrying_value, 4);
        $revalued = (float) $this->fx->convert(
            number_format((float) $position->foreign_net, 4, '.', ''),
            $closingRate,
        );
        $difference = round($revalued - $carrying, 4);

        if (abs($difference) <= 0.00005) {
            if ($existing !== null) {
                $existing->forceFill([
                    'status' => 'reversed',
                    'journal_id' => null,
                    'updated_by' => $actorId,
                ])->save();
            }

            return $existing ?? FxRevaluation::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'fiscal_year_id' => $fiscalYear->id,
                'period_id' => $period?->id,
                'currency_id' => (int) $position->currency_id,
                'as_of_date' => $asOfDate,
                'closing_rate' => $closingRate,
                'carrying_value' => $carrying,
                'revalued_value' => $revalued,
                'difference' => 0,
                'journal_id' => null,
                'status' => 'reversed',
                'created_by' => $actorId,
            ]);
        }

        return DB::transaction(function () use ($instituteId, $branchId, $fiscalYear, $period, $position, $closingRate, $asOfDate, $actorId, $existing, $carrying, $revalued, $difference) {
            $journal = $this->posting->create(
                $this->adjustmentPayload($instituteId, $branchId, $position, $difference, $closingRate, $asOfDate),
                $actorId,
            );

            $attributes = [
                'closing_rate' => $closingRate,
                'carrying_value' => $carrying,
                'revalued_value' => $revalued,
                'difference' => $difference,
                'journal_id' => $journal->id,
                'status' => 'posted',
                'updated_by' => $actorId,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $revaluation = $existing;
            } else {
                $revaluation = FxRevaluation::create(array_merge($attributes, [
                    'institute_id' => $instituteId,
                    'branch_id' => $branchId,
                    'fiscal_year_id' => $fiscalYear->id,
                    'period_id' => $period?->id,
                    'currency_id' => (int) $position->currency_id,
                    'as_of_date' => $asOfDate,
                    'created_by' => $actorId,
                ]));
            }

            $this->audit->log($instituteId, [
                'branch_id' => $branchId,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'create',
                'entity_type' => 'fx_revaluation',
                'entity_id' => $revaluation->id,
                'after_payload' => [
                    'currency_id' => $revaluation->currency_id,
                    'as_of_date' => $asOfDate,
                    'closing_rate' => $closingRate,
                    'carrying_value' => $carrying,
                    'revalued_value' => $revalued,
                    'difference' => $difference,
                    'journal' => $journal->journal_no,
                ],
            ]);

            return $revaluation;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function adjustmentPayload(int $instituteId, ?int $branchId, object $position, float $difference, string $closingRate, string $asOfDate): array
    {
        $isAssetPosition = (float) $position->foreign_net >= 0;
        $isGain = $difference > 0;

        $positionAccount = $isAssetPosition
            ? $this->receivableAccount($instituteId, $branchId)
            : $this->payableAccount($instituteId, $branchId);

        $fxAccount = $isGain
            ? $this->accountBySetting($instituteId, $branchId, 'fx_unrealized_gain_account_code', '4901', 'unrealized FX gain account')
            : $this->accountBySetting($instituteId, $branchId, 'fx_unrealized_loss_account_code', '5901', 'unrealized FX loss account');

        $amount = abs($difference);
        $currencyCode = Currency::query()->whereKey((int) $position->currency_id)->value('code');

        $debitAccount = $isGain ? $positionAccount : $fxAccount;
        $creditAccount = $isGain ? $fxAccount : $positionAccount;

        return [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $asOfDate,
            'currency_id' => $this->fx->baseCurrencyId($instituteId, $branchId),
            'exchange_rate' => $closingRate,
            'type' => 'adjustment',
            'ref_type' => 'fx_revaluation',
            'description' => "FX revaluation {$currencyCode} @ {$closingRate} on {$asOfDate}",
            'entries' => [
                [
                    'coa_id' => $debitAccount->id,
                    'party_id' => null,
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => 'Unrealized FX '.($isGain ? 'gain' : 'loss')." revaluation ({$currencyCode})",
                ],
                [
                    'coa_id' => $creditAccount->id,
                    'party_id' => null,
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => 'Unrealized FX '.($isGain ? 'gain' : 'loss')." revaluation ({$currencyCode})",
                ],
            ],
        ];
    }

    /**
     * Open foreign-currency positions from posted entries (reversal pairs net
     * automatically; drafts/voids excluded).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function openPositions(int $instituteId, ?int $branchId, int $baseCurrencyId, string $asOfDate): \Illuminate\Support\Collection
    {
        return DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('je.institute_id', $instituteId)
            ->where('j.status', 'posted')
            ->whereNull('j.reversal_of')
            ->whereNull('je.deleted_at')
            ->whereNull('j.deleted_at')
            ->whereNotNull('je.currency_id')
            ->where('je.currency_id', '!=', $baseCurrencyId)
            ->whereDate('je.journal_date', '<=', $asOfDate)
            ->when($branchId !== null, fn ($query) => $query->where('je.branch_id', $branchId))
            ->select('je.currency_id')
            ->selectRaw('COALESCE(SUM(je.foreign_debit - je.foreign_credit), 0) AS foreign_net')
            ->selectRaw('COALESCE(SUM(je.debit - je.credit), 0) AS carrying_value')
            ->groupBy('je.currency_id')
            ->get()
            ->filter(fn ($row) => abs((float) $row->foreign_net) > 0.00005)
            ->values();
    }

    private function resolveFiscalYear(int $instituteId, ?int $branchId, string $asOfDate): FiscalYear
    {
        $year = FiscalYear::query()
            ->where('institute_id', $instituteId)
            ->whereDate('start_date', '<=', $asOfDate)
            ->whereDate('end_date', '>=', $asOfDate)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();

        if ($year === null) {
            throw ValidationException::withMessages([
                'as_of_date' => 'No fiscal year covers this date. Configure a fiscal year first.',
            ]);
        }

        if ($year->isClosed()) {
            throw ValidationException::withMessages([
                'as_of_date' => 'FX revaluation is not allowed in a closed fiscal year.',
            ]);
        }

        return $year;
    }

    private function resolveOpenPeriod(int $instituteId, ?int $branchId, FiscalYear $fiscalYear, string $asOfDate): ?AccountingPeriod
    {
        $hasPeriods = AccountingPeriod::query()
            ->where('institute_id', $instituteId)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->exists();

        if (! $hasPeriods) {
            return null;
        }

        $period = AccountingPeriod::query()
            ->where('institute_id', $instituteId)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->whereDate('start_date', '<=', $asOfDate)
            ->whereDate('end_date', '>=', $asOfDate)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();

        if ($period === null) {
            throw ValidationException::withMessages([
                'as_of_date' => 'No accounting period covers this date.',
            ]);
        }

        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'as_of_date' => 'FX revaluation is not allowed in a closed or locked period.',
            ]);
        }

        return $period;
    }

    private function resolveClosingRate(int $instituteId, ?int $branchId, int $fromCurrencyId, int $baseCurrencyId, string $asOfDate, ?string $explicitRate): string
    {
        $resolved = $this->fx->resolveRate(
            $instituteId,
            $branchId,
            $fromCurrencyId,
            $baseCurrencyId,
            $asOfDate,
            filled($explicitRate) ? (string) $explicitRate : null,
        );

        return $resolved['rate'];
    }

    private function receivableAccount(int $instituteId, ?int $branchId): ChartOfAccount
    {
        $account = app(ChartOfAccountService::class)->accountByCode($instituteId, '1100', $branchId)
            ?? ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where(fn ($query) => $query
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id'))
                ->where('is_receivable', true)
                ->where('is_active', true)
                ->first();

        if ($account === null) {
            throw new \RuntimeException('No receivable account is configured for this institute.');
        }

        return $account;
    }

    private function payableAccount(int $instituteId, ?int $branchId): ChartOfAccount
    {
        $account = app(ChartOfAccountService::class)->accountByCode($instituteId, '2001', $branchId)
            ?? ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where(fn ($query) => $query
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id'))
                ->where('is_payable', true)
                ->where('is_active', true)
                ->first();

        if ($account === null) {
            throw new \RuntimeException('No payable account is configured for this institute.');
        }

        return $account;
    }

    private function accountBySetting(int $instituteId, ?int $branchId, string $settingKey, string $defaultCode, string $label): ChartOfAccount
    {
        $code = (string) $this->settings->getSetting($instituteId, $settingKey, $defaultCode, $branchId);

        $account = app(ChartOfAccountService::class)->accountByCode($instituteId, $code, $branchId)
            ?? ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where(fn ($query) => $query
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id'))
                ->where('code', $code)
                ->where('is_active', true)
                ->first();

        if ($account === null) {
            throw new \RuntimeException("No {$label} (code {$code}) is configured for this institute.");
        }

        return $account;
    }
}
