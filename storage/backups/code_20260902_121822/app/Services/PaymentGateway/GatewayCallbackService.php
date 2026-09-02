<?php

namespace App\Services\PaymentGateway;

use App\Models\AccountingSetting;
use App\Models\ChartOfAccount;
use App\Models\InstitutePaymentGateway;
use App\Models\Invoice;
use App\Models\OnlinePaymentAttempt;
use App\Services\Accounting\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GatewayCallbackService
{
    public function __construct(
        private readonly PaymentGatewayManager $manager,
        private readonly PaymentService $paymentService,
    ) {}

    public function handleCallback(string $gatewaySlug, array $payload, ?string $signature): OnlinePaymentAttempt
    {
        $gatewayConfig = InstitutePaymentGateway::query()
            ->where('is_enabled', true)
            ->with('gateway')
            ->first();

        if ($gatewayConfig === null || $gatewayConfig->gateway->slug !== $gatewaySlug) {
            throw ValidationException::withMessages([
                'gateway' => 'Gateway not configured.',
            ]);
        }

        $gateway = $this->manager->resolveGateway($gatewaySlug);

        if (! $gateway->verifyWebhookSignature($gatewayConfig, json_encode($payload), $signature)) {
            throw ValidationException::withMessages([
                'signature' => 'Invalid webhook signature.',
            ]);
        }

        $result = $gateway->handleCallback($payload, $signature);

        if (! ($result['valid'] ?? false)) {
            throw ValidationException::withMessages([
                'payload' => $result['error'] ?? 'Invalid callback payload.',
            ]);
        }

        $attemptId = (int) $result['attempt_id'];
        $attempt = OnlinePaymentAttempt::withoutGlobalScopes()
            ->where('institute_id', $gatewayConfig->institute_id)
            ->where('id', $attemptId)
            ->first();

        if ($attempt === null) {
            throw ValidationException::withMessages([
                'attempt' => 'Payment attempt not found.',
            ]);
        }

        if ($attempt->isTerminal()) {
            return $attempt->fresh();
        }

        $newStatus = $result['status'];
        $updateData = [
            'status' => $newStatus,
            'gateway_response' => $result['gateway_response'] ?? null,
            'completed_at' => in_array($newStatus, [OnlinePaymentAttempt::STATUS_COMPLETED, OnlinePaymentAttempt::STATUS_FAILED], true) ? now() : null,
        ];

        if ($newStatus === OnlinePaymentAttempt::STATUS_FAILED) {
            $updateData['failure_reason'] = $result['failure_reason'] ?? 'Payment failed.';
        }

        if (($result['gateway_reference'] ?? null) !== null && $attempt->gateway_reference === null) {
            $updateData['gateway_reference'] = $result['gateway_reference'];
        }

        $attempt->forceFill($updateData)->save();

        if ($newStatus === OnlinePaymentAttempt::STATUS_COMPLETED) {
            $this->recordPaymentFromAttempt($attempt);
        }

        return $attempt->fresh();
    }

    private function recordPaymentFromAttempt(OnlinePaymentAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt) {
            $payment = $this->paymentService->record(
                $attempt->institute_id,
                $attempt->branch_id,
                [
                    'invoice_id' => $attempt->invoice_id,
                    'amount' => $attempt->amount,
                    'payment_method' => 'online',
                    'payment_method_id' => null,
                    'installment_id' => $attempt->installment_id,
                    'transaction_id' => $attempt->gateway_reference,
                    'paid_at' => $attempt->completed_at?->toDateTimeString() ?? now()->toDateTimeString(),
                ],
                $attempt->created_by,
            );

            $attempt->forceFill([
                'payment_id' => $payment->id,
                'journal_id' => $payment->journal_id,
            ])->save();
        });
    }
}
