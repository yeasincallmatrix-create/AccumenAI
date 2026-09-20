<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdvancedAccounting
{
    /**
     * Block advanced-only pages when the tenant runs in simple mode.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! advanced_accounting()) {
            abort(403, 'Advanced Accounting mode is disabled. Enable it in Settings.');
        }

        return $next($request);
    }
}
