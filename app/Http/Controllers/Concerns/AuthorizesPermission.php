<?php

namespace App\Http\Controllers\Concerns;

use App\Models\PlatformAdmin;
use App\Models\InstituteUser;
use App\Models\User;
use App\Support\Workspace;

/**
 * Controller-level permission checks that mirror CheckPermission middleware.
 * Used when route middleware is not applied (or as defense-in-depth).
 */
trait AuthorizesPermission
{
    protected function requirePermission(string ...$permissions): void
    {
        $user = auth()->user();

        if ($user instanceof PlatformAdmin) {
            return;
        }

        if ($user instanceof InstituteUser && $user->hasAnyPermission($permissions)) {
            return;
        }

        if ($user instanceof User) {
            $membership = Workspace::membership();
            if ($membership === null) {
                $membership = \App\Models\Membership::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->orderBy('institution_id')
                    ->first();
            }
            if ($membership !== null && $membership->hasAnyPermission($permissions)) {
                return;
            }
        }

        abort(403, 'You are not authorized to perform this action.');
    }
}
