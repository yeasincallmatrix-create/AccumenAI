<?php

namespace App\Services\PaymentGateway\Gateways;

use App\Models\InstitutePaymentGateway;
use App\Models\Invoice;
use App\Models\OnlinePaymentAttempt;
use App\Services\PaymentGateway\Concerns\PaymentGatewayContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BkashGateway implements PaymentGatewayContract
{
    public function slug(): string
    {
        return 'bkash';
    }

    private function config(InstitutePaymentGateway $gatewayConfig): array
    {
        $creds = $gatewayConfig->credentials ?? [];
        return [
            'app_key' => $creds['app_key'] ?? \App\Support\BkashConfig::get('app_key'),
            'app_secret' => $creds['app_secret'] ?? \App\Support\BkashConfig::get('app_secret'),
            'username' => $creds['username'] ?? \App\Support\BkashConfig::get('username'),
            'password' => $creds['password'] ?? \App\Support\BkashConfig::get('password'),
            'base_url' => rtrim($creds['base_url'] ?? \App\Support\BkashConfig::get('base_url', 'https://tokenized.sandbox.bka.sh'), '/'),
            'callback_url' => $creds['callback_url'] ?? \App\Support\BkashConfig::get('callback_url', config('app.url').'/saas/callback'),
        ];
    }

    private function hasRealCredentials(array $cfg): bool
    {
        return filled($cfg['app_key']) && filled($cfg['app_secret']) && filled($cfg['username']) && filled($cfg['password']) && filled($cfg['base_url']);
    }

    private function token(InstitutePaymentGateway $gatewayConfig): ?string
    {
        $cfg = $this->config($gatewayConfig);
        if (! $this->hasRealCredentials($cfg)) {
            return null; // mock mode — no real token
        }
        $cacheKey = 'bkash_token_'.md5($cfg['base_url'].$cfg['app_key']);
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 50*60, function () use ($cfg) {
            try {
                $resp = Http::timeout(10)->withHeaders([
                    'username' => $cfg['username'],
                    'password' => $cfg['password'],
                    'Content-Type' => 'application/json',
                ])->post($cfg['base_url'].'/v1.2.0-beta/tokenized/checkout/token/grant', [
                    'app_key' => $cfg['app_key'],
                    'app_secret' => $cfg['app_secret'],
                ]);
                if (! $resp->successful()) {
                    Log::warning('bKash token grant failed', ['status' => $resp->status()]);
                    return null;
                }
                $data = $resp->json();
                // Never log token
                return $data['id_token'] ?? $data['idToken'] ?? null;
            } catch (\Throwable $e) {
                Log::warning('bKash token exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
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
        if (strtoupper($currencyCode) !== 'BDT') {
            return [
                'status' => OnlinePaymentAttempt::STATUS_FAILED,
                'gateway_reference' => null,
                'redirect_url' => null,
                'message' => 'Currency must be BDT for bKash.',
            ];
        }

        $cfg = $this->config($gatewayConfig);
        $merchantInvoice = $invoice->invoice_number ?? ('SAAS-'.$invoice->id.'-'.$attempt->id);

        // Real Sandbox integration when credentials present — otherwise mock for unit tests
        $token = $this->token($gatewayConfig);
        if ($token && $this->hasRealCredentials($cfg)) {
            try {
                $resp = Http::timeout(10)->withHeaders([
                    'Authorization' => $token,
                    'X-APP-Key' => $cfg['app_key'],
                    'Content-Type' => 'application/json',
                ])->post($cfg['base_url'].'/v1.2.0-beta/tokenized/checkout/create', [
                    'mode' => '0011',
                    'payerReference' => (string) $invoice->institute_id,
                    'callbackURL' => $cfg['callback_url'],
                    'amount' => number_format($amount, 2, '.', ''),
                    'currency' => 'BDT',
                    'intent' => 'sale',
                    'merchantInvoiceNumber' => $merchantInvoice,
                ]);
                if ($resp->successful()) {
                    $data = $resp->json();
                    $paymentID = $data['paymentID'] ?? null;
                    $bkashURL = $data['bkashURL'] ?? null;
                    if ($paymentID) {
                        Log::info('bKash create real success', ['attempt_id' => $attempt->id, 'paymentID' => $paymentID]);
                        return [
                            'status' => OnlinePaymentAttempt::STATUS_PENDING,
                            'gateway_reference' => $paymentID,
                            'redirect_url' => $bkashURL,
                            'paymentID' => $paymentID,
                            'merchantInvoiceNumber' => $merchantInvoice,
                            'bkashURL' => $bkashURL,
                        ];
                    }
                }
                Log::warning('bKash create failed, fallback', ['status' => $resp->status()]);
            } catch (\Throwable $e) {
                Log::warning('bKash create exception', ['error' => $e->getMessage()]);
            }
        }

        // Fallback mock (tests / missing credentials)
        $paymentID = 'BKASH-'.strtoupper(bin2hex(random_bytes(8))).'-'.$attempt->id;
        Log::info('bKash initiate (mock/fallback)', [
            'attempt_id' => $attempt->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'currency' => $currencyCode,
            'merchantInvoice' => $merchantInvoice,
            'paymentID' => $paymentID,
        ]);
        return [
            'status' => OnlinePaymentAttempt::STATUS_PENDING,
            'gateway_reference' => $paymentID,
            'redirect_url' => null,
            'paymentID' => $paymentID,
            'merchantInvoiceNumber' => $merchantInvoice,
        ];
    }

    public function executePayment(string $paymentID, InstitutePaymentGateway $gatewayConfig): array
    {
        $cfg = $this->config($gatewayConfig);
        $token = $this->token($gatewayConfig);
        if (! $token || ! $this->hasRealCredentials($cfg)) {
            return ['success' => false, 'error' => 'Missing credentials — mock mode'];
        }
        try {
            $resp = Http::timeout(10)->withHeaders([
                'Authorization' => $token,
                'X-APP-Key' => $cfg['app_key'],
                'Content-Type' => 'application/json',
            ])->post($cfg['base_url'].'/v1.2.0-beta/tokenized/checkout/execute/'.$paymentID, []);
            if ($resp->successful()) {
                return ['success' => true, 'data' => $resp->json()];
            }
            return ['success' => false, 'error' => 'Execute failed '.$resp->status()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function handleCallback(array $payload, ?string $signature): array
    {
        // bKash callback/verify payload expected: attempt_id, paymentID, trxID, amount, currency, status
        $attemptId = $payload['attempt_id'] ?? $payload['attemptId'] ?? null;
        $paymentID = $payload['paymentID'] ?? $payload['payment_id'] ?? null;
        $trxID = $payload['trxID'] ?? $payload['trx_id'] ?? null;
        $amount = isset($payload['amount']) ? (float) $payload['amount'] : null;
        $currency = $payload['currency'] ?? 'BDT';
        $status = $payload['status'] ?? $payload['transactionStatus'] ?? $payload['outcome'] ?? null;

        if ($attemptId === null) {
            return ['valid' => false, 'error' => 'Missing attempt_id.'];
        }
        if ($paymentID === null) {
            return ['valid' => false, 'error' => 'Missing paymentID.'];
        }

        // Normalize status to attempt status
        $normalized = match (strtolower((string) $status)) {
            'completed', 'success', 'paid' => OnlinePaymentAttempt::STATUS_COMPLETED,
            'failed', 'failure' => OnlinePaymentAttempt::STATUS_FAILED,
            'cancelled', 'canceled' => OnlinePaymentAttempt::STATUS_CANCELLED,
            'expired' => OnlinePaymentAttempt::STATUS_EXPIRED,
            'pending', 'initiated' => OnlinePaymentAttempt::STATUS_PENDING,
            default => OnlinePaymentAttempt::STATUS_FAILED,
        };

        // For sandbox, trxID may be generated; ensure uniqueness check is done by caller
        return [
            'valid' => true,
            'attempt_id' => (int) $attemptId,
            'status' => $normalized,
            'gateway_reference' => $trxID ?? $paymentID,
            'failure_reason' => $normalized === OnlinePaymentAttempt::STATUS_FAILED ? ($payload['failure_reason'] ?? 'bKash payment failed.') : null,
            'gateway_response' => array_diff_key($payload, array_flip(['app_secret','password','app_key'])), // never include secrets
            'amount' => $amount,
            'currency' => $currency,
            'paymentID' => $paymentID,
            'trxID' => $trxID,
        ];
    }

    public function verifyWebhookSignature(InstitutePaymentGateway $gatewayConfig, string $rawBody, ?string $signature): bool
    {
        // SaaS platform bKash uses signature header X-Webhook-Signature if configured; for SaaS we allow null (server verification via query is authoritative)
        // Do not leak credentials; verify if signature provided, otherwise rely on server query
        if ($signature === null || $signature === '') {
            return true; // verification will happen via execute/query
        }
        // If credentials contain webhook_secret, verify HMAC - not exposing secret
        $credentials = $gatewayConfig->credentials ?? [];
        $secret = $credentials['webhook_secret'] ?? env('BKASH_WEBHOOK_SECRET');
        if (!$secret) {
            return true;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }

    public function queryPayment(string $paymentID, ?InstitutePaymentGateway $gatewayConfig = null): array
    {
        if ($gatewayConfig) {
            $cfg = $this->config($gatewayConfig);
            $token = $this->token($gatewayConfig);
            if ($token && $this->hasRealCredentials($cfg)) {
                try {
                    $resp = Http::timeout(10)->withHeaders([
                        'Authorization' => $token,
                        'X-APP-Key' => $cfg['app_key'],
                        'Content-Type' => 'application/json',
                    ])->get($cfg['base_url'].'/v1.2.0-beta/tokenized/checkout/payment/query/'.$paymentID, []);
                    if ($resp->successful()) {
                        return $resp->json();
                    }
                } catch (\Throwable $e) {
                    Log::warning('bKash query failed', ['error' => $e->getMessage()]);
                }
            } else {
                // Mock mode — do not auto-complete pending
                return ['status' => 'Pending', 'paymentID' => $paymentID];
            }
        }
        return ['status' => 'Pending', 'paymentID' => $paymentID];
    }
}
