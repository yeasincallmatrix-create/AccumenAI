<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Accounting\ExecutiveDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STEP 90 — Executive Dashboard & BI Analytics Controller.
 */
class ExecutiveDashboardController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly ExecutiveDashboardService $executiveDashboard,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $kpis = $this->executiveDashboard->kpiSummary((int) $institute->id, $branchId, $from, $to);

        return view('institute.accounting.executive.dashboard', [
            'institute' => $institute,
            'branch' => $this->actingBranch($request),
            'kpis' => $kpis,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function revenue(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $data = $this->executiveDashboard->revenueAnalytics((int) $institute->id, $branchId, $from, $to);

        return view('institute.accounting.executive.revenue', array_merge($data, [
            'institute' => $institute,
            'branch' => $this->actingBranch($request),
        ]));
    }

    public function profit(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $data = $this->executiveDashboard->profitAnalysis((int) $institute->id, $branchId, $from, $to);

        return view('institute.accounting.executive.profit', array_merge($data, [
            'institute' => $institute,
            'branch' => $this->actingBranch($request),
        ]));
    }

    public function cash(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $asOfDate = $request->query('as_of_date', now()->toDateString());

        $data = $this->executiveDashboard->cashForecast((int) $institute->id, $branchId, $asOfDate);

        return view('institute.accounting.executive.cash', array_merge($data, [
            'institute' => $institute,
            'branch' => $this->actingBranch($request),
        ]));
    }

    public function insights(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $data = $this->executiveDashboard->businessInsights((int) $institute->id, $branchId, $from, $to);

        return view('institute.accounting.executive.insights', array_merge($data, [
            'institute' => $institute,
            'branch' => $this->actingBranch($request),
        ]));
    }

    public function hr(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $data = $this->executiveDashboard->hrAnalytics((int) $institute->id, $branchId, $from, $to);

        return view('institute.accounting.executive.hr', array_merge($data, [
            'institute' => $institute,
            'branch' => $this->actingBranch($request),
        ]));
    }

    public function salesFunnel(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $data = $this->executiveDashboard->salesFunnel((int) $institute->id, $branchId, $from, $to);

        return view('institute.accounting.executive.sales-funnel', array_merge($data, [
            'institute' => $institute,
            'branch' => $this->actingBranch($request),
        ]));
    }

    public function departments(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $data = $this->executiveDashboard->departmentReports((int) $institute->id, $branchId, $from, $to);

        return view('institute.accounting.executive.departments', array_merge($data, [
            'institute' => $institute,
            'branch' => $this->actingBranch($request),
        ]));
    }
}
