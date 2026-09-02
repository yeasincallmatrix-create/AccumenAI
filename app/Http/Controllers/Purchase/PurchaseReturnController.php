<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\PurchaseReturn;
use App\Services\Purchase\PurchaseReturnService;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly PurchaseReturnService $returns) {}

    public function index(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $q = PurchaseReturn::with(['supplier', 'purchaseOrder'])
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($qq) => $qq->where(fn ($q2) => $q2->where('branch_id', $branchId)->orWhereNull('branch_id')));

        if ($request->filled('q')) {
            $s = $request->input('q');
            $q->where(function ($qq) use ($s) {
                $qq->where('return_number', 'like', "%{$s}%")
                   ->orWhere('credit_note_number', 'like', "%{$s}%")
                   ->orWhereHas('supplier', fn ($sup) => $sup->where('name', 'like', "%{$s}%"));
            });
        }
        if ($request->filled('status')) $q->where('status', $request->input('status'));

        $returns = $q->orderByDesc('id')->paginate(20)->withQueryString();

        return view('purchase.returns.index', ['institute' => $institute, 'returns' => $returns]);
    }

    public function create(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $goodsReceipts = \App\Models\GoodsReceipt::withoutGlobalScopes()->where('institute_id', $institute->id)->where('status', 'confirmed')
            ->when($branchId !== null, fn ($qq) => $qq->where(fn ($q2) => $q2->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->orderByDesc('id')->limit(50)->get();

        $purchaseOrders = \App\Models\PurchaseOrder::withoutGlobalScopes()->where('institute_id', $institute->id)
            ->whereIn('status', ['approved','partially_received','fully_received'])
            ->when($branchId !== null, fn ($qq) => $qq->where(fn ($q2) => $q2->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->orderByDesc('id')->limit(50)->get();

        return view('purchase.returns.create', [
            'institute' => $institute,
            'goodsReceipts' => $goodsReceipts,
            'purchaseOrders' => $purchaseOrders,
        ]);
    }

    public function store(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $data = $request->validate([
            'purchase_order_id' => ['nullable','integer','exists:purchase_orders,id'],
            'goods_receipt_id' => ['nullable','integer','exists:goods_receipts,id'],
            'purchase_invoice_id' => ['nullable','integer','exists:purchase_invoices,id'],
            'supplier_id' => ['required','integer','exists:parties,id'],
            'warehouse_id' => ['nullable','integer','exists:inventory_warehouses,id'],
            'return_date' => ['required','date'],
            'reason' => ['nullable','string','max:2000'],
            'notes' => ['nullable','string','max:2000'],
            'lines' => ['required','array','min:1'],
            'lines.*.purchase_order_line_id' => ['nullable','integer','exists:purchase_order_lines,id'],
            'lines.*.goods_receipt_item_id' => ['nullable','integer','exists:goods_receipt_items,id'],
            'lines.*.inventory_item_id' => ['nullable','integer','exists:inventory_items,id'],
            'lines.*.description' => ['required','string','max:500'],
            'lines.*.quantity' => ['required','numeric','gt:0'],
            'lines.*.unit' => ['nullable','string','max:30'],
            'lines.*.unit_price' => ['required','numeric','min:0'],
            'lines.*.discount_amount' => ['nullable','numeric','min:0'],
            'lines.*.discount_type' => ['nullable','in:fixed,percent'],
            'lines.*.tax_group_id' => ['nullable','integer','exists:tax_groups,id'],
        ]);

        $ret = $this->returns->create($institute->id, $branchId, $data, (int) $this->actorId($request));

        return redirect()->route('purchase.returns.show', $ret)->with('status', 'Purchase return ' . $ret->return_number . ' created.');
    }

    public function show(Request $request, PurchaseReturn $return)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int)$return->institute_id !== (int)$institute->id, 404);
        $this->assertBranch($return, $request);
        $return->load(['supplier','purchaseOrder','goodsReceipt','warehouse','items.inventoryItem','journal','purchaseInvoice']);

        $credit = \App\Models\SupplierCreditBalance::withoutGlobalScopes()->where('purchase_return_id', $return->id)->first();
        $refunds = \App\Models\SupplierRefund::withoutGlobalScopes()->where('purchase_return_id', $return->id)->get();

        return view('purchase.returns.show', ['institute'=>$institute,'return'=>$return,'credit'=>$credit,'refunds'=>$refunds]);
    }

    public function submit(Request $request, PurchaseReturn $return)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int)$return->institute_id !== (int)$institute->id, 404);
        $this->assertBranch($return, $request);
        $this->returns->submit($return, (int)$this->actorId($request));
        return back()->with('status','Return submitted.');
    }

    public function approve(Request $request, PurchaseReturn $return)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int)$return->institute_id !== (int)$institute->id, 404);
        $this->assertBranch($return, $request);
        $this->returns->approve($return, (int)$this->actorId($request));
        return back()->with('status','Return approved.');
    }

    public function post(Request $request, PurchaseReturn $return)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int)$return->institute_id !== (int)$institute->id, 404);
        $this->assertBranch($return, $request);
        $this->returns->post($return, (int)$this->actorId($request));
        return back()->with('status','Return posted. Inventory and credit note created.');
    }

    public function cancel(Request $request, PurchaseReturn $return)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int)$return->institute_id !== (int)$institute->id, 404);
        $this->assertBranch($return, $request);
        $this->returns->cancel($return, (int)$this->actorId($request));
        return back()->with('status','Return cancelled.');
    }

    public function reverse(Request $request, PurchaseReturn $return)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int)$return->institute_id !== (int)$institute->id, 404);
        $this->assertBranch($return, $request);
        $this->returns->reverse($return, (int)$this->actorId($request), $request->input('reason'));
        return back()->with('status','Return reversed.');
    }

    public function creditNote(Request $request, PurchaseReturn $return)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int)$return->institute_id !== (int)$institute->id, 404);
        $return->load(['supplier','items','journal']);
        return view('purchase.returns.credit_note', ['institute'=>$institute,'return'=>$return]);
    }

    public function print(Request $request, PurchaseReturn $return)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int)$return->institute_id !== (int)$institute->id, 404);
        $return->load(['supplier','purchaseOrder','goodsReceipt','warehouse','items','branch','institute']);
        return view('purchase.returns.print', ['institute'=>$institute,'return'=>$return]);
    }

    public function refund(Request $request, PurchaseReturn $return)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int)$return->institute_id !== (int)$institute->id, 404);
        $this->assertBranch($return, $request);
        $data = $request->validate([
            'amount'=>['required','numeric','gt:0'],
            'refund_method'=>['nullable','string','max:30'],
            'notes'=>['nullable','string','max:1000'],
        ]);
        $this->returns->refund($institute->id, $return->branch_id, $return->supplier_id, (float)$data['amount'], array_merge($data, ['purchase_return_id'=>$return->id]), (int)$this->actorId($request));
        return back()->with('status','Refund recorded.');
    }

    public function adjust(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $data = $request->validate([
            'supplier_id'=>['required','integer','exists:parties,id'],
            'purchase_invoice_id'=>['required','integer','exists:purchase_invoices,id'],
            'amount'=>['required','numeric','gt:0'],
        ]);
        $this->returns->adjustCreditAgainstInvoice($institute->id,$branchId,(int)$data['supplier_id'],(int)$data['purchase_invoice_id'],(float)$data['amount'],(int)$this->actorId($request));
        return back()->with('status','Credit adjusted against invoice.');
    }

    private function assertBranch(PurchaseReturn $ret, Request $request): void
    {
        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $ret->branch_id !== null && (int)$ret->branch_id !== (int)$branchId) abort(404);
    }
}
