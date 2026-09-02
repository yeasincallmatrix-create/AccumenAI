<?php

namespace App\Http\Middleware;

use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Support\Workspace;
use Closure;
use Illuminate\Http\Request;

/**
 * Route-level permission check.
 *
 * Usage: ->middleware('permission:students.manage') for a single permission,
 * or ->middleware('permission:students.manage,students.view') for "any".
 *
 * Platform admins bypass institute-level checks; institute users and global
 * users (through their active membership) must hold the permission (or be the
 * institute owner).
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = $request->user();

        if ($user instanceof PlatformAdmin) {
            return $next($request);
        }

        if ($user instanceof InstituteUser && $user->hasAnyPermission($permissions)) {
            return $next($request);
        }

        if ($user instanceof User) {
            $membership = Workspace::membership();
            if ($membership === null) {
                $wid = Workspace::id() ?? \App\Support\TenantContext::id();
                if ($wid !== null) {
                    $membership = \App\Models\Membership::where('user_id', $user->id)->where('institution_id', $wid)->where('status', 'active')->first();
                }
                if ($membership === null) {
                    $membership = \App\Models\Membership::where('user_id', $user->id)->where('status', 'active')->orderBy('institution_id')->first();
                }
            }

            if ($membership !== null && $membership->hasAnyPermission($permissions)) {
                return $next($request);
            }
        }

        abort(403, 'You are not authorized to perform this action.');
    }
}
