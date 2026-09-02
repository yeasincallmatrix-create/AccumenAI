<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Invoice;
use App\Models\OnlinePaymentAttempt;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnlinePaymentController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly PaymentGatewayManager $gatewayManager) {}

    public function initiate(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $invoiceId = (int) $request->query('invoice_id', 0);

        $invoice = Invoice::query()
            ->where('institute_id', $institute->id)
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'paid')
            ->with(['student', 'installments', 'currency'])
            ->find($invoiceId);

        $gateways = $this->gatewayManager->enabledGateways($institute->id);

        return view('institute.finance.online-payments.initiate', [
            'institute' => $institute,
            'invoice' => $invoice,
            'gateways' => $gateways,
            'branchId' => $branchId,
        ]);
    }

    public function process(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'installment_id' => ['nullable', 'integer'],
        ]);

        $invoice = Invoice::query()
            ->where('institute_id', $institute->id)
            ->find((int) $data['invoice_id']);

        if ($invoice === null || $invoice->status === 'cancelled' || $invoice->status === 'paid') {
            return back()->withErrors(['invoice_id' => 'Invalid invoice.']);
        }

        $attempt = $this->gatewayManager->initiate(
            $institute->id,
            $branchId,
            (int) $data['invoice_id'],
            (float) $data['amount'],
            ($data['installment_id'] ?? null) !== null ? (int) $data['installment_id'] : null,
            $invoice->student_id,
            (int) $this->actorId($request),
        );

        if ($attempt->gateway_reference !== null) {
            return redirect()->route('online-payments.status', $attempt->id)
                ->with('status', 'Payment initiated. Reference: '.$attempt->gateway_reference);
        }

        return back()->with('status', 'Payment attempt recorded. Awaiting gateway confirmation.');
    }

    public function status(Request $request, OnlinePaymentAttempt $attempt): View
    {
        $institute = $this->requireInstitute($request);

        if ((int) $attempt->institute_id !== (int) $institute->id) {
            abort(404);
        }

        $attempt->load(['gateway', 'invoice', 'payment', 'student']);

        return view('institute.finance.online-payments.status', [
            'institute' => $institute,
            'attempt' => $attempt,
        ]);
    }

    public function history(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $attempts = OnlinePaymentAttempt::query()
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($s) => $s->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->with(['gateway', 'invoice', 'student', 'payment'])
            ->orderByDesc('id')
            ->paginate(25);

        return view('institute.finance.online-payments.history', [
            'institute' => $institute,
            'attempts' => $attempts,
        ]);
    }
}
