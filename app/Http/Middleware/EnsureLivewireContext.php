<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Livewire's update/upload/preview endpoints are registered by the package with
 * only the `web` group - they never run the route-level `auth` / `tenant`
 * middleware every normal page runs. Two failures follow without this guard:
 *
 *  1. Sessions on a non-default guard (institute_user, guardian, ...) are
 *     invisible to auth()/Gate there, so component code sees a null user.
 *  2. SetTenantContext never runs, TenantContext stays null, and the
 *     TenantScoped global scope early-returns -> every tenant-scoped query
 *     made during a component re-render runs UNFILTERED and returns all
 *     tenants' rows (cross-tenant data exposure).
 *
 * So for those endpoints only: pin the guard that is actually authenticated
 * (403/404 when nobody is), bind tenant + branch context, and refuse to
 * execute the component at all when no tenant context could be bound.
 */
class EnsureLivewireContext
{
    /** Checked in order; institute_user first (main app surface). */
    protected const GUARDS = ['institute_user', 'web', 'guardian', 'platform_admin', 'platform_staff'];

    /** Package routes that execute server-side component / upload logic. */
    protected const ENDPOINTS = ['*livewire.update', '*livewire.upload-file', '*livewire.preview-file'];

    public function handle(Request $request, Closure $next)
    {
        if (! $this->isProtectedEndpoint($request)) {
            return $next($request);
        }

        $guard = $this->resolveGuard();

        if ($guard === null) {
            // A real Livewire payload gets an explicit refusal; anything else
            // is hidden with a 404 exactly like Livewire's header check does.
            abort(($request->hasHeader('X-Livewire') && $request->isJson()) ? 403 : 404);
        }

        Auth::shouldUse($guard);

        return app(SetTenantContext::class)->handle($request, function ($request) use ($next) {
            abort_unless(
                TenantContext::enabled(),
                403,
                'No tenant context could be bound for this request.'
            );

            return $next($request);
        });
    }

    protected function isProtectedEndpoint(Request $request): bool
    {
        $route = $request->route();

        if ($route === null) {
            return false;
        }

        foreach (self::ENDPOINTS as $pattern) {
            if ($route->named($pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function resolveGuard(): ?string
    {
        foreach (self::GUARDS as $guard) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }
}
