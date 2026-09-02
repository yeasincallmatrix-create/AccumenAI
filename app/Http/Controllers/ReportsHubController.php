<?php

namespace App\Http\Controllers;

use App\Services\Reports\ReportRegistry;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportsHubController extends Controller
{
    public function index(Request $request)
    {
        $institute = $this->resolveInstitute($request);
        $actor = $this->resolveActor($request);

        $user = $actor instanceof \App\Models\User ? $actor : null;
        $instituteUser = $actor instanceof \App\Models\InstituteUser ? $actor : null;

        $grouped = ReportRegistry::grouped($institute, $actor);
        // Correct count with proper typed params
        $allCount = count(ReportRegistry::forInstitute($institute, $user, $instituteUser));

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['groups' => $grouped, 'count' => $allCount]);
        }

        return view('reports.hub', ['grouped' => $grouped, 'institute' => $institute, 'count' => $allCount]);
    }

    public function show(Request $request, string $key)
    {
        $institute = $this->resolveInstitute($request);
        $actor = $this->resolveActor($request);

        $report = ReportRegistry::find($key);
        if (! $report) abort(404);

        // Industry gate (already in forInstitute, but double-check for direct URL)
        if ($report['industry'] !== null) {
            if ($institute === null || $institute->industry !== $report['industry']) abort(404);
            if ($report['sub_industry'] !== null) {
                $allowed = (array) $report['sub_industry'];
                if (! in_array($institute->sub_industry, $allowed, true)) abort(404);
            }
        }

        // Permission gate
        $permission = $report['permission'] ?? null;
        if ($permission && $actor) {
            $has = false;
            if ($actor instanceof \App\Models\PlatformAdmin) $has = true;
            elseif (method_exists($actor, 'hasPermission')) $has = $actor->hasPermission($permission);
            elseif (method_exists($actor, 'hasAnyPermission')) $has = $actor->hasAnyPermission([$permission]);
            if (! $has) abort(403);
        } elseif ($permission) {
            abort(403);
        }

        // Module gate
        if ($report['module'] !== 'audit' && $institute) {
            $enabled = app(\App\Services\ModuleAccessService::class)->isEnabled($institute, $report['module']);
            // Legacy free handling: already via isEnabled, but also check FREE
            if (! $enabled) {
                // Fallback for audit-free institutes handled via isEnabledForFree inside service? Check directly
                $isFreeEnabled = app(\App\Services\ModuleAccessService::class)->isEnabledForFree($report['module']);
                $isFreeInstitute = $institute->package_id === null;
                if (! ($isFreeInstitute && $isFreeEnabled)) {
                    abort(403, 'Module not enabled for your subscription');
                }
            }
        }

        // Redirect to the canonical report route (preserves original filters, branch isolation via original controller)
        if (! empty($report['route'])) {
            try {
                return redirect()->route($report['route']);
            } catch (\Throwable $e) {
                // Fallback to hub if route not resolvable
            }
        }

        return redirect()->route('reports.hub');
    }

    protected function resolveInstitute(Request $request): ?\App\Models\Institute
    {
        $id = TenantContext::id();
        if ($id) return \App\Models\Institute::withoutGlobalScopes()->find($id);
        $user = $request->user();
        if ($user instanceof \App\Models\InstituteUser) return \App\Models\Institute::withoutGlobalScopes()->find($user->institute_id);
        if ($user instanceof \App\Models\User) {
            $membership = \App\Support\Workspace::membership();
            if ($membership) return \App\Models\Institute::withoutGlobalScopes()->find($membership->institution_id);
        }
        return null;
    }

    protected function resolveActor(Request $request)
    {
        return $request->user();
    }
}
