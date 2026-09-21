<?php

namespace App\Http\Middleware;

use App\Services\LabIntegration\DeviceAuthService;
use App\Services\LabIntegration\HmacSignature;
use Closure;
use Illuminate\Http\Request;

class AuthenticateLabDevice
{
    public function __construct(protected DeviceAuthService $auth) {}

    public function handle(Request $request, Closure $next, ?string $ability = null)
    {
        // Extract bearer token from Authorization header
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json([
                'code' => 'MISSING_TOKEN',
                'message' => 'Authorization bearer token required.',
                'retryable' => false,
            ], 401);
        }

        // Authenticate
        $analyzer = $this->auth->authenticate($token, $request->ip());
        if (! $analyzer) {
            return response()->json([
                'code' => 'INVALID_TOKEN',
                'message' => 'Invalid or revoked device token.',
                'retryable' => false,
            ], 401);
        }

        // Bind analyzer to request for downstream
        $request->attributes->set('lab_analyzer', $analyzer);
        $request->attributes->set('lab_device_token', $token);

        // Ability check (optional)
        if ($ability) {
            $credential = $analyzer->credential;
            $abilities = $credential->abilities ?? [];
            if (! in_array($ability, $abilities, true)) {
                return response()->json([
                    'code' => 'MISSING_ABILITY',
                    'message' => "Device missing required ability: {$ability}",
                    'retryable' => false,
                ], 403);
            }
        }

        // HMAC verification for POST endpoints (result ingest)
        if ($request->isMethod('POST') && $request->is('api/lab-gateway/results')) {
            $ts = (int) $request->header(HmacSignature::TIMESTAMP_HEADER);
            $sig = (string) $request->header(HmacSignature::SIGNATURE_HEADER);
            $body = $request->getContent();

            if (! $ts || ! $sig) {
                return response()->json([
                    'code' => 'MISSING_SIGNATURE',
                    'message' => 'X-Lab-Timestamp and X-Lab-Signature required.',
                    'retryable' => false,
                ], 401);
            }

            if (! HmacSignature::verify($body, $ts, $sig, $token)) {
                return response()->json([
                    'code' => 'INVALID_SIGNATURE',
                    'message' => 'HMAC signature verification failed.',
                    'retryable' => false,
                ], 401);
            }
        }

        return $next($request);
    }
}
