<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Payment;
use App\Services\Accounting\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Payments (Step 32): record and reverse AR receipts against invoices.
 * Overpayments are rejected with a 422; each payment posts a RECEIPT journal.
 */
class FinancePaymentController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly PaymentService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = Payment::query()->with(['invoice', 'party', 'paymentMethod', 'receivedBy']);

        if (filled($q = $request->query('q'))) {
            $query->whereHas('invoice', fn ($builder) => $builder->where('invoice_number', 'like', "%{$q}%"));
        }

        if (filled($request->query('from'))) {
            $query->whereDate('paid_at', '>=', $request->query('from'));
        }

        if (filled($request->query('to'))) {
            $query->whereDate('paid_at', '<=', $request->query('to'));
        }

        $payments = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.finance.payments.index', [
            'institute' => $institute,
            'payments' => $payments,
            'filters' => $request->query(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $this->validated($request);

        $payment = $this->service->record(
            $institute->id,
            $this->actingBranchId($request),
            $data,
            (int) $this->actorId($request),
        );

        return redirect()
            ->route('finance.invoices.show', $payment->invoice_id)
            ->with('status', 'Payment of '.number_format((float) $payment->amount, 2).' recorded.');
    }

    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->reverse(
            $payment,
            $institute->id,
            (int) $this->actorId($request),
            $request->input('reason'),
        );

        return redirect()
            ->route('finance.invoices.show', $payment->invoice_id)
            ->with('status', 'Payment reversed and the receipt journal has been reversed.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'invoice_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(['cash', 'bkash', 'nagad', 'rocket', 'bank', 'card', 'other'])],
            'payment_method_id' => ['nullable', 'integer'],
            'installment_id' => ['nullable', 'integer'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
        ]);
    }
}
