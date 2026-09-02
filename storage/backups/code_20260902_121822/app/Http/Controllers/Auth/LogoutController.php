<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Logs out whichever guard is currently authenticated so a single
 * /logout endpoint works for platform admins, institute users and the
 * global account.
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request)
    {
        $wasPlatformAdmin = Auth::guard('platform_admin')->check();
        $wasInstituteUser = Auth::guard('institute_user')->check();
        $wasGuardian = Auth::guard('guardian')->check();

        foreach (['web', 'institute_user', 'platform_admin', 'guardian'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        TenantContext::clear();
        Workspace::clear();

        if ($wasPlatformAdmin) {
            return redirect()->route('admin.login');
        }
        // institute/login permanently removed — all non-admin logouts return to original portal login
        if ($wasGuardian) {
            return redirect()->route('guardian.login');
        }

        // Fallback: respect referer only for admin, otherwise always original login
        $referer = (string) $request->headers->get('referer', '');
        if (str_contains($referer, '/admin')) {
            return redirect()->route('admin.login');
        }

        return redirect()->route('login');
    }
}
