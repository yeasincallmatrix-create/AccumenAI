<?php

namespace App\Services\Accounting;

use App\Models\Currency;
use App\Models\Party;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Derived receivables & payables (AR/AP in derive mode).
 *
 * Balances are computed from posted journal lines on receivable/payable CoA
 * accounts (chart_of_accounts.is_receivable / is_payable) that carry a
 * party_id. There are no AR/AP balance tables: everything is derived on demand.
 *
 * Only entries belonging to POSTED journals count. A reversal is itself a
 * posted journal with swapped lines, so it nets the original automatically;
 * drafts and voids never appear.
 *
 * Receivables increase on the debit side; payables increase on the credit side.
 */
class ReceivablesPayablesService
{
    /**
     * Balance for a single party.
     *
     * @return array{receivable: float, payable: float, net: float}
     */
    public function partyBalance(Party $party, ?string $asOfDate = null): array
    {
        $query = $this->entryQuery($party->institute_id, $party->branch_id)
            ->where('je.party_id', $party->id)
            ->when($asOfDate !== null, fn (Builder $q) => $q->whereDate('je.journal_date', '<=', $asOfDate));

        $receivable = (float) (clone $query)
            ->where('coa.is_receivable', true)
            ->selectRaw('COALESCE(SUM(je.debit - je.credit), 0) AS b')
            ->value('b');

        $payable = (float) (clone $query)
            ->where('coa.is_payable', true)
            ->selectRaw('COALESCE(SUM(je.credit - je.debit), 0) AS b')
            ->value('b');

        return [
            'receivable' => round($receivable, 4),
            'payable' => round($payable, 4),
            'net' => round($receivable - $payable, 4),
        ];
    }

    /**
     * Balances for all customers (type customer|both).
     *
     * @return Collection<int, object>
     */
    public function customerBalances(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): Collection
    {
        return $this->balancesByType($instituteId, $branchId, 'customer', $asOfDate);
    }

    /**
     * Balances for all suppliers (type supplier|both).
     *
     * @return Collection<int, object>
     */
    public function supplierBalances(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): Collection
    {
        return $this->balancesByType($instituteId, $branchId, 'supplier', $asOfDate);
    }

    /**
     * Customer balances enriched with per-party aging buckets (AR screen).
     *
     * @return Collection<int, object>
     */
    public function customerBalancesWithAging(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): Collection
    {
        $balances = $this->balancesByType($instituteId, $branchId, 'customer', $asOfDate);
        $partyIds = $balances->pluck('id')->filter()->values();
        $parties = Party::query()->whereIn('id', $partyIds)->get()->keyBy('id');

        return $balances->map(function ($row) use ($parties, $asOfDate) {
            $party = $parties->get($row->id);
            $row->aging = $party !== null
                ? $this->aging($party, $asOfDate)
                : ['current' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '91_plus' => 0.0];

            return $row;
        });
    }

    /**
     * Supplier balances enriched with per-party aging buckets (AP screen).
     *
     * @return Collection<int, object>
     */
    public function supplierBalancesWithAging(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): Collection
    {
        $balances = $this->balancesByType($instituteId, $branchId, 'supplier', $asOfDate);
        $partyIds = $balances->pluck('id')->filter()->values();
        $parties = Party::query()->whereIn('id', $partyIds)->get()->keyBy('id');

        return $balances->map(function ($row) use ($parties, $asOfDate) {
            $party = $parties->get($row->id);
            $row->aging = $party !== null
                ? $this->aging($party, $asOfDate, 'payable')
                : ['current' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '91_plus' => 0.0];

            return $row;
        });
    }

    /**
     * Aggregated receivables / payables totals across all parties.
     *
     * @return array{receivable: float, payable: float, net: float}
     */
    public function totals(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): array
    {
        $query = $this->entryQuery($instituteId, $branchId)
            ->whereNotNull('je.party_id')
            ->when($asOfDate !== null, fn (Builder $q) => $q->whereDate('je.journal_date', '<=', $asOfDate));

        $receivable = (float) (clone $query)
            ->where('coa.is_receivable', true)
            ->selectRaw('COALESCE(SUM(je.debit - je.credit), 0) AS b')
            ->value('b');

        $payable = (float) (clone $query)
            ->where('coa.is_payable', true)
            ->selectRaw('COALESCE(SUM(je.credit - je.debit), 0) AS b')
            ->value('b');

        return [
            'receivable' => round($receivable, 4),
            'payable' => round($payable, 4),
            'net' => round($receivable - $payable, 4),
        ];
    }

