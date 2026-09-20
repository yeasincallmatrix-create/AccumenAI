<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Phase 10 — Assign a unique request ID to every HTTP request.
 *
 * Uses the incoming X-Request-Id header when present (for distributed
 * tracing), otherwise generates a new UUID v4. The ID is stored on the
 * request for downstream consumers (e.g. access-decision logging).
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): mixed
    {
        $requestId = $request->header('X-Request-Id') ?? Str::uuid()->toString();

        $request->attributes->set('request_id', $requestId);

        $response = $next($request);

        if (method_exists($response, 'header')) {
            $response->header('X-Request-Id', $requestId);
        }

        return $response;
    }
}
