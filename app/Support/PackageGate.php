<?php

namespace App\Support;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Package selected" gate.
 *
 * An organization has no package until its owner explicitly picks one on the
 * registration package step (or an admin assigns one later). While
 * institutes.package_id is NULL the tenant is locked out of the dashboard and
 * every tenant surface, so nobody can enter the app before choosing a plan.
 *
 * Resolution mirrors App\Http\Middleware\CheckModuleAccess so both gates see
 * the same institute for the same request.
 */
final class PackageGate
{
    /**
     * Route names that stay reachable while a tenant has no package yet.
     * Everything needed to pick/complete a package, plus session lifecycle.
     */
    public const EXEMPT_ROUTES = [
        'register.*',
        'owner.register*',
        'logout*',
        'login*',
        'workspace.*',
        'saas.*',
        'upgrade.show',
        'account.*',
        'verify.*',
        'verification.*',
        'auth.google.*',
        'password.*',
        'two-factor.challenge',
        'admin.*',
        'super-admin.*',
        'guardian.*',
        'institute.login*',
        'deploy.*',
    ];

    /** URI-level fallback for routes that carry no name. */
    public const EXEMPT_URIS = [
        'register*',
        'logout*',
        'login*',
        'admin/login*',
        'workspace*',
        'saas*',
        'guardian*',
        'auth/google*',
        'up',
    ];

    private function __construct() {}

    public static function isExempt(Request $request): bool
    {
        foreach (self::EXEMPT_ROUTES as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        foreach (self::EXEMPT_URIS as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The organization this request acts on, or null when there is none
     * (guests, platform admins, accounts without a workspace).
     */
    public static function resolveInstitute(Request $request): ?Institute
    {
        // Platform admins manage every tenant — they are never gated.
        try {
            if (Auth::guard('platform_admin')->check()) {
                return null;
            }
        } catch (\Throwable) {
            // Guard unavailable (console/API context) — fall through.
        }

        $instituteId = null;

        $instituteUser = self::guardUser($request, 'institute_user');
        if ($instituteUser instanceof InstituteUser) {
            $instituteId = (int) $instituteUser->institute_id;
        } else {
            $user = self::guardUser($request, 'web');
            if (! $user instanceof User) {
                return null;
            }

            $membership = Workspace::membershipFor($user);
            if ($membership !== null) {
                $instituteId = (int) $membership->institution_id;
            } else {
                $workspaceId = Workspace::id() ?? TenantContext::id();
                if ($workspaceId !== null) {
                    $instituteId = (int) Membership::query()
                        ->where('user_id', $user->id)
                        ->where('institution_id', $workspaceId)
                        ->where('status', 'active')
                        ->value('institution_id');
                }

                if ($instituteId === 0 || $instituteId === null) {
                    $instituteId = (int) Membership::query()
                        ->where('user_id', $user->id)
                        ->where('status', 'active')
                        ->orderBy('institution_id')
                        ->value('institution_id');
                }
            }
        }

        if ($instituteId <= 0) {
            return null;
        }

        return Institute::withoutGlobalScopes()->find($instituteId);
    }

    public static function requiresSelection(Request $request): bool
    {
        if (self::isExempt($request)) {
            return false;
        }

        $institute = self::resolveInstitute($request);

        return $institute !== null && $institute->package_id === null;
    }

    private static function guardUser(Request $request, string $guard): mixed
    {
        try {
            $request->user($guard);

            return Auth::guard($guard)->user();
        } catch (\Throwable) {
            return null;
        }
    }
}
