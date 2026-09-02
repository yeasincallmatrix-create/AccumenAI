<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\User;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Http\Request;

/**
 * Shared controller context resolution.
 *
 * - Institute identity comes ONLY from the authenticated user / workspace
 *   (never from request input).
 * - The acting branch comes from the user's assigned branch (null =
 *   whole-institute), never from request input.
 * - created_by/updated_by references institute_users; platform users acting
 *   through a workspace leave it empty.
 */
trait ResolvesInstitute
{
    private function resolveInstitute(Request $request): ?Institute
    {
        $user = $request->user();

        if ($user instanceof InstituteUser) {
            return Institute::query()->find($user->institute_id);
        }

        if ($user instanceof User) {
            // Primary path: Workspace::membership() resolves via auth() + session.
            $membership = Workspace::membership();

            if ($membership !== null) {
                $inst = Institute::query()->find($membership->institution_id);
                if ($inst !== null) {
                    return $inst;
                }
            }

            // Fallback 1: workspace ID was already verified by SetTenantContext
            // middleware; use TenantContext which was bound during that step.
            $tenantId = TenantContext::id();
            if ($tenantId !== null) {
                $inst = Institute::query()->find($tenantId);
                if ($inst !== null) {
                    return $inst;
                }
            }

            // Fallback 2: direct DB lookup — handles stale session / session
            // regeneration edge cases where Workspace::membership() cannot
            // resolve auth() state but the verified workspace ID is available.
            $wid = $tenantId ?? Workspace::id();
            if ($wid !== null) {
                $direct = Membership::query()
                    ->where('user_id', $user->id)
                    ->where('institution_id', $wid)
                    ->where('status', 'active')
                    ->first();
                if ($direct !== null) {
                    $inst = Institute::query()->find($direct->institution_id);
                    if ($inst !== null) {
                        return $inst;
                    }
                }
            }

            // Fallback 3: first active membership (single-institute users).
            $first = Membership::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->orderBy('institution_id')
                ->first();
            if ($first !== null) {
                $inst = Institute::query()->find($first->institution_id);
                if ($inst !== null) {
                    return $inst;
                }
            }
        }

        return null;
    }

    private function requireInstitute(Request $request): Institute
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        return $institute;
    }

    /**
     * The acting user's assigned branch id (null = whole-institute access).
     */
    private function actingBranchId(Request $request): ?int
    {
        $user = $request->user();

        if ($user instanceof InstituteUser && $user->branch_id !== null) {
            return (int) $user->branch_id;
        }

        if ($user instanceof User) {
            $membership = Workspace::membership();

            if ($membership !== null && $membership->branch_id !== null) {
                return (int) $membership->branch_id;
            }

            // Fallback: tenant context was set by SetTenantContext middleware
            $tenantId = TenantContext::id();
            if ($tenantId !== null) {
                $direct = Membership::query()
                    ->where('user_id', $user->id)
                    ->where('institution_id', $tenantId)
                    ->where('status', 'active')
                    ->first();
                if ($direct !== null && $direct->branch_id !== null) {
                    return (int) $direct->branch_id;
                }
            }
        }

        return null;
    }

    private function actingBranch(Request $request): ?Branch
    {
        $branchId = $this->actingBranchId($request);

        return $branchId !== null ? Branch::query()->find($branchId) : null;
    }

    /**
     * Centralised institute ID accessor.
     *
     * Works for both InstituteUser (direct attribute) and User (via workspace /
     * membership). Every controller that needs the current institute ID should
     * call this instead of `$request->user()->institute_id`.
     */
    private function instituteId(Request $request): ?int
    {
        $user = $request->user();

        if ($user instanceof InstituteUser) {
            return (int) $user->institute_id;
        }

        $institute = $this->resolveInstitute($request);

        return $institute?->id !== null ? (int) $institute->id : null;
    }

    /**
     * Actor id for created_by/updated_by (institute_users table).
     */
    private function actorId(Request $request): ?int
    {
        $user = $request->user();

        return $user instanceof InstituteUser ? (int) $user->id : null;
    }
}
