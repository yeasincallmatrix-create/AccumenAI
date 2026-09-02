<?php

namespace App\Services\Accounting;

use App\Models\CrmLead;
use App\Models\CrmLeadStatus;
use App\Models\HrAttendance;
use App\Models\HrDepartment;
use App\Models\HrEmployee;
use App\Models\HrLeaveApplication;
use App\Models\HrPayroll;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\SalesDelivery;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Services\Inventory\InventoryReportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * STEP 90 — Executive Dashboard & BI Analytics.
 *
 * High-level KPIs, revenue analytics, profit analysis, cash forecasting and
 * business insights. All data is derived from existing accounting and
 * inventory reporting services — no balance tables are written.
 *
 * Every query is tenant-scoped and branch-aware.
 */
class ExecutiveDashboardService
{
    public function __construct(
        private readonly FinancialReportService $financialReports,
        private readonly AccountingReportService $accountingReports,
        private readonly ReceivablesPayablesService $arp,
        private readonly InventoryReportService $inventoryReports,
    ) {}

    /**
     * Core KPI summary: revenue, expenses, net income, cash balance,
     * AR, AP, active customers, active suppliers.
     */
    public function kpiSummary(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null): array
    {
        $from ??= now()->startOfYear()->toDateString();
        $to ??= now()->toDateString();

        $pnl = $this->accountingReports->profitAndLoss($instituteId, $branchId, $from, $to);
        $cash = $this->financialReports->cashBankSummary($instituteId, $branchId, $to);
        $arpTotals = $this->arp->totals($instituteId, $branchId, $to);

        $activeCustomers = Party::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereIn('type', ['customer', 'both'])
            ->where('is_active', true)
            ->count();

        $activeSuppliers = Party::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereIn('type', ['supplier', 'both'])
            ->where('is_active', true)
            ->count();

        return [
            'total_revenue' => $pnl['total_income'],
            'total_expenses' => $pnl['total_expense'],
            'net_income' => $pnl['net'],
            'cash_balance' => round((float) $cash->sum('balance'), 4),
            'accounts_receivable' => $arpTotals['receivable'],
            'accounts_payable' => $arpTotals['payable'],
            'active_customers' => $activeCustomers,
            'active_suppliers' => $activeSuppliers,
        ];
    }

