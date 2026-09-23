<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\AuthorizesPermission;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Accounting\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceivePaymentController extends Controller
{
    use AuthorizesPermission;
    use ResolvesInstitute;

    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(Request $request): View
    {
        $this->requirePermission('sales.payments.view', 'sales.view', 'sales.manage');
        $institute = $this->requireInstitute($request);

        $payments = Payment::query()
            ->where('institute_id', $institute->id)
            ->with(['party', 'invoice', 'paymentMethod'])
            ->when($request->filled('customer_id'), fn ($q) => $q->where('party_id', $request->input('customer_id')))
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('paid_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('paid_at', '<=', $request->input('to_date')))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('sales.payments.index', [
            'institute' => $institute,
            'payments' => $payments,
        ]);
    }

    public function create(Request $request): View
    {
        $this->requirePermission('sales.payments.create', 'sales.manage');
        $institute = $this->requireInstitute($request);

        $customers = Party::query()
            ->where('institute_id', $institute->id)
            ->whereIn('type', ['customer', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $invoices = Invoice::query()
            ->where('institute_id', $institute->id)
            ->whereIn('status', ['unpaid', 'partial'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $methods = PaymentMethod::query()
            ->where('institute_id', $institute->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('sales.payments.create', [
            'institute' => $institute,
            'customers' => $customers,
            'invoices' => $invoices,
            'methods' => $methods,
        ]);
    }

    public function show(Request $request, Payment $payment): View
    {
        $this->requirePermission('sales.payments.view', 'sales.view', 'sales.manage');
        $institute = $this->requireInstitute($request);
        abort_unless($payment->institute_id === $institute->id, 404);
        $payment->load(['party', 'invoice', 'paymentMethod']);

        return view('sales.payments.show', [
            'institute' => $institute,
            'payment' => $payment,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requirePermission('sales.payments.create', 'sales.manage');
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bkash,nagad,rocket,bank,card,online,other'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'paid_at' => ['required', 'date'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
        ]);

        $this->paymentService->record(
            $institute->id,
            $branchId,
            $validated,
            $this->actorId($request),
        );

        return redirect()
            ->route('sales.payments.index')
            ->with('status', 'Payment received successfully.');
    }
}