    /**
     * Per-currency breakdown of customer AR balances.
     *
     * Returns a collection where each row carries:
     *   currency_id (null = base), foreign_amount, base_amount, average_rate,
     *   currency_code (resolved separately).
     *
     * @return Collection<int, object>
     */
    public function customerBalancesByCurrency(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): Collection
    {
        return $this->balancesByCurrency($instituteId, $branchId, 'customer', $asOfDate);
    }

    /**
     * Per-currency breakdown of supplier AP balances.
     *
     * @return Collection<int, object>
     */
    public function supplierBalancesByCurrency(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): Collection
    {
        return $this->balancesByCurrency($instituteId, $branchId, 'supplier', $asOfDate);
    }

    /**
     * Per-currency AR/AP totals (both directions combined).
     *
     * @return Collection<int, object>
     */
    public function totalsByCurrency(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): Collection
    {
        return $this->balancesByCurrency($instituteId, $branchId, null, $asOfDate);
    }

    /**
     * Per-currency AR breakdown for a single party.
     *
     * @return Collection<int, object>
     */
    public function partyBalancesByCurrency(Party $party, ?string $asOfDate = null): Collection
    {
        $query = $this->entryQuery($party->institute_id, $party->branch_id)
            ->where('je.party_id', $party->id)
            ->when($asOfDate !== null, fn (Builder $q) => $q->whereDate('je.journal_date', '<=', $asOfDate))
            ->select('je.currency_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN coa.is_receivable = 1 THEN je.debit - je.credit ELSE 0 END), 0) AS receivable_base')
            ->selectRaw('COALESCE(SUM(CASE WHEN coa.is_payable = 1 THEN je.credit - je.debit ELSE 0 END), 0) AS payable_base')
            ->selectRaw('COALESCE(SUM(je.foreign_debit - je.foreign_credit), 0) AS foreign_net')
            ->selectRaw('COALESCE(SUM(je.debit - je.credit), 0) AS carrying_value')
            ->groupBy('je.currency_id')
            ->get();

        $partyCurrencyIds = $query->pluck('currency_id')->filter()->values()->unique();
        $currencies = Currency::query()->whereIn('id', $partyCurrencyIds)->get()->keyBy('id');

        return $query->map(function ($row) use ($party, $currencies) {
            $row->receivable_base = round((float) $row->receivable_base, 4);
            $row->payable_base = round((float) $row->payable_base, 4);
            $row->foreign_amount = round((float) $row->foreign_net, 4);
            $row->base_amount = round((float) $row->carrying_value, 4);
            $row->average_rate = abs((float) $row->foreign_amount) > 0.00005
                ? round($row->base_amount / $row->foreign_amount, 8)
                : null;
            $row->currency_code = $row->currency_id !== null
                ? ($currencies->get((int) $row->currency_id)?->code ?? 'N/A')
                : $this->baseCurrencyCode($party->institute_id, $party->branch_id);
            $row->currency_id = $row->currency_id !== null ? (int) $row->currency_id : null;

            return $row;
        });
    }

