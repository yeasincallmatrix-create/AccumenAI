<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\ApprovalRequest;
use App\Models\FiscalYear;
use App\Models\Journal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * STEP 13 — Accounting Dashboard.
 *
 * Read/analytics orchestration over the authoritative reporting services from
 * STEPS 5/11/12. It never re-implements a formula: profit & loss figures come
 * from AccountingReportService::profitAndLoss(), receivables/payables and
 * aging from ReceivablesPayablesService, cash/bank flows from
 * FinancialReportService::cashBankFlows(), and period/year status from
 * AccountingPeriodService. Every query is scoped by the explicit
 * (instituteId, branchId) the controller resolved from the authenticated
 * user/workspace — never from request input.
 */
class AccountingDashboardService
{
    public function __construct(
        private readonly AccountingReportService $reports,
        private readonly FinancialReportService $financial,
        private readonly ReceivablesPayablesService $arp,
        private readonly AccountingPeriodService $periods,
        private readonly BudgetCalculationService $budgetCalc,
        private readonly ApprovalWorkflowService $approvalSvc,
    ) {}

    /**
     * Everything the dashboard view renders for one (institute, branch, range).
     *
     * @return array{
     *     summary: array{revenue: float, expenses: float, net: float},
     *     receivables: float,
     *     payables: float,
     *     cash: array{accounts: Collection, total_opening: float, total_inflow: float, total_outflow: float, total_closing: float},
     *     arp_aging: array{customers: array, suppliers: array},
     *     monthly: Collection,
     *     top_accounts: array{income: Collection, expense: Collection, debit: Collection, credit: Collection},
     *     recent_journals: Collection,
     *     period_status: array{fiscal_year: ?FiscalYear, period: ?AccountingPeriod, year_end: array}
     * }
     */
    public function summary(int $instituteId, ?int $branchId, string $from, string $to, ?int $fiscalYearId = null): array
    {
        $pnl = $this->reports->profitAndLoss($instituteId, $branchId, $from, $to);

        $arpTotals = $this->arp->totals($instituteId, $branchId, $to);

        $cash = $this->financial->cashBankFlows($instituteId, $branchId, $from, $to, $fiscalYearId);

        $customers = $this->arp->customerBalancesWithAging($instituteId, $branchId, $to);
        $suppliers = $this->arp->supplierBalancesWithAging($instituteId, $branchId, $to);

        $current = $this->periods->covering($instituteId, $branchId, $to);

        $budgetUtil = $fiscalYearId !== null
            ? $this->budgetUtilization($instituteId, $branchId, $fiscalYearId)
            : ['total_budget' => 0, 'total_actual' => 0, 'utilization_pct' => 0];

        $pendingApprovals = $this->pendingApprovals($instituteId);

        return [
            'summary' => [
                'revenue' => $pnl['total_income'],
                'expenses' => $pnl['total_expense'],
                'net' => $pnl['net'],
            ],
            'receivables' => $arpTotals['receivable'],
            'payables' => $arpTotals['payable'],
            'cash' => $cash,
            'arp_aging' => [
                'customers' => $this->aggregateAging($customers, 'receivable'),
                'suppliers' => $this->aggregateAging($suppliers, 'payable'),
            ],
            'monthly' => $this->monthlyRevenueExpense($instituteId, $branchId, $from, $to),
            'top_accounts' => $this->topAccounts($instituteId, $branchId, $from, $to, $pnl),
            'recent_journals' => $this->recentJournals($instituteId, $branchId, 10),
            'period_status' => [
                'fiscal_year' => $current['year'],
                'period' => $current['period'],
                'year_end' => $this->yearEndStatus($instituteId, $fiscalYearId, $current['year']),
            ],
            'budget_utilization' => $budgetUtil,
            'pending_approvals' => $pendingApprovals,
        ];
    }

    /**
     * Revenue / expense / net profit per month over a range. Each month uses
     * the same authoritative P&L calculation, so chart data always agrees with
     * the Profit & Loss report. Capped to the most recent 36 months.
     */
    public function monthlyRevenueExpense(int $instituteId, ?int $branchId, string $from, string $to): Collection
    {
        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);
        $cursor = $fromDate->copy()->startOfMonth();
        $endCursor = $toDate->copy()->startOfMonth();

        $months = collect();
        $guard = 0;

        for (; $cursor->lte($endCursor) && $guard < 36; $cursor->addMonth(), $guard++) {
            $monthStart = $cursor->copy()->max($fromDate)->startOfDay();
            $monthEnd = $cursor->copy()->endOfMonth()->min($toDate)->endOfDay();

            $pnl = $this->reports->profitAndLoss(
                $instituteId,
                $branchId,
                $monthStart->toDateString(),
                $monthEnd->toDateString(),
            );

            $months->push([
                'label' => $cursor->format('M Y'),
                'key' => $cursor->format('Y-m'),
                'revenue' => $pnl['total_income'],
                'expense' => $pnl['total_expense'],
                'profit' => $pnl['net'],
            ]);
        }

