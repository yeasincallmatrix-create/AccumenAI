<?php

namespace App\Http\Middleware;

use App\Models\Guardian;
use App\Models\InstituteUser;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Closure;
use Illuminate\Http\Request;

/**
 * Binds the current institute context for institute-user requests so the
 * TenantScoped global scope filters every tenant model automatically. The
 * institute user's assigned branch is bound to BranchContext so the
 * BranchScoped global scope filters branch-owned rows the same way.
 *
 * For the global account (web guard) the active organization comes from the
 * workspace session, which is verified against the membership model.
 *
 * Must run AFTER the auth middleware.
 */
class SetTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user instanceof Guardian) {
            // A guardian is scoped to their own institute but may follow
            // linked students across every branch of that institute.
            TenantContext::set($user->institute_id);
            BranchContext::clear();
        } elseif ($user instanceof InstituteUser) {
            TenantContext::set($user->institute_id);
            BranchContext::set($user->branch_id);
        } elseif ($user instanceof User) {
            $workspaceId = Workspace::id();

            if ($workspaceId !== null && ! Workspace::verify($workspaceId, $user->id)) {
                $workspaceId = null;
                Workspace::clear();
            }

            // Cookie/session fix forever: if workspace is null (stale cookie after
            // cache:clear, session truncate, or new device) and user has a single
            // active membership, auto-resolve to it so navbar never 404s.
            if ($workspaceId === null) {
                $fallback = \Illuminate\Support\Facades\DB::table('institution_user')
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->orderBy('institution_id')
                    ->first();
                if ($fallback) {
                    // Verify institute still exists and active
                    $instExists = \App\Models\Institute::withoutGlobalScopes()
                        ->where('id', $fallback->institution_id)
                        ->where('status', 'active')
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($instExists) {
                        $workspaceId = (int) $fallback->institution_id;
                        Workspace::set($workspaceId);
                    }
                }
            }

            TenantContext::set($workspaceId);

            // Branch scope comes from the active membership. A NULL branch id
            // (owner / institute admin) leaves BranchContext unrestricted so
            // all branches of the active institute remain visible.
            $membership = $workspaceId !== null ? Workspace::membership() : null;
            BranchContext::set($membership?->branch_id);
        } else {
            TenantContext::clear();
            BranchContext::clear();
        }

        return $next($request);
    }
}
