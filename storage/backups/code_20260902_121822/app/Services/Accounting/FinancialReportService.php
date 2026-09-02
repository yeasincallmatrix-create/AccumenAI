<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Financial reports derived from the ledger.
 *
 * All balances come from posted journal entries (journals.status = 'posted',
 * reversal_of IS NULL, not soft-deleted) plus per-fiscal-year opening balances.
 * Drafts and voids never appear; reversals net their originals automatically.
 *
 * A row is either a CoA account with summed debit/credit totals, or a ledger
 * detail line. Money precision is rounded to 4 decimals.
 */
class FinancialReportService
{
    public function __construct(
        private readonly ReceivablesPayablesService $arp,
    ) {}

    /**
     * Trial balance as of a date (opening balances + postings).
     *
     * @return Collection<int, object>
     */
    public function trialBalance(int $instituteId, ?int $branchId, ?string $asOfDate = null, ?int $fiscalYearId = null): Collection
    {
        $entries = $this->accountTotals($instituteId, $branchId, $asOfDate);

        $opening = $this->openingTotals($instituteId, $branchId, $fiscalYearId)
            ->keyBy('coa_id');

        $rows = $entries->map(function ($row) use ($opening) {
            $open = $opening->get($row->coa_id);
            $row->debit = round((float) $row->debit + (float) ($open->debit ?? 0), 4);
            $row->credit = round((float) $row->credit + (float) ($open->credit ?? 0), 4);
            $row->balance = round($row->debit - $row->credit, 4);

            return $row;
        })->values();

        $rows = $rows->merge($opening->filter(fn ($open) => ! $entries->contains('coa_id', $open->coa_id))->map(function ($open) {
            return (object) [
                'coa_id' => $open->coa_id,
                'code' => $open->code,
                'name' => $open->name,
                'type' => $open->type,
                'debit' => round((float) $open->debit, 4),
                'credit' => round((float) $open->credit, 4),
                'balance' => round((float) $open->debit - (float) $open->credit, 4),
            ];
        }));

        return $rows->sortBy(fn ($row) => [$this->typeOrder($row->type), $row->code])->values();
    }

    /**
     * Income statement over a date range.
     *
     * @return array{income: Collection<int, object>, expense: Collection<int, object>, total_income: float, total_expense: float, net: float}
     */
    public function incomeStatement(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null): array
    {
        $totals = $this->accountTotals($instituteId, $branchId, $to, $from);

        $income = $totals->where('type', 'income')->values()->map(function ($row) {
            $row->balance = round((float) $row->credit - (float) $row->debit, 4);

            return $row;
        })->filter(fn ($row) => abs($row->balance) > 0.0001)->values();

        $expense = $totals->where('type', 'expense')->values()->map(function ($row) {
            $row->balance = round((float) $row->debit - (float) $row->credit, 4);

            return $row;
        })->filter(fn ($row) => abs($row->balance) > 0.0001)->values();

        $totalIncome = round($income->sum('balance'), 4);
        $totalExpense = round($expense->sum('balance'), 4);

        return [
            'income' => $income,
            'expense' => $expense,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net' => round($totalIncome - $totalExpense, 4),
        ];
    }

