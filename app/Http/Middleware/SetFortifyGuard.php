<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Pins the active Fortify guard for the current request.
 *
 * The application uses two distinct guards (platform_admin + institute_user)
 * with a guaranteed one-role-per-session invariant. Fortify is configured
 * against a single guard, so this middleware selects the real one:
 *   - during a two-factor challenge: the guard stored in the session,
 *   - otherwise: whichever guard is currently authenticated.
 */
class SetFortifyGuard
{
    public function handle(Request $request, Closure $next)
    {
        $guard = $this->resolveGuard($request);

        config(['fortify.guard' => $guard]);
        config(['fortify.passwords' => match ($guard) {
            'web' => 'users',
            'platform_admin' => 'platform_admins',
            default => 'institute_users',
        }]);
        Auth::setDefaultDriver($guard);

        return $next($request);
    }

    protected function resolveGuard(Request $request): string
    {
        $sessionGuard = $request->session()->get('login.guard');

        if (in_array($sessionGuard, ['web', 'platform_admin', 'institute_user'], true)) {
            return $sessionGuard;
        }

        if (Auth::guard('web')->check()) {
            return 'web';
        }

        if (Auth::guard('platform_admin')->check()) {
            return 'platform_admin';
        }

        return 'institute_user';
    }
}
