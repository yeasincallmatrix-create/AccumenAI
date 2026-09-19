<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\InstituteFeatureOverride;
use App\Models\ModuleAccessLog;
use App\Models\PackageFeature;
use App\Models\SubscriptionPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeatureAdminController extends Controller
{
    public function index(Request $request): View
    {
        $query = FeatureRegistry::query();

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('feature_key', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($module = $request->query('module')) {
            $query->where('module_key', $module);
        }

        $features = $query->orderBy('module_key')
                          ->orderBy('sort_order')
                          ->paginate(50)
                          ->withQueryString();

        $packages = SubscriptionPackage::where('status', 'active')
            ->orderBy('id')->get();

        if ($packageFilter = $request->query('package')) {
            $packages = $packages->filter(fn ($pkg) => $pkg->id === (int) $packageFilter);
        }

        $packageFeatures = [];
        foreach ($packages as $pkg) {
            $packageFeatures[$pkg->id] = PackageFeature::where('package_id', $pkg->id)
                ->where('enabled', true)
                ->pluck('feature_key')
                ->toArray();
        }

        $modules = FeatureRegistry::distinct()->pluck('module_key')->sort()->values();

        return view('admin.features.index', compact(
            'features', 'packages', 'packageFeatures', 'modules'
        ));
    }

    public function show(string $feature_key): View
    {
        $feature = FeatureRegistry::where('feature_key', $feature_key)
            ->firstOrFail();

        $packages = SubscriptionPackage::where('status', 'active')->orderBy('id')->get();

        $packageFeatures = [];
        foreach ($packages as $pkg) {
            $pf = PackageFeature::where('package_id', $pkg->id)
                ->where('feature_key', $feature_key)
                ->first();
            $packageFeatures[$pkg->id] = $pf?->enabled ?? false;
        }

        $recentLogs = ModuleAccessLog::where('module_key', $feature->module_key)
            ->where('notes', 'like', '%' . $feature_key . '%')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $instituteOverrides = InstituteFeatureOverride::where('feature_key', $feature_key)
            ->with(['institute', 'overriddenBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        $institutes = Institute::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'industry']);

        return view('admin.features.show', compact(
            'feature', 'packages', 'packageFeatures', 'recentLogs',
            'instituteOverrides', 'institutes'
        ));
    }

    public function togglePackage(Request $request, string $feature_key, int $package_id): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $feature = FeatureRegistry::where('feature_key', $feature_key)->firstOrFail();
        $package = SubscriptionPackage::findOrFail($package_id);

        $wasEnabled = PackageFeature::where('package_id', $package_id)
            ->where('feature_key', $feature_key)
            ->value('enabled') ?? false;

        $nowEnabled = (bool) $validated['enabled'];

        if ($wasEnabled === $nowEnabled) {
            return back()->with('info', "Feature '{$feature->name}' already "
                . ($nowEnabled ? 'enabled' : 'disabled')
                . " for {$package->name}.");
        }

        PackageFeature::updateOrCreate(
            ['package_id' => $package_id, 'feature_key' => $feature_key],
            ['enabled' => $nowEnabled]
        );

        ModuleAccessLog::create([
            'institute_id' => null,
            'module_key' => $feature->module_key,
            'action' => $nowEnabled ? 'feature_enabled' : 'feature_disabled',
            'actor_id' => $request->user()?->id,
            'actor_type' => 'platform_admin',
            'previous_state' => $wasEnabled ? 'enabled' : 'disabled',
            'new_state' => $nowEnabled ? 'enabled' : 'disabled',
            'package_id' => $package_id,
            'notes' => "Feature '{$feature_key}' "
                . ($nowEnabled ? 'enabled' : 'disabled')
                . " for package '{$package->slug}'",
        ]);

        $status = $nowEnabled ? 'enabled' : 'disabled';

        return back()->with('success',
            "Feature '{$feature->name}' {$status} for {$package->name}.");
    }

    public function addInstituteOverride(Request $request, string $feature_key): RedirectResponse
    {
        $validated = $request->validate([
            'institute_id' => ['required', 'integer', 'exists:institutes,id'],
            'enabled'      => ['required', 'boolean'],
            'reason'       => ['nullable', 'string', 'max:255'],
        ]);

        $feature = FeatureRegistry::where('feature_key', $feature_key)->firstOrFail();
        $institute = Institute::findOrFail($validated['institute_id']);

        $enabled = (bool) $validated['enabled'];

        $existing = InstituteFeatureOverride::where('institute_id', $institute->id)
            ->where('feature_key', $feature_key)
            ->first();

        $wasEnabled = $existing?->enabled;

        if ($existing !== null && $wasEnabled === $enabled) {
            return back()->with('info',
                "Feature '{$feature->name}' is already "
                . ($enabled ? 'enabled' : 'disabled')
                . " for '{$institute->name}' via override.");
        }

        InstituteFeatureOverride::updateOrCreate(
            ['institute_id' => $institute->id, 'feature_key' => $feature_key],
            [
                'enabled'       => $enabled,
                'overridden_by' => $request->user()->id,
                'reason'        => $validated['reason'] ?? null,
            ]
        );

        ModuleAccessLog::create([
            'institute_id'   => $institute->id,
            'module_key'     => explode('.', $feature_key, 2)[0],
            'action'         => $enabled ? 'feature_override_enabled' : 'feature_override_disabled',
            'actor_id'       => $request->user()->id,
            'actor_type'     => 'platform_admin',
            'previous_state' => $wasEnabled === null
                                  ? null
                                  : ($wasEnabled ? 'enabled' : 'disabled'),
            'new_state'      => $enabled ? 'enabled' : 'disabled',
            'package_id'     => $institute->package_id,
            'notes'          => "Feature '{$feature_key}' overridden for institute '{$institute->name}'",
        ]);

        return back()->with('success',
            "Feature override " . ($enabled ? 'enabled' : 'disabled') . " for '{$institute->name}'.");
    }

    public function removeInstituteOverride(Request $request, string $feature_key, int $institute_id): RedirectResponse
    {
        $feature = FeatureRegistry::where('feature_key', $feature_key)->firstOrFail();
        $institute = Institute::findOrFail($institute_id);

        $override = InstituteFeatureOverride::where('institute_id', $institute_id)
            ->where('feature_key', $feature_key)
            ->first();

        if (! $override) {
            return back()->with('info', 'No override to remove.');
        }

        $previousState = $override->enabled ? 'enabled' : 'disabled';

        $override->delete();

        ModuleAccessLog::create([
            'institute_id'   => $institute_id,
            'module_key'     => explode('.', $feature_key, 2)[0],
            'action'         => 'feature_override_removed',
            'actor_id'       => $request->user()->id,
            'actor_type'     => 'platform_admin',
            'previous_state' => $previousState,
            'new_state'      => null,
            'package_id'     => $institute->package_id,
            'notes'          => "Feature override removed for institute '{$institute->name}'",
        ]);

        return back()->with('success',
            "Feature override removed for '{$institute->name}'.");
    }
}
