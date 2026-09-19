<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\InstituteFeatureOverride;
use App\Services\ModuleAccessService;
use App\Support\TenantContext;
use Illuminate\View\View;

class FeatureAccessController extends Controller
{
    public function index(): View
    {
        $institute = Institute::findOrFail(TenantContext::id());

        $service = app(ModuleAccessService::class);

        $features = FeatureRegistry::orderBy('module_key')
            ->orderBy('sort_order')
            ->get();

        $accessMap = $service->getFeatureAccessMap($institute);

        $states = [];
        foreach ($features as $feature) {
            $effective = $accessMap[$feature->feature_key] ?? false;

            $override = InstituteFeatureOverride::where('institute_id', $institute->id)
                ->where('feature_key', $feature->feature_key)
                ->first();

            $source = $override ? 'override' : 'package';
            $grantedBy = $override?->overriddenBy?->first_name
                ? $override->overriddenBy->first_name
                : null;

            $states[$feature->feature_key] = [
                'enabled'    => $effective,
                'source'     => $source,
                'granted_by' => $grantedBy,
                'reason'     => $override?->reason,
            ];
        }

        return view('settings.features', compact('features', 'states', 'institute'));
    }
}