    /**
     * Aging buckets for a party, on receivables (default) or payables.
     * Buckets are based on journal date; the payable direction sums
     * credit minus debit on is_payable accounts so supplier AP ages
     * correctly (AR screen keeps the receivable direction).
     *
     * @return array{current: float, 31_60: float, 61_90: float, 91_plus: float}
     */
    public function aging(Party $party, ?string $asOfDate = null, string $direction = 'receivable'): array
    {
        $asOf = Carbon::parse($asOfDate ?? now())->toDateString();
        $asOfLiteral = $this->quoteLiteral($asOf);
        $isPayable = $direction === 'payable';

        $rows = $this->entryQuery($party->institute_id, $party->branch_id)
            ->where('je.party_id', $party->id)
            ->where($isPayable ? 'coa.is_payable' : 'coa.is_receivable', true)
            ->whereDate('je.journal_date', '<=', $asOf)
            ->selectRaw("
                CASE
                    WHEN DATEDIFF({$asOfLiteral}, je.journal_date) <= 30 THEN 'current'
                    WHEN DATEDIFF({$asOfLiteral}, je.journal_date) <= 60 THEN '31_60'
                    WHEN DATEDIFF({$asOfLiteral}, je.journal_date) <= 90 THEN '61_90'
                    ELSE '91_plus'
                END AS bucket,
                SUM(".($isPayable ? 'je.credit - je.debit' : 'je.debit - je.credit').') AS amount
            ')
            ->groupBy('bucket')
            ->get()
            ->pluck('amount', 'bucket');

        return [
            'current' => round((float) ($rows['current'] ?? 0), 4),
            '31_60' => round((float) ($rows['31_60'] ?? 0), 4),
            '61_90' => round((float) ($rows['61_90'] ?? 0), 4),
            '91_plus' => round((float) ($rows['91_plus'] ?? 0), 4),
        ];
    }

    private function quoteLiteral(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    /**
     * Per-currency balance aggregation.
     *
     * When $partyFilter is 'customer' or 'supplier', only entries for that
     * party type are included. Null returns all directions.
     *
     * @return Collection<int, object>
     */
    private function balancesByCurrency(int $instituteId, ?int $branchId, ?string $partyFilter, ?string $asOfDate): Collection
    {
        $types = match ($partyFilter) {
            'customer' => ['customer', 'both'],
            'supplier' => ['supplier', 'both'],
            default => null,
        };

        $query = $this->entryQuery($instituteId, $branchId)
            ->when($types !== null, fn (Builder $q) => $q
                ->join('parties as p', 'p.id', '=', 'je.party_id')
                ->whereIn('p.type', $types)
                ->where('p.is_active', true))
            ->when($asOfDate !== null, fn (Builder $q) => $q->whereDate('je.journal_date', '<=', $asOfDate))
            ->select('je.currency_id')
            ->selectRaw('COALESCE(SUM(je.foreign_debit - je.foreign_credit), 0) AS foreign_net')
            ->selectRaw('COALESCE(SUM(je.debit - je.credit), 0) AS carrying_value')
            ->groupBy('je.currency_id')
            ->get()
            ->filter(fn ($row) => abs((float) $row->foreign_net) > 0.00005 || abs((float) $row->carrying_value) > 0.00005);

        $baseCurrencyId = $this->resolveBaseCurrencyId($instituteId, $branchId);

        $currencyIds = $query->pluck('currency_id')->filter()->values()->unique();
        $currencies = Currency::query()->whereIn('id', $currencyIds)->get()->keyBy('id');

        return $query->values()->map(function ($row) use ($instituteId, $branchId, $baseCurrencyId, $currencies) {
            $row->foreign_amount = round((float) $row->foreign_net, 4);
            $row->base_amount = round((float) $row->carrying_value, 4);
            $row->average_rate = abs($row->foreign_amount) > 0.00005
                ? round($row->base_amount / $row->foreign_amount, 8)
                : null;
            $row->currency_id = $row->currency_id !== null ? (int) $row->currency_id : null;
            $row->currency_code = $row->currency_id !== null
                ? ($currencies->get((int) $row->currency_id)?->code ?? 'N/A')
                : $this->baseCurrencyCode($instituteId, $branchId);

            return $row;
        })->values();
    }

    private function baseCurrencyCode(int $instituteId, ?int $branchId): string
    {
        $id = $this->resolveBaseCurrencyId($instituteId, $branchId);

        return Currency::query()->whereKey($id)->value('code') ?? 'N/A';
    }

    private function resolveBaseCurrencyId(int $instituteId, ?int $branchId): int
    {
        return app(FxConversionService::class)->baseCurrencyId($instituteId, $branchId);
    }

    /**
     * @return Collection<int, object>
     */
    private function balancesByType(int $instituteId, ?int $branchId, string $type, ?string $asOfDate): Collection
    {
        $types = $type === 'customer' ? ['customer', 'both'] : ['supplier', 'both'];

        return $this->entryQuery($instituteId, $branchId)
            ->join('parties as p', 'p.id', '=', 'je.party_id')
            ->whereIn('p.type', $types)
            ->where('p.is_active', true)
            ->when($asOfDate !== null, fn (Builder $q) => $q->whereDate('je.journal_date', '<=', $asOfDate))
            ->select(
                'p.id',
                'p.name',
                'p.phone',
                'p.type',
                'p.email',
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN coa.is_receivable = 1 THEN je.debit - je.credit ELSE 0 END), 0) AS receivable')
            ->selectRaw('COALESCE(SUM(CASE WHEN coa.is_payable = 1 THEN je.credit - je.debit ELSE 0 END), 0) AS payable')
            ->groupBy('p.id', 'p.name', 'p.phone', 'p.type', 'p.email')
            ->get()
            ->map(function ($row) {
                $row->receivable = round((float) $row->receivable, 4);
                $row->payable = round((float) $row->payable, 4);
                $row->net = round($row->receivable - $row->payable, 4);

                return $row;
            });
    }

    /**
     * Base query over posted journal entries joined to their journal and CoA.
     */
    private function entryQuery(int $instituteId, ?int $branchId): Builder
    {
        return DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.coa_id')
            ->where('je.institute_id', $instituteId)
            ->where('j.status', 'posted')
            ->whereNull('j.reversal_of')
            ->whereNull('je.deleted_at')
            ->whereNull('j.deleted_at')
            ->when($branchId !== null, fn (Builder $q) => $q->where('je.branch_id', $branchId));
    }
}
