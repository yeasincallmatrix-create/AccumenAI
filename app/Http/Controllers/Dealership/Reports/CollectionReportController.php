<?php

namespace App\Http\Controllers\Dealership\Reports;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Accounting\ReportExportService;
use App\Services\Dealership\Reports\CollectionReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin controller over CollectionReportService.
 */
class CollectionReportController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly CollectionReportService $service,
        private readonly ReportExportService $exporter,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $filters = $request->only(['sr', 'customer', 'status', 'from', 'to', 'as_of']);
        $report = $this->service->calculate($filters, $institute->id);

        return view('dealership.reports.collection.index', [
            'institute' => $institute,
            'report' => $report,
            'filters' => $filters,
            'methods' => config('dealership.collection_methods', []),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $institute = $this->requireInstitute($request);
        $filters = $request->only(['sr', 'customer', 'status', 'from', 'to', 'as_of']);
        $report = $this->service->calculate($filters, $institute->id);

        $rows = collect($report['by_sr'])->map(fn ($r) => [
            'SR' => $r['sales_force_id'],
            'Receipts' => $r['receipts'],
            'Amount' => $r['amount'],
        ]);

        return $this->exporter->csv($rows, 'collection-report.csv');
    }
}
