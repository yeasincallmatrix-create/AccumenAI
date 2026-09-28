<?php

namespace App\Http\Controllers\Dealership\Reports;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Accounting\ReportExportService;
use App\Services\Dealership\Reports\SrSalesReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin controller over SrSalesReportService. Institute identity comes
 * only from the authenticated workspace (ResolvesInstitute).
 */
class SrReportController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly SrSalesReportService $service,
        private readonly ReportExportService $exporter,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $filters = $request->only(['sr', 'status', 'from', 'to']);
        $report = $this->service->calculate($filters, $institute->id);

        return view('dealership.reports.sr.index', [
            'institute' => $institute,
            'report' => $report,
            'filters' => $filters,
            'statuses' => config('dealership.order_statuses', []),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $institute = $this->requireInstitute($request);
        $filters = $request->only(['sr', 'status', 'from', 'to']);
        $report = $this->service->calculate($filters, $institute->id);

        $rows = collect($report['rows'])->map(fn ($r) => [
            'SR' => $r['sales_force_id'],
            'Orders' => $r['orders'],
            'Qty' => $r['qty'],
            'Amount' => $r['amount'],
        ]);

        return $this->exporter->csv($rows, 'sr-sales-report.csv');
    }
}
