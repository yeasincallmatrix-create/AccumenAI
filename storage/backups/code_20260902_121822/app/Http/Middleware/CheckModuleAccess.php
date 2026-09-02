<?php

namespace App\Http\Middleware;

use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Closure;
use Illuminate\Http\Request;

/**
 * Route-level module access check.
 *
 * Usage: ->middleware('module_access:education') for a single module,
 * or ->middleware('module_access:education,crm') for "any".
 *
 * Platform admins bypass module checks. Institute users must have the
 * module enabled for their institute.
 */
class CheckModuleAccess
{
    public function handle(Request $request, Closure $next, string ...$modules): mixed
    {
        $user = $request->user();

        if ($user instanceof PlatformAdmin) {
            return $next($request);
        }

        $institute = null;

        if ($user instanceof InstituteUser) {
            $institute = $user->institute;
        } elseif ($user instanceof User) {
            $membership = Workspace::membership();
            if ($membership) {
                $institute = $membership->institute;
                if (! $institute) {
                    $institute = \App\Models\Institute::withoutGlobalScopes()->find($membership->institution_id);
                }
            } else {
                $wid = Workspace::id() ?? \App\Support\TenantContext::id();
                if ($wid !== null) {
                    $direct = \App\Models\Membership::where('user_id', $user->id)->where('institution_id', $wid)->where('status', 'active')->first();
                    if ($direct) {
                        $institute = $direct->institution;
                        if (! $institute) {
                            $institute = \App\Models\Institute::withoutGlobalScopes()->find($direct->institution_id);
                        }
                    }
                }
                if (! $institute) {
                    $first = \App\Models\Membership::where('user_id', $user->id)->where('status', 'active')->orderBy('institution_id')->first();
                    if ($first) {
                        $institute = $first->institution;
                        if (! $institute) {
                            $institute = \App\Models\Institute::withoutGlobalScopes()->find($first->institution_id);
                        }
                    }
                }
            }
        }

        if (! $institute) {
            abort(404, 'Institute not found.');
        }

        $moduleService = app(ModuleAccessService::class);

        foreach ($modules as $module) {
            if ($moduleService->isEnabled($institute, $module)) {
                return $next($request);
            }
        }

        abort(403, 'This module is not enabled for your subscription. Please contact your administrator.');
    }
}
