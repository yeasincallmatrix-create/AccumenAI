<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Standard envelope for /api/v1/* (mobile).
 *
 * Success: { "success": true, "data": ..., "meta": {...} }
 * Error:   { "success": false, "error": { "code", "message", "details" } }
 *
 * NOTE: the legacy web-adjacent API (routes/api.php) keeps using the
 * App\Http\Controllers\Concerns\ApiResponse trait — this class is for
 * the versioned mobile surface only.
 */
class ApiResponse
{
    public static function success(mixed $data = null, ?array $meta = null, int $status = 200): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }

    public static function validation(array $errors): JsonResponse
    {
        return static::error('VALIDATION_FAILED', 'The given data was invalid.', 422, $errors);
    }

    public static function unauthorized(string $msg = 'Unauthenticated'): JsonResponse
    {
        return static::error('UNAUTHENTICATED', $msg, 401);
    }

    public static function forbidden(string $msg = 'Forbidden'): JsonResponse
    {
        return static::error('FORBIDDEN', $msg, 403);
    }

    public static function notFound(string $msg = 'Not found'): JsonResponse
    {
        return static::error('NOT_FOUND', $msg, 404);
    }
}
