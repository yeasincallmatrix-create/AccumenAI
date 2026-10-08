<?php

namespace App\Http\Middleware;

use App\Support\PackageGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the dashboard (and every tenant surface) locked until the
 * organization has picked a package.
 *
 * Applied to the whole `web` group so no route can be reached by bypassing
 * the dashboard — see bootstrap/app.php.
 */
class EnsurePackageSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PackageGate::requiresSelection($request)) {
            return $next($request);
        }

        $target = route('register.package');

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Please choose a package for your organization before continuing.',
                'redirect' => $target,
            ], 403);
        }

        return redirect()
            ->to($target)
            ->with('warning', 'Please choose a package for your organization to continue.');
    }
}
