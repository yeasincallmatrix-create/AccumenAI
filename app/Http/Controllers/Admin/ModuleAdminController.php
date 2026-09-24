<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Industry;
use App\Models\Institute;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleAccessLog;
use App\Models\ModuleRegistry;
use App\Models\PackageScope;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleAdminController extends Controller
{
    public function index(Request $request): View
    {
        $industries = Industry::active()->orderBy('sort_order')->orderBy('name')->get();
        $selectedIndustryId = $request->input('industry_id');

        $modules = ModuleRegistry::orderBy('sort_order')->get();
        $packages = SubscriptionPackage::where('status', 'active')->orderBy('id')->get();

        if ($selectedIndustryId) {
            $scopedPackageIds = PackageScope::where('industry_id', $selectedIndustryId)
                ->where('status', 'active')
                ->pluck('package_id')
                ->toArray();

            $globalPackageIds = PackageScope::whereNull('industry_id')
                ->where('status', 'active')
                ->pluck('package_id')
                ->toArray();

            $packages = $packages->whereIn('id', array_unique(array_merge($scopedPackageIds, $globalPackageIds)));

            $scopedModuleKeys = \App\Models\PackageScopedModule::whereHas('scope', function ($q) use ($selectedIndustryId) {
                $q->where('industry_id', $selectedIndustryId)->where('status', 'active');
            })->where('enabled', true)
                ->pluck('module_key')
                ->unique()
                ->toArray();

            if (!empty($scopedModuleKeys)) {
                $modules = $modules->filter(fn ($m) => in_array($m->key, $scopedModuleKeys, true));
            }
            // When no scoped modules exist for the industry, show all modules
            // (fallback was previously filtering to type=core only, hiding industry sub-modules)
        }

        $packageModules = [];
        foreach ($packages as $pkg) {
            $packageModules[$pkg->id] = $pkg->packageModules()->pluck('enabled', 'module_key')->toArray();
        }

        return view('admin.modules.index', compact('modules', 'packages', 'packageModules', 'industries', 'selectedIndustryId'));
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

    public function instituteModules(Institute $institute, Request $request): View
    {
        $institute->load('package');
        $service = app(ModuleAccessService::class);
        $selectedIndustry = $request->query('industry');
        $industries = Industry::active()->orderBy('name')->get();

        $allModules = ModuleRegistry::where('status', 'active')->orderBy('sort_order')->get();

        if ($selectedIndustry) {
            $moduleIndustryMap = [
                'education' => 'education',
                'medical' => 'healthcare',
                'training_center' => 'training_center',
            ];
            $allModules = $allModules->filter(function ($module) use ($selectedIndustry, $moduleIndustryMap) {
                $rootKey = explode('.', $module->key, 2)[0];
                if (isset($moduleIndustryMap[$rootKey])) {
                    return $moduleIndustryMap[$rootKey] === $selectedIndustry;
                }
                return true;
            })->values();
        }

        $resolved = $service->resolveEnabled($institute);
        $overrides = $institute->moduleOverrides()->get()->keyBy('module_key');

        // Phase 5 — hard-boundary flags per module (Layers 7 + 8).
        $locked = [];
        foreach ($allModules as $module) {
            if (! $service->isIndustryCompatible($institute, $module->key)) {
                $locked[$module->key] = 'industry';
            } elseif (! $service->isCountryTaxAllowed($module->key, $institute)) {
                $locked[$module->key] = 'country';
            }
        }

        $subCategory = $institute->subcategory_key
            ? \Illuminate\Support\Facades\DB::table('industry_subcategories')
                ->where('industry_key', $institute->industry)
                ->where('subcategory_key', $institute->subcategory_key)
                ->first()
            : null;

        $activeEmergency = \Illuminate\Support\Facades\DB::table('super_admin_overrides')
            ->where('institute_id', $institute->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        return view('admin.modules.institute-modules', compact(
            'institute',
            'allModules',
            'resolved',
            'overrides',
            'industries',
            'selectedIndustry',
            'locked',
            'subCategory',
            'activeEmergency'
        ));
    }

    public function updateInstituteModules(Institute $institute, Request $request): RedirectResponse
    {
        $request->validate([
            'modules' => 'required|array',
            'modules.*' => 'string|exists:module_registry,key',
            'reason' => 'nullable|string|max:255',
        ]);

        $service = app(ModuleAccessService::class);

        // STEP 1: HARD BOUNDARY CHECK (BEFORE anything else) — admin cannot
        // request enabling a module the resolver would veto (Layer 7 / 8).
        // Disabling is always allowed.
        $blocked = [];
        foreach ((array) $request->input('modules', []) as $key) {
            if (! $service->isIndustryCompatible($institute, $key)) {
                $blocked[] = "{$key} (industry boundary)";
            } elseif (! $service->isCountryTaxAllowed($key, $institute)) {
                $blocked[] = "{$key} (country boundary)";
            }
        }

        if ($blocked) {
            return back()->withErrors([
                'modules' => '❌ BLOCKED: hard boundary bypass not allowed for: ' . implode(', ', $blocked)
                    . '. Admin cannot bypass industry/country boundaries — use Super Admin Emergency Override instead.',
            ]);
        }

        // MEDIUM RISK: enabling a module the tenant's package does not
        // include requires an explicit reason (audit trail).
        $packageAdds = [];
        foreach ((array) $request->input('modules', []) as $key) {
            if (! $service->isEnabled($institute, $key) && ! $service->isPackageAllowed($key, $institute->id)) {
                $packageAdds[] = $key;
            }
        }

        if ($packageAdds && ! $request->filled('reason')) {
            return back()->withErrors([
                'reason' => '⚠️ Reason required (medium risk) for package additions: ' . implode(', ', $packageAdds) . '.',
            ]);
        }

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