    /**
     * Balance sheet as of a date. Net income for the current period is folded
     * into equity.
     *
     * @return array{assets: Collection<int, object>, liabilities: Collection<int, object>, equity: Collection<int, object>, total_assets: float, total_liabilities: float, total_equity: float, net_income: float}
     */
    public function balanceSheet(int $instituteId, ?int $branchId, ?string $asOfDate = null, ?int $fiscalYearId = null): array
    {
        $tb = $this->trialBalance($instituteId, $branchId, $asOfDate, $fiscalYearId);

        $assets = $tb->where('type', 'asset')->values()->map(function ($row) {
            $row->balance = $row->debit - $row->credit;

            return $row;
        })->filter(fn ($row) => abs($row->balance) > 0.0001)->values();

        $liabilities = $tb->where('type', 'liability')->values()->map(function ($row) {
            $row->balance = $row->credit - $row->debit;

            return $row;
        })->filter(fn ($row) => abs($row->balance) > 0.0001)->values();

        $equity = $tb->where('type', 'equity')->values()->map(function ($row) {
            $row->balance = $row->credit - $row->debit;

            return $row;
        })->filter(fn ($row) => abs($row->balance) > 0.0001)->values();

        $netIncome = $this->netIncome($instituteId, $branchId, $asOfDate);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => round($assets->sum('balance'), 4),
            'total_liabilities' => round($liabilities->sum('balance'), 4),
            'total_equity' => round($equity->sum('balance') + $netIncome, 4),
            'net_income' => $netIncome,
        ];
    }

    /**
     * General ledger lines for one account (or all accounts when coaId is null).
     *
     * @return Collection<int, object>
     */
    public function generalLedger(int $instituteId, ?int $branchId, ?int $coaId = null, ?string $from = null, ?string $to = null, ?int $fiscalYearId = null): Collection
    {
        $query = $this->entryQuery($instituteId, $branchId, $to, $from)
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.coa_id')
            ->leftJoin('institute_users as iu', 'iu.id', '=', 'j.created_by')
            ->select(
                'je.id',
                'j.id as journal_id',
                'j.journal_no',
                'je.journal_date',
                'coa.id as coa_id',
                'coa.code as code',
                'coa.name as account_name',
                'je.debit',
                'je.credit',
                'je.memo',
                'j.description as journal_description',
                'iu.name as created_by_name',
            )
            ->orderBy('je.journal_date')
            ->orderBy('j.id')
            ->orderBy('je.id');

        if ($coaId !== null) {
            $query->where('je.coa_id', $coaId);
        }

        $rows = $query->get();

        $opening = $this->openingTotals($instituteId, $branchId, $fiscalYearId, $coaId);

        $running = 0.0;
        $ledger = $rows->map(function ($row) use (&$running) {
            $running += (float) $row->debit - (float) $row->credit;
            $row->running_balance = round($running, 4);

            return $row;
        });

        if ($ledger->isNotEmpty()) {
            $firstAccount = $ledger->first();
            $openingDebit = (float) ($opening->get($firstAccount->coa_id)->debit ?? 0);
            $openingCredit = (float) ($opening->get($firstAccount->coa_id)->credit ?? 0);
            $running = $openingDebit - $openingCredit;

            $ledger = $ledger->map(function ($row) use (&$running) {
                $running += (float) $row->debit - (float) $row->credit;
                $row->running_balance = round($running, 4);

                return $row;
            });
        }

        return $ledger;
    }

    /**
     * Account ledger: one account's activity within a date range, with an
     * opening balance (opening balances + earlier postings), per-line running
     * balance, period totals and a closing balance.
     *
     * The account must belong to the institute (optionally branch-scoped);
     * otherwise a model-not-found exception is thrown.
     *
     * @return array{account: object, opening: float, lines: Collection<int, object>, debit: float, credit: float, closing: float}
     */
    public function accountLedger(int $instituteId, ?int $branchId, int $coaId, ?string $from = null, ?string $to = null, ?int $fiscalYearId = null): array
    {
        $account = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->where('id', $coaId)
            ->first();

        if ($account === null) {
            throw (new ModelNotFoundException)
                ->setModel(ChartOfAccount::class, [$coaId]);
        }

        $opening = 0.0;

        $openingRow = $this->openingTotals($instituteId, $branchId, $fiscalYearId, $coaId)->get($coaId);
        if ($openingRow !== null) {
            $opening += (float) $openingRow->debit - (float) $openingRow->credit;
        }

        if ($from !== null) {
            $prior = $this->entryQuery($instituteId, $branchId, null, null)
                ->where('je.coa_id', $coaId)
                ->whereDate('je.journal_date', '<', $from)
                ->selectRaw('COALESCE(SUM(je.debit), 0) - COALESCE(SUM(je.credit), 0) AS balance')
                ->value('balance');
            $opening += (float) $prior;
        }

        $lines = $this->entryQuery($instituteId, $branchId, $to, $from)
            ->where('je.coa_id', $coaId)
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.coa_id')
            ->leftJoin('institute_users as iu', 'iu.id', '=', 'j.created_by')
            ->select(
                'je.id',
                'j.id as journal_id',
                'j.journal_no',
                'je.journal_date',
                'coa.code',
                'coa.name as account_name',
                'je.debit',
                'je.credit',
                'je.memo',
                'j.description as journal_description',
                'iu.name as created_by_name',
            )
            ->orderBy('je.journal_date')
            ->orderBy('j.id')
            ->orderBy('je.id')
            ->get();

        $running = $opening;
        $lines = $lines->map(function ($row) use (&$running) {
            $running += (float) $row->debit - (float) $row->credit;
            $row->running_balance = round($running, 4);

            return $row;
        });

        return [
            'account' => (object) [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ],
            'opening' => round($opening, 4),
            'lines' => $lines,
            'debit' => round((float) $lines->sum('debit'), 4),
            'credit' => round((float) $lines->sum('credit'), 4),
            'closing' => round($running, 4),
        ];
    }

    /**
     * Cash & bank account balances (asset accounts flagged is_cash/is_bank).
     *
     * @return Collection<int, object>
     */
    public function cashBankSummary(int $instituteId, ?int $branchId, ?string $asOfDate = null, ?int $fiscalYearId = null): Collection
    {
        $accounts = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where(fn ($q) => $q->where('is_cash', true)->orWhere('is_bank', true))
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'is_cash', 'is_bank']);

        $entries = $this->accountTotals($instituteId, $branchId, $asOfDate);
        $opening = $this->openingTotals($instituteId, $branchId, $fiscalYearId);

        return $accounts->map(function ($account) use ($entries, $opening) {
            $entry = $entries->firstWhere('coa_id', $account->id);
            $open = $opening->get($account->id);

            $balance = round(
                ((float) ($entry->debit ?? 0) + (float) ($open->debit ?? 0))
                - ((float) ($entry->credit ?? 0) + (float) ($open->credit ?? 0)),
                4,
            );

            return (object) [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'is_cash' => (bool) $account->is_cash,
                'is_bank' => (bool) $account->is_bank,
                'balance' => $balance,
            ];
        })->values();
    }

    /**
     * Cash & bank flow breakdown over a date range: per-account opening balance,
     * inflow (debits), outflow (credits) and closing balance, plus totals.
     *
     * Closing equals cashBankSummary's balance for the same as-of date (same
     * posted-entry + opening-balance source), so the dashboard stays consistent
     * with the Cash/Bank report.
     *
     * @return array{accounts: Collection<int, object>, total_opening: float, total_inflow: float, total_outflow: float, total_closing: float}
     */
    public function cashBankFlows(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null, ?int $fiscalYearId = null): array
    {
        $accounts = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where(fn ($q) => $q->where('is_cash', true)->orWhere('is_bank', true))
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'is_cash', 'is_bank']);

        $ids = $accounts->pluck('id')->all();

        $opening = $this->openingTotals($instituteId, $branchId, $fiscalYearId);

        $prior = $ids !== [] && $from !== null
            ? $this->cashTotals($instituteId, $branchId, $ids, null, Carbon::parse($from)->subDay()->toDateString())
            : collect();

        $range = $ids !== []
            ? $this->cashTotals($instituteId, $branchId, $ids, $from, $to)
            : collect();

        $rows = $accounts->map(function ($account) use ($opening, $prior, $range) {
            $open = $opening->get($account->id);
            $priorRow = $prior->get($account->id);
            $rangeRow = $range->get($account->id);

            $openingBalance = round(
                ((float) ($open->debit ?? 0) - (float) ($open->credit ?? 0))
                + ((float) ($priorRow->debit ?? 0) - (float) ($priorRow->credit ?? 0)),
                4,
            );

            $inflow = round((float) ($rangeRow->debit ?? 0), 4);
            $outflow = round((float) ($rangeRow->credit ?? 0), 4);

            return (object) [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'is_cash' => (bool) $account->is_cash,
                'is_bank' => (bool) $account->is_bank,
                'opening' => $openingBalance,
                'inflow' => $inflow,
                'outflow' => $outflow,
                'closing' => round($openingBalance + $inflow - $outflow, 4),
            ];
        })->values();

        return [
            'accounts' => $rows,
            'total_opening' => round($rows->sum('opening'), 4),
            'total_inflow' => round($rows->sum('inflow'), 4),
            'total_outflow' => round($rows->sum('outflow'), 4),
            'total_closing' => round($rows->sum('closing'), 4),
        ];
    }

    /**
     * Cash flow statement (direct method) over a date range.
     *
     * Classifies cash movements by counterpart account cash_flow_category:
     * operating, investing, or financing. Signs are determined by account type:
     * - Income/expense counterpart: debit = inflow (+), credit = outflow (−)
     * - Asset/liability counterpart: debit = outflow (−), credit = inflow (+)
     *
     * @return array{operating: float, investing: float, financing: float, net_change: float, opening: float, closing: float, unclassified_amount: float}
     */
    public function cashFlowStatement(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null, ?int $fiscalYearId = null): array
    {
        // Net change in cash/bank for the period (cashBankFlows closing − opening).
        $flows = $this->cashBankFlows($instituteId, $branchId, $from, $to, $fiscalYearId);
        $opening = $flows['total_opening'];

        // Cash/bank account IDs for this institute/branch.
        $cashAccounts = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->where(fn ($q) => $q->where('is_cash', true)->orWhere('is_bank', true))
            ->where('is_active', true)
            ->get(['id']);

        $cashIds = $cashAccounts->pluck('id')->all();
        if ($cashIds === []) {
            return ['operating' => 0.0, 'investing' => 0.0, 'financing' => 0.0, 'net_change' => 0.0, 'opening' => 0.0, 'closing' => 0.0, 'unclassified_amount' => 0.0];
        }

        // Step 1: Find journal_ids that have at least one cash/bank entry in the period.
        $cashJournalIds = DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('je.institute_id', $instituteId)
            ->where('j.status', 'posted')
            ->whereNull('j.reversal_of')
            ->whereNull('je.deleted_at')
            ->whereNull('j.deleted_at')
            ->whereIn('je.coa_id', $cashIds)
            ->when($branchId !== null, fn ($q) => $q->where('je.branch_id', $branchId))
            ->when($from !== null, fn ($q) => $q->whereDate('je.journal_date', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('je.journal_date', '<=', $to))
            ->pluck('je.journal_id')
            ->unique()
            ->all();

        if ($cashJournalIds === []) {
            return ['operating' => 0.0, 'investing' => 0.0, 'financing' => 0.0, 'net_change' => 0.0, 'opening' => round($opening, 4), 'closing' => round($opening, 4), 'unclassified_amount' => 0.0];
        }

        // Step 2: Get all non-cash entries from those journals, aggregated by category.
        // Each non-cash entry appears exactly once — no row multiplication.
        $agg = DB::table('journal_entries as je')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.coa_id')
            ->where('je.institute_id', $instituteId)
            ->whereIn('je.journal_id', $cashJournalIds)
            ->whereNotIn('je.coa_id', $cashIds)
            ->whereNull('je.deleted_at')
            ->when($branchId !== null, fn ($q) => $q->where('je.branch_id', $branchId))
            ->select(
                'coa.cash_flow_category as category',
                DB::raw('COALESCE(SUM(je.credit - je.debit), 0) AS net'),
            )
            ->groupBy('coa.cash_flow_category')
            ->get()
            ->pluck('net', 'category');

        $operating = round((float) ($agg['operating'] ?? 0.0), 4);
        $investing = round((float) ($agg['investing'] ?? 0.0), 4);
        $financing = round((float) ($agg['financing'] ?? 0.0), 4);
        $classified = $operating + $investing + $financing;
        $totalCashChange = $flows['total_closing'] - $flows['total_opening'];

        return [
            'operating' => $operating,
            'investing' => $investing,
            'financing' => $financing,
            'net_change' => round($classified, 4),
            'opening' => round($opening, 4),
            'closing' => round($opening + $classified, 4),
            'unclassified_amount' => round($totalCashChange - $classified, 4),
        ];
    }

    /**
     * Net income up to a date.
     */
    public function netIncome(int $instituteId, ?int $branchId, ?string $asOfDate = null): float
    {
        $totals = $this->accountTotals($instituteId, $branchId, $asOfDate);

        $income = $totals->where('type', 'income')->sum(fn ($row) => (float) $row->credit - (float) $row->debit);
        $expense = $totals->where('type', 'expense')->sum(fn ($row) => (float) $row->debit - (float) $row->credit);

        return round($income - $expense, 4);
    }

    // ------------------------------------------------------------------
    // STEP 19 — Multi-currency reports
    // ------------------------------------------------------------------

    /**
     * Trial balance grouped by currency: each row carries foreign_amount,
     * base_amount and average_rate alongside the CoA metadata.
     *
     * @return Collection<int, object>
     */
    public function trialBalanceByCurrency(int $instituteId, ?int $branchId, ?string $asOfDate = null, ?int $fiscalYearId = null): Collection
    {
        $baseCurrencyId = app(FxConversionService::class)->baseCurrencyId($instituteId, $branchId);

        $query = $this->entryQuery($instituteId, $branchId, $asOfDate)
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.coa_id')
            ->select('je.currency_id')
            ->selectRaw('COALESCE(SUM(je.debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(je.credit), 0) AS credit')
            ->selectRaw('COALESCE(SUM(je.foreign_debit - je.foreign_credit), 0) AS foreign_net')
            ->groupBy('je.currency_id')
            ->get()
            ->filter(fn ($row) => abs((float) $row->debit) > 0.00005 || abs((float) $row->credit) > 0.00005);

        $currencyIds = $query->pluck('currency_id')->filter()->values()->unique();
        $currencies = Currency::query()->whereIn('id', $currencyIds)->get()->keyBy('id');

        return $query->values()->map(function ($row) use ($baseCurrencyId, $currencies) {
            $row->currency_id = $row->currency_id !== null ? (int) $row->currency_id : null;
            $row->foreign_amount = round((float) $row->foreign_net, 4);
            $row->base_amount = round((float) $row->debit - (float) $row->credit, 4);
            $row->debit = round((float) $row->debit, 4);
            $row->credit = round((float) $row->credit, 4);
            $row->average_rate = abs($row->foreign_amount) > 0.00005
                ? round($row->base_amount / $row->foreign_amount, 8)
                : null;
            $row->currency_code = $row->currency_id !== null
                ? ($currencies->get((int) $row->currency_id)?->code ?? 'N/A')
                : 'BASE';

            return $row;
        })->values();
    }

    /**
     * Realized and unrealized FX gains/losses derived from posted journals.
     * Scans journal entries for CoA accounts of type 'income' or 'expense'
     * whose name or memo references FX, then also pulls the unrealized
     * revaluation differences from fx_revaluations.
     *
     * @return array{realized: Collection<int, object>, unrealized: Collection<int, object>, total_realized: float, total_unrealized: float}
     */
    public function fxGainLossReport(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null): array
    {
        $baseCurrencyId = app(FxConversionService::class)->baseCurrencyId($instituteId, $branchId);

        // Realized gains/losses from journal entries on FX income/expense accounts.
        $realized = $this->entryQuery($instituteId, $branchId, $to, $from)
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.coa_id')
            ->where('coa.type', 'income')
            ->where('coa.is_active', true)
            ->where(fn ($q) => $q->where('coa.name', 'LIKE', '%FX%')->orWhere('coa.name', 'LIKE', '%Foreign%'))
            ->select('coa.id as coa_id', 'coa.code', 'coa.name')
            ->selectRaw('COALESCE(SUM(je.credit - je.debit), 0) AS amount')
            ->groupBy('coa.id', 'coa.code', 'coa.name')
            ->get()
            ->merge(
                $this->entryQuery($instituteId, $branchId, $to, $from)
                    ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.coa_id')
                    ->where('coa.type', 'expense')
                    ->where('coa.is_active', true)
                    ->where(fn ($q) => $q->where('coa.name', 'LIKE', '%FX%')->orWhere('coa.name', 'LIKE', '%Foreign%'))
                    ->select('coa.id as coa_id', 'coa.code', 'coa.name')
                    ->selectRaw('COALESCE(SUM(je.debit - je.credit), 0) AS amount')
                    ->groupBy('coa.id', 'coa.code', 'coa.name')
                    ->get()
            );

        // Unrealized gains/losses from posted revaluation adjustments.
        $unrealizedQuery = DB::table('fx_revaluations as fxr')
            ->join('journals as j', 'j.id', '=', 'fxr.journal_id')
            ->where('fxr.institute_id', $instituteId)
            ->where('fxr.status', 'posted')
            ->whereNull('j.deleted_at')
            ->when($branchId !== null, fn ($q) => $q->where('fxr.branch_id', $branchId))
            ->when($from !== null, fn ($q) => $q->whereDate('fxr.as_of_date', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('fxr.as_of_date', '<=', $to))
            ->selectRaw('COALESCE(SUM(fxr.difference), 0) AS total')
            ->value('total') ?? 0;

        $unrealizedDetail = DB::table('fx_revaluations as fxr')
            ->where('fxr.institute_id', $instituteId)
            ->where('fxr.status', 'posted')
            ->when($branchId !== null, fn ($q) => $q->where('fxr.branch_id', $branchId))
            ->when($from !== null, fn ($q) => $q->whereDate('fxr.as_of_date', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('fxr.as_of_date', '<=', $to))
            ->selectRaw('currency_id, COALESCE(SUM(difference), 0) AS amount, COUNT(*) AS revaluation_count')
            ->groupBy('currency_id')
            ->get();

        $fxCurrencyIds = $unrealizedDetail->pluck('currency_id')->filter()->values()->unique();
        $fxCurrencies = Currency::query()->whereIn('id', $fxCurrencyIds)->get()->keyBy('id');

        $unrealizedDetail = $unrealizedDetail->map(function ($row) use ($baseCurrencyId, $fxCurrencies) {
            $row->currency_id = $row->currency_id !== null ? (int) $row->currency_id : null;
            $row->currency_code = $row->currency_id !== null
                ? ($fxCurrencies->get((int) $row->currency_id)?->code ?? 'N/A')
                : 'BASE';
            $row->amount = round((float) $row->amount, 4);

            return $row;
        });

        return [
            'realized' => $realized,
            'unrealized' => $unrealizedDetail,
            'total_realized' => round((float) $realized->sum('amount'), 4),
            'total_unrealized' => round((float) $unrealizedQuery, 4),
        ];
    }

    // ------------------------------------------------------------- Internals

    private function typeOrder(string $type): int
    {
        return [
            'asset' => 1,
            'liability' => 2,
            'equity' => 3,
            'income' => 4,
            'expense' => 5,
        ][$type] ?? 9;
    }

    /**
     * Summed debit/credit per account from posted entries (optionally ranged).
     *
     * @return Collection<int, object>
     */
    private function accountTotals(int $instituteId, ?int $branchId, ?string $to = null, ?string $from = null): Collection
    {
        return $this->entryQuery($instituteId, $branchId, $to, $from)
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.coa_id')
            ->select(
                'je.coa_id',
                'coa.code',
                'coa.name',
                'coa.type',
            )
            ->selectRaw('COALESCE(SUM(je.debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(je.credit), 0) AS credit')
            ->groupBy('je.coa_id', 'coa.code', 'coa.name', 'coa.type')
            ->get();
    }

    /**
     * Opening balances per account keyed by coa_id.
     *
     * @return Collection<int, object>
     */
    private function openingTotals(int $instituteId, ?int $branchId, ?int $fiscalYearId = null, ?int $coaId = null): Collection
    {
        $query = DB::table('opening_balances as ob')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'ob.coa_id')
            ->where('ob.institute_id', $instituteId)
            ->whereNull('ob.deleted_at')
            ->when($branchId !== null, fn (Builder $q) => $q->where('ob.branch_id', $branchId))
            ->when($fiscalYearId !== null, fn (Builder $q) => $q->where('ob.fiscal_year_id', $fiscalYearId))
            ->when($coaId !== null, fn (Builder $q) => $q->where('ob.coa_id', $coaId))
            ->select(
                'ob.coa_id',
                'coa.code',
                'coa.name',
                'coa.type',
            )
            ->selectRaw('COALESCE(SUM(ob.debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(ob.credit), 0) AS credit')
            ->groupBy('ob.coa_id', 'coa.code', 'coa.name', 'coa.type');

        return $query->get()->keyBy('coa_id');
    }

    /**
     * Summed debit/credit for a set of accounts within a range, keyed by coa_id.
     * Shared by the cash/bank flow breakdown.
     */
    private function cashTotals(int $instituteId, ?int $branchId, array $coaIds, ?string $from, ?string $to): Collection
    {
        return $this->entryQuery($instituteId, $branchId, $to, $from)
            ->whereIn('je.coa_id', $coaIds)
            ->select('je.coa_id')
            ->selectRaw('COALESCE(SUM(je.debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(je.credit), 0) AS credit')
            ->groupBy('je.coa_id')
            ->get()
            ->keyBy('coa_id');
    }

    /**
     * Posted-entry base query (branched scoping handled the same way as the
     * AR/AP service: only a non-null branch filters).
     */
    private function entryQuery(int $instituteId, ?int $branchId, ?string $to = null, ?string $from = null): Builder
    {
        return DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('je.institute_id', $instituteId)
            ->where('j.status', 'posted')
            ->whereNull('j.reversal_of')
            ->whereNull('je.deleted_at')
            ->whereNull('j.deleted_at')
            ->when($branchId !== null, fn (Builder $q) => $q->where('je.branch_id', $branchId))
            ->when($from !== null, fn (Builder $q) => $q->whereDate('je.journal_date', '>=', $from))
            ->when($to !== null, fn (Builder $q) => $q->whereDate('je.journal_date', '<=', $to));
    }
}
