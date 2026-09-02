<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\OnlinePaymentAttempt;
use App\Models\SubscriptionPackage;
use App\Services\SaasSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SaasCheckoutController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly SaasSubscriptionService $saas) {}

    public function packages(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        // Do not block view; UI will hide bKash for non-Bangladesh, server checkout still rejects
        $packages = $this->saas->availablePackages();
        $currentPackageId = $institute->package_id;
        return view('saas.packages', compact('institute','packages','currentPackageId'));
    }

    public function checkoutForm(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        // Bangladesh-only UI, but endpoint also protected
        $packages = $this->saas->availablePackages();
        return view('saas.checkout', compact('institute','packages'));
    }

    public function checkout(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $request->validate([
            'package_id' => ['required','exists:subscription_packages,id'],
            'billing_cycle' => ['required','in:monthly,yearly'],
        ]);
        $packageId = (int) $request->input('package_id');
        $billingCycle = (string) $request->input('billing_cycle');
        // Bangladesh-only enforcement server-side
        $this->saas->assertBangladesh($institute);
        $result = $this->saas->createCheckout($institute, $packageId, $billingCycle, $request->user()?->id);
        // For bKash, redirect to bKash URL if provided, else to attempt status page
        $attempt = $result['attempt'];
        if (!empty($result['attempt']->gateway_reference)) {
            // In real bKash, redirect_url would be bKash checkout; here we simulate immediate callback via query
            return redirect()->route('saas.callback', ['attempt' => $attempt->id, 'paymentID' => $attempt->gateway_reference]);
        }
        return redirect()->route('saas.attempt.show', $attempt);
    }

    public function attemptShow(Request $request, OnlinePaymentAttempt $attempt): View
    {
        $institute = $this->requireInstitute($request);
        if ((int)$attempt->institute_id !== (int)$institute->id) abort(403);
        return view('saas.attempt', compact('attempt','institute'));
    }

    public function callback(Request $request, OnlinePaymentAttempt $attempt): \Illuminate\Http\Response|RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        if ((int)$attempt->institute_id !== (int)$institute->id) abort(403);
        $this->saas->assertBangladesh($institute);
        // Never trust frontend success — verify server-side
        $payload = $request->all();
        // Ensure attempt_id is present for verification
        $payload['attempt_id'] = $attempt->id;
        $payload['paymentID'] = $payload['paymentID'] ?? $attempt->gateway_reference;
        $payload['amount'] = $payload['amount'] ?? $attempt->amount;
        $payload['currency'] = $payload['currency'] ?? $attempt->currency_code ?? 'BDT';
        $payload['status'] = $payload['status'] ?? 'success';
        // If no status provided, treat as success for redirect flow (will be verified via amount/currency)
        if (!isset($payload['status'])) $payload['status'] = 'success';
        try {
            $verified = $this->saas->verifyAndActivate($attempt, $payload, $request->header('X-Signature'));
            if ($verified->status === OnlinePaymentAttempt::STATUS_COMPLETED) {
                return redirect()->route('saas.packages')->with('status','Package activated: '.$verified->invoice->invoice_number);
            }
            return redirect()->route('saas.attempt.show', $attempt)->with('error','Payment not completed: '.$verified->status);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    // Webhook for bKash server-to-server (no auth session, but institute scoped via payload attempt)
    public function webhook(Request $request, string $gatewaySlug = 'bkash'): \Illuminate\Http\JsonResponse
    {
        // Webhook is unauthenticated but must be idempotent and verified
        $payload = $request->all();
        $signature = $request->header('X-Webhook-Signature') ?? $request->header('X-Signature');
        try {
            // Find attempt via gateway_reference or attempt_id in payload
            $attemptId = $payload['attempt_id'] ?? $payload['merchantInvoiceNumber'] ?? null;
            $attempt = null;
            if ($attemptId) {
                $attempt = OnlinePaymentAttempt::withoutGlobalScopes()->where('id', (int)$attemptId)->first();
            }
            if (!$attempt && isset($payload['paymentID'])) {
                $attempt = OnlinePaymentAttempt::withoutGlobalScopes()->where('gateway_reference', $payload['paymentID'])->first();
            }
            if (!$attempt) {
                return response()->json(['error' => 'Attempt not found'], 422);
            }
            $result = $this->saas->verifyAndActivate($attempt, $payload, $signature);
            return response()->json(['status'=>'ok','attempt_id'=>$result->id,'attempt_status'=>$result->status]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error'=>$e->getMessage()], 422);
        }
    }
}
