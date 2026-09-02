<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * STEP 99 — Security Headers Middleware.
 *
 * Appends standard security headers to every HTTP response:
 * Content-Security-Policy, X-Content-Type-Options, X-Frame-Options,
 * X-XSS-Protection, Referrer-Policy and Strict-Transport-Security.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.bunny.net; font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com https://fonts.bunny.net; img-src 'self' data: https:; connect-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com; frame-ancestors 'none';"
        );

        return $response;
    }
}
