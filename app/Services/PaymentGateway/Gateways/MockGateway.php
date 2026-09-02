<?php

namespace App\Services\PaymentGateway\Gateways;

use App\Models\InstitutePaymentGateway;
use App\Models\Invoice;
use App\Models\OnlinePaymentAttempt;
use App\Services\PaymentGateway\Concerns\PaymentGatewayContract;

class MockGateway implements PaymentGatewayContract
{
    public function slug(): string
    {
        return 'mock';
    }

    public function initiatePayment(
        InstitutePaymentGateway $gatewayConfig,
        Invoice $invoice,
        OnlinePaymentAttempt $attempt,
        float $amount,
        string $currencyCode,
        ?int $installmentId,
        ?string $idempotencyKey,
    ): array {
        return [
            'status' => OnlinePaymentAttempt::STATUS_PENDING,
            'gateway_reference' => 'MOCK-'.$attempt->id.'-'.strtoupper(bin2hex(random_bytes(6))),
            'redirect_url' => null,
            'message' => 'Mock gateway: payment initiated.',
        ];
    }

    public function handleCallback(array $payload, ?string $signature): array
    {
        $attemptId = $payload['attempt_id'] ?? null;
        $outcome = $payload['outcome'] ?? 'success';

        if ($attemptId === null) {
            return ['valid' => false, 'error' => 'Missing attempt_id.'];
        }

        return [
            'valid' => true,
            'attempt_id' => (int) $attemptId,
            'status' => $outcome === 'success'
                ? OnlinePaymentAttempt::STATUS_COMPLETED
                : OnlinePaymentAttempt::STATUS_FAILED,
            'gateway_reference' => $payload['gateway_reference'] ?? null,
            'failure_reason' => $outcome === 'success' ? null : ($payload['failure_reason'] ?? 'Payment failed.'),
            'gateway_response' => $payload,
        ];
    }

    public function verifyWebhookSignature(InstitutePaymentGateway $gatewayConfig, string $rawBody, ?string $signature): bool
    {
        return true;
    }
}
