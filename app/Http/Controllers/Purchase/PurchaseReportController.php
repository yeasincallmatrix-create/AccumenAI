<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Purchase\PurchaseReportService;
use App\Support\CsvStream;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseReportController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly PurchaseReportService $reports) {}

    private function filters(Request $request): array
    {
        // Never trust institute_id/branch_id from input; derived via ResolvesInstitute
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'supplier_id' => ['nullable', 'integer', 'exists:parties,id'],
            'product_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'category_id' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'payment_status' => ['nullable', 'in:paid,unpaid,partially_paid'],
        ]);

        return $validated;
    }

    public function dashboard(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $metrics = $this->reports->dashboardMetrics($institute->id, $branchId, $filters);
        $suppliers = $this->reports->distinctSuppliers($institute->id, $branchId);

        return view('purchase.reports.dashboard', [
            'institute' => $institute,
            'metrics' => $metrics,
            'filters' => $filters,
            'suppliers' => $suppliers,
        ]);
    }

    public function daily(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $rows = $this->reports->timeSeries($institute->id, $branchId, $filters, 'daily');

        return view('purchase.reports.daily', ['institute' => $institute, 'rows' => $rows, 'filters' => $filters]);
    }

    public function weekly(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $rows = $this->reports->timeSeries($institute->id, $branchId, $filters, 'weekly');

        return view('purchase.reports.weekly', ['institute' => $institute, 'rows' => $rows, 'filters' => $filters]);
    }

    public function monthly(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $rows = $this->reports->timeSeries($institute->id, $branchId, $filters, 'monthly');

        return view('purchase.reports.monthly', ['institute' => $institute, 'rows' => $rows, 'filters' => $filters]);
    }

    public function yearly(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $rows = $this->reports->timeSeries($institute->id, $branchId, $filters, 'yearly');

        return view('purchase.reports.yearly', ['institute' => $institute, 'rows' => $rows, 'filters' => $filters]);
    }

    public function supplierWise(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $rows = $this->reports->supplierWise($institute->id, $branchId, $filters);

        return view('purchase.reports.supplier', ['institute' => $institute, 'rows' => $rows, 'filters' => $filters]);
    }

    public function productWise(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $rows = $this->reports->productWise($institute->id, $branchId, $filters, 20);

        return view('purchase.reports.product', ['institute' => $institute, 'rows' => $rows, 'filters' => $filters]);
    }

    public function categoryWise(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $rows = $this->reports->categoryWise($institute->id, $branchId, $filters);

        return view('purchase.reports.category', ['institute' => $institute, 'rows' => $rows, 'filters' => $filters]);
    }

    public function branchWise(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $rows = $this->reports->branchWise($institute->id, $branchId, $filters);

        return view('purchase.reports.branch', ['institute' => $institute, 'rows' => $rows, 'filters' => $filters]);
    }

    public function warehouseWise(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $rows = $this->reports->warehouseReceiving($institute->id, $branchId, $filters);

        return view('purchase.reports.warehouse', ['institute' => $institute, 'rows' => $rows, 'filters' => $filters]);
    }

    public function returnsReport(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $rows = $this->reports->returnsReport($institute->id, $branchId, $filters, 20);

        return view('purchase.reports.returns', ['institute' => $institute, 'rows' => $rows, 'filters' => $filters]);
    }

    public function paymentsReport(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $rows = $this->reports->paymentsReport($institute->id, $branchId, $filters, 20);

        return view('purchase.reports.payments', ['institute' => $institute, 'rows' => $rows, 'filters' => $filters]);
    }

    public function payableReport(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $rows = $this->reports->outstandingPayableReport($institute->id, $branchId, $filters);

        return view('purchase.reports.payable', ['institute' => $institute, 'rows' => $rows, 'filters' => $filters]);
    }

    public function inventoryReconciliation(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $filters = $this->filters($request);
        $rows = $this->reports->inventoryReconciliation($institute->id, $branchId, $filters, 20);

        return view('purchase.reports.inventory', ['institute' => $institute, 'rows' => $rows, 'filters' => $filters]);
    }

    public function supplierStatement(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $request->validate(['supplier_id' => ['required', 'integer', 'exists:parties,id'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $filters = $request->only(['from', 'to']);
        $data = $this->reports->supplierStatement($institute->id, $branchId, (int) $request->input('supplier_id'), $filters);
        $suppliers = $this->reports->distinctSuppliers($institute->id, $branchId);

        return view('purchase.reports.supplier_statement', ['institute' => $institute, 'data' => $data, 'filters' => $filters, 'suppliers' => $suppliers]);
    }

    public function export(Request $request): StreamedResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $type = $request->input('type', 'dashboard');
        $filters = $this->filters($request);

        // Bounded export: max 5000 rows, streaming via generator
        $filename = 'purchase-'.$type.'-'.date('Y-m-d').'.csv';

        $headers = [];
        $rows = function () use ($type, $institute, $branchId, $filters) {
            switch ($type) {
                case 'supplier':
                    $data = $this->reports->supplierWise($institute->id, $branchId, $filters);
                    foreach ($data->take(5000) as $r) {
                        yield [$r->supplier_name, $r->supplier_phone, $r->cnt, number_format((float) $r->total, 2, '.', ''), number_format((float) $r->tax, 2, '.', '')];
                    }
                    break;
                case 'payable':
                    $data = $this->reports->outstandingPayableReport($institute->id, $branchId, $filters);
                    foreach ($data->take(5000) as $r) {
                        yield [$r->name, $r->phone ?? '', number_format((float) $r->payable, 2, '.', ''), number_format((float) ($r->aging['current'] ?? 0), 2, '.', '')];
                    }
                    break;
                case 'dashboard':
                default:
                    $m = $this->reports->dashboardMetrics($institute->id, $branchId, $filters);
                    foreach ($m as $k => $v) {
                        yield [$k, is_numeric($v) ? number_format((float) $v, 2, '.', '') : (string) $v];
                    }
                    break;
            }
        };

        $csvHeaders = match ($type) {
            'supplier' => ['Supplier', 'Phone', 'Invoices', 'Total', 'Tax'],
            'payable' => ['Supplier', 'Phone', 'Payable', 'Current'],
            default => ['Metric', 'Value'],
        };

        return CsvStream::download($filename, $csvHeaders, $rows());
    }

    public function print(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $type = $request->input('type', 'dashboard');
        $filters = $this->filters($request);
        $data = match ($type) {
            'supplier' => $this->reports->supplierWise($institute->id, $branchId, $filters),
            'payable' => $this->reports->outstandingPayableReport($institute->id, $branchId, $filters),
            'daily' => $this->reports->timeSeries($institute->id, $branchId, $filters, 'daily'),
            default => $this->reports->dashboardMetrics($institute->id, $branchId, $filters),
        };

        return view('purchase.reports.print', ['institute' => $institute, 'type' => $type, 'data' => $data, 'filters' => $filters]);
    }
}
