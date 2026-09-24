<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\ModuleAccessLog;
use App\Models\SubscriptionPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Phase 5 — per-tenant audit log + bulk module overview.
 *
 * NOTE: the Tenant Module Access page itself lives on the pre-existing
 * ModuleAdminController (route admin.institutes.modules was already taken);
 * this controller covers the two NEW read-only pages.
 */
class InstituteModuleOverrideController extends Controller
{
    public function accessLog(Institute $institute, Request $request): View
    {
        $query = ModuleAccessLog::where('institute_id', $institute->id)
            ->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('module_key')) {
            $query->where('module_key', $request->input('module_key'));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $logs = $query->paginate(20)->withQueryString();

        $actions = ModuleAccessLog::where('institute_id', $institute->id)
            ->distinct()->orderBy('action')->pluck('action');

        return view('admin.institutes.access-log', compact('institute', 'logs', 'actions'));
    }

    public function overview(Request $request): View
    {
        $institutes = Institute::query()
            ->when($request->filled('industry'), fn ($q, $i) => $q->where('industry', $i))
            ->when($request->filled('package'), fn ($q, $p) => $q->where('package_id', $p))
            ->when($request->filled('search'), fn ($q, $s) => $q->where('name', 'LIKE', "%{$s}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $overrides = DB::table('institute_module_overrides')
            ->select('institute_id', DB::raw('COUNT(*) as override_count'))
            ->groupBy('institute_id')
            ->pluck('override_count', 'institute_id');

        $emergencyOverrides = DB::table('super_admin_overrides')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get()
            ->groupBy('institute_id');

        $packages = SubscriptionPackage::where('status', 'active')->orderBy('id')->get(['id', 'name', 'slug']);
        $industries = Institute::distinct()->whereNotNull('industry')->orderBy('industry')->pluck('industry');

        return view('admin.institutes.overview', compact(
            'institutes',
            'overrides',
            'emergencyOverrides',
            'packages',
            'industries'
        ));
    }
}
