<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureRegistry;
use App\Models\ModuleAccessLog;
use App\Models\PackageFeature;
use App\Models\SubscriptionPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeatureAdminController extends Controller
{
    public function index(): View
    {
        $features = FeatureRegistry::orderBy('sort_order')->get();
        $packages = SubscriptionPackage::where('status', 'active')->orderBy('id')->get();

        $packageFeatures = [];
        foreach ($packages as $pkg) {
            $packageFeatures[$pkg->id] = PackageFeature::where('package_id', $pkg->id)
                ->where('enabled', true)
                ->pluck('feature_key')
                ->toArray();
        }

        return view('admin.features.index', compact('features', 'packages', 'packageFeatures'));
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

        return view('admin.features.show', compact('feature', 'packages', 'packageFeatures', 'recentLogs'));
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
}
