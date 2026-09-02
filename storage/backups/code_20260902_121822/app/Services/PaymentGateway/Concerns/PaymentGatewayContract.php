<?php

namespace App\Services\PaymentGateway\Concerns;

use App\Models\InstitutePaymentGateway;
use App\Models\Invoice;
use App\Models\OnlinePaymentAttempt;

interface PaymentGatewayContract
{
    public function slug(): string;

    public function initiatePayment(
        InstitutePaymentGateway $gatewayConfig,
        Invoice $invoice,
        OnlinePaymentAttempt $attempt,
        float $amount,
        string $currencyCode,
        ?int $installmentId,
        ?string $idempotencyKey,
    ): array;

    public function handleCallback(array $payload, ?string $signature): array;

    public function verifyWebhookSignature(InstitutePaymentGateway $gatewayConfig, string $rawBody, ?string $signature): bool;
}
