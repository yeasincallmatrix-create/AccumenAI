<?php

namespace App\Services;

use App\Models\Institute;
use App\Models\Invoice;
use App\Models\OnlinePaymentAttempt;
use App\Models\SubscriptionPackage;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaasSubscriptionService
{
    public function __construct(
        private readonly PaymentGatewayManager $gatewayManager,
        private readonly ModuleAccessService $moduleAccess,
    ) {}

    /**
     * Bangladesh-only: institute.country must be Bangladesh
     */
    public function assertBangladesh(Institute $institute): void
    {
        $country = $institute->country ?? $institute->country_name ?? null;
        // institutes.country stores country name such as Bangladesh
        if (strtolower((string) $institute->country) !== 'bangladesh' && strtolower((string) $country) !== 'bangladesh') {
            throw ValidationException::withMessages(['country' => 'bKash SaaS checkout is available only for Bangladesh institutes.']);
        }
    }

    public function availablePackages(): \Illuminate\Support\Collection
    {
        return SubscriptionPackage::where('status','active')->orderBy('id')->get();
    }

    public function calculatePrice(SubscriptionPackage $package, string $billingCycle): float
    {
        $cycle = strtolower($billingCycle);
        if (!in_array($cycle, ['monthly','yearly'], true)) {
            throw ValidationException::withMessages(['billing_cycle' => 'Invalid billing cycle.']);
        }
        if ($cycle === 'monthly') {
            return (float) ($package->price_monthly ?? $package->price ?? 0);
        }
        return (float) ($package->price_yearly ?? $package->price ?? 0);
    }

    /**
     * Create SaaS invoice + online payment attempt (pending) — amount from DB, not request
     */
    public function createCheckout(Institute $institute, int $packageId, string $billingCycle, ?int $actorId = null): array
    {
        $this->assertBangladesh($institute);
        $package = SubscriptionPackage::where('id',$packageId)->where('status','active')->first();
        if (!$package) {
            throw ValidationException::withMessages(['package_id' => 'Selected package is not available.']);
        }
        if (strtolower($package->slug) === 'free') {
            throw ValidationException::withMessages(['package_id' => 'FREE package does not require payment.']);
        }
        $billingCycle = strtolower($billingCycle);
        if (!in_array($billingCycle, ['monthly','yearly'], true)) {
            throw ValidationException::withMessages(['billing_cycle' => 'Billing cycle must be monthly or yearly.']);
        }
        $price = $this->calculatePrice($package, $billingCycle);
        if ($price <= 0) {
            throw ValidationException::withMessages(['price' => 'Package price is not configured.']);
        }

        // Create invoice via TenantScoped Invoice (bypass journal posting for SaaS — keep as platform revenue, not institute cash book)
        $invoice = DB::transaction(function () use ($institute, $package, $billingCycle, $price, $actorId) {
            $invoiceNumber = $this->allocateInvoiceNumber($institute->id);
            $description = $package->name.' — '.ucfirst($billingCycle).' (SaaS)';
            $invoice = Invoice::create([
                'institute_id' => $institute->id,
                'invoice_number' => $invoiceNumber,
                'invoice_type' => 'other',
                'total_amount' => $price,
                'discount' => 0,
                'payable_amount' => $price,
                'paid_amount' => 0,
                'due_amount' => $price,
                'status' => 'unpaid',
                'currency_id' => null, // BDT base
                'invoice_meta' => [
                    'source' => 'saas',
                    'package_id' => $package->id,
                    'package_slug' => $package->slug,
                    'billing_cycle' => $billingCycle,
                    'country' => 'Bangladesh',
                    'currency' => 'BDT',
                ],
                'created_by' => $actorId,
            ]);
            \App\Models\InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $description,
                'amount' => $price,
            ]);
            return $invoice;
        });

        $attempt = $this->gatewayManager->initiateSaas(
            $institute->id,
            $invoice->id,
            $price,
            $actorId,
            (string) Str::ulid()
        );

        return [
            'invoice' => $invoice->fresh(),
            'attempt' => $attempt->fresh(),
            'package' => $package,
            'price' => $price,
            'billing_cycle' => $billingCycle,
        ];
    }

    /**
     * Verify and activate after bKash callback — idempotent, transaction-safe
     */
    public function verifyAndActivate(OnlinePaymentAttempt $attempt, array $gatewayPayload, ?string $signature = null): OnlinePaymentAttempt
    {
        $invoice = $attempt->invoice;
        if (!$invoice) {
            throw ValidationException::withMessages(['invoice' => 'Invoice not found.']);
        }
        $institute = \App\Models\Institute::withoutGlobalScopes()->find($attempt->institute_id);
        if (!$institute) {
            throw ValidationException::withMessages(['institute' => 'Institute not found.']);
        }
        $this->assertBangladesh($institute);
        // Extract expected values server-side
        $expectedAmount = (float) $invoice->payable_amount;
        $expectedCurrency = 'BDT';
        $meta = $invoice->invoice_meta ?? [];
        $packageId = $meta['package_id'] ?? null;
        $billingCycle = $meta['billing_cycle'] ?? 'monthly';
        if (!$packageId) {
            throw ValidationException::withMessages(['package' => 'SaaS invoice missing package.']);
        }
        $package = SubscriptionPackage::find($packageId);
        if (!$package || $package->status !== 'active') {
            throw ValidationException::withMessages(['package' => 'Package not available.']);
        }

        // Resolve gateway for verification
        $gatewayConfig = new \App\Models\InstitutePaymentGateway([
            'institute_id' => $attempt->institute_id,
            'gateway_id' => $attempt->gateway_id,
            'is_enabled' => true,
            'credentials' => [],
        ]);
        $gateway = $this->gatewayManager->resolveGateway('bkash');
        if (!$gateway->verifyWebhookSignature($gatewayConfig, json_encode($gatewayPayload), $signature)) {
            throw ValidationException::withMessages(['signature' => 'Invalid signature.']);
        }
        $result = $gateway->handleCallback(array_merge($gatewayPayload, ['attempt_id' => $attempt->id]), $signature);
        if (!($result['valid'] ?? false)) {
            throw ValidationException::withMessages(['payload' => $result['error'] ?? 'Invalid payload']);
        }
        // Real Sandbox: if status pending and we have paymentID, try server execute/query for authoritative status
        if (($result['status'] ?? null) === OnlinePaymentAttempt::STATUS_PENDING && isset($result['paymentID']) && $gateway instanceof \App\Services\PaymentGateway\Gateways\BkashGateway) {
            $gatewayConfigReal = new \App\Models\InstitutePaymentGateway(['institute_id'=>$attempt->institute_id,'gateway_id'=>$attempt->gateway_id,'is_enabled'=>true,'credentials'=>[]]);
            $exec = $gateway->executePayment($result['paymentID'], $gatewayConfigReal);
            if ($exec['success'] ?? false) {
                $data = $exec['data'] ?? [];
                $trxStatus = $data['transactionStatus'] ?? $data['status'] ?? 'Completed';
                $result['status'] = strtolower($trxStatus)==='completed' ? OnlinePaymentAttempt::STATUS_COMPLETED : $result['status'];
                $result['gateway_reference'] = $data['trxID'] ?? $data['trxId'] ?? $result['gateway_reference'];
                $result['gateway_response'] = array_merge($result['gateway_response'] ?? [], ['execute' => $data]);
            } else {
                // Fallback to query
                $query = $gateway->queryPayment($result['paymentID'], $gatewayConfigReal);
                if (($query['transactionStatus'] ?? $query['status'] ?? null) === 'Completed') {
                    $result['status'] = OnlinePaymentAttempt::STATUS_COMPLETED;
                }
            }
        }

        // Verify amount/currency
        $payloadAmount = isset($result['amount']) ? (float)$result['amount'] : (isset($gatewayPayload['amount']) ? (float)$gatewayPayload['amount'] : null);
        $payloadCurrency = $result['currency'] ?? $gatewayPayload['currency'] ?? 'BDT';
        if ($payloadAmount !== null && abs($payloadAmount - $expectedAmount) > 0.01) {
            throw ValidationException::withMessages(['amount' => 'Amount mismatch. Expected '.number_format($expectedAmount,2).' got '.number_format($payloadAmount,2)]);
        }
        if (strtoupper($payloadCurrency) !== $expectedCurrency) {
            throw ValidationException::withMessages(['currency' => 'Currency must be BDT']);
        }
        // trxID/paymentID uniqueness
        $trx = $result['gateway_reference'] ?? $result['trxID'] ?? null;
        if ($trx && OnlinePaymentAttempt::where('gateway_reference', $trx)->where('id','!=',$attempt->id)->exists()) {
            throw ValidationException::withMessages(['trxID' => 'Transaction already used.']);
        }

        $newStatus = $result['status'];
        return DB::transaction(function () use ($attempt, $newStatus, $result, $trx, $invoice, $institute, $package, $billingCycle) {
            $fresh = OnlinePaymentAttempt::withoutGlobalScopes()->where('id',$attempt->id)->lockForUpdate()->first();
            if ($fresh->isTerminal()) {
                return $fresh; // idempotent
            }
            $fresh->forceFill([
                'status' => $newStatus,
                'gateway_response' => $result['gateway_response'] ?? $result,
                'gateway_reference' => $trx ?? $fresh->gateway_reference,
                'completed_at' => in_array($newStatus, [OnlinePaymentAttempt::STATUS_COMPLETED, OnlinePaymentAttempt::STATUS_FAILED], true) ? now() : null,
            ])->save();

            if ($newStatus === OnlinePaymentAttempt::STATUS_COMPLETED) {
                // Settle invoice
                $fresh->refresh();
                $invoice->forceFill([
                    'paid_amount' => $invoice->payable_amount,
                    'due_amount' => 0,
                    'status' => 'paid',
                ])->save();
                // Do NOT create finance_transactions for SaaS platform revenue — keep invoice as audit only (per spec 3,15)

                // Create institute_subscriptions row (actual columns: start_date, end_date, price_paid, payment_reference, billing_cycle monthly/yearly/trial)
                $startsAt = now()->toDateString();
                $endsAt = $billingCycle === 'yearly' ? now()->addYear()->toDateString() : now()->addMonth()->toDateString();
                \App\Models\InstituteSubscription::create([
                    'institute_id' => $institute->id,
                    'package_id' => $package->id,
                    'status' => 'active',
                    'start_date' => $startsAt,
                    'end_date' => $endsAt,
                    'price_paid' => $invoice->payable_amount,
                    'billing_cycle' => $billingCycle === 'yearly' ? 'yearly' : 'monthly',
                    'payment_reference' => $trx ?? $attempt->gateway_reference,
                ]);
                // Activate package via ModuleAccessService (preserves 63 entitlements)
                $oldPackageId = $institute->package_id;
                $institute->forceFill(['package_id' => $package->id])->save();
                $this->moduleAccess->changePackage($institute, $oldPackageId, $package->id, $attempt->created_by);
                // changePackage already flushes cache; ensure also flush
                $this->moduleAccess->flushCache($institute->id);
            }
            return $fresh->fresh();
        });
    }

    private function allocateInvoiceNumber(int $instituteId): string
    {
        for ($i=0;$i<10;$i++) {
            $candidate = 'SAAS-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
            if (! Invoice::where('institute_id',$instituteId)->where('invoice_number',$candidate)->exists()) {
                return $candidate;
            }
        }
        throw new \RuntimeException('Could not allocate SaaS invoice number');
    }
}
