<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Industry;
use App\Models\Institute;
use App\Models\ModuleAccessLog;
use App\Models\ModuleRegistry;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use App\Services\Pricing\IndustryPricingCardsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PackageIndustryController extends Controller
{
    public function __construct(private readonly IndustryPricingCardsService $pricingCards) {}

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

        $rows = $packages->map(function ($package) use ($mapping, $industryModuleStats, $globalModuleCounts, $configured, $countryPrices, $country) {
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

            $baseMonthly = $map !== null && $map->price_monthly !== null ? (float) $map->price_monthly : (float) $package->price_monthly;
            $baseYearly = $map !== null && $map->price_yearly !== null ? (float) $map->price_yearly : (float) $package->price_yearly;

            $discount = $this->discountState($map);
            $trialDays = $map !== null && isset($map->trial_days) && $map->trial_days !== null ? (int) $map->trial_days : null;

            // Country mode: country row wins for price/discount/trial display.
            $countryRow = ($country !== '') ? ($countryPrices->get($package->id) ?? null) : null;
            if ($countryRow) {
                if ($countryRow->price_monthly !== null) {
                    $baseMonthly = (float) $countryRow->price_monthly;
                }
                if ($countryRow->price_yearly !== null) {
                    $baseYearly = (float) $countryRow->price_yearly;
                }
                if (isset($countryRow->discount_percent) && $countryRow->discount_percent !== null && (float) $countryRow->discount_percent > 0) {
                    $discount = $this->discountState($countryRow);
                }
                if (isset($countryRow->trial_days) && $countryRow->trial_days !== null) {
                    $trialDays = (int) $countryRow->trial_days;
                }
            }

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
                'discount_percent' => $discount['percent'],
                'discount_ends_at' => $discount['ends_at'],
                'discount_days_left' => $discount['days_left'],
                'discount_active' => $discount['active'],
                'trial_days' => $trialDays,
                'price_monthly_effective' => $discount['active'] && $discount['percent'] !== null
                    ? round($baseMonthly * (1 - $discount['percent'] / 100), 2)
                    : $baseMonthly,
                'price_yearly_effective' => $discount['active'] && $discount['percent'] !== null
                    ? round($baseYearly * (1 - $discount['percent'] / 100), 2)
                    : $baseYearly,
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
     * Pricing cards: 4 cards (free + starter/growth/enterprise) for one
     * industry, with country + industry selectors. Read-only showcase of
     * the effective prices (industry override → country price → discount).
     */
    public function pricingCards(Request $request): View
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
        $currency = $country !== '' ? $countries[$country]['currency'] : 'BDT';

        $view = (string) $request->query('view', 'admin');
        if (! in_array($view, ['admin', 'customer'], true)) {
            $view = 'admin';
        }

        // 4 cards: free first, then starter → growth → enterprise.
        $cards = array_slice($this->pricingCards->cards($industry, $country), 0, 4);

        return view('admin.package-industries.pricing-cards', [
            'industries' => $industries,
            'industry' => $industry,
            'industryName' => $industries->firstWhere('slug', $industry)?->name ?? $industry,
            'countries' => $countryGroups,
            'country' => $country,
            'currency' => $currency,
            'cards' => $cards,
            'view' => $view,
        ]);
    }

    /**
     * Discount state for one package_industries row.
     *
     * @return array{percent: ?float, ends_at: ?string, days_left: ?int, active: bool}
     */
    private function discountState(mixed $map): array
    {
        return $this->pricingCards->discountState($map);
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
            'discount_percent' => 'nullable|array',
            'discount_percent.*' => 'nullable|numeric|min:0|max:100',
            'discount_ends_at' => 'nullable|array',
            'discount_ends_at.*' => 'nullable|date',
            'trial_days' => 'nullable|array',
            'trial_days.*' => 'nullable|integer|min:0|max:365',
        ]);

        $industry = $validated['industry'];
        $selected = array_values(array_unique(array_map('intval', $validated['packages'] ?? [])));
        $sortOrder = $validated['sort_order'] ?? [];
        $monthly = $validated['price_monthly'] ?? [];
        $yearly = $validated['price_yearly'] ?? [];
        $discounts = $validated['discount_percent'] ?? [];
        $discountEnds = $validated['discount_ends_at'] ?? [];
        $trialDays = $validated['trial_days'] ?? [];

        $previous = (string) DB::table('package_industries')
            ->where('industry_key', $industry)
            ->whereIn('package_id', $selected)
            ->count();

        DB::transaction(function () use ($industry, $selected, $sortOrder, $monthly, $yearly, $discounts, $discountEnds, $trialDays) {
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
                        'discount_percent' => $this->normalizeDiscount($discounts[$packageId] ?? null),
                        'discount_ends_at' => $this->normalizeDate($discountEnds[$packageId] ?? null),
                        'trial_days' => $this->normalizeTrialDays($trialDays[$packageId] ?? null),
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
        $matrix = [];
        foreach ($rows as $row) {
            if ((bool) $row->enabled) {
                $selection[$row->module_key] = true;
            }
            // Category radio state: stored value wins, else legacy
            // enabled mapping (true → default, false → hidden).
            $matrix[$row->module_key] = $row->category
                ?? ((bool) $row->enabled ? 'default' : 'hidden');
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

        // ── Group by Parent Module, ordered by relevance for this industry ──
        // Sections: industry (this industry's own parents) → core/shared →
        // other industries → blocked (config disabled). Parent row first.
        $groupedModules = $this->groupModulesForIndustry($modules, $industry, $industryDisabled);

        // Upgrade-locked: off here, on in a higher-tier package same industry.
        $locked = $service->upgradeUnlockedBy($packageModel->id, $industry);

        return view('admin.package-industries.modules', [
            'package' => $packageModel,
            'industry' => $industry,
            'industryName' => $industryModel->name,
            'modules' => $modules,
            'groupedModules' => $groupedModules,
            'selection' => $selection,
            'matrix' => $matrix,
            'locked' => $locked,
            'industryDisabled' => $industryDisabled,
            'service' => $service,
            'source' => $source,
            'mapped' => $mapped,
        ]);
    }

    /**
     * Group modules as ordered sections → parent groups → modules.
     *
     * Industry relevance from root key (medical→healthcare, education,
     * training_center, restaurant, real_estate, manufacturing, retail;
     * everything else is shared/core). A parent group is blocked when its
     * parent key or root is in the industry config `disabled` list.
     *
     * @return array<int, array{key: string, label: string, parents: array<string, array{parent: mixed, children: mixed, blocked: bool}>}>
     */
    protected function groupModulesForIndustry($modules, string $industry, array $industryDisabled): array
    {
        $rootToIndustry = [
            'education' => 'education',
            'training_center' => 'training_center',
            'medical' => 'healthcare',
            'restaurant' => 'restaurant',
            'real_estate' => 'real_estate',
            'manufacturing' => 'manufacturing',
            'retail' => 'retail',
        ];

        $byKey = $modules->keyBy('key');
        $parents = [];
        foreach ($modules as $module) {
            $parentKey = $module->parent_key ?: $module->key;
            $parents[$parentKey]['parent'] = $byKey->get($parentKey);
            $parents[$parentKey]['children'][] = $module;
        }

        // Parent row first, rest in sort_order.
        foreach ($parents as $pKey => &$pg) {
            usort($pg['children'], function ($a, $b) use ($pKey) {
                if ($a->key === $pKey) {
                    return -1;
                }
                if ($b->key === $pKey) {
                    return 1;
                }

                return ($a->sort_order ?? 0) <=> ($b->sort_order ?? 0);
            });
            $root = explode('.', $pKey, 2)[0];
            $pg['blocked'] = isset($industryDisabled[$pKey]) || isset($industryDisabled[$root]);
            $pg['section'] = $pg['blocked']
                ? 'blocked'
                : (($rootToIndustry[$root] ?? 'shared') === $industry ? 'industry' : (($rootToIndustry[$root] ?? null) ? 'other' : 'core'));
        }
        unset($pg);

        $sectionLabels = [
            'industry' => 'This industry',
            'core' => 'Shared / Core',
            'other' => 'Other industries',
            'blocked' => 'Not available in this industry',
        ];

        $sectionOrder = ['industry' => 0, 'core' => 1, 'other' => 2, 'blocked' => 3];
        $sections = [];
        foreach ($parents as $pKey => $pg) {
            $sections[$pg['section']]['key'] = $pg['section'];
            $sections[$pg['section']]['label'] = $sectionLabels[$pg['section']];
            $sections[$pg['section']]['parents'][$pKey] = $pg;
        }
        uksort($sections, fn ($a, $b) => ($sectionOrder[$a] ?? 9) <=> ($sectionOrder[$b] ?? 9));
        foreach ($sections as &$section) {
            uasort($section['parents'], function ($a, $b) {
                $an = $a['parent']?->name ?? ($a['children'][0]->parent_key ?? '');
                $bn = $b['parent']?->name ?? ($b['children'][0]->parent_key ?? '');

                return strcasecmp($an, $bn);
            });
        }
        unset($section);

        return array_values($sections);
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
        ]);

        $rawModules = array_values($validated['modules'] ?? []);
        $assignments = [];
        if ($rawModules !== [] && is_string(reset($rawModules))) {
            // Legacy flat payload: list of enabled module keys → category 'default'.
            foreach (array_unique($rawModules) as $key) {
                $assignments[$key] = 'default';
            }
        } else {
            // Matrix payload: rows of [module_key, category].
            $validKeys = ModuleRegistry::where('status', 'active')->pluck('key')->flip();
            $validCategories = ['mandatory' => true, 'default' => true, 'optional' => true, 'hidden' => true];
            foreach ($rawModules as $row) {
                if (! is_array($row) || ! isset($row['module_key']) || ! is_string($row['module_key'])) {
                    continue;
                }
                $key = $row['module_key'];
                if (! isset($validKeys[$key])) {
                    continue;
                }
                $category = $row['category'] ?? 'optional';
                if (! isset($validCategories[$category])) {
                    $category = 'optional';
                }
                $assignments[$key] = $category;
            }
        }

        $service = app(ModuleAccessService::class);

        $previousCount = DB::table('package_industry_modules')
            ->where('package_id', $packageModel->id)
            ->where('industry_key', $industry)
            ->where('enabled', true)
            ->count();

        $allKeys = ModuleRegistry::where('status', 'active')->pluck('key')->all();

        DB::transaction(function () use ($packageModel, $industry, $allKeys, $assignments, $service) {
            $industryConfig = config("industry-modules.{$industry}", []);
            $disabledLookup = array_flip($industryConfig['disabled'] ?? []);
            // A disabled root disables its whole subtree (e.g. real_estate ⇒
            // real_estate.*), matching the resolver's root-based industry gate.
            $isDisabled = static fn (string $key): bool => isset($disabledLookup[$key])
                || isset($disabledLookup[explode('.', $key, 2)[0]]);

            DB::table('package_industry_modules')
                ->where('package_id', $packageModel->id)
                ->where('industry_key', $industry)
                ->delete();

            foreach ($allKeys as $key) {
                $category = $assignments[$key] ?? 'hidden';

                if ($isDisabled($key)) {
                    $category = 'hidden';
                }

                if ($service->isCoreModule($key)) {
                    $category = 'mandatory';
                }

                DB::table('package_industry_modules')->insert([
                    'package_id' => $packageModel->id,
                    'industry_key' => $industry,
                    'module_key' => $key,
                    'enabled' => in_array($category, ['mandatory', 'default'], true),
                    'category' => $category,
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
            (string) count(array_filter($assignments, fn ($c) => in_array($c, ['mandatory', 'default'], true))),
            "{$packageModel->slug} × {$industry}: ".count($assignments).' module(s)'
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

    private function normalizeDiscount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return min(100, max(0, round((float) $value, 2)));
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeTrialDays(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return min(365, max(0, (int) $value));
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
