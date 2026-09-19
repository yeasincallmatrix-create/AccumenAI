<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\FeatureRegistry;
use App\Models\Industry;
use App\Models\Institute;
use App\Models\ModuleAccessLog;
use App\Models\PackageScope;
use App\Models\PackageScopedFeature;
use App\Models\SubIndustry;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PackageScopeAdminController extends Controller
{
    /**
     * List scopes for a package (nested route also supports global
     * listing via ?package_id / ?country_id / ?status filters).
     */
    public function index(Request $request, ?SubscriptionPackage $package = null): View
    {
        // When the nested route supplies {package}, pre-select it.
        $packageFilter = $request->query('package_id', $package?->id);

        $query = PackageScope::query()
            ->with(['package', 'country', 'industry', 'subIndustry'])
            ->orderBy('package_id')
            ->orderBy('country_id')
            ->orderBy('industry_id')
            ->orderBy('sub_industry_id');

        if ($packageFilter) {
            $query->where('package_id', $packageFilter);
        }

        if ($countryId = $request->query('country_id')) {
            $query->where('country_id', $countryId);
        }

        if ($status = $request->query('status')) {
            if (in_array($status, ['active', 'inactive'], true)) {
                $query->where('status', $status);
            }
        }

        $scopes = $query->paginate(50)->withQueryString();

        // Feature counts per scope (no model relationship exists —
        // query PackageScopedFeature directly).
        $featureCounts = PackageScopedFeature::whereIn(
                'package_scope_id',
                $scopes->getCollection()->pluck('id')->all()
            )
            ->where('enabled', true)
            ->selectRaw('package_scope_id, COUNT(*) as cnt')
            ->groupBy('package_scope_id')
            ->pluck('cnt', 'package_scope_id')
            ->all();

        $packages = SubscriptionPackage::orderBy('id')->get();
        $countries = Country::orderBy('name')->get(['id', 'name']);
        $industries = Industry::orderBy('name')->get(['id', 'name']);

        return view('admin.scopes.index', compact(
            'scopes', 'packages', 'countries', 'industries', 'package', 'featureCounts'
        ));
    }

    public function show(PackageScope $scope): View
    {
        $scope->load(['package', 'country', 'industry', 'subIndustry']);

        $scopedRows = PackageScopedFeature::where('package_scope_id', $scope->id)->get();
        $scopedMap = $scopedRows->pluck('enabled', 'feature_key')->all();

        $allFeatures = FeatureRegistry::orderBy('module_key')
            ->orderBy('sort_order')
            ->get();

        $scopedFeatures = $allFeatures->groupBy('module_key');

        $parentScope = app(ModuleAccessService::class)->resolveParentScope($scope);
        $parentMap = [];
        if ($parentScope) {
            $parentMap = PackageScopedFeature::where('package_scope_id', $parentScope->id)
                ->where('enabled', true)
                ->pluck('feature_key')
                ->flip()
                ->all();
        }

        return view('admin.scopes.show', compact(
            'scope', 'scopedFeatures', 'scopedMap', 'allFeatures', 'parentScope', 'parentMap'
        ));
    }

    public function create(SubscriptionPackage $package): View
    {
        $packages = SubscriptionPackage::orderBy('id')->get();
        $countries = Country::orderBy('name')->get(['id', 'name']);
        $industries = Industry::orderBy('name')->get(['id', 'name']);
        $subIndustries = SubIndustry::orderBy('name')->get(['id', 'name', 'industry_id']);

        return view('admin.scopes.create', compact(
            'package', 'packages', 'countries', 'industries', 'subIndustries'
        ));
    }

    public function store(Request $request, SubscriptionPackage $package): RedirectResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'exists:subscription_packages,id'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'industry_id' => ['nullable', 'exists:industries,id'],
            'sub_industry_id' => ['nullable', 'exists:sub_industries,id'],
            'inherit_from_parent' => ['required', 'boolean'],
            'price_monthly' => ['nullable', 'numeric', 'min:0'],
            'price_yearly' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        if ((int) $validated['package_id'] !== (int) $package->id) {
            abort(422, 'Package mismatch between route and payload.');
        }

        $scope = DB::transaction(function () use ($validated) {
            $created = PackageScope::create([
                'package_id' => $validated['package_id'],
                'country_id' => $validated['country_id'] ?? null,
                'industry_id' => $validated['industry_id'] ?? null,
                'sub_industry_id' => $validated['sub_industry_id'] ?? null,
                'inherit_from_parent' => (bool) $validated['inherit_from_parent'],
                'price_monthly' => $validated['price_monthly'] ?? null,
                'price_yearly' => $validated['price_yearly'] ?? null,
                'currency' => $validated['currency'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            if ($created->inherit_from_parent) {
                $parent = app(ModuleAccessService::class)->resolveParentScope($created);
                if ($parent) {
                    $parentFeatures = PackageScopedFeature::where('package_scope_id', $parent->id)->get();
                    foreach ($parentFeatures as $pf) {
                        PackageScopedFeature::create([
                            'package_scope_id' => $created->id,
                            'feature_key' => $pf->feature_key,
                            'enabled' => $pf->enabled,
                        ]);
                    }
                }
            }

            return $created;
        });

        $this->audit($request, null, $scope->package_id, 'scope_created',
            null, $scope->status, "Scope created for package '{$package->slug}'");

        return redirect()->route('admin.scopes.show', $scope)
            ->with('success', 'Scope created successfully.');
    }

    public function edit(PackageScope $scope): View
    {
        $scope->load(['package', 'country', 'industry', 'subIndustry']);

        $packages = SubscriptionPackage::orderBy('id')->get();
        $countries = Country::orderBy('name')->get(['id', 'name']);
        $industries = Industry::orderBy('name')->get(['id', 'name']);
        $subIndustries = SubIndustry::orderBy('name')->get(['id', 'name', 'industry_id']);

        return view('admin.scopes.edit', compact(
            'scope', 'packages', 'countries', 'industries', 'subIndustries'
        ));
    }

    public function update(Request $request, PackageScope $scope): RedirectResponse
    {
        $validated = $request->validate([
            'package_id' => ['sometimes', 'exists:subscription_packages,id'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'industry_id' => ['nullable', 'exists:industries,id'],
            'sub_industry_id' => ['nullable', 'exists:sub_industries,id'],
            'inherit_from_parent' => ['required', 'boolean'],
            'price_monthly' => ['nullable', 'numeric', 'min:0'],
            'price_yearly' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        if (array_key_exists('package_id', $validated)
            && (int) $validated['package_id'] !== (int) $scope->package_id) {
            abort(422, 'package_id is immutable and cannot be changed.');
        }

        $previousState = "monthly:{$scope->price_monthly}|yearly:{$scope->price_yearly}|{$scope->currency}|{$scope->status}";

        DB::transaction(function () use ($scope, $validated) {
            $scope->fill([
                'country_id' => $validated['country_id'] ?? null,
                'industry_id' => $validated['industry_id'] ?? null,
                'sub_industry_id' => $validated['sub_industry_id'] ?? null,
                'inherit_from_parent' => (bool) $validated['inherit_from_parent'],
                'price_monthly' => $validated['price_monthly'] ?? null,
                'price_yearly' => $validated['price_yearly'] ?? null,
                'currency' => $validated['currency'] ?? null,
                'status' => $validated['status'] ?? $scope->status,
            ]);

            if ($scope->isDirty(['country_id', 'industry_id', 'sub_industry_id'])) {
                $scope->scope_hash = implode('-', [
                    $scope->package_id,
                    $scope->country_id ?? 'G',
                    $scope->industry_id ?? 'G',
                    $scope->sub_industry_id ?? 'G',
                ]);
            }

            $scope->save();
        });

        app(ModuleAccessService::class)->flushFeatureCacheForScope($scope->fresh());

        $newState = "monthly:{$scope->price_monthly}|yearly:{$scope->price_yearly}|{$scope->currency}|{$scope->status}";
        $this->audit($request, null, $scope->package_id, 'scope_updated',
            $previousState, $newState, "Scope #{$scope->id} updated");

        return back()->with('success', 'Scope updated successfully.');
    }

    public function updateFeatures(Request $request, PackageScope $scope): RedirectResponse
    {
        $validated = $request->validate([
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'exists:feature_registry,feature_key'],
        ]);

        $enabledKeys = array_values(array_unique($validated['features'] ?? []));

        $allKeys = FeatureRegistry::pluck('feature_key')->all();

        DB::transaction(function () use ($scope, $allKeys, $enabledKeys) {
            $enabledLookup = array_flip($enabledKeys);

            foreach ($allKeys as $key) {
                PackageScopedFeature::updateOrCreate(
                    ['package_scope_id' => $scope->id, 'feature_key' => $key],
                    ['enabled' => isset($enabledLookup[$key])]
                );
            }
        });

        app(ModuleAccessService::class)->flushFeatureCacheForScope($scope);

        $this->audit($request, null, $scope->package_id, 'scope_features_updated',
            null, count($enabledKeys) . ' features enabled',
            "Scope #{$scope->id} features synced");

        return back()->with('success', 'Scope features updated successfully.');
    }

    public function destroy(Request $request, PackageScope $scope): RedirectResponse
    {
        $referencing = Institute::where('package_id', $scope->package_id)
            ->where('country_id', $scope->country_id)
            ->where('industry_id', $scope->industry_id)
            ->where('sub_industry_id', $scope->sub_industry_id)
            ->count();

        if ($referencing > 0) {
            abort(422, 'Cannot delete scope: institutes reference this scope.');
        }

        $packageId = $scope->package_id;
        $scopeId = $scope->id;

        DB::transaction(function () use ($scope) {
            PackageScopedFeature::where('package_scope_id', $scope->id)->delete();
            $scope->delete();
        });

        $this->audit($request, null, $packageId, 'scope_deleted',
            'active', 'deleted', "Scope #{$scopeId} deleted");

        return redirect()->route('admin.packages.scopes.index', $packageId)
            ->with('success', 'Scope deleted successfully.');
    }

    private function audit(
        Request $request,
        ?int $instituteId,
        ?int $packageId,
        string $action,
        ?string $previous,
        ?string $next,
        string $notes
    ): void {
        ModuleAccessLog::create([
            'institute_id' => $instituteId,
            'module_key' => 'package_scope',
            'action' => $action,
            'actor_id' => $request->user()?->id,
            'actor_type' => 'platform_admin',
            'previous_state' => $previous,
            'new_state' => $next,
            'package_id' => $packageId,
            'notes' => $notes,
        ]);
    }
}
