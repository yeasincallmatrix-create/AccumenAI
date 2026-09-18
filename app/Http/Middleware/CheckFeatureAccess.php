<?php

namespace App\Http\Middleware;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\TenantContext;
use App\Support\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level feature access check.
 *
 * Usage: ->middleware('feature:medical.pharmacy')
 *
 * Platform admins bypass feature checks. Institute users must have the
 * feature enabled for their institute (parent module + feature_registry +
 * package_features).
 */
class CheckFeatureAccess
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $user = $request->user();

        if ($user instanceof PlatformAdmin) {
            return $next($request);
        }

        $institute = $this->resolveInstitute($request);

        if ($institute === null) {
            abort(403, 'No tenant context for feature access.');
        }

        $service = app(ModuleAccessService::class);

        if (! $service->isFeatureEnabled($institute, $featureKey)) {
            abort(403, "Feature '{$featureKey}' is not enabled for this institute.");
        }

        return $next($request);
    }

    private function resolveInstitute(Request $request): ?Institute
    {
        $id = TenantContext::id();
        if ($id) {
            return Institute::withoutGlobalScopes()->find($id);
        }

        $wid = Workspace::id();
        if ($wid) {
            return Institute::withoutGlobalScopes()->find($wid);
        }

        $user = $request->user();
        if ($user instanceof InstituteUser) {
            return Institute::withoutGlobalScopes()->find($user->institute_id);
        }
        if ($user instanceof User) {
            $membership = Workspace::membership();
            if ($membership) {
                return Institute::withoutGlobalScopes()->find($membership->institution_id);
            }
        }

        return null;
    }
}