        return $months;
    }

    /**
     * Recent posted journals (tenant + branch scoped), with per-journal
     * debit/credit totals. Reversed and draft journals never appear.
     */
    public function recentJournals(int $instituteId, ?int $branchId, int $limit = 10): Collection
    {
        return Journal::query()
            ->with('entries')
            ->where('institute_id', $instituteId)
            ->where('status', 'posted')
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('journal_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Journal $journal) => (object) [
                'id' => $journal->id,
                'journal_no' => $journal->journal_no,
                'journal_date' => $journal->journal_date,
                'description' => $journal->description,
                'debit' => round((float) $journal->entries->sum('debit'), 4),
                'credit' => round((float) $journal->entries->sum('credit'), 4),
                'status' => $journal->status,
            ]);
    }

    /**
     * Highest-activity accounts in the range:
     * - top income accounts by P&L balance
     * - top expense accounts by P&L balance
     * - top accounts by debit activity
     * - top accounts by credit activity
     */
    public function topAccounts(int $instituteId, ?int $branchId, string $from, string $to, array $pnl): array
    {
        $income = collect($pnl['income'])
            ->sortByDesc('balance')
            ->take(5)
            ->values()
            ->map(fn ($row) => (object) ['code' => $row->code, 'name' => $row->name, 'amount' => round((float) $row->balance, 4)]);

        $expense = collect($pnl['expense'])
            ->sortByDesc('balance')
            ->take(5)
            ->values()
            ->map(fn ($row) => (object) ['code' => $row->code, 'name' => $row->name, 'amount' => round((float) $row->balance, 4)]);

        $activity = $this->financial->generalLedger($instituteId, $branchId, null, $from, $to)
            ->groupBy('coa_id')
            ->map(fn (Collection $lines) => (object) [
                'coa_id' => $lines->first()->coa_id,
                'code' => $lines->first()->code,
                'name' => $lines->first()->account_name,
                'debit' => round((float) $lines->sum('debit'), 4),
                'credit' => round((float) $lines->sum('credit'), 4),
            ])
            ->values();

        $toAmount = fn (Collection $rows, string $prop) => $rows
            ->map(fn ($row) => (object) [
                'coa_id' => $row->coa_id,
                'code' => $row->code,
                'name' => $row->name,
                'amount' => round((float) $row->{$prop}, 4),
            ])
            ->values();

        return [
            'income' => $income,
            'expense' => $expense,
            'debit' => $toAmount($activity->sortByDesc('debit')->take(5), 'debit'),
            'credit' => $toAmount($activity->sortByDesc('credit')->take(5), 'credit'),
        ];
    }

    /**
     * Year-end status panel for a fiscal year (the filtered year if one was
     * selected, otherwise the current year): how many of its periods are closed.
     */
    public function yearEndStatus(int $instituteId, ?int $fiscalYearId, ?FiscalYear $currentYear = null): array
    {
        $year = $fiscalYearId !== null
            ? FiscalYear::query()->where('institute_id', $instituteId)->where('id', $fiscalYearId)->first()
            : null;

        $year = $year ?? $currentYear;

        if ($year === null) {
            return ['year' => null, 'total_periods' => 0, 'closed_periods' => 0, 'days_to_end' => null];
        }

        $periods = AccountingPeriod::query()
            ->where('institute_id', $instituteId)
            ->where('fiscal_year_id', $year->id)
            ->get(['status']);

        $daysToEnd = Carbon::parse($year->end_date)->startOfDay()->diffInDays(Carbon::now()->startOfDay());

        return [
            'year' => $year,
            'total_periods' => $periods->count(),
            'closed_periods' => $periods->where('status', 'closed')->count(),
            'days_to_end' => max(0, (int) $daysToEnd),
        ];
    }

    // ------------------------------------------------------------- Internals

    /**
     * Roll per-party aging buckets into one totals row per side.
     */
    private function aggregateAging(Collection $rows, string $field): array
    {
        $current = round((float) $rows->sum(fn ($r) => $r->aging['current'] ?? 0), 4);
        $b31_60 = round((float) $rows->sum(fn ($r) => $r->aging['31_60'] ?? 0), 4);
        $b61_90 = round((float) $rows->sum(fn ($r) => $r->aging['61_90'] ?? 0), 4);
        $b91_plus = round((float) $rows->sum(fn ($r) => $r->aging['91_plus'] ?? 0), 4);

        return [
            'total' => round((float) $rows->sum($field), 4),
            'current' => $current,
            'overdue' => round($b31_60 + $b61_90 + $b91_plus, 4),
            'b31_60' => $b31_60,
            'b61_90' => $b61_90,
            'b91_plus' => $b91_plus,
        ];
    }

    /**
     * Budget utilization for the active fiscal year: total budgeted amount vs
     * total actual expense, as a percentage.
     */
    private function budgetUtilization(int $instituteId, ?int $branchId, int $fiscalYearId): array
    {
        try {
            $analysis = $this->budgetCalc->varianceAnalysis($instituteId, $branchId, $fiscalYearId);

            $totalBudget = 0;
            $totalActual = 0;

            foreach ($analysis['accounts'] ?? [] as $account) {
                $totalBudget += (float) ($account['budget'] ?? 0);
                $totalActual += (float) ($account['actual'] ?? 0);
            }

            $utilizationPct = $totalBudget > 0 ? round($totalActual / $totalBudget * 100, 1) : 0;

            return [
                'total_budget' => round($totalBudget, 4),
                'total_actual' => round($totalActual, 4),
                'utilization_pct' => $utilizationPct,
            ];
        } catch (\Throwable) {
            return ['total_budget' => 0, 'total_actual' => 0, 'utilization_pct' => 0];
        }
    }

    /**
     * Count of pending approval requests across all workflows for this tenant.
     */
    private function pendingApprovals(int $instituteId): int
    {
        return (int) ApprovalRequest::query()
            ->where('institute_id', $instituteId)
            ->where('status', 'pending_approval')
            ->count();
    }
}
