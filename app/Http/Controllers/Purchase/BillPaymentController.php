<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Concerns\AuthorizesPermission;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseSupplierPayment;
use App\Services\Purchase\PurchasePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillPaymentController extends Controller
{
    use AuthorizesPermission;
    use ResolvesInstitute;

    public function __construct(private readonly PurchasePaymentService $payments) {}

    public function index(Request $request): View
    {
        $this->requirePermission('purchase.payments.view', 'purchase.view', 'purchase.manage');
        $institute = $this->requireInstitute($request);

        $payments = PurchaseSupplierPayment::query()
            ->where('institute_id', $institute->id)
            ->with(['supplier', 'purchaseInvoice', 'paymentMethod'])
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('supplier_id', $request->input('vendor_id')))
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('paid_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('paid_at', '<=', $request->input('to_date')))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchase.payments.index', [
            'institute' => $institute,
            'payments' => $payments,
        ]);
    }

    public function create(Request $request): View
    {
        $this->requirePermission('purchase.payments.create', 'purchase.manage');
        $institute = $this->requireInstitute($request);

        $vendors = Party::query()
            ->where('institute_id', $institute->id)
            ->whereIn('type', ['supplier', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $bills = PurchaseInvoice::query()
            ->where('institute_id', $institute->id)
            ->whereIn('status', ['posted'])
            ->where('due_amount', '>', 0)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('purchase.payments.create', [
            'institute' => $institute,
            'vendors' => $vendors,
            'bills' => $bills,
        ]);
    }

    public function show(Request $request, PurchaseSupplierPayment $payment): View
    {
        $this->requirePermission('purchase.payments.view', 'purchase.view', 'purchase.manage');
        $institute = $this->requireInstitute($request);
        abort_unless($payment->institute_id === $institute->id, 404);
        $payment->load(['supplier', 'purchaseInvoice', 'paymentMethod']);

        return view('purchase.payments.show', [
            'institute' => $institute,
            'payment' => $payment,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requirePermission('purchase.payments.create', 'purchase.manage');
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $validated = $request->validate([
            'purchase_invoice_id' => ['required', 'integer', 'exists:purchase_invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:20'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'paid_at' => ['required', 'date'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
        ]);

        $this->payments->pay(
            $institute->id,
            $branchId,
            (int) $validated['purchase_invoice_id'],
            $validated,
            (int) $this->actorId($request),
        );

        return redirect()
            ->route('purchase.payments.index')
            ->with('status', 'Bill payment recorded successfully.');
    }
}
