<?php

namespace App\Services\FixedAsset;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\FixedAsset;
use App\Models\Journal;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\PaymentAccountResolverService;
use Illuminate\Validation\ValidationException;

/**
 * Fixed Asset <-> Accounting bridge (STEP 17).
 *
 * Resolves CoA accounts (category override wins, otherwise the TEMPLATE code
 * 1300/1301/5010/4010/5011/5012/4011) and posts every asset journal through
 * JournalPostingService so balance, ownership, fiscal-period locking,
 * immutability and duplicate-posting rules apply unchanged. Never hard-code ids.
 */
class FixedAssetAccountingService
{
    public function __construct(
        private readonly JournalPostingService $posting,
        private readonly AccountingSetupService $settings,
        private readonly AccountingAuditService $audit,
        private readonly PaymentAccountResolverService $paymentAccounts,
        private readonly ChartOfAccountService $chartOfAccounts,
    ) {}

    public function assetAccount(FixedAsset $asset, int $instituteId, ?int $branchId): ChartOfAccount
    {
        return $this->resolveAccount($instituteId, $branchId, $asset->category?->asset_account_id, '1300', 'fixed asset account');
    }

    public function accumulatedDepreciationAccount(FixedAsset $asset, int $instituteId, ?int $branchId): ChartOfAccount
    {
        return $this->resolveAccount($instituteId, $branchId, $asset->category?->accumulated_depreciation_account_id, '1301', 'accumulated depreciation account');
    }

    public function depreciationExpenseAccount(FixedAsset $asset, int $instituteId, ?int $branchId): ChartOfAccount
    {
        return $this->resolveAccount($instituteId, $branchId, $asset->category?->depreciation_expense_account_id, '5010', 'depreciation expense account');
    }

    public function disposalGainAccount(FixedAsset $asset, int $instituteId, ?int $branchId): ChartOfAccount
    {
        return $this->resolveAccount($instituteId, $branchId, $asset->category?->disposal_gain_account_id, '4010', 'gain on disposal account');
    }

    public function disposalLossAccount(FixedAsset $asset, int $instituteId, ?int $branchId): ChartOfAccount
    {
        return $this->resolveAccount($instituteId, $branchId, $asset->category?->disposal_loss_account_id, '5011', 'loss on disposal account');
    }

    public function impairmentAccount(FixedAsset $asset, int $instituteId, ?int $branchId): ChartOfAccount
    {
        return $this->resolveAccount($instituteId, $branchId, $asset->category?->impairment_account_id, '5012', 'impairment expense account');
    }

    public function revaluationSurplusAccount(FixedAsset $asset, int $instituteId, ?int $branchId): ChartOfAccount
    {
        return $this->resolveAccount($instituteId, $branchId, $asset->category?->impairment_account_id, '3100', 'revaluation surplus account');
    }

