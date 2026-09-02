<?php

namespace App\Services\Accounting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * STEP 81 — Advanced Financial Reports.
 *
 * Comparative income statement, comparative balance sheet, monthly revenue
 * trend, expense analysis, and profitability dashboard.
 * All figures derived from the same ledger data as FinancialReportService.
 */
class AdvancedFinancialReportService
{
    public function __construct(
        private readonly FinancialReportService $financialReport,
    ) {}

    /**
     * Comparative income statement: current period vs prior period.
     *
     * @return array{current: array, prior: array, variance: array}
     */
    public function comparativeIncomeStatement(
        int $instituteId,
        ?int $branchId,
        string $currentFrom,
        string $currentTo,
        ?string $priorFrom = null,
        ?string $priorTo = null,
    ): array {
        $current = $this->financialReport->incomeStatement($instituteId, $branchId, $currentFrom, $currentTo);

        $priorFrom ??= Carbon::parse($currentFrom)->subYear()->toDateString();
        $priorTo ??= Carbon::parse($currentTo)->subYear()->toDateString();
        $prior = $this->financialReport->incomeStatement($instituteId, $branchId, $priorFrom, $priorTo);

        return [
            'current' => $current,
            'prior' => $prior,
            'variance' => [
                'total_income' => round($current['total_income'] - $prior['total_income'], 4),
                'total_expense' => round($current['total_expense'] - $prior['total_expense'], 4),
                'net' => round($current['net'] - $prior['net'], 4),
                'income_pct' => $prior['total_income'] != 0
                    ? round(($current['total_income'] - $prior['total_income']) / abs($prior['total_income']) * 100, 2)
                    : 0,
                'expense_pct' => $prior['total_expense'] != 0
                    ? round(($current['total_expense'] - $prior['total_expense']) / abs($prior['total_expense']) * 100, 2)
                    : 0,
                'net_pct' => $prior['net'] != 0
                    ? round(($current['net'] - $prior['net']) / abs($prior['net']) * 100, 2)
                    : 0,
            ],
            'current_from' => $currentFrom,
            'current_to' => $currentTo,
            'prior_from' => $priorFrom,
            'prior_to' => $priorTo,
        ];
    }

    /**
     * Comparative balance sheet: current date vs prior date.
     *
     * @return array{current: array, prior: array, variance: array}
     */
    public function comparativeBalanceSheet(
        int $instituteId,
        ?int $branchId,
        string $currentDate,
        ?string $priorDate = null,
        ?int $fiscalYearId = null,
    ): array {
        $current = $this->financialReport->balanceSheet($instituteId, $branchId, $currentDate, $fiscalYearId);

        $priorDate ??= Carbon::parse($currentDate)->subYear()->toDateString();
        $prior = $this->financialReport->balanceSheet($instituteId, $branchId, $priorDate, $fiscalYearId);

        return [
            'current' => $current,
            'prior' => $prior,
            'variance' => [
                'total_assets' => round($current['total_assets'] - $prior['total_assets'], 4),
                'total_liabilities' => round($current['total_liabilities'] - $prior['total_liabilities'], 4),
                'total_equity' => round($current['total_equity'] - $prior['total_equity'], 4),
                'assets_pct' => $prior['total_assets'] != 0
                    ? round(($current['total_assets'] - $prior['total_assets']) / abs($prior['total_assets']) * 100, 2)
                    : 0,
                'liabilities_pct' => $prior['total_liabilities'] != 0
                    ? round(($current['total_liabilities'] - $prior['total_liabilities']) / abs($prior['total_liabilities']) * 100, 2)
                    : 0,
                'equity_pct' => $prior['total_equity'] != 0
                    ? round(($current['total_equity'] - $prior['total_equity']) / abs($prior['total_equity']) * 100, 2)
                    : 0,
            ],
            'current_date' => $currentDate,
            'prior_date' => $priorDate,
        ];
    }

    /**
     * Monthly revenue trend: income totals per month within a date range.
     *
     * @return Collection<int, object>  { month, total_income, total_expense, net }
     */
    public function monthlyRevenueTrend(
        int $instituteId,
        ?int $branchId,
        string $from,
        string $to,
    ): Collection {
        $start = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->endOfMonth();

        $months = collect();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $monthFrom = $cursor->startOfMonth()->toDateString();
            $monthTo = $cursor->endOfMonth()->toDateString();

            $stmt = $this->financialReport->incomeStatement($instituteId, $branchId, $monthFrom, $monthTo);

            $months->push((object) [
                'month' => $cursor->format('Y-m'),
                'month_label' => $cursor->format('M Y'),
                'total_income' => $stmt['total_income'],
                'total_expense' => $stmt['total_expense'],
                'net' => $stmt['net'],
            ]);

            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Expense analysis: expense breakdown by account, grouped by top-level
     * expense account group.
     *
     * @return array{expenses: Collection, total: float, groups: Collection}
     */
    public function expenseAnalysis(
        int $instituteId,
        ?int $branchId,
        string $from,
        string $to,
    ): array {
        $totals = $this->financialReport
            ->incomeStatement($instituteId, $branchId, $from, $to);

        $expenses = collect($totals['expense'])->map(fn ($row) => (object) [
            'coa_id' => $row->coa_id,
            'code' => $row->code,
            'name' => $row->name,
            'balance' => $row->balance,
            'pct' => $totals['total_expense'] != 0
                ? round($row->balance / $totals['total_expense'] * 100, 2)
                : 0,
        ])->sortByDesc('balance')->values();

        $total = $totals['total_expense'];

        $groups = DB::table('chart_of_accounts as coa')
            ->join('account_groups as ag', 'ag.id', '=', 'coa.account_group_id')
            ->whereIn('coa.id', $expenses->pluck('coa_id'))
            ->select('ag.name as group_name', 'ag.code as group_code')
            ->get()
            ->unique('group_name')
            ->values();

        return [
            'expenses' => $expenses,
            'total' => $total,
            'groups' => $groups,
        ];
    }

    /**
     * Profitability dashboard: key metrics aggregated across a date range.
     *
     * @return array{revenue: float, cogs: float, gross_profit: float, operating_expenses: float, operating_income: float, net_income: float, gross_margin: float, operating_margin: float, net_margin: float}
     */
    public function profitabilityDashboard(
        int $instituteId,
        ?int $branchId,
        string $from,
        string $to,
    ): array {
        $stmt = $this->financialReport->incomeStatement($instituteId, $branchId, $from, $to);

        $revenue = $stmt['total_income'];
        $totalExpense = $stmt['total_expense'];

        // Estimate COGS from expense accounts with code prefix 5001-5009
        $cogs = $stmt['expense']
            ->filter(fn ($row) => str_starts_with((string) $row->code, '50'))
            ->sum('balance');

        $operatingExpenses = $totalExpense - $cogs;

        $grossProfit = $revenue - $cogs;
        $operatingIncome = $grossProfit - $operatingExpenses;
        $netIncome = $stmt['net'];

        return [
            'revenue' => round($revenue, 4),
            'cogs' => round($cogs, 4),
            'gross_profit' => round($grossProfit, 4),
            'operating_expenses' => round($operatingExpenses, 4),
            'operating_income' => round($operatingIncome, 4),
            'net_income' => round($netIncome, 4),
            'gross_margin' => $revenue != 0 ? round($grossProfit / $revenue * 100, 2) : 0,
            'operating_margin' => $revenue != 0 ? round($operatingIncome / $revenue * 100, 2) : 0,
            'net_margin' => $revenue != 0 ? round($netIncome / $revenue * 100, 2) : 0,
        ];
    }
}
