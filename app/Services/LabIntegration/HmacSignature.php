<?php

namespace App\Services\LabIntegration;

/**
 * HMAC-SHA256 request signing for gateway → LIS result POSTs.
 *
 * MVP design: the device token itself acts as the HMAC secret (the gateway
 * stores it locally and the server looks up the credential by token prefix).
 * A separate derived signing_secret is Phase 6 scope; the verify() contract
 * (body + timestamp + secret) stays identical so rotation is transparent.
 */
class HmacSignature
{
    public const TIMESTAMP_HEADER = 'X-Lab-Timestamp';
    public const SIGNATURE_HEADER = 'X-Lab-Signature';
    public const SKEW_SECONDS = 300; // ±5 min

    /**
     * Compute HMAC-SHA256 signature over body + timestamp.
     * Secret = device token plain (per-device secret).
     */
    public static function compute(string $body, int $timestamp, string $secret): string
    {
        $payload = $timestamp.'.'.$body;

        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify an incoming request.
     * Returns true if signature valid and timestamp within skew.
     */
    public static function verify(string $body, int $timestamp, string $signature, string $secret): bool
    {
        if (abs(time() - $timestamp) > self::SKEW_SECONDS) {
            return false;
        }
        $expected = self::compute($body, $timestamp, $secret);

        return hash_equals($expected, $signature);
    }
}