    /**
     * Capitalization: Dr Fixed Asset / Cr AP (vendor party) or Cr cash/bank.
     *
     * @param  array<string, mixed>  $options
     */
    public function capitalizationJournal(
        FixedAsset $asset,
        ?int $partyId,
        ?int $actorId = null,
        ?string $journalDate = null,
        array $options = [],
    ): Journal {
        $instituteId = $asset->institute_id;
        $branchId = $asset->branch_id;
        $cost = $asset->cost();

        if ($cost <= 0) {
            throw ValidationException::withMessages(['asset' => 'The capitalized cost must be greater than zero.']);
        }

        $entries = [[
            'coa_id' => $this->assetAccount($asset, $instituteId, $branchId)->id,
            'party_id' => null,
            'debit' => $cost,
            'credit' => 0,
            'memo' => 'Asset capitalization: '.$asset->name,
        ]];

        if (($options['paid_immediately'] ?? false) === true) {
            $cashAccountId = $this->paymentAccounts->resolve(
                $instituteId,
                $branchId,
                $options['payment_method_id'] ?? null,
                $options['payment_method'] ?? 'cash',
            );
            $entries[] = [
                'coa_id' => $cashAccountId,
                'party_id' => null,
                'debit' => 0,
                'credit' => $cost,
                'memo' => 'Cash/bank payment for asset',
            ];
        } else {
            $entries[] = [
                'coa_id' => $this->payableAccountId($instituteId, $branchId),
                'party_id' => $partyId,
                'debit' => 0,
                'credit' => $cost,
                'memo' => 'Supplier bill for asset',
            ];
        }

        $journal = $this->posting->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $journalDate ?? now()->toDateString(),
            'currency_id' => $options['currency_id'] ?? $this->resolveCurrencyId($instituteId, $branchId),
            'type' => 'purchase',
            'ref_type' => 'asset_capitalization',
            'ref_id' => $asset->id,
            'description' => 'Asset capitalization: '.$asset->name,
            'entries' => $entries,
        ], $actorId);

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'asset_capitalization',
            'entity_id' => $journal->id,
            'after_payload' => ['asset' => $asset->name, 'cost' => $cost, 'journal' => $journal->journal_no],
        ]);

        return $journal;
    }

    /**
     * Periodic depreciation: Dr Depreciation Expense / Cr Accumulated
     * Depreciation (per asset). Entries are keyed by asset id.
     *
     * @param  array<int, array{asset: FixedAsset, amount: float}>  $lines
     */
    public function depreciationJournal(
        int $instituteId,
        ?int $branchId,
        array $lines,
        string $periodStart,
        string $periodEnd,
        ?int $actorId = null,
    ): Journal {
        $entries = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $asset = $line['asset'];
            $amount = round((float) $line['amount'], 4);
            if ($amount <= 0) {
                continue;
            }
            $total += $amount;

            $entries[] = [
                'coa_id' => $this->depreciationExpenseAccount($asset, $instituteId, $branchId)->id,
                'party_id' => null,
                'debit' => $amount,
                'credit' => 0,
                'memo' => 'Depreciation: '.$asset->name,
                'line_meta' => ['asset_id' => $asset->id, 'period_start' => $periodStart],
            ];
            $entries[] = [
                'coa_id' => $this->accumulatedDepreciationAccount($asset, $instituteId, $branchId)->id,
                'party_id' => null,
                'debit' => 0,
                'credit' => $amount,
                'memo' => 'Accumulated depreciation: '.$asset->name,
                'line_meta' => ['asset_id' => $asset->id, 'period_start' => $periodStart],
            ];
        }

        if ($total <= 0) {
            throw ValidationException::withMessages(['lines' => 'No depreciation amount to post for this period.']);
        }

        $journal = $this->posting->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => now()->toDateString(),
            'currency_id' => $this->resolveCurrencyId($instituteId, $branchId),
            'type' => 'adjustment',
            'ref_type' => 'asset_depreciation',
            'ref_id' => null,
            'description' => 'Depreciation for '.$periodStart.' to '.$periodEnd,
            'entries' => $entries,
        ], $actorId);

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'asset_depreciation',
            'entity_id' => $journal->id,
            'after_payload' => ['amount' => $total, 'journal' => $journal->journal_no],
        ]);

        return $journal;
    }

    /**
     * Disposal: Dr Cash/Bank (proceeds), Dr Accumulated Depreciation, (Dr Loss)
     * / Cr Fixed Asset (cost), (Cr Gain). Result is a gain when proceeds > NBV.
     *
     * @param  array<string, mixed>  $options
     */
    public function disposalJournal(
        FixedAsset $asset,
        float $saleProceeds,
        float $gainLoss,
        ?int $actorId = null,
        ?string $journalDate = null,
        array $options = [],
    ): Journal {
        $instituteId = $asset->institute_id;
        $branchId = $asset->branch_id;
        $cost = $asset->cost();
        $accumulated = (float) $asset->accumulated_depreciation;

        $entries = [];

        if ($saleProceeds > 0) {
            $cashAccountId = $this->paymentAccounts->resolve(
                $instituteId,
                $branchId,
                $options['payment_method_id'] ?? null,
                $options['payment_method'] ?? 'cash',
            );
            $entries[] = ['coa_id' => $cashAccountId, 'party_id' => null, 'debit' => $saleProceeds, 'credit' => 0, 'memo' => 'Disposal proceeds: '.$asset->name];
        }

        if ($accumulated > 0) {
            $entries[] = ['coa_id' => $this->accumulatedDepreciationAccount($asset, $instituteId, $branchId)->id, 'party_id' => null, 'debit' => $accumulated, 'credit' => 0, 'memo' => 'Remove accumulated depreciation: '.$asset->name];
        }

        if ($gainLoss < 0) {
            $entries[] = ['coa_id' => $this->disposalLossAccount($asset, $instituteId, $branchId)->id, 'party_id' => null, 'debit' => abs($gainLoss), 'credit' => 0, 'memo' => 'Loss on disposal: '.$asset->name];
        } elseif ($gainLoss > 0) {
            $entries[] = ['coa_id' => $this->disposalGainAccount($asset, $instituteId, $branchId)->id, 'party_id' => null, 'debit' => 0, 'credit' => $gainLoss, 'memo' => 'Gain on disposal: '.$asset->name];
        }

        $entries[] = ['coa_id' => $this->assetAccount($asset, $instituteId, $branchId)->id, 'party_id' => null, 'debit' => 0, 'credit' => $cost, 'memo' => 'Remove fixed asset: '.$asset->name];

        $journal = $this->posting->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $journalDate ?? now()->toDateString(),
            'currency_id' => $options['currency_id'] ?? $this->resolveCurrencyId($instituteId, $branchId),
            'type' => 'journal',
            'ref_type' => 'asset_disposal',
            'ref_id' => $asset->id,
            'description' => 'Asset disposal: '.$asset->name,
            'entries' => $entries,
        ], $actorId);

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'asset_disposal',
            'entity_id' => $journal->id,
            'after_payload' => ['asset' => $asset->name, 'proceeds' => $saleProceeds, 'gain_loss' => $gainLoss],
        ]);

        return $journal;
    }

    /**
     * Impairment: Dr Impairment Expense / Cr Fixed Asset.
     */
    public function impairmentJournal(FixedAsset $asset, float $amount, ?int $actorId = null, ?string $journalDate = null): Journal
    {
        $instituteId = $asset->institute_id;
        $branchId = $asset->branch_id;

        $entries = [
            ['coa_id' => $this->impairmentAccount($asset, $instituteId, $branchId)->id, 'party_id' => null, 'debit' => $amount, 'credit' => 0, 'memo' => 'Impairment: '.$asset->name],
            ['coa_id' => $this->assetAccount($asset, $instituteId, $branchId)->id, 'party_id' => null, 'debit' => 0, 'credit' => $amount, 'memo' => 'Impairment write-down: '.$asset->name],
        ];

        $journal = $this->posting->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $journalDate ?? now()->toDateString(),
            'currency_id' => $this->resolveCurrencyId($instituteId, $branchId),
            'type' => 'adjustment',
            'ref_type' => 'asset_impairment',
            'ref_id' => $asset->id,
            'description' => 'Asset impairment: '.$asset->name,
            'entries' => $entries,
        ], $actorId);

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'asset_impairment',
            'entity_id' => $journal->id,
            'after_payload' => ['asset' => $asset->name, 'amount' => $amount],
        ]);

        return $journal;
    }

    /**
     * Revaluation (upward): Dr Fixed Asset / Cr Revaluation Surplus.
     * Downward differences are posted as Dr Revaluation Surplus / Cr Fixed Asset.
     */
    public function revaluationJournal(FixedAsset $asset, float $difference, ?int $actorId = null, ?string $journalDate = null): Journal
    {
        $instituteId = $asset->institute_id;
        $branchId = $asset->branch_id;
        $surplus = $this->revaluationSurplusAccount($asset, $instituteId, $branchId)->id;
        $assetAccount = $this->assetAccount($asset, $instituteId, $branchId)->id;

        if ($difference >= 0) {
            $entries = [
                ['coa_id' => $assetAccount, 'party_id' => null, 'debit' => $difference, 'credit' => 0, 'memo' => 'Revaluation increase: '.$asset->name],
                ['coa_id' => $surplus, 'party_id' => null, 'debit' => 0, 'credit' => $difference, 'memo' => 'Revaluation surplus: '.$asset->name],
            ];
        } else {
            $entries = [
                ['coa_id' => $surplus, 'party_id' => null, 'debit' => abs($difference), 'credit' => 0, 'memo' => 'Revaluation decrease: '.$asset->name],
                ['coa_id' => $assetAccount, 'party_id' => null, 'debit' => 0, 'credit' => abs($difference), 'memo' => 'Revaluation write-down: '.$asset->name],
            ];
        }

        $journal = $this->posting->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $journalDate ?? now()->toDateString(),
            'currency_id' => $this->resolveCurrencyId($instituteId, $branchId),
            'type' => 'journal',
            'ref_type' => 'asset_revaluation',
            'ref_id' => $asset->id,
            'description' => 'Asset revaluation: '.$asset->name,
            'entries' => $entries,
        ], $actorId);

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'asset_revaluation',
            'entity_id' => $journal->id,
            'after_payload' => ['asset' => $asset->name, 'difference' => $difference],
        ]);

        return $journal;
    }

    public function resolveCurrencyId(int $instituteId, ?int $branchId): int
    {
        $code = $this->settings->getSetting($instituteId, 'base_currency', null, $branchId);
        $currency = Currency::query()->where('code', $code)->first();

        return $currency !== null ? (int) $currency->id : 1;
    }

    private function resolveAccount(int $instituteId, ?int $branchId, ?int $overrideId, string $fallbackCode, string $label): ChartOfAccount
    {
        if ($overrideId !== null) {
            $account = ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where('id', $overrideId)
                ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->where('is_active', true)
                ->first();

            if ($account !== null) {
                return $account;
            }
        }

        $account = $this->chartOfAccounts->accountByCode($instituteId, $fallbackCode, $branchId);

        if ($account === null || ! (bool) $account->is_active) {
            throw ValidationException::withMessages([
                'account' => 'No active '.$label.' is configured for this institute. Run chart-of-account setup first.',
            ]);
        }

        return $account;
    }

    private function payableAccountId(int $instituteId, ?int $branchId): int
    {
        $account = $this->chartOfAccounts->accountByCode($instituteId, '2001', $branchId)
            ?? ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where('is_payable', true)
                ->where('is_active', true)
                ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->first();

        if ($account === null) {
            throw ValidationException::withMessages([
                'payable_account' => 'No accounts payable account is configured for this institute.',
            ]);
        }

        return (int) $account->id;
    }
}
