<?php

namespace App\Services\PaymentGateway;

use App\Models\InstitutePaymentGateway;
use App\Models\OnlinePaymentAttempt;
use App\Models\PaymentGateway;
use App\Services\PaymentGateway\Concerns\PaymentGatewayContract;
use App\Services\PaymentGateway\Gateways\BkashGateway;
use App\Services\PaymentGateway\Gateways\MockGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PaymentGatewayManager
{
    private const RATE_LIMIT_KEY = 'online_payment_init:';
    private const RATE_LIMIT_MAX = 10;
    private const RATE_LIMIT_WINDOW = 60;

    public function initiate(
        int $instituteId,
        ?int $branchId,
        int $invoiceId,
        float $amount,
        ?int $installmentId,
        ?int $studentId,
        ?int $actorId,
        ?string $idempotencyKey = null,
    ): OnlinePaymentAttempt {
        $rateLimitKey = self::RATE_LIMIT_KEY . $instituteId . ':' . ($actorId ?? 0);
        $attempts = Cache::get($rateLimitKey, 0);

        if ($attempts >= self::RATE_LIMIT_MAX) {
            throw ValidationException::withMessages([
                'rate_limit' => 'Too many payment attempts. Please wait a moment.',
            ]);
        }

        Cache::put($rateLimitKey, $attempts + 1, now()->addSeconds(self::RATE_LIMIT_WINDOW));

        $gatewayConfig = InstitutePaymentGateway::query()
            ->where('institute_id', $instituteId)
            ->where('is_enabled', true)
            ->with('gateway')
            ->first();

        if ($gatewayConfig === null || ! $gatewayConfig->gateway->is_active) {
            throw ValidationException::withMessages([
                'gateway' => 'No online payment gateway is configured for this institute.',
            ]);
        }

        $invoice = \App\Models\Invoice::query()
            ->where('institute_id', $instituteId)
            ->find($invoiceId);

        if ($invoice === null) {
            throw ValidationException::withMessages([
                'invoice_id' => 'The invoice does not exist.',
            ]);
        }

        if ($invoice->status === 'cancelled' || $invoice->status === 'paid') {
            throw ValidationException::withMessages([
                'invoice_id' => 'This invoice cannot accept payments.',
            ]);
        }

        if ($amount <= 0 || $amount > (float) $invoice->due_amount + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => 'The amount must be between 0 and the invoice due amount ('.number_format((float) $invoice->due_amount, 2).').',
            ]);
        }

        $attempt = OnlinePaymentAttempt::create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'gateway_id' => $gatewayConfig->gateway_id,
            'invoice_id' => $invoiceId,
            'installment_id' => $installmentId,
            'student_id' => $studentId,
            'amount' => $amount,
            'base_amount' => $invoice->base_payable_amount ? $amount : null,
            'exchange_rate' => $invoice->exchange_rate,
            'currency_code' => $invoice->currency?->code,
            'status' => OnlinePaymentAttempt::STATUS_PENDING,
            'idempotency_key' => $idempotencyKey,
            'created_by' => $actorId,
            'initiated_at' => now(),
        ]);

        $gateway = $this->resolveGateway($gatewayConfig->gateway->slug);

        $result = $gateway->initiatePayment(
            $gatewayConfig,
            $invoice,
            $attempt,
            $amount,
            $invoice->currency?->code ?? 'BDT',
            $installmentId,
            $idempotencyKey,
        );

        $attempt->forceFill([
            'status' => $result['status'] ?? OnlinePaymentAttempt::STATUS_PENDING,
            'gateway_reference' => $result['gateway_reference'] ?? null,
        ])->save();

        return $attempt;
    }

    public function resolveGateway(string $slug): PaymentGatewayContract
    {
        return match ($slug) {
            'mock' => new MockGateway(),
            'bkash' => new BkashGateway(),
            default => throw ValidationException::withMessages([
                'gateway' => "Unknown payment gateway: {$slug}.",
            ]),
        };
    }

    /**
     * Ensure platform bKash gateway exists (SaaS revenue, not per-institute).
     */
    public function ensureBkashGateway(): \App\Models\PaymentGateway
    {
        return PaymentGateway::firstOrCreate(
            ['slug' => 'bkash'],
            ['name' => 'bKash', 'description' => 'bKash SaaS subscription (Bangladesh)', 'is_active' => true, 'config_schema' => []]
        );
    }

    /**
     * Platform-level bKash initiate for SaaS — bypasses InstitutePaymentGateway, uses global gateway.
     * Keeps same idempotency/verification guarantees.
     */
    public function initiateSaas(
        int $instituteId,
        int $invoiceId,
        float $amount,
        ?int $actorId = null,
        ?string $idempotencyKey = null,
    ): OnlinePaymentAttempt {
        $gateway = $this->ensureBkashGateway();
        if (! $gateway->is_active) {
            throw ValidationException::withMessages(['gateway' => 'bKash gateway is disabled.']);
        }
        // Create synthetic InstitutePaymentGateway for contract compatibility (platform credentials)
        $gatewayConfig = new \App\Models\InstitutePaymentGateway([
            'institute_id' => $instituteId,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'credentials' => [
                'app_key' => env('BKASH_APP_KEY'),
                'app_secret' => env('BKASH_APP_SECRET'),
                'username' => env('BKASH_USERNAME'),
                'password' => env('BKASH_PASSWORD'),
                'base_url' => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh'),
                'sandbox' => env('BKASH_SANDBOX', true),
            ],
        ]);
        $gatewayConfig->setRelation('gateway', $gateway);

        $invoice = \App\Models\Invoice::where('institute_id', $instituteId)->findOrFail($invoiceId);
        $attempt = OnlinePaymentAttempt::create([
            'institute_id' => $instituteId,
            'branch_id' => null,
            'gateway_id' => $gateway->id,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'currency_code' => 'BDT',
            'status' => OnlinePaymentAttempt::STATUS_PENDING,
            'idempotency_key' => $idempotencyKey ?? (string) \Illuminate\Support\Str::ulid(),
            'created_by' => $actorId,
            'initiated_at' => now(),
        ]);
        $bkash = $this->resolveGateway('bkash');
        $result = $bkash->initiatePayment($gatewayConfig, $invoice, $attempt, $amount, 'BDT', null, $idempotencyKey);
        $attempt->forceFill([
            'status' => $result['status'] ?? OnlinePaymentAttempt::STATUS_PENDING,
            'gateway_reference' => $result['gateway_reference'] ?? null,
            'gateway_response' => $result,
        ])->save();
        return $attempt;
    }

    public function enabledGateways(int $instituteId): \Illuminate\Support\Collection
    {
        return InstitutePaymentGateway::query()
            ->where('institute_id', $instituteId)
            ->where('is_enabled', true)
            ->with('gateway')
            ->get()
            ->filter(fn (InstitutePaymentGateway $ig) => $ig->gateway?->is_active);
    }

    public function allGateways(): \Illuminate\Support\Collection
    {
        return PaymentGateway::query()->where('is_active', true)->get();
    }
}
