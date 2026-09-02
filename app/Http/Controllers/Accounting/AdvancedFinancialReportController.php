<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Accounting\AdvancedFinancialReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STEP 81 — Advanced Financial Reports Controller.
 */
class AdvancedFinancialReportController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly AdvancedFinancialReportService $advReport,
    ) {}

    public function comparativeIncomeStatement(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $currentFrom = $request->query('current_from', now()->startOfYear()->toDateString());
        $currentTo = $request->query('current_to', now()->toDateString());
        $priorFrom = $request->query('prior_from');
        $priorTo = $request->query('prior_to');

        $report = $this->advReport->comparativeIncomeStatement(
            $institute->id, $branchId, $currentFrom, $currentTo, $priorFrom, $priorTo,
        );

        return view('institute.accounting.reports.comparative-income-statement', array_merge($report, [
            'institute' => $institute,
        ]));
    }

    public function comparativeBalanceSheet(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $currentDate = $request->query('current_date', now()->toDateString());
        $priorDate = $request->query('prior_date');

        $report = $this->advReport->comparativeBalanceSheet(
            $institute->id, $branchId, $currentDate, $priorDate,
        );

        return view('institute.accounting.reports.comparative-balance-sheet', array_merge($report, [
            'institute' => $institute,
        ]));
    }

    public function monthlyRevenueTrend(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $months = $this->advReport->monthlyRevenueTrend($institute->id, $branchId, $from, $to);

        return view('institute.accounting.reports.monthly-revenue-trend', [
            'institute' => $institute,
            'months' => $months,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function expenseAnalysis(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $report = $this->advReport->expenseAnalysis($institute->id, $branchId, $from, $to);

        return view('institute.accounting.reports.expense-analysis', array_merge($report, [
            'institute' => $institute,
            'from' => $from,
            'to' => $to,
        ]));
    }

    public function profitabilityDashboard(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $report = $this->advReport->profitabilityDashboard($institute->id, $branchId, $from, $to);

        return view('institute.accounting.reports.profitability-dashboard', array_merge($report, [
            'institute' => $institute,
            'from' => $from,
            'to' => $to,
        ]));
    }
}
