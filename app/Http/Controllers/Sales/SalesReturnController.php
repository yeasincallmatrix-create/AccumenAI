<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Sales\SalesReturnService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesReturnController extends Controller
{
    public function __construct(private readonly SalesReturnService $returns) {}

    private function instituteId(Request $r): int
    {
        $id = TenantContext::id();
        if ($id === null) abort(403, 'Tenant context required.');
        return $id;
    }
    private function branchId(): ?int
    {
        return \App\Support\BranchContext::id();
    }

    public function index(Request $r)
    {
        $inst = $this->instituteId($r);
        $branch = $this->branchId();
        $data = $this->returns->list($inst, $branch, [
            'search' => $r->query('search'),
            'status' => $r->query('status'),
            'refund_status' => $r->query('refund_status'),
        ], (int) $r->query('per_page',15));

        if ($r->expectsJson() || $r->wantsJson()) return response()->json($data);
        return view('sales.returns.index', ['returns'=>$data,'filters'=>$r->only('search','status')]);
    }

    public function create(Request $r)
    {
        $inst = $this->instituteId($r);
        $invoices = Invoice::withoutGlobalScopes()->where('institute_id',$inst)->whereNotIn('status',['cancelled'])->latest()->limit(50)->get();
        $warehouses = \App\Models\InventoryWarehouse::query()->where('institute_id',$inst)->where('is_active',true)->get();
        return view('sales.returns.create', compact('invoices','warehouses'));
    }

    public function store(Request $r)
    {
        $inst = $this->instituteId($r);
        $branch = $this->branchId();
        $validated = $r->validate([
            'invoice_id'=>['required','integer','exists:invoices,id'],
            'warehouse_id'=>['nullable','integer','exists:inventory_warehouses,id'],
            'return_date'=>['required','date'],
            'reason'=>['required','string','max:500'],
            'notes'=>['nullable','string'],
            'lines'=>['required','array','min:1'],
            'lines.*.invoice_item_id'=>['required','integer','exists:invoice_items,id'],
            'lines.*.quantity'=>['required','numeric','gt:0'],
        ]);
        $ret = $this->returns->createDraft($inst,$branch,$validated['invoice_id'],$validated['warehouse_id']??null,$validated['return_date'],$validated['reason'],$validated['notes']??null,$validated['lines'], auth()->id());
        if ($r->expectsJson() || $r->wantsJson()) return response()->json(['id'=>$ret->id,'return_number'=>$ret->return_number],201);
        return redirect()->route('sales.returns.show', $ret->id)->with('success','Return created');
    }

    public function show(Request $r, int $id)
    {
        $inst = $this->instituteId($r);
        $branch = $this->branchId();
        $ret = $this->returns->find($inst,$branch,$id);
        if ($r->expectsJson() || $r->wantsJson()) return response()->json($ret);
        return view('sales.returns.show', ['ret'=>$ret]);
    }

    public function creditNote(Request $r, int $id)
    {
        $inst = $this->instituteId($r);
        $branch = $this->branchId();
        $ret = $this->returns->find($inst,$branch,$id);
        if ($r->query('print') || $r->expectsJson()) {
            if ($r->expectsJson()) return response()->json(['credit_note_number'=>$ret->credit_note_number,'return'=>$ret]);
            return view('sales.returns.credit-note-print', ['ret'=>$ret]);
        }
        return view('sales.returns.credit-note', ['ret'=>$ret]);
    }

    public function approve(Request $r, int $id)
    {
        $inst = $this->instituteId($r);
        $branch = $this->branchId();
        $ret = $this->returns->approve($inst,$branch,$id, auth()->id());
        if ($r->expectsJson()||$r->wantsJson()) return response()->json($ret);
        return back()->with('success','Approved');
    }

    public function post(Request $r, int $id)
    {
        $inst = $this->instituteId($r);
        $branch = $this->branchId();
        $ret = $this->returns->post($inst,$branch,$id, auth()->id());
        if ($r->expectsJson()||$r->wantsJson()) return response()->json($ret);
        return back()->with('success','Posted - credit note '.$ret->credit_note_number);
    }

    public function cancel(Request $r, int $id)
    {
        $inst = $this->instituteId($r);
        $branch = $this->branchId();
        $ret = $this->returns->cancel($inst,$branch,$id, auth()->id());
        if ($r->expectsJson()||$r->wantsJson()) return response()->json($ret);
        return back()->with('success','Cancelled');
    }

    public function reverse(Request $r, int $id)
    {
        $inst = $this->instituteId($r);
        $branch = $this->branchId();
        $ret = $this->returns->reverse($inst,$branch,$id, auth()->id());
        if ($r->expectsJson()||$r->wantsJson()) return response()->json($ret);
        return back()->with('success','Reversed');
    }

    public function refund(Request $r, int $id)
    {
        $inst = $this->instituteId($r);
        $branch = $this->branchId();
        $validated = $r->validate([
            'amount'=>['required','numeric','gt:0'],
            'method'=>['required', Rule::in(['cash','bank','other','credit'])],
            'reference'=>['nullable','string','max:200'],
            'refund_date'=>['required','date'],
            'payment_method_id'=>['nullable','integer','exists:payment_methods,id'],
        ]);
        $refund = $this->returns->refund($inst,$branch,$id,(float)$validated['amount'],$validated['method'],$validated['reference']??null,$validated['refund_date'],$validated['payment_method_id']??null, auth()->id());
        if ($r->expectsJson()||$r->wantsJson()) return response()->json($refund,201);
        return back()->with('success','Refund recorded');
    }

    public function invoiceLines(Request $r, int $invoiceId)
    {
        $inst = $this->instituteId($r);
        $inv = Invoice::withoutGlobalScopes()->where('id',$invoiceId)->where('institute_id',$inst)->with('items.inventoryItem')->firstOrFail();
        if ($this->branchId()!==null && $inv->sales_order_id) {
            $o = \App\Models\SalesOrder::withoutGlobalScopes()->find($inv->sales_order_id);
            if ($o && $o->branch_id!==null && (int)$o->branch_id !== (int)$this->branchId()) abort(404);
        }
        $remaining = $this->returns->remainingForInvoice($inv);
        return response()->json(['invoice'=>$inv,'remaining'=>$remaining]);
    }
}
