<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Narrow teacher-deny gate for the finance + accounting modules
 * (Phase 7 STEP 3C, extended 3H.3).
 *
 * Teachers have no finance function: any teacher-role actor gets 403 on
 * finance/* and accounting/* URIs. All other roles pass through untouched
 * (the role→permission grant matrix is empty in seeded data, so a broad
 * permission middleware would 403 accountants/receptionists too).
 *
 * URI-scoped internally ($request->is('finance*', 'accounting*')) so the
 * middleware can sit on shared route groups (web.php institute group,
 * institute_modules.php tenant group) without affecting non-finance
 * routes like /teachers or /crm.
 *
 * Covers both guards: InstituteUser::hasRole() checks the user's own
 * role; User::hasRole() resolves the active workspace membership.
 * Unknown user types / missing methods fail OPEN (current behaviour).
 */
class DenyTeacherFromFinance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('finance*', 'accounting*')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user !== null && method_exists($user, 'hasRole') && $user->hasRole('teacher')) {
            abort(403, 'Teachers do not have access to the finance and accounting modules.');
        }

        return $next($request);
    }
}
