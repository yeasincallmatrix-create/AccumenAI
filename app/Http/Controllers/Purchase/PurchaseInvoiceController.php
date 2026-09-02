<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Currency;
use App\Models\GoodsReceipt;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Services\Purchase\PurchaseInvoiceService;
use App\Services\Purchase\PurchasePaymentService;
use Illuminate\Http\Request;

class PurchaseInvoiceController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly PurchaseInvoiceService $invoices, private readonly PurchasePaymentService $payments) {}

    public function index(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $q = PurchaseInvoice::with(['supplier', 'purchaseOrder', 'currency'])
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($qq) => $qq->where(fn ($q2) => $q2->where('branch_id', $branchId)->orWhereNull('branch_id')));

        if ($request->filled('q')) {
            $search = $request->input('q');
            $q->where(function ($qq) use ($search) {
                $qq->where('invoice_number', 'like', "%{$search}%")
                   ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('status')) $q->where('status', $request->input('status'));
        if ($request->filled('supplier_id')) $q->where('supplier_id', $request->input('supplier_id'));
        if ($request->filled('purchase_order_id')) $q->where('purchase_order_id', $request->input('purchase_order_id'));

        $invoices = $q->orderByDesc('id')->paginate(20)->withQueryString();

        return view('purchase.invoices.index', ['institute' => $institute, 'invoices' => $invoices]);
    }

    public function create(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $poId = $request->input('purchase_order_id');
        $grId = $request->input('goods_receipt_id');
        $po = null; $gr = null; $eligible = [];

        if ($grId) {
            $gr = GoodsReceipt::withoutGlobalScopes()->where('institute_id', $institute->id)->where('id', $grId)->first();
            if ($gr) {
                $po = $gr->purchaseOrder;
                $eligible = $this->invoices->eligibleForInvoicing($po);
            }
        } elseif ($poId) {
            $po = PurchaseOrder::withoutGlobalScopes()->where('institute_id', $institute->id)->where('id', $poId)->first();
            if ($po) $eligible = $this->invoices->eligibleForInvoicing($po);
        }

        $purchaseOrders = PurchaseOrder::withoutGlobalScopes()->where('institute_id', $institute->id)
            ->whereIn('status', ['approved','partially_received','fully_received'])
            ->when($branchId !== null, fn ($qq) => $qq->where(fn ($q2) => $q2->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->orderByDesc('id')->limit(50)->get();

        $goodsReceipts = GoodsReceipt::withoutGlobalScopes()->where('institute_id', $institute->id)->where('status', 'confirmed')
            ->when($branchId !== null, fn ($qq) => $qq->where(fn ($q2) => $q2->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->orderByDesc('id')->limit(50)->get();

        $currencies = Currency::where('is_active', true)->orderBy('code')->get();

        return view('purchase.invoices.create', [
            'institute' => $institute,
            'purchaseOrders' => $purchaseOrders,
            'goodsReceipts' => $goodsReceipts,
            'selectedPO' => $po,
            'selectedGR' => $gr,
            'eligible' => $eligible,
            'currencies' => $currencies,
        ]);
    }

    public function store(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $data = $request->validate([
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'goods_receipt_id' => ['nullable', 'integer', 'exists:goods_receipts,id'],
            'supplier_id' => ['required', 'integer', 'exists:parties,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'payment_terms' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['nullable', 'integer', 'exists:purchase_order_lines,id'],
            'lines.*.goods_receipt_item_id' => ['nullable', 'integer', 'exists:goods_receipt_items,id'],
            'lines.*.inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['nullable', 'string', 'max:30'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_type' => ['nullable', 'in:fixed,percent'],
            'lines.*.tax_group_id' => ['nullable', 'integer', 'exists:tax_groups,id'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoice = $this->invoices->create($institute->id, $branchId, $data, (int) $this->actorId($request));

        return redirect()->route('purchase.invoices.show', $invoice)->with('status', 'Purchase invoice ' . $invoice->invoice_number . ' created.');
    }

    public function show(Request $request, PurchaseInvoice $invoice)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $invoice->institute_id !== (int) $institute->id, 404);
        $this->assertBranch($invoice, $request);
        $invoice->load(['supplier', 'purchaseOrder', 'goodsReceipt', 'currency', 'items.inventoryItem', 'items.taxGroup', 'journal', 'payments.journal']);

        return view('purchase.invoices.show', ['institute' => $institute, 'invoice' => $invoice]);
    }

    public function post(Request $request, PurchaseInvoice $invoice)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $invoice->institute_id !== (int) $institute->id, 404);
        $this->assertBranch($invoice, $request);

        $this->invoices->post($invoice, (int) $this->actorId($request));

        return back()->with('status', 'Invoice posted. AP liability created.');
    }

    public function cancel(Request $request, PurchaseInvoice $invoice)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $invoice->institute_id !== (int) $institute->id, 404);
        $this->assertBranch($invoice, $request);

        $this->invoices->cancel($invoice, (int) $this->actorId($request));

        return back()->with('status', 'Invoice cancelled.');
    }

    public function reverse(Request $request, PurchaseInvoice $invoice)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $invoice->institute_id !== (int) $institute->id, 404);
        $this->assertBranch($invoice, $request);

        $this->invoices->reverse($invoice, (int) $this->actorId($request), $request->input('reason'));

        return back()->with('status', 'Invoice reversed.');
    }

    public function print(Request $request, PurchaseInvoice $invoice)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $invoice->institute_id !== (int) $institute->id, 404);
        $invoice->load(['supplier', 'purchaseOrder', 'goodsReceipt', 'currency', 'items', 'branch', 'institute']);
        return view('purchase.invoices.print', ['institute' => $institute, 'invoice' => $invoice]);
    }

    public function pay(Request $request, PurchaseInvoice $invoice)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $invoice->institute_id !== (int) $institute->id, 404);
        $this->assertBranch($invoice, $request);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->payments->pay($institute->id, $invoice->branch_id, $invoice->id, $data, (int) $this->actorId($request));

        return back()->with('status', 'Payment recorded.');
    }

    public function reversePayment(Request $request, \App\Models\PurchaseSupplierPayment $payment)
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $payment->institute_id !== (int) $institute->id, 404);
        $this->payments->reverse($payment, (int) $this->actorId($request), $request->input('reason'));
        return back()->with('status', 'Payment reversed.');
    }

    private function assertBranch(PurchaseInvoice $invoice, Request $request): void
    {
        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $invoice->branch_id !== null && (int) $invoice->branch_id !== (int) $branchId) abort(404);
    }
}
