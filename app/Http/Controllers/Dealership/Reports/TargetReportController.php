<?php

namespace App\Http\Controllers\Dealership\Reports;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Accounting\ReportExportService;
use App\Services\Dealership\Reports\TargetReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin controller over TargetReportService.
 */
class TargetReportController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly TargetReportService $service,
        private readonly ReportExportService $exporter,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $filters = $request->only(['sr', 'period']);
        $report = $this->service->calculate($filters, $institute->id);

        return view('dealership.reports.targets.index', [
            'institute' => $institute,
            'report' => $report,
            'filters' => $filters,
            'periods' => config('dealership.period_types', []),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $institute = $this->requireInstitute($request);
        $filters = $request->only(['sr', 'period']);
        $report = $this->service->calculate($filters, $institute->id);

        $rows = collect($report['rows'])->map(fn ($r) => [
            'SR' => $r['sales_force_id'],
            'Period' => $r['period_type'].' '.$r['period_start'],
            'Target' => $r['target'],
            'Achieved' => $r['achieved'],
            'Variance' => $r['variance'],
            'Achievement %' => $r['achievement_pct'],
        ]);

        return $this->exporter->csv($rows, 'target-report.csv');
    }
}
