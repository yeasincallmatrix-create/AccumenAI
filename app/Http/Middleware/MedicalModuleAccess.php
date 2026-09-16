<?php

namespace App\Http\Middleware;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\TenantContext;
use App\Support\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks that a specific medical sub-module is enabled for the
 * current institute via ModuleAccessService.
 *
 * Usage: ->middleware('medical.module:medical.opd')
 *        ->middleware('medical.module:medical.pharmacy')
 */
class MedicalModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $user = $request->user();

        if ($user instanceof \App\Models\PlatformAdmin) {
            return $next($request);
        }

        $institute = $this->resolveInstitute($request);

        if ($institute === null) {
            abort(403, 'No institute context.');
        }

        $moduleAccess = app(ModuleAccessService::class);

        if (! $moduleAccess->isEnabled($institute, $moduleKey)) {
            abort(403, "Module '{$moduleKey}' is not enabled for this institution.");
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
