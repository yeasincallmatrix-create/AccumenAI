<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Support\CsvStream;
use App\Services\Sales\SalesReportExportService;
use App\Services\Sales\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly SalesReportService $reports,
        private readonly SalesReportExportService $exports,
    ) {}

    private function filters(Request $request): array
    {
        return [
            'from' => $this->safeDate($request->query('from')),
            'to' => $this->safeDate($request->query('to')),
            'customer_id' => $request->query('customer_id') ? (int)$request->query('customer_id') : null,
            'product_id' => $request->query('product_id') ? (int)$request->query('product_id') : null,
            'category_id' => $request->query('category_id') ? (int)$request->query('category_id') : null,
            'salesperson_id' => $request->query('salesperson_id') ? (int)$request->query('salesperson_id') : null,
            'branch_id' => $request->query('branch_id') ? (int)$request->query('branch_id') : null,
            'status' => $request->query('status'),
            'payment_status' => $request->query('payment_status'),
            'warehouse_id' => $request->query('warehouse_id') ? (int)$request->query('warehouse_id') : null,
        ];
    }

    private function safeDate(?string $d): ?string
    {
        if (!$d) return null;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/',$d) ? $d : null;
    }

    private function csvStream(array $export): StreamedResponse
    {
        if (empty($export['valid'])) abort(422, $export['message'] ?? 'Export not ready.');
        return CsvStream::download($export['filename'],$export['headers'],$export['rows']);
    }

    // Dashboard
    public function dashboard(Request $request): View|StreamedResponse
    {
        $institute=$this->requireInstitute($request); $branchId=$this->actingBranchId($request);
        $filters=$this->filters($request);
        if ($request->query('export')==='csv') {
            return $this->csvStream($this->exports->dashboardExport($institute->id,$branchId,$filters['from'],$filters['to']));
        }
        $data=$this->reports->dashboard($institute->id,$branchId,$filters['from'],$filters['to']);
        return view('sales.reports.dashboard', ['institute'=>$institute,'data'=>$data,'filters'=>$filters]);
    }

    // Period reports: daily/weekly/monthly/yearly share view
    private function period(Request $request, string $group): View|StreamedResponse
    {
        $institute=$this->requireInstitute($request); $branchId=$this->actingBranchId($request);
        $filters=$this->filters($request);
        if ($request->query('export')==='csv') {
            return $this->csvStream($this->exports->periodExport($institute->id,$branchId,$group,$filters['from'],$filters['to'],$filters));
        }
        $rows=$this->reports->salesByPeriod($institute->id,$branchId,$group,$filters['from'],$filters['to'],$filters);
        return view('sales.reports.period', ['institute'=>$institute,'rows'=>$rows,'group'=>$group,'filters'=>$filters]);
    }
    public function daily(Request $request): View|StreamedResponse { return $this->period($request,'daily'); }
    public function weekly(Request $request): View|StreamedResponse { return $this->period($request,'weekly'); }
    public function monthly(Request $request): View|StreamedResponse { return $this->period($request,'monthly'); }
    public function yearly(Request $request): View|StreamedResponse { return $this->period($request,'yearly'); }

    public function product(Request $request): View|StreamedResponse
    {
        $institute=$this->requireInstitute($request); $branchId=$this->actingBranchId($request); $filters=$this->filters($request);
        if ($request->query('export')==='csv') return $this->csvStream($this->exports->productExport($institute->id,$branchId,$filters['from'],$filters['to'],$filters));
        $rows=$this->reports->productWise($institute->id,$branchId,$filters['from'],$filters['to'],$filters);
        return view('sales.reports.product', ['institute'=>$institute,'rows'=>$rows,'filters'=>$filters]);
    }

    public function category(Request $request): View|StreamedResponse
    {
        $institute=$this->requireInstitute($request); $branchId=$this->actingBranchId($request); $filters=$this->filters($request);
        if ($request->query('export')==='csv') return $this->csvStream($this->exports->categoryExport($institute->id,$branchId,$filters['from'],$filters['to']));
        $rows=$this->reports->categoryWise($institute->id,$branchId,$filters['from'],$filters['to'],$filters);
        return view('sales.reports.category', ['institute'=>$institute,'rows'=>$rows,'filters'=>$filters]);
    }

    public function customer(Request $request): View|StreamedResponse
    {
        $institute=$this->requireInstitute($request); $branchId=$this->actingBranchId($request); $filters=$this->filters($request);
        if ($request->query('export')==='csv') return $this->csvStream($this->exports->customerExport($institute->id,$branchId,$filters['from'],$filters['to']));
        $rows=$this->reports->customerWise($institute->id,$branchId,$filters['from'],$filters['to'],$filters);
        return view('sales.reports.customer', ['institute'=>$institute,'rows'=>$rows,'filters'=>$filters]);
    }

    public function salesperson(Request $request): View|StreamedResponse
    {
        $institute=$this->requireInstitute($request); $branchId=$this->actingBranchId($request); $filters=$this->filters($request);
        if ($request->query('export')==='csv') return $this->csvStream($this->exports->salespersonExport($institute->id,$branchId,$filters['from'],$filters['to']));
        $rows=$this->reports->salespersonWise($institute->id,$branchId,$filters['from'],$filters['to']);
        return view('sales.reports.salesperson', ['institute'=>$institute,'rows'=>$rows,'filters'=>$filters]);
    }

    public function branch(Request $request): View|StreamedResponse
    {
        $institute=$this->requireInstitute($request); $branchId=$this->actingBranchId($request); $filters=$this->filters($request);
        if ($request->query('export')==='csv') return $this->csvStream($this->exports->branchExport($institute->id,$branchId,$filters['from'],$filters['to']));
        $rows=$this->reports->branchWise($institute->id,$branchId,$filters['from'],$filters['to']);
        return view('sales.reports.branch', ['institute'=>$institute,'rows'=>$rows,'filters'=>$filters]);
    }

    public function warehouse(Request $request): View|StreamedResponse
    {
        $institute=$this->requireInstitute($request); $branchId=$this->actingBranchId($request); $filters=$this->filters($request);
        if ($request->query('export')==='csv') return $this->csvStream($this->exports->warehouseExport($institute->id,$branchId,$filters['from'],$filters['to']));
        $rows=$this->reports->warehouseWise($institute->id,$branchId,$filters['from'],$filters['to']);
        return view('sales.reports.warehouse', ['institute'=>$institute,'rows'=>$rows,'filters'=>$filters]);
    }

    public function returns(Request $request): View|StreamedResponse
    {
        $institute=$this->requireInstitute($request); $branchId=$this->actingBranchId($request); $filters=$this->filters($request);
        if ($request->query('export')==='csv') return $this->csvStream($this->exports->returnsExport($institute->id,$branchId,$filters['from'],$filters['to']));
        $rows=$this->reports->returnsReport($institute->id,$branchId,$filters['from'],$filters['to'],$filters);
        $detail=$this->reports->returnsDetail($institute->id,$branchId,$filters,20);
        return view('sales.reports.returns', ['institute'=>$institute,'rows'=>$rows,'detail'=>$detail,'filters'=>$filters]);
    }

    public function statement(Request $request): View|StreamedResponse
    {
        $institute=$this->requireInstitute($request); $branchId=$this->actingBranchId($request);
        $customerId = $request->query('customer_id') ? (int)$request->query('customer_id') : null;
        if (!$customerId) {
            // List customers for selection
            $customers = Party::where('institute_id',$institute->id)->whereIn('type',['customer','both'])->where('is_active',true)
                ->when($branchId!==null, fn($q)=>$q->where(function($qq) use ($branchId){ $qq->where('branch_id',$branchId)->orWhereNull('branch_id'); }))
                ->orderBy('name')->limit(100)->get();
            return view('sales.reports.statement-select', ['institute'=>$institute,'customers'=>$customers]);
        }
        $customer = Party::where('institute_id',$institute->id)->where('id',$customerId)->firstOrFail();
        if ($branchId!==null && $customer->branch_id !==null && (int)$customer->branch_id !== $branchId) abort(404);
        $filters=$this->filters($request);
        if ($request->query('export')==='csv') return $this->csvStream($this->exports->statementExport($institute->id,$branchId,$customerId,$filters['from'],$filters['to']));
        $data=$this->reports->customerStatement($institute->id,$branchId,$customerId,$filters['from'],$filters['to']);
        return view('sales.reports.statement', ['institute'=>$institute,'customer'=>$customer,'data'=>$data,'filters'=>$filters]);
    }
}
