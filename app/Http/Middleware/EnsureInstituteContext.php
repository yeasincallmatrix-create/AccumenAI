<?php

namespace App\Http\Middleware;

use App\Models\InstituteUser;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstituteContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->unauthenticated($request);
        }

        if ($user instanceof InstituteUser) {
            TenantContext::set($user->institute_id);
            BranchContext::set($user->branch_id);

            return $next($request);
        }

        if ($user instanceof User) {
            $membership = Workspace::membership();

            if ($membership === null) {
                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => 'No active institute workspace.'], 403)
                    : redirect()->route('workspace.select');
            }

            TenantContext::set($membership->institution_id);
            BranchContext::set($membership->branch_id);

            return $next($request);
        }

        return $this->unauthenticated($request);
    }

    private function unauthenticated(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        return redirect()->route('admin.login');
    }
}
