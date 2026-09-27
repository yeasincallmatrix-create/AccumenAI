<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Industry;
use App\Models\Institute;
use App\Models\ModuleAccessLog;
use App\Models\ModuleRegistry;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PackageIndustryController extends Controller
{
    /**
     * Pricing selector region buckets (ISO2 lists).
     *
     * Only used to order/group the country dropdown — availability itself
     * comes from `package_country_prices`.
     */
    private const COUNTRY_REGIONS = [
        'SAARC' => ['BD', 'IN', 'PK', 'LK', 'NP', 'BT', 'MV'],
        'Gulf' => ['AE', 'SA', 'QA', 'KW', 'BH', 'OM'],
        'Southeast Asia' => ['MY', 'TH', 'ID', 'PH', 'VN'],
        'First World' => ['US', 'GB', 'CA', 'AU', 'DE', 'FR', 'SG', 'NL'],
        'European Union' => ['IT', 'ES', 'PL', 'SE', 'BE', 'AT', 'DK', 'FI', 'IE', 'PT', 'GR', 'CZ', 'RO', 'HU'],
    ];

    /**
     * Show the per-industry package configuration screen.
     */
    public function index(Request $request): View
    {
        $industries = Industry::where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        abort_if($industries->isEmpty(), 500, 'No active industries configured.');

        $industry = (string) $request->query('industry', 'healthcare');
        if (! $industries->contains('slug', $industry)) {
            $industry = $industries->first()->slug;
        }

        $countryGroups = $this->pricingCountryGroups();

        $countries = [];
        foreach ($countryGroups as $group) {
            foreach ($group as $code => $meta) {
                $countries[$code] = $meta;
            }
        }

        $country = (string) $request->query('country', '');
        if (! isset($countries[$country])) {
            $country = '';
        }

        $countryPrices = $country === ''
            ? collect()
            : DB::table('package_country_prices')
                ->where('country_code', $country)
                ->get()
                ->keyBy('package_id');

        $packages = DB::table('subscription_packages')
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $mapping = DB::table('package_industries')
            ->where('industry_key', $industry)
            ->get()
            ->keyBy('package_id');

        $industryModuleStats = $this->industryModuleStats($industry);
        $globalModuleCounts = $this->moduleCounts('package_modules');
        $configured = $mapping->isNotEmpty();

        $rows = $packages->map(function ($package) use ($mapping, $industryModuleStats, $globalModuleCounts, $configured) {
            $map = $mapping->get($package->id);
            $stats = $industryModuleStats[$package->id] ?? null;
            $hasIndustryConfig = $stats !== null;
            $moduleCount = $hasIndustryConfig
                ? (int) $stats['enabled']
                : (int) ($globalModuleCounts[$package->id] ?? 0);

            // An unconfigured industry implicitly offers every package.
            $enabled = $configured
                ? ($map !== null && (bool) $map->is_active)
                : true;

            return [
                'package' => $package,
                'enabled' => $enabled,
                'sort_order' => (int) ($map->sort_order ?? 0),
                'has_industry_modules' => $hasIndustryConfig,
                'module_count' => $moduleCount,
                'module_source' => $hasIndustryConfig ? 'industry' : 'package',
                'price_monthly' => $map !== null && $map->price_monthly !== null ? $map->price_monthly : null,
                'price_yearly' => $map !== null && $map->price_yearly !== null ? $map->price_yearly : null,
                'has_price_override' => $map !== null
                    && ($map->price_monthly !== null || $map->price_yearly !== null),
            ];
        });

        $totals = [
            'packages' => $packages->count(),
            'enabled' => $rows->where('enabled', true)->count(),
            'configured' => $mapping->isNotEmpty(),
        ];

        return view('admin.package-industries.index', [
            'industries' => $industries,
            'industry' => $industry,
            'industryName' => $industries->firstWhere('slug', $industry)?->name ?? $industry,
            'rows' => $rows,
            'totals' => $totals,
            'countries' => $countryGroups,
            'country' => $country,
            'countryPrices' => $countryPrices,
            'countryCurrency' => $country !== '' ? $countries[$country]['currency'] : 'BDT',
        ]);
    }

    /**
     * Persist which packages are available (and at what price) for an industry.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'industry' => 'required|string|exists:industries,slug',
            'packages' => 'required|array|min:1',
            'packages.*' => 'integer|exists:subscription_packages,id',
            'sort_order' => 'nullable|array',
            'sort_order.*' => 'nullable|integer|min:0|max:9999',
            'price_monthly' => 'nullable|array',
            'price_monthly.*' => 'nullable|numeric|min:0|max:999999',
            'price_yearly' => 'nullable|array',
            'price_yearly.*' => 'nullable|numeric|min:0|max:999999',
        ]);

        $industry = $validated['industry'];
        $selected = array_values(array_unique(array_map('intval', $validated['packages'] ?? [])));
        $sortOrder = $validated['sort_order'] ?? [];
        $monthly = $validated['price_monthly'] ?? [];
        $yearly = $validated['price_yearly'] ?? [];

        $previous = (string) DB::table('package_industries')
            ->where('industry_key', $industry)
            ->whereIn('package_id', $selected)
            ->count();

        DB::transaction(function () use ($industry, $selected, $sortOrder, $monthly, $yearly) {
            DB::table('package_industries')
                ->where('industry_key', $industry)
                ->whereNotIn('package_id', $selected)
                ->delete();

            foreach ($selected as $position => $packageId) {
                DB::table('package_industries')->updateOrInsert(
                    ['package_id' => $packageId, 'industry_key' => $industry],
                    [
                        'is_active' => true,
                        'sort_order' => (int) ($sortOrder[$packageId] ?? $position),
                        'price_monthly' => $this->normalizePrice($monthly[$packageId] ?? null),
                        'price_yearly' => $this->normalizePrice($yearly[$packageId] ?? null),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });

        $this->flushIndustryCache($industry, $selected);

        $this->audit(
            $request,
            'package_industry_updated',
            $previous,
            (string) count($selected),
            "Industry '{$industry}': ".count($selected).' package(s) mapped'
        );

        return redirect()
            ->route('admin.package-industries.index', ['industry' => $industry])
            ->with('success', 'Package configuration saved for '.$industry.'.');
    }

    /**
     * Show the per-industry module configuration for one package.
     */
    public function showModules(Request $request, int $package, string $industry): View
    {
        $packageModel = SubscriptionPackage::find($package);
        abort_if(! $packageModel, 404);

        $industryModel = Industry::where('slug', $industry)->where('status', 'active')->first();
        abort_if(! $industryModel, 404);

        $modules = ModuleRegistry::where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $industryConfig = config("industry-modules.{$industry}", []);
        $industryDisabled = array_flip($industryConfig['disabled'] ?? []);

        $service = app(ModuleAccessService::class);

        $rows = DB::table('package_industry_modules')
            ->where('package_id', $packageModel->id)
            ->where('industry_key', $industry)
            ->get();

        $hasIndustryConfig = $rows->isNotEmpty();
        $selection = [];
        foreach ($rows as $row) {
            if ((bool) $row->enabled) {
                $selection[$row->module_key] = true;
            }
        }

        $source = 'industry';
        if (! $hasIndustryConfig) {
            $source = 'package';
            foreach (DB::table('package_modules')
                ->where('package_id', $packageModel->id)
                ->where('enabled', true)
                ->pluck('module_key') as $key) {
                $selection[$key] = true;
            }
        }

        $mapped = DB::table('package_industries')
            ->where('package_id', $packageModel->id)
            ->where('industry_key', $industry)
            ->where('is_active', true)
            ->exists();

        return view('admin.package-industries.modules', [
            'package' => $packageModel,
            'industry' => $industry,
            'industryName' => $industryModel->name,
            'modules' => $modules,
            'selection' => $selection,
            'industryDisabled' => $industryDisabled,
            'service' => $service,
            'source' => $source,
            'mapped' => $mapped,
        ]);
    }

    /**
     * Persist the per-industry module selection for one package.
     */
    public function updateModules(Request $request, int $package, string $industry): RedirectResponse
    {
        $packageModel = SubscriptionPackage::find($package);
        abort_if(! $packageModel, 404);

        $industryModel = Industry::where('slug', $industry)->where('status', 'active')->first();
        abort_if(! $industryModel, 404);

        $validated = $request->validate([
            'modules' => 'nullable|array',
            'modules.*' => 'string|exists:module_registry,key',
        ]);

        $selected = array_values(array_unique($validated['modules'] ?? []));
        $selectedLookup = array_flip($selected);

        $service = app(ModuleAccessService::class);

        $previousCount = DB::table('package_industry_modules')
            ->where('package_id', $packageModel->id)
            ->where('industry_key', $industry)
            ->where('enabled', true)
            ->count();

        $allKeys = ModuleRegistry::where('status', 'active')->pluck('key')->all();

        DB::transaction(function () use ($packageModel, $industry, $allKeys, $selectedLookup, $service) {
            $industryConfig = config("industry-modules.{$industry}", []);
            $disabledLookup = array_flip($industryConfig['disabled'] ?? []);

            DB::table('package_industry_modules')
                ->where('package_id', $packageModel->id)
                ->where('industry_key', $industry)
                ->delete();

            foreach ($allKeys as $key) {
                $enabled = isset($selectedLookup[$key]);

                if (isset($disabledLookup[$key])) {
                    $enabled = false;
                }

                if ($service->isCoreModule($key)) {
                    $enabled = true;
                }

                DB::table('package_industry_modules')->insert([
                    'package_id' => $packageModel->id,
                    'industry_key' => $industry,
                    'module_key' => $key,
                    'enabled' => $enabled,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            }
        });

        $this->flushIndustryCache($industry, [$packageModel->id]);

        $this->audit(
            $request,
            'package_industry_modules_updated',
            (string) $previousCount,
            (string) count($selected),
            "{$packageModel->slug} × {$industry}: ".count($selected).' module(s)'
        );

        return redirect()
            ->route('admin.package-industries.show-modules', [
                'package' => $packageModel->id,
                'industry' => $industry,
            ])
            ->with('success', "Modules updated for {$packageModel->name} × {$industryModel->name}.");
    }

    /**
     * Enabled module counts per package for one table.
     *
     * @return array<int, int>
     */
    private function moduleCounts(string $table, ?string $industry = null): array
    {
        $query = DB::table($table)
            ->where('enabled', true)
            ->groupBy('package_id')
            ->select('package_id', DB::raw('COUNT(*) as total'));

        if ($industry !== null) {
            $query->where('industry_key', $industry);
        }

        $counts = [];
        foreach ($query->get() as $row) {
            $counts[(int) $row->package_id] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * Per-industry module configuration state per package:
     * package_id => ['configured' => bool, 'enabled' => int].
     *
     * @return array<int, array{configured: bool, enabled: int}>
     */
    private function industryModuleStats(string $industry): array
    {
        $stats = [];

        $rows = DB::table('package_industry_modules')
            ->where('industry_key', $industry)
            ->groupBy('package_id')
            ->select('package_id', DB::raw('COUNT(*) as total'), DB::raw('SUM(enabled) as enabled_count'))
            ->get();

        foreach ($rows as $row) {
            $stats[(int) $row->package_id] = [
                'configured' => true,
                'enabled' => (int) $row->enabled_count,
            ];
        }

        return $stats;
    }

    /**
     * Countries offered in the pricing selector, grouped for <optgroup>s.
     *
     * Source of truth: every country that actually has price rows in
     * `package_country_prices`, so the selector always matches what is
     * editable (SAARC + Gulf + Southeast Asia + First World, plus any
     * future region).
     *
     * @return array<string, array<string, array{name: string, currency: string}>>
     */
    private function pricingCountryGroups(): array
    {
        $codes = DB::table('package_country_prices')
            ->distinct()
            ->orderBy('country_code')
            ->pluck('country_code')
            ->all();

        if ($codes === []) {
            return [];
        }

        $mapped = DB::table('country_currency_map')
            ->whereIn('country_code', $codes)
            ->get()
            ->keyBy('country_code');

        $names = DB::table('countries')
            ->whereIn('iso2', $codes)
            ->get()
            ->keyBy('iso2');

        $groups = [];
        foreach (self::COUNTRY_REGIONS as $region => $regionCodes) {
            $groups[$region] = [];
        }
        $groups['Other'] = [];

        foreach ($codes as $code) {
            if (! isset($mapped[$code])) {
                continue;
            }

            $meta = [
                'name' => $names[$code]->name ?? $mapped[$code]->country_name,
                'currency' => $mapped[$code]->currency_code,
            ];

            foreach (self::COUNTRY_REGIONS as $region => $regionCodes) {
                if (in_array($code, $regionCodes, true)) {
                    $groups[$region][$code] = $meta;
                    continue 2;
                }
            }

            $groups['Other'][$code] = $meta;
        }

        return array_filter($groups, static fn (array $group): bool => $group !== []);
    }

    private function normalizePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * Drop the module/feature caches for every tenant this change can touch.
     *
     * @param  array<int, int>  $packageIds
     */
    private function flushIndustryCache(string $industry, array $packageIds = []): void
    {
        $service = app(ModuleAccessService::class);

        $query = Institute::query()->where('industry', $industry);

        if (! empty($packageIds)) {
            $query->orWhereIn('package_id', $packageIds);
        }

        foreach ($query->pluck('id') as $instituteId) {
            $service->flushCache((int) $instituteId);
            $service->flushFeatureCache((int) $instituteId);
        }
    }

    private function audit(
        Request $request,
        string $action,
        ?string $previous,
        ?string $next,
        string $notes
    ): void {
        if (! Schema::hasTable('module_access_logs')) {
            return;
        }

        ModuleAccessLog::create([
            'institute_id' => null,
            'module_key' => 'package_industry',
            'action' => $action,
            'actor_id' => $request->user()?->id,
            'actor_type' => 'platform_admin',
            'previous_state' => $previous,
            'new_state' => $next,
            'package_id' => null,
            'notes' => $notes,
        ]);
    }
}
