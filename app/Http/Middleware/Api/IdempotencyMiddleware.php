<?php

namespace App\Http\Middleware\Api;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key support for /api/v1/* POST endpoints.
 *
 * - Header missing → pass through.
 * - Same key + same payload hash → replay cached response.
 * - Same key + different payload → 422 IDEMPOTENCY_CONFLICT.
 * - New key → proceed, cache (status + body) for 24 hours.
 */
class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            return $next($request);
        }

        if (! preg_match('/^[A-Za-z0-9\-_:.]{8,128}$/', $key)) {
            return ApiResponse::error('VALIDATION_FAILED', 'Invalid Idempotency-Key format.', 422);
        }

        $cacheKey = 'idem:'.$key;
        $payloadHash = hash('sha256', (string) $request->getContent());

        $stored = Cache::get($cacheKey);

        if (is_array($stored)) {
            if (($stored['hash'] ?? null) !== $payloadHash) {
                return ApiResponse::error(
                    'IDEMPOTENCY_CONFLICT',
                    'Idempotency-Key was already used with a different payload.',
                    422
                );
            }

            return response()->json(
                json_decode($stored['body'], true),
                $stored['status'] ?? 200
            );
        }

        /** @var \Illuminate\Http\JsonResponse $response */
        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            Cache::put($cacheKey, [
                'hash' => $payloadHash,
                'status' => $response->getStatusCode(),
                'body' => $response->getContent(),
            ], now()->addHours(24));
        }

        return $response;
    }
}