    /**
     * Revenue analytics: monthly trend, top revenue accounts, period comparison.
     */
    public function revenueAnalytics(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null): array
    {
        $from ??= now()->startOfYear()->toDateString();
        $to ??= now()->toDateString();

        $monthly = $this->monthlyRevenueExpense($instituteId, $branchId, $from, $to);

        $topAccounts = $this->topRevenueAccounts($instituteId, $branchId, $from, $to);

        $periodComparison = $this->revenuePeriodComparison($instituteId, $branchId, $from, $to);

        return [
            'monthly' => $monthly,
            'top_accounts' => $topAccounts,
            'period_comparison' => $periodComparison,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Profit analysis: gross profit, net profit, margins, expense breakdown.
     */
    public function profitAnalysis(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null): array
    {
        $from ??= now()->startOfYear()->toDateString();
        $to ??= now()->toDateString();

        $pnl = $this->accountingReports->profitAndLoss($instituteId, $branchId, $from, $to);

        $totalIncome = $pnl['total_income'];
        $totalExpense = $pnl['total_expense'];
        $netIncome = $pnl['net'];

        $grossProfit = $totalIncome;
        $grossMargin = $totalIncome > 0 ? round(($grossProfit / $totalIncome) * 100, 2) : 0.0;
        $netMargin = $totalIncome > 0 ? round(($netIncome / $totalIncome) * 100, 2) : 0.0;

        $expenseBreakdown = $pnl['expense']->map(fn ($row) => [
            'code' => $row->code,
            'name' => $row->name,
            'amount' => $row->balance,
            'percentage' => $totalExpense > 0 ? round(($row->balance / $totalExpense) * 100, 2) : 0.0,
        ])->values();

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_income' => $netIncome,
            'gross_profit' => $grossProfit,
            'gross_margin' => $grossMargin,
            'net_margin' => $netMargin,
            'expense_breakdown' => $expenseBreakdown,
            'income_breakdown' => $pnl['income']->map(fn ($row) => [
                'code' => $row->code,
                'name' => $row->name,
                'amount' => $row->balance,
                'percentage' => $totalIncome > 0 ? round(($row->balance / $totalIncome) * 100, 2) : 0.0,
            ])->values(),
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Cash forecast: current cash position, projected inflow (AR aging),
     * projected outflow (AP aging), projected balance.
     */
    public function cashForecast(int $instituteId, ?int $branchId, ?string $asOfDate = null): array
    {
        $asOfDate ??= now()->toDateString();

        $cashSummary = $this->financialReports->cashBankSummary($instituteId, $branchId, $asOfDate);
        $currentBalance = round((float) $cashSummary->sum('balance'), 4);

        $arpTotals = $this->arp->totals($instituteId, $branchId, $asOfDate);

        $customerAging = $this->arp->customerBalancesWithAging($instituteId, $branchId, $asOfDate);
        $supplierAging = $this->arp->supplierBalancesWithAging($instituteId, $branchId, $asOfDate);

        $projectedInflow = $this->estimateInflow($customerAging);
        $projectedOutflow = $this->estimateOutflow($supplierAging);

        return [
            'current_balance' => $currentBalance,
            'projected_inflow' => $projectedInflow,
            'projected_outflow' => $projectedOutflow,
            'projected_balance' => round($currentBalance + $projectedInflow - $projectedOutflow, 4),
            'ar_total' => $arpTotals['receivable'],
            'ap_total' => $arpTotals['payable'],
            'customer_aging' => $customerAging,
            'supplier_aging' => $supplierAging,
            'as_of_date' => $asOfDate,
        ];
    }

    /**
     * Business insights: top customers by revenue, top suppliers by spend,
     * low stock alerts, overdue invoices.
     */
    public function businessInsights(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null): array
    {
        $from ??= now()->startOfYear()->toDateString();
        $to ??= now()->toDateString();

        $topCustomers = $this->topCustomersByRevenue($instituteId, $branchId, $from, $to);
        $topSuppliers = $this->topSuppliersBySpend($instituteId, $branchId, $from, $to);
        $lowStock = $this->inventoryReports->lowStock($instituteId, $branchId);
        $overdueInvoices = $this->overdueInvoices($instituteId, $branchId, $to);

        return [
            'top_customers' => $topCustomers,
            'top_suppliers' => $topSuppliers,
            'low_stock_alerts' => $lowStock,
            'overdue_invoices' => $overdueInvoices,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * HR analytics: total employees, payroll cost, attendance rate, leave utilization.
     */
    public function hrAnalytics(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null): array
    {
        $from ??= now()->startOfYear()->toDateString();
        $to ??= now()->toDateString();

        $employeeQuery = HrEmployee::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')));

        $totalEmployees = $employeeQuery->count();
        $activeEmployees = (clone $employeeQuery)->where('employment_status', 'active')->count();

        $payrollCost = HrPayroll::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->whereIn('status', ['approved', 'paid'])
            ->whereBetween('created_at', [$from, $to])
            ->sum('net_salary');

        $attendanceQuery = HrAttendance::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->whereBetween('attendance_date', [$from, $to]);

        $totalAttendance = $attendanceQuery->count();
        $presentDays = (clone $attendanceQuery)->whereIn('status', ['present', 'late'])->count();
        $attendanceRate = $totalAttendance > 0 ? round(($presentDays / $totalAttendance) * 100, 2) : 0.0;

        $leaveApplications = HrLeaveApplication::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->whereBetween('start_date', [$from, $to]);

        $totalLeaveRequests = $leaveApplications->count();
        $approvedLeaves = (clone $leaveApplications)->where('status', 'approved')->count();
        $totalLeaveDays = (clone $leaveApplications)->where('status', 'approved')->sum('days_count');
        $leaveUtilization = $totalLeaveRequests > 0 ? round(($approvedLeaves / $totalLeaveRequests) * 100, 2) : 0.0;

        $headcountByStatus = HrEmployee::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->selectRaw('employment_status, count(*) as cnt')
            ->groupBy('employment_status')
            ->pluck('cnt', 'employment_status');

        $headcountByDepartment = HrEmployee::query()
            ->withoutGlobalScopes()
            ->where('hr_employees.institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('hr_employees.branch_id', $branchId)
                ->orWhereNull('hr_employees.branch_id')))
            ->where('employment_status', 'active')
            ->join('hr_departments', 'hr_employees.department_id', '=', 'hr_departments.id')
            ->selectRaw('hr_departments.name as department_name, count(*) as cnt')
            ->groupBy('hr_departments.name')
            ->pluck('cnt', 'department_name');

        return [
            'total_employees' => $totalEmployees,
            'active_employees' => $activeEmployees,
            'payroll_cost' => round((float) $payrollCost, 4),
            'attendance_rate' => $attendanceRate,
            'total_attendance_records' => $totalAttendance,
            'present_records' => $presentDays,
            'total_leave_requests' => $totalLeaveRequests,
            'approved_leaves' => $approvedLeaves,
            'total_leave_days_taken' => (float) $totalLeaveDays,
            'leave_utilization' => $leaveUtilization,
            'headcount_by_status' => $headcountByStatus,
            'headcount_by_department' => $headcountByDepartment,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Sales funnel: leads, quotations, orders, deliveries with conversion rates.
     */
    public function salesFunnel(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null): array
    {
        $from ??= now()->startOfYear()->toDateString();
        $to ??= now()->toDateString();

        $leadQuery = CrmLead::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->whereBetween('created_at', [$from, $to]);

        $leadsCount = $leadQuery->count();
        $leadsWon = (clone $leadQuery)->whereHas('status', fn ($s) => $s->where('slug', CrmLeadStatus::SLUG_WON))->count();
        $leadsLost = (clone $leadQuery)->whereHas('status', fn ($s) => $s->where('slug', CrmLeadStatus::SLUG_LOST))->count();

        $quotationQuery = SalesQuotation::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->whereBetween('created_at', [$from, $to]);

        $quotationsSent = $quotationQuery->count();
        $quotationsAccepted = (clone $quotationQuery)->where('status', SalesQuotation::STATUS_ACCEPTED)->count();

        $orderQuery = SalesOrder::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->whereBetween('created_at', [$from, $to]);

        $ordersConfirmed = $orderQuery->count();
        $ordersCompleted = (clone $orderQuery)->where('status', SalesOrder::STATUS_COMPLETED)->count();

        $deliveryQuery = SalesDelivery::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->whereBetween('created_at', [$from, $to]);

        $deliveriesTotal = $deliveryQuery->count();
        $deliveriesCompleted = (clone $deliveryQuery)->where('status', SalesDelivery::STATUS_DELIVERED)->count();

        $leadToQuotationRate = $leadsCount > 0 ? round(($quotationsSent / $leadsCount) * 100, 2) : 0.0;
        $quotationToOrderRate = $quotationsSent > 0 ? round(($ordersConfirmed / $quotationsSent) * 100, 2) : 0.0;
        $orderToDeliveryRate = $ordersConfirmed > 0 ? round(($deliveriesCompleted / $ordersConfirmed) * 100, 2) : 0.0;
        $overallConversion = $leadsCount > 0 ? round(($ordersCompleted / $leadsCount) * 100, 2) : 0.0;

        return [
            'leads_count' => $leadsCount,
            'leads_won' => $leadsWon,
            'leads_lost' => $leadsLost,
            'quotations_sent' => $quotationsSent,
            'quotations_accepted' => $quotationsAccepted,
            'orders_confirmed' => $ordersConfirmed,
            'orders_completed' => $ordersCompleted,
            'deliveries_total' => $deliveriesTotal,
            'deliveries_completed' => $deliveriesCompleted,
            'lead_to_quotation_rate' => $leadToQuotationRate,
            'quotation_to_order_rate' => $quotationToOrderRate,
            'order_to_delivery_rate' => $orderToDeliveryRate,
            'overall_conversion_rate' => $overallConversion,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Department-wise revenue and expense breakdown.
     */
    public function departmentReports(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null): array
    {
        $from ??= now()->startOfYear()->toDateString();
        $to ??= now()->toDateString();

        $departments = HrDepartment::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $departmentData = $departments->map(function ($dept) use ($instituteId, $branchId) {
            $activeEmployees = HrEmployee::query()
                ->withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->where('department_id', $dept->id)
                ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id')))
                ->where('employment_status', 'active')
                ->count();

            $payrollCost = HrPayroll::query()
                ->withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->whereHas('employee', fn ($e) => $e->where('department_id', $dept->id))
                ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id')))
                ->whereIn('status', ['approved', 'paid'])
                ->sum('net_salary');

            return [
                'id' => $dept->id,
                'name' => $dept->name,
                'active_employees' => $activeEmployees,
                'payroll_cost' => round((float) $payrollCost, 4),
            ];
        });

        $pnl = $this->accountingReports->profitAndLoss($instituteId, $branchId, $from, $to);

        $incomeByAccount = $pnl['income']->map(fn ($row) => [
            'code' => $row->code,
            'name' => $row->name,
            'amount' => $row->balance,
        ])->values();

        $expenseByAccount = $pnl['expense']->map(fn ($row) => [
            'code' => $row->code,
            'name' => $row->name,
            'amount' => $row->balance,
        ])->values();

        return [
            'departments' => $departmentData,
            'income_breakdown' => $incomeByAccount,
            'expense_breakdown' => $expenseByAccount,
            'total_income' => $pnl['total_income'],
            'total_expense' => $pnl['total_expense'],
            'from' => $from,
            'to' => $to,
        ];
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Monthly revenue and expense totals for the given date range.
     */
    private function monthlyRevenueExpense(int $instituteId, ?int $branchId, string $from, string $to): Collection
    {
        $start = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->startOfMonth();

        $months = collect();
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $monthFrom = $cursor->copy()->startOfMonth()->toDateString();
            $monthTo = $cursor->copy()->endOfMonth()->toDateString();

            if ($monthTo > $to) {
                $monthTo = $to;
            }

            $pnl = $this->accountingReports->profitAndLoss($instituteId, $branchId, $monthFrom, $monthTo);

            $months->push([
                'month' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
                'revenue' => $pnl['total_income'],
                'expense' => $pnl['total_expense'],
                'profit' => $pnl['net'],
            ]);

            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Top revenue accounts (income type) ranked by balance descending.
     */
    private function topRevenueAccounts(int $instituteId, ?int $branchId, string $from, string $to): Collection
    {
        $pnl = $this->accountingReports->profitAndLoss($instituteId, $branchId, $from, $to);

        return $pnl['income']
            ->sortByDesc('balance')
            ->take(10)
            ->values()
            ->map(fn ($row) => [
                'code' => $row->code,
                'name' => $row->name,
                'amount' => $row->balance,
            ]);
    }

    /**
     * Revenue period comparison: current period vs previous equal-length period.
     */
    private function revenuePeriodComparison(int $instituteId, ?int $branchId, string $from, string $to): array
    {
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to));
        $priorTo = Carbon::parse($from)->subDay()->toDateString();
        $priorFrom = Carbon::parse($from)->subDays($days + 1)->toDateString();

        $current = $this->accountingReports->profitAndLoss($instituteId, $branchId, $from, $to);
        $previous = $this->accountingReports->profitAndLoss($instituteId, $branchId, $priorFrom, $priorTo);

        $currentRevenue = $current['total_income'];
        $previousRevenue = $previous['total_income'];

        $change = $previousRevenue > 0
            ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2)
            : 0.0;

        return [
            'current_revenue' => $currentRevenue,
            'previous_revenue' => $previousRevenue,
            'change_percentage' => $change,
            'current_from' => $from,
            'current_to' => $to,
            'previous_from' => $priorFrom,
            'previous_to' => $priorTo,
        ];
    }

    /**
     * Estimate inflow from AR aging (current + 31-60 fully, 61-90 50%, 91+ 25%).
     */
    private function estimateInflow(Collection $customerAging): float
    {
        $total = 0.0;
        foreach ($customerAging as $customer) {
            $aging = $customer->aging ?? [];
            $total += (float) ($aging['current'] ?? 0);
            $total += (float) ($aging['31_60'] ?? 0);
            $total += (float) ($aging['61_90'] ?? 0) * 0.5;
            $total += (float) ($aging['91_plus'] ?? 0) * 0.25;
        }

        return round($total, 4);
    }

    /**
     * Estimate outflow from AP aging (current + 31-60 fully, 61-90 50%, 91+ 25%).
     */
    private function estimateOutflow(Collection $supplierAging): float
    {
        $total = 0.0;
        foreach ($supplierAging as $supplier) {
            $aging = $supplier->aging ?? [];
            $total += (float) ($aging['current'] ?? 0);
            $total += (float) ($aging['31_60'] ?? 0);
            $total += (float) ($aging['61_90'] ?? 0) * 0.5;
            $total += (float) ($aging['91_plus'] ?? 0) * 0.25;
        }

        return round($total, 4);
    }

    /**
     * Top customers ranked by receivable balance (revenue proxy).
     */
    private function topCustomersByRevenue(int $instituteId, ?int $branchId, string $from, string $to): Collection
    {
        return $this->arp->customerBalancesWithAging($instituteId, $branchId, $to)
            ->sortByDesc('balance')
            ->take(10)
            ->values();
    }

    /**
     * Top suppliers ranked by payable balance (spend proxy).
     */
    private function topSuppliersBySpend(int $instituteId, ?int $branchId, string $from, string $to): Collection
    {
        return $this->arp->supplierBalancesWithAging($instituteId, $branchId, $to)
            ->sortByDesc('balance')
            ->take(10)
            ->values();
    }

    /**
     * Invoices past due date and not fully paid.
     */
    private function overdueInvoices(int $instituteId, ?int $branchId, string $asOfDate): Collection
    {
        return Invoice::query()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->whereDate('due_date', '<', $asOfDate)
            ->where(function ($q) {
                $q->where('status', '!=', 'paid')
                    ->orWhereNull('status');
            })
            ->with('student')
            ->orderBy('due_date')
            ->get();
    }
}
