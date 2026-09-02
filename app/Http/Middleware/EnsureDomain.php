<?php
namespace App\Http\Middleware;

use App\Support\InstituteDomain;
use App\Support\TenantContext;
use App\Support\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDomain
{
    public function handle(Request $request, Closure $next, string $domain): Response
    {
        $institute = $this->resolveInstitute($request);
        if ($institute === null) {
            // No institute context (platform admin or unauthenticated) - deny academic/professional explicitly
            abort(403, "Domain $domain required.");
        }
        $actual = InstituteDomain::fromInstitute($institute);
        if ($actual !== $domain) {
            abort(403, "This feature is available only for $domain institutes. Current domain: $actual.");
        }
        return $next($request);
    }

    private function resolveInstitute(Request $request): ?\App\Models\Institute
    {
        // Prefer TenantContext (set by SetTenantContext middleware after auth)
        $id = TenantContext::id();
        if ($id) {
            return \App\Models\Institute::withoutGlobalScopes()->find($id);
        }
        // Fallback to Workspace (for web guard)
        $wid = Workspace::id();
        if ($wid) {
            return \App\Models\Institute::withoutGlobalScopes()->find($wid);
        }
        // Fallback to institute_user direct
        $user = $request->user();
        if ($user instanceof \App\Models\InstituteUser) {
            return \App\Models\Institute::withoutGlobalScopes()->find($user->institute_id);
        }
        if ($user instanceof \App\Models\User) {
            $membership = Workspace::membership();
            if ($membership) {
                return \App\Models\Institute::withoutGlobalScopes()->find($membership->institution_id);
            }
        }
        return null;
    }
}
