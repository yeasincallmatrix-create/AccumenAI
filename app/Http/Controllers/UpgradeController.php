<?php

namespace App\Http\Controllers;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\PackageFeature;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class UpgradeController extends Controller
{
    public function show(Request $request)
    {
        $featureKey = $request->query('feature');
        abort_unless(is_string($featureKey) && str_contains($featureKey, '.'), 404);

        $feature = FeatureRegistry::where('feature_key', $featureKey)
            ->where('status', 'active')
            ->first();
        abort_unless($feature, 404);

        $institute = TenantContext::id()
            ? Institute::withoutGlobalScopes()->find(TenantContext::id())
            : null;
        abort_unless($institute, 403);

        $requiredPackage = PackageFeature::where('feature_key', $featureKey)
            ->where('enabled', true)
            ->join('subscription_packages', 'subscription_packages.id', '=', 'package_features.package_id')
            ->orderBy('subscription_packages.price_monthly')
            ->select('subscription_packages.*')
            ->first();

        $currentPackage = $institute->package_id
            ? SubscriptionPackage::find($institute->package_id)
            : null;

        return view('upgrade.show', compact(
            'feature', 'currentPackage', 'requiredPackage'
        ));
    }
}
