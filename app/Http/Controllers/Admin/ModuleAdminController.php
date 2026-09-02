<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleAccessLog;
use App\Models\ModuleRegistry;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleAdminController extends Controller
{
    public function index(): View
    {
        $modules = ModuleRegistry::orderBy('sort_order')->get();
        $packages = SubscriptionPackage::where('status', 'active')->orderBy('id')->get();

        $packageModules = [];
        foreach ($packages as $pkg) {
            $packageModules[$pkg->id] = $pkg->packageModules()->pluck('enabled', 'module_key')->toArray();
        }

        return view('admin.modules.index', compact('modules', 'packages', 'packageModules'));
    }

    public function update(ModuleRegistry $module, Request $request): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $module->update(['status' => $request->status]);

        return back()->with('success', 'Module status updated.');
    }

    public function packageModules(SubscriptionPackage $package): View
    {
        $modules = ModuleRegistry::where('status', 'active')->orderBy('sort_order')->get();
        $packageModules = $package->packageModules()->pluck('enabled', 'module_key')->toArray();

        return view('admin.modules.package-modules', compact('package', 'modules', 'packageModules'));
    }

    public function updatePackageModules(SubscriptionPackage $package, Request $request): RedirectResponse
    {
        $request->validate([
            'modules' => 'required|array',
            'modules.*' => 'string|exists:module_registry,key',
        ]);

        $service = app(ModuleAccessService::class);
        $service->setPackageModules($package, $request->modules);

        return back()->with('success', "Modules updated for {$package->name}.");
    }

    public function instituteModules(Institute $institute): View
    {
        $institute->load('package');
        $service = app(ModuleAccessService::class);
        $allModules = ModuleRegistry::where('status', 'active')->orderBy('sort_order')->get();
        $resolved = $service->resolveEnabled($institute);
        $overrides = $institute->moduleOverrides()->get()->keyBy('module_key');

        return view('admin.modules.institute-modules', compact('institute', 'allModules', 'resolved', 'overrides'));
    }

    public function updateInstituteModules(Institute $institute, Request $request): RedirectResponse
    {
        $request->validate([
            'modules' => 'required|array',
            'modules.*' => 'string|exists:module_registry,key',
            'reason' => 'nullable|string|max:255',
        ]);

        $service = app(ModuleAccessService::class);
        $allKeys = ModuleRegistry::where('status', 'active')->pluck('key')->toArray();
        $enabled = $request->modules ?? [];
        $actorId = $request->user()?->id;
        $reason = $request->input('reason');

        foreach ($allKeys as $key) {
            $shouldBeEnabled = in_array($key, $enabled, true);
            $currentlyEnabled = $service->isEnabled($institute, $key);

            if ($shouldBeEnabled && ! $currentlyEnabled) {
                $service->enableModule($institute, $key, $actorId, $reason);
            } elseif (! $shouldBeEnabled && $currentlyEnabled) {
                $service->disableModule($institute, $key, $actorId, $reason);
            }
        }

        return back()->with('success', 'Module access updated for ' . $institute->name);
    }

    public function removeOverride(Institute $institute, string $moduleKey): RedirectResponse
    {
        InstituteModuleOverride::where('institute_id', $institute->id)
            ->where('module_key', $moduleKey)
            ->delete();

        app(ModuleAccessService::class)->flushCache($institute->id);

        return back()->with('success', 'Override removed. Package default restored.');
    }

    public function accessLogs(Request $request): View
    {
        $query = ModuleAccessLog::with(['institute', 'actor', 'package'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('institute_id')) {
            $query->where('institute_id', $request->institute_id);
        }
        if ($request->filled('module_key')) {
            $query->where('module_key', $request->module_key);
        }

        $logs = $query->paginate(50)->withQueryString();
        $modules = ModuleRegistry::orderBy('sort_order')->get();

        return view('admin.modules.access-logs', compact('logs', 'modules'));
    }
}
