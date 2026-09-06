<?php

namespace App\Http\Middleware;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use App\Support\InstituteDomain;
use App\Support\TenantContext;
use App\Support\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 0 — HMS Foundation.
 *
 * Blocks non-medical institutes from the medical module, following the
 * same resolution pattern as EnsureDomain (TenantContext → Workspace →
 * authenticated user). Platform admins bypass (no institute context).
 */
class MedicalDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof \App\Models\PlatformAdmin) {
            return $next($request);
        }

        $institute = $this->resolveInstitute($request);

        if ($institute === null) {
            abort(403, 'Institute context not found.');
        }

        if (InstituteDomain::fromInstitute($institute) !== InstituteDomain::MEDICAL) {
            abort(403, 'This module is only available for medical institutions.');
        }

        return $next($request);
    }

    private function resolveInstitute(Request $request): ?Institute
    {
        // Explicit route-bound institute takes precedence (e.g. {institute} param).
        $routed = $request->route('institute');
        if ($routed instanceof Institute) {
            return $routed;
        }
        if (is_numeric($routed)) {
            $found = Institute::withoutGlobalScopes()->find($routed);
            if ($found) {
                return $found;
            }
        }

        // Prefer TenantContext (set by SetTenantContext middleware after auth).
        $id = TenantContext::id();
        if ($id) {
            return Institute::withoutGlobalScopes()->find($id);
        }

        // Fallback to Workspace (web guard).
        $wid = Workspace::id();
        if ($wid) {
            return Institute::withoutGlobalScopes()->find($wid);
        }

        // Fallback to direct authenticated user.
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
