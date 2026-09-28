<?php

namespace App\Http\Controllers\Dealership\Reports;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Accounting\ReportExportService;
use App\Services\Dealership\Reports\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin controller over SalesReportService.
 */
class SalesReportController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly SalesReportService $service,
        private readonly ReportExportService $exporter,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $filters = $request->only(['group_by', 'brand', 'product', 'channel', 'from', 'to']);
        $report = $this->service->calculate($filters, $institute->id);

        return view('dealership.reports.sales.index', [
            'institute' => $institute,
            'report' => $report,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $institute = $this->requireInstitute($request);
        $filters = $request->only(['group_by', 'brand', 'product', 'channel', 'from', 'to']);
        $report = $this->service->calculate($filters, $institute->id);

        $label = ucfirst($report['group_by']);
        $rows = collect($report['rows'])->map(fn ($r) => [
            $label => $r[$report['group_by']],
            'Qty' => $r['qty'],
            'Amount' => $r['amount'],
        ]);

        return $this->exporter->csv($rows, 'sales-report.csv');
    }
}
