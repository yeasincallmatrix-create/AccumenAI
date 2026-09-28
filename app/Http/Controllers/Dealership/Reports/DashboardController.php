<?php

namespace App\Http\Controllers\Dealership\Reports;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Dealership\Reports\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Thin controller over DashboardService (KPI rollup).
 */
class DashboardController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly DashboardService $service,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $filters = $request->only(['from', 'to']);
        $kpis = $this->service->calculate($filters, $institute->id);

        return view('dealership.reports.dashboard.index', [
            'institute' => $institute,
            'kpis' => $kpis,
            'filters' => $filters,
        ]);
    }
}
