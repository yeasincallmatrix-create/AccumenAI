<?php

namespace App\Services;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\InstituteFeatureOverride;
use App\Models\InstituteModuleEntitlement;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleAccessLog;
use App\Models\ModuleRegistry;
use App\Models\PackageFeature;
use App\Models\PackageModule;
use App\Models\PackageScope;
use App\Models\PackageScopedFeature;
use App\Models\PackageScopedModule;
use App\Models\SubscriptionPackage;
use App\Models\TenantAccessDenial;
use App\Models\TenantAccessGrant;
use App\Support\AccessDecisionContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Single entitlement engine for module access.
 *
 * FUTURE BILLING COMPATIBILITY (63G — preparation only, no payment yet):
 *   Module → Module Price → Add-on Order/Subscription → Payment → Payment Success → grantModule() → Entitlement → ModuleAccessService → Access
 *   Commercial fields (monthly_price, yearly_price, billing_cycle, auto_renew, discount_percent, purchased_by) are
 *   nullable informational metadata in this step; isEnabled() does NOT require payment status. Future flow will set
 *   these fields via successful payment webhook then call grantModule() to activate real access.
 */
class ModuleAccessService
{
    protected string $cachePrefix = 'module_access:';

    protected string $featureCachePrefix = 'feature_access:';

    /**
     * Layer 8 memo — allowed tax modules per country code (per instance).
     *
     * @var array<string, array<int, string>>
     */
    protected array $countryTaxMemo = [];

    /**
     * Per-industry package allow-list memo — industry slug => [package_id => true],
     * or null when the industry has no package_industries rows (unconfigured).
     *
     * @var array<string, array<int, bool>|null>
     */
    protected array $industryPackageMapMemo = [];

    /**
     * Per-industry module set memo — "packageId|industryKey" => list of module keys,
     * or null when the package has no explicit industry module configuration.
     *
     * @var array<string, array<int, string>|null>
     */
    protected array $industryPackageModulesMemo = [];

    public function isEnabled(Institute $institute, string $moduleKey): bool
    {
        $enabled = $this->getEnabledModules($institute);

        return in_array($moduleKey, $enabled, true);
    }

    public function isEnabledForFree(string $moduleKey): bool
    {
        $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->first();
        if (! $free) {
            return false;
        }

        return PackageModule::where('package_id', $free->id)
            ->where('module_key', $moduleKey)
            ->where('enabled', true)
            ->exists();
    }

    /**
     * Check if a module is allowed by the tenant's package (without overrides/entitlements).
     */
    public function isPackageAllowed(string $moduleKey, int $instituteId): bool
    {
        $institute = Institute::withoutGlobalScopes()->find($instituteId);
        if (! $institute || ! $institute->package_id) {
            return false;
        }

        return PackageModule::where('package_id', $institute->package_id)
            ->where('module_key', $moduleKey)
            ->where('enabled', true)
            ->exists();
    }

    public function getEnabledModules(Institute $institute): array
    {
        $cacheKey = $this->cachePrefix.$institute->id;

        return Cache::remember($cacheKey, 3600, function () use ($institute) {
            $resolved = $this->resolveEnabled($institute);

            return array_keys(array_filter($resolved));
        });
    }

    public function getAllModules(): array
    {
        return ModuleRegistry::all()->keyBy('key')->toArray();
    }

    public function getPackageModules(SubscriptionPackage $package): array
    {
        return PackageModule::where('package_id', $package->id)
            ->pluck('enabled', 'module_key')
            ->toArray();
    }

    public function enableModule(Institute $institute, string $moduleKey, ?int $actorId = null, ?string $reason = null): void
    {
        $previousState = $this->isEnabled($institute, $moduleKey) ? 'enabled' : 'disabled';

        InstituteModuleOverride::updateOrCreate(
            [
                'institute_id' => $institute->id,
                'module_key' => $moduleKey,
            ],
            [
                'enabled' => true,
                'overridden_by' => $actorId,
                'reason' => $reason,
            ]
        );

        $this->logAccess(
            $institute->id,
            $moduleKey,
            'enable',
            $actorId,
            $previousState,
            'enabled',
            $institute->package_id,
            $reason
        );

        $this->flushCache($institute->id);
        $this->flushFeatureCache($institute->id);
    }

    public function disableModule(Institute $institute, string $moduleKey, ?int $actorId = null, ?string $reason = null): void
    {
        $previousState = $this->isEnabled($institute, $moduleKey) ? 'enabled' : 'disabled';

        InstituteModuleOverride::updateOrCreate(
            [
                'institute_id' => $institute->id,
                'module_key' => $moduleKey,
            ],
            [
                'enabled' => false,
                'overridden_by' => $actorId,
                'reason' => $reason,
            ]
        );

        $this->logAccess(
            $institute->id,
            $moduleKey,
            'disable',
            $actorId,
            $previousState,
            'disabled',
            $institute->package_id,
            $reason
        );

        $this->flushCache($institute->id);
        $this->flushFeatureCache($institute->id);
    }

    public function setPackageModules(SubscriptionPackage $package, array $moduleKeys): void
    {
        DB::transaction(function () use ($package, $moduleKeys) {
            PackageModule::where('package_id', $package->id)->delete();

            $records = array_map(fn ($key) => [
                'package_id' => $package->id,
                'module_key' => $key,
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], $moduleKeys);

            if (! empty($records)) {
                PackageModule::insert($records);
            }
        });

        $instituteIds = Institute::where('package_id', $package->id)->pluck('id')->toArray();
        foreach ($instituteIds as $id) {
            $this->flushCache($id);
            $this->flushFeatureCache($id);
        }
    }

    public function checkDependencies(string $moduleKey, array $enabledModules): array
    {
        $module = ModuleRegistry::where('key', $moduleKey)->first();

        if (! $module || empty($module->dependencies)) {
            return [];
        }

        $dependencies = $module->dependencies;

        return array_values(array_diff($dependencies, $enabledModules));
    }

    public function changePackage(Institute $institute, ?int $oldPackageId, ?int $newPackageId, ?int $actorId): void
    {
        $newModules = [];
        if ($newPackageId) {
            $newModules = PackageModule::where('package_id', $newPackageId)
                ->where('enabled', true)
                ->pluck('module_key')
                ->toArray();
        }

        $oldModules = [];
        if ($oldPackageId) {
            $oldModules = PackageModule::where('package_id', $oldPackageId)
                ->where('enabled', true)
                ->pluck('module_key')
                ->toArray();
        }

        $added = array_diff($newModules, $oldModules);
        $removed = array_diff($oldModules, $newModules);

        foreach ($added as $moduleKey) {
            $this->logAccess(
                $institute->id,
                $moduleKey,
                'package_added',
                $actorId,
                null,
                'enabled',
                $newPackageId,
                "Module added via package change from {$oldPackageId} to {$newPackageId}"
            );
        }

        foreach ($removed as $moduleKey) {
            $this->logAccess(
                $institute->id,
                $moduleKey,
                'package_removed',
                $actorId,
                'enabled',
                null,
                $newPackageId,
                "Module removed via package change from {$oldPackageId} to {$newPackageId}"
            );
        }

        $this->archiveOverrides($institute, 'package_change', $actorId, $oldPackageId, $newPackageId);

        $this->flushCache($institute->id);
        $this->flushFeatureCache($institute->id);
    }

    public function resolveEnabled(Institute $institute): array
    {
        $allModules = ModuleRegistry::all()->keyBy('key');
        $industry = $institute->industry ?? 'real_estate';
        $config = $this->getIndustryConfig($industry);

        // Step 1: Core modules (always enabled)
        $candidate = [];
        foreach ($this->getCoreModules() as $key) {
            $candidate[$key] = true;
        }

        // Step 2: Industry default modules
        foreach ($this->resolveIndustryDefaults($industry) as $key) {
            $candidate[$key] = true;
        }

        // Layer 3: Sub-category defaults (mandatory + default)
        foreach ($this->resolveSubCategoryModules($institute) as $key) {
            $candidate[$key] = true;
        }

        // Step 3: Package modules
        foreach ($this->resolvePackageModules($institute) as $key) {
            $candidate[$key] = true;
        }

        $overrides = InstituteModuleOverride::where('institute_id', $institute->id)
            ->get()
            ->keyBy('module_key');

        // Step 4: Tenant overrides — optional modules may be toggled on;
        // any override also applies (enable adds / disable removes).
        // Package × industry matrix wins over an enable: Settings renders
        // everything outside that set as "Upgrade required" and refuses the
        // toggle, so an override must not silently keep it alive.
        $matrixModules = $this->industryMatrixModuleSet($institute);

        foreach ($this->resolveIndustryOptional($industry) as $key) {
            if (isset($overrides[$key]) && $overrides[$key]->enabled
                && ($matrixModules === null || isset($matrixModules[$key]))) {
                $candidate[$key] = true;
            }
        }

        foreach ($overrides as $key => $override) {
            if ($override->enabled) {
                if ($matrixModules !== null && ! isset($matrixModules[$key])) {
                    continue;
                }
                $candidate[$key] = true;
            } else {
                unset($candidate[$key]);
            }
        }

        // Step 5: Entitlements (grant/deny)
        foreach ($this->getActiveEntitlementMap($institute) as $key => $entitlement) {
            if ($entitlement->is_grant) {
                $candidate[$key] = true;
            } else {
                unset($candidate[$key]);
            }
        }

        // Step 6: Remove industry-disabled modules
        foreach ($this->resolveIndustryDisabled($industry) as $key) {
            unset($candidate[$key]);
        }

        // Layer 6.5: Super Admin emergency overrides (time-limited) — added
        // AFTER every denial so only a live super_admin_overrides row wins.
        $superAdminOverrides = $this->getSuperAdminOverrides($institute);
        foreach ($superAdminOverrides as $key) {
            $candidate[$key] = true;
        }

        // Layers 7–10: industry boundary (HARD), country filter (HARD),
        // dependency closure, parent gate. Super Admin overridden keys bypass
        // the hard boundaries and the parent gate (emergency only).
        $result = [];
        foreach ($allModules as $key => $module) {
            $finalState = isset($candidate[$key]);
            $bypassHard = in_array($key, $superAdminOverrides, true);

            if ($finalState && ! $bypassHard && ! $this->isIndustryCompatible($institute, $key)) {
                $finalState = false;
            }

            if ($finalState && ! $bypassHard && ! $this->isCountryTaxAllowed($key, $institute)) {
                $finalState = false;
            }

            $missingDeps = $this->checkDependencies($key, array_keys(array_filter($result)));
            if (! empty($missingDeps)) {
                $finalState = false;
            }

            if ($finalState && ! $bypassHard && ! empty($module->parent_key)) {
                $parentEnabled = $result[$module->parent_key] ?? false;
                if (! $parentEnabled) {
                    $finalState = false;
                }
            }

            $result[$key] = $finalState;
        }

        return $result;
    }

    /**
     * Layer 6.5 — active Super Admin emergency overrides for a tenant.
     *
     * Only non-expired rows from super_admin_overrides are returned; each
     * key bypasses Layers 7/8 (hard boundaries) and the parent gate.
     *
     * @return array<int, string>
     */
    protected function getSuperAdminOverrides(Institute $institute): array
    {
        if (! Schema::hasTable('super_admin_overrides')) {
            return [];
        }

        return DB::table('super_admin_overrides')
            ->where('institute_id', $institute->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->pluck('module_key')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get industry module config
     */
    protected function getIndustryConfig(string $industry): array
    {
        return config("industry-modules.{$industry}") ?? [];
    }

    /**
     * Get core modules (always available)
     */
    protected function getCoreModules(): array
    {
        return config('industry-modules.core', []);
    }

    /**
     * Check if module is core
     */
    public function isCoreModule(string $moduleKey): bool
    {
        return in_array($moduleKey, $this->getCoreModules(), true);
    }

    /**
     * Resolve industry default modules
     */
    protected function resolveIndustryDefaults(string $industry): array
    {
        return $this->getIndustryConfig($industry)['default'] ?? [];
    }

    /**
     * Resolve industry optional modules
     */
    protected function resolveIndustryOptional(string $industry): array
    {
        return $this->getIndustryConfig($industry)['optional'] ?? [];
    }

    /**
     * Resolve industry disabled modules
     */
    protected function resolveIndustryDisabled(string $industry): array
    {
        return $this->getIndustryConfig($industry)['disabled'] ?? [];
    }

    /**
     * Phase 10 — Module resolution with structured decision reasons.
     *
     * Same logic as resolveEnabled() but also returns per-module denial reasons.
     * Used by computeFeatureAccessMapWithMeta() to annotate feature decisions.
     *
     * Every module gets a reason (the LAST gate that determined the final state).
     *
     * @return array{map: array<string, bool>, reasons: array<string, string>}
     */
    public function resolveEnabledWithReasons(Institute $institute): array
    {
        $allModules = ModuleRegistry::all()->keyBy('key');
        $industry = $institute->industry ?? 'real_estate';
        $config = $this->getIndustryConfig($industry);

        $candidate = [];
        foreach ($this->getCoreModules() as $key) {
            $candidate[$key] = true;
        }
        foreach ($this->resolveIndustryDefaults($industry) as $key) {
            $candidate[$key] = true;
        }
        foreach ($this->resolveSubCategoryModules($institute) as $key) {
            $candidate[$key] = true;
        }
        foreach ($this->resolvePackageModules($institute) as $key) {
            $candidate[$key] = true;
        }

        $overrides = InstituteModuleOverride::where('institute_id', $institute->id)
            ->get()
            ->keyBy('module_key');

        $matrixModules = $this->industryMatrixModuleSet($institute);

        foreach ($this->resolveIndustryOptional($industry) as $key) {
            if (isset($overrides[$key]) && $overrides[$key]->enabled
                && ($matrixModules === null || isset($matrixModules[$key]))) {
                $candidate[$key] = true;
            }
        }

        foreach ($overrides as $key => $override) {
            if ($override->enabled) {
                if ($matrixModules !== null && ! isset($matrixModules[$key])) {
                    continue;
                }
                $candidate[$key] = true;
            } else {
                unset($candidate[$key]);
            }
        }

        $entitlementMap = $this->getActiveEntitlementMap($institute);
        foreach ($entitlementMap as $key => $ent) {
            if ($ent->is_grant) {
                $candidate[$key] = true;
            } else {
                unset($candidate[$key]);
            }
        }

        foreach ($this->resolveIndustryDisabled($industry) as $key) {
            unset($candidate[$key]);
        }

        // Layer 6.5: Super Admin emergency overrides (mirrors resolveEnabled)
        $superAdminOverrides = $this->getSuperAdminOverrides($institute);
        foreach ($superAdminOverrides as $key) {
            $candidate[$key] = true;
        }

        $result = [];
        $reasons = [];

        foreach ($allModules as $key => $module) {
            $inCandidate = isset($candidate[$key]);
            $finalState = $inCandidate;
            $bypassHard = in_array($key, $superAdminOverrides, true);

            if (! $inCandidate) {
                $reasons[$key] = AccessDecisionContext::REASON_PACKAGE_FEATURE_NOT_ENTITLED;
            } elseif (isset($overrides[$key])) {
                $reasons[$key] = $overrides[$key]->enabled
                    ? AccessDecisionContext::REASON_OVERRIDE_ENABLED
                    : AccessDecisionContext::REASON_OVERRIDE_DISABLED;
            } elseif (in_array($key, $this->resolveIndustryDisabled($industry), true)) {
                $reasons[$key] = AccessDecisionContext::REASON_INDUSTRY_VETO;
            } elseif (isset($entitlementMap[$key])) {
                $reasons[$key] = $entitlementMap[$key]->is_grant
                    ? AccessDecisionContext::REASON_GRANT_APPLIED
                    : AccessDecisionContext::REASON_DENIAL_APPLIED;
            } else {
                $reasons[$key] = AccessDecisionContext::REASON_PACKAGE_INCLUDED;
            }

            if (isset($entitlementMap[$key])) {
                $finalState = (bool) $entitlementMap[$key]->is_grant;
                $reasons[$key] = $entitlementMap[$key]->is_grant
                    ? AccessDecisionContext::REASON_GRANT_APPLIED
                    : AccessDecisionContext::REASON_DENIAL_APPLIED;
            }

            if ($finalState && ! $bypassHard && ! $this->isIndustryCompatible($institute, $key)) {
                $finalState = false;
                $reasons[$key] = AccessDecisionContext::REASON_INDUSTRY_VETO;
            }

            if ($finalState && ! $bypassHard && ! $this->isCountryTaxAllowed($key, $institute)) {
                $finalState = false;
                $reasons[$key] = AccessDecisionContext::REASON_COUNTRY_VETO;
            }

            $missingDeps = $this->checkDependencies($key, array_keys(array_filter($result)));
            if (! empty($missingDeps)) {
                $finalState = false;
                $reasons[$key] = AccessDecisionContext::REASON_DEPENDENCY_DISABLED;
            }

            if ($finalState && ! $bypassHard && ! empty($module->parent_key)) {
                $parentEnabled = $result[$module->parent_key] ?? false;
                if (! $parentEnabled) {
                    $finalState = false;
                    $reasons[$key] = AccessDecisionContext::REASON_PARENT_DISABLED;
                }
            }

            $result[$key] = $finalState;
        }

        return ['map' => $result, 'reasons' => $reasons];
    }

    /**
     * Active entitlement map for an institute.
     * Deterministic: latest updated_at wins, deny wins on tie.
     */
    protected function getActiveEntitlementMap(Institute $institute): array
    {
        $now = Carbon::now();
        $all = InstituteModuleEntitlement::where('institute_id', $institute->id)
            ->whereIn('status', ['active', 'trialing'])
            ->get();

        $active = [];
        foreach ($all as $ent) {
            if (! $this->isEntitlementActive($ent, $now)) {
                continue;
            }
            $key = $ent->module_key;
            if (! isset($active[$key])) {
                $active[$key] = $ent;
            } else {
                $existing = $active[$key];
                // Latest updated_at wins; on tie, deny (is_grant=false) wins
                $entTime = $ent->updated_at ?? $ent->created_at;
                $existTime = $existing->updated_at ?? $existing->created_at;
                if ($entTime->gt($existTime) || ($entTime->eq($existTime) && ! $ent->is_grant && $existing->is_grant)) {
                    $active[$key] = $ent;
                }
            }
        }

        return $active;
    }

    /**
     * Active rule per spec 63B §2.
     */
    protected function isEntitlementActive(InstituteModuleEntitlement $ent, ?Carbon $now = null): bool
    {
        $now = $now ?? Carbon::now();

        // Soft-deleted already excluded by Eloquent; status must be active or trialing
        if ($ent->status === 'revoked' || $ent->status === 'expired' || $ent->status === 'pending') {
            return false;
        }

        if ($ent->status === 'trialing') {
            if ($ent->trial_starts_at && $now->lt($ent->trial_starts_at)) {
                return false;
            }
            if ($ent->trial_ends_at && $now->gt($ent->trial_ends_at)) {
                return false;
            }

            return true;
        }

        if ($ent->status === 'active') {
            if ($ent->starts_at && $now->lt($ent->starts_at)) {
                return false;
            }
            if ($ent->ends_at && $now->gt($ent->ends_at)) {
                return false;
            }
            // Also respect trial window if present on active (defensive)
            if ($ent->trial_starts_at && $now->lt($ent->trial_starts_at)) {
                // trial not started yet — still active if main window open
            }
            if ($ent->trial_ends_at && $now->gt($ent->trial_ends_at) && $ent->trial_ends_at !== null) {
                // trial ended but status still active — consider still active if main window open
                // Spec says trialing status handles trial; active should ignore trial window
            }

            return true;
        }

        return false;
    }

    /**
     * Industry compatibility gate for industry-scoped modules.
     *
     * The map is module key → owning industry, so a direct lookup finds the
     * required industry for a given module key. Non-industry modules
     * (finance, crm, ...) have no entry and are always compatible.
     */
    public function isIndustryCompatible(Institute $institute, string $moduleKey): bool
    {
        $industry = $institute->industry ?? null;

        // Map module keys to industries they are compatible with
        $moduleIndustryMap = [
            'education' => 'education',
            'medical' => 'healthcare',
            'training_center' => 'training_center',
            'restaurant' => 'restaurant',
            'real_estate' => 'real_estate',
            'manufacturing' => 'manufacturing',
            'retail' => 'retail',
        ];

        // Resolve child modules to their parent for map lookup
        // e.g. 'education.classes' → 'education', 'medical.opd' → 'medical'
        $rootKey = explode('.', $moduleKey, 2)[0];

        if (isset($moduleIndustryMap[$rootKey])) {
            return $moduleIndustryMap[$rootKey] === $industry;
        }

        // For modules that are not industry-specific, they are compatible by default
        return true;
    }

    /**
     * Layer 3 — sub-category default modules (mandatory + default).
     *
     * Reads industry_subcategories + subcategory_default_modules (Phase 1 tables).
     * Returns [] when the institute has no subcategory_key or no matching row.
     *
     * @return array<int, string>
     */
    protected function resolveSubCategoryModules(Institute $institute): array
    {
        if (! $institute->subcategory_key) {
            return [];
        }

        $subModules = app(IndustrySubcategoryService::class)->getModules(
            $institute->industry ?? '',
            $institute->subcategory_key
        );

        return array_merge($subModules['mandatory'] ?? [], $subModules['default'] ?? []);
    }

    /**
     * Layer 8 — HARD country tax boundary (per module key). PUBLIC so the
     * admin UI can show/validate the same boundary the resolver enforces.
     *
     * Only tax modules are filtered (vat, gst, sales_tax, pst, tds); every other
     * module passes. Source of truth: country_tax_modules table, with a built-in
     * fallback map while that table is unseeded (Phase 3).
     *
     * CANNOT be bypassed by admin/tenant overrides or entitlements — applied
     * after those layers in both resolve pipelines.
     */
    public function isCountryTaxAllowed(string $moduleKey, Institute $institute): bool
    {
        static $allTaxModules = ['vat', 'gst', 'sales_tax', 'pst', 'tds'];

        if (! in_array($moduleKey, $allTaxModules, true)) {
            return true;
        }

        $countryCode = $institute->country_code ?: 'BD';

        $allowed = $this->allowedTaxModulesForCountry($countryCode);

        return in_array($moduleKey, $allowed, true);
    }

    /**
     * Layer 8 — allowed tax modules for a country (DB first, config fallback).
     *
     * Memoized per service instance (not static) so test transactions and
     * long-lived processes never observe stale table data.
     *
     * @return array<int, string>
     */
    protected function allowedTaxModulesForCountry(string $countryCode): array
    {
        if (array_key_exists($countryCode, $this->countryTaxMemo)) {
            return $this->countryTaxMemo[$countryCode];
        }

        $allowed = DB::table('country_tax_modules')
            ->where('country_code', $countryCode)
            ->where('is_active', true)
            ->pluck('tax_module')
            ->all();

        if (empty($allowed)) {
            $allowed = [
                'BD' => ['vat', 'tds'],
                'IN' => ['gst', 'tds'],
                'US' => ['sales_tax'],
                'GB' => ['vat', 'tds'],
            ][$countryCode] ?? ['vat', 'tds'];
        }

        return $this->countryTaxMemo[$countryCode] = $allowed;
    }

    /**
     * Layer 8 — HARD country filter over a module list (roadmap signature).
     *
     * @param  array<int, string>  $modules
     * @return array<int, string>
     */
    protected function applyCountryFilterHard(array $modules, Institute $institute): array
    {
        return array_values(array_filter(
            $modules,
            fn ($module) => $this->isCountryTaxAllowed($module, $institute)
        ));
    }

    public function logAccess(
        int $instituteId,
        string $moduleKey,
        string $action,
        ?int $actorId,
        ?string $previousState,
        ?string $newState,
        ?int $packageId,
        ?string $notes,
        ?string $actorType = null,
        ?string $reason = null,
        ?string $featureKey = null,
        ?string $decision = null,
        ?string $requestId = null,
        ?string $riskLevel = null,
    ): void {
        $resolvedType = $actorType ?? $this->resolveActorType();

        $payload = [
            'institute_id' => $instituteId,
            'module_key' => $moduleKey,
            'action' => $action,
            'actor_id' => $actorId,
            'actor_type' => $resolvedType,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'package_id' => $packageId,
            'notes' => $notes,
            'reason' => $reason,
            'feature_key' => $featureKey,
            'decision' => $decision,
            'request_id' => $requestId,
        ];

        // Phase 6 — only write risk_level when explicitly provided AND the
        // column exists (pre-migration environments stay safe).
        if ($riskLevel !== null && Schema::hasColumn('module_access_logs', 'risk_level')) {
            $payload['risk_level'] = $riskLevel;
        }

        ModuleAccessLog::create($payload);
    }

    private function resolveActorType(): ?string
    {
        foreach (['platform_admin', 'institute_user', 'web', 'guardian', 'platform_staff'] as $guard) {
            if (auth()->guard($guard)->check()) {
                return $guard;
            }
        }

        return app()->runningInConsole() ? 'system' : null;
    }

    public function flushCache(int $instituteId): void
    {
        Cache::forget($this->cachePrefix.$instituteId);
    }

    private function archiveOverrides(
        Institute $institute,
        string $archiveReason,
        ?int $actorId = null,
        ?int $oldPackageId = null,
        ?int $newPackageId = null,
    ): int {
        $rows = InstituteModuleOverride::where('institute_id', $institute->id)->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $now = now();
        $archiveRows = [];
        foreach ($rows as $row) {
            $archiveRows[] = [
                'original_id' => $row->id,
                'institute_id' => $row->institute_id,
                'module_key' => $row->module_key,
                'enabled' => $row->enabled,
                'overridden_by' => $row->overridden_by,
                'reason' => $row->reason,
                'archived_at' => $now,
                'archived_by' => $actorId,
                'archive_reason' => $archiveReason,
                'old_package_id' => $oldPackageId,
                'new_package_id' => $newPackageId,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        DB::transaction(function () use ($institute, $archiveRows) {
            DB::table('institute_module_overrides_archive')->insert($archiveRows);
            InstituteModuleOverride::where('institute_id', $institute->id)->delete();
        });

        return count($archiveRows);
    }

    public function removeOverride(Institute $institute, string $moduleKey): void
    {
        InstituteModuleOverride::where('institute_id', $institute->id)
            ->where('module_key', $moduleKey)
            ->delete();

        $this->flushCache($institute->id);
        $this->flushFeatureCache($institute->id);
    }

    /**
     * Grant or deny a module via individual entitlement (63B).
     * Validates module exists, respects industry, creates entitlement row.
     */
    public function grantModule(Institute $institute, string $moduleKey, array $attributes = [], $actorId = null): InstituteModuleEntitlement
    {
        $module = ModuleRegistry::where('key', $moduleKey)->first();
        if (! $module) {
            throw ValidationException::withMessages(['module_key' => "Module {$moduleKey} does not exist."]);
        }

        $previousState = $this->isEnabled($institute, $moduleKey) ? 'enabled' : 'disabled';

        $data = array_merge([
            'institute_id' => $institute->id,
            'module_key' => $moduleKey,
            'status' => $attributes['status'] ?? 'active',
            'is_grant' => $attributes['is_grant'] ?? true,
            'starts_at' => $attributes['starts_at'] ?? null,
            'ends_at' => $attributes['ends_at'] ?? null,
            'trial_starts_at' => $attributes['trial_starts_at'] ?? null,
            'trial_ends_at' => $attributes['trial_ends_at'] ?? null,
            'monthly_price' => $attributes['monthly_price'] ?? null,
            'yearly_price' => $attributes['yearly_price'] ?? null,
            'billing_cycle' => $attributes['billing_cycle'] ?? null,
            'auto_renew' => $attributes['auto_renew'] ?? false,
            'discount_percent' => $attributes['discount_percent'] ?? null,
            'purchased_by' => $attributes['purchased_by'] ?? null,
            'granted_by' => $actorId,
            'notes' => $attributes['notes'] ?? null,
        ], []);

        // Normalize booleans for DB
        $data['is_grant'] = (bool) $data['is_grant'];
        $data['auto_renew'] = (bool) $data['auto_renew'];

        $entitlement = InstituteModuleEntitlement::create($data);

        // Audit: distinguish trial lifecycle
        if ($entitlement->status === 'trialing') {
            $this->logAccess(
                $institute->id,
                $moduleKey,
                'trial_started',
                $actorId,
                $previousState,
                'trialing',
                $institute->package_id,
                $attributes['notes'] ?? null
            );
        } else {
            $this->logAccess(
                $institute->id,
                $moduleKey,
                $entitlement->is_grant ? 'entitlement_granted' : 'entitlement_denied',
                $actorId,
                $previousState,
                $entitlement->is_grant ? 'enabled' : 'disabled',
                $institute->package_id,
                $attributes['notes'] ?? null
            );
        }

        $this->flushCache($institute->id);
        $this->flushFeatureCache($institute->id);

        return $entitlement;
    }

    public function revokeModule(Institute $institute, string $moduleKey, $actorId = null): void
    {
        $previousState = $this->isEnabled($institute, $moduleKey) ? 'enabled' : 'disabled';

        $entitlements = InstituteModuleEntitlement::where('institute_id', $institute->id)
            ->where('module_key', $moduleKey)
            ->whereIn('status', ['active', 'trialing', 'pending'])
            ->get();

        foreach ($entitlements as $ent) {
            $ent->update(['status' => 'revoked']);
            $ent->delete();
        }

        $this->logAccess(
            $institute->id,
            $moduleKey,
            'entitlement_revoked',
            $actorId,
            $previousState,
            'disabled',
            $institute->package_id,
            null
        );

        $this->flushCache($institute->id);
        $this->flushFeatureCache($institute->id);
    }

    /**
     * Extend an existing entitlement's expiry (Super Admin Business Profile → Extend).
     * Must go through service — not direct model update — to enforce industry, flush cache, audit.
     */
    public function extendEntitlement(InstituteModuleEntitlement $entitlement, array $attributes, ?int $actorId = null): InstituteModuleEntitlement
    {
        // Ensure entitlement belongs to institute context is validated by caller; re-check institute compatibility
        $institute = $entitlement->institute;
        if (! $institute) {
            $institute = Institute::withoutGlobalScopes()->find($entitlement->institute_id);
        }
        // Industry still enforced
        if (! $this->isIndustryCompatible($institute, $entitlement->module_key)) {
            throw ValidationException::withMessages(['module_key' => 'Module is incompatible with institute industry.']);
        }

        // Prevent duplicate active entitlements race — use transaction with lock
        return DB::transaction(function () use ($entitlement, $attributes, $actorId, $institute) {
            $entitlement->lockForUpdate();
            $previousEnds = $entitlement->ends_at ? $entitlement->ends_at->copy() : null;
            $previousTrialEnds = $entitlement->trial_ends_at ? $entitlement->trial_ends_at->copy() : null;

            $data = [];
            if (array_key_exists('ends_at', $attributes)) {
                $data['ends_at'] = $attributes['ends_at'];
            }
            if (array_key_exists('trial_ends_at', $attributes)) {
                $data['trial_ends_at'] = $attributes['trial_ends_at'];
            }
            if (array_key_exists('monthly_price', $attributes)) {
                $data['monthly_price'] = $attributes['monthly_price'];
            }
            if (array_key_exists('yearly_price', $attributes)) {
                $data['yearly_price'] = $attributes['yearly_price'];
            }
            if (array_key_exists('billing_cycle', $attributes)) {
                $data['billing_cycle'] = $attributes['billing_cycle'];
            }
            if (array_key_exists('discount_percent', $attributes)) {
                $data['discount_percent'] = $attributes['discount_percent'];
            }
            if (array_key_exists('notes', $attributes)) {
                $data['notes'] = $attributes['notes'];
            }

            // If status was expired/pending but extending should reactivate? Keep original status unless explicitly extending expiry
            // For simplicity, if entitlement was expired, set to active
            if (in_array($entitlement->status, ['expired', 'pending'], true) && isset($data['ends_at']) && $data['ends_at'] && Carbon::parse($data['ends_at'])->isFuture()) {
                $data['status'] = 'active';
            }

            $entitlement->update($data);

            // Audit
            $previousState = $previousEnds ? $previousEnds->format('Y-m-d') : ($previousTrialEnds ? $previousTrialEnds->format('Y-m-d') : $entitlement->status);
            $newState = $entitlement->ends_at ? $entitlement->ends_at->format('Y-m-d') : ($entitlement->trial_ends_at ? $entitlement->trial_ends_at->format('Y-m-d') : $entitlement->status);
            $this->logAccess(
                $entitlement->institute_id,
                $entitlement->module_key,
                'entitlement_extended',
                $actorId,
                (string) $previousEnds,
                (string) $entitlement->ends_at,
                $institute->package_id,
                $attributes['notes'] ?? 'Extended via Super Admin Business Profile'
            );

            $this->flushCache($entitlement->institute_id);
            $this->flushFeatureCache($entitlement->institute_id);

            return $entitlement->fresh();
        });
    }

    public function isEntitled(Institute $institute, string $moduleKey): bool
    {
        $map = $this->getActiveEntitlementMap($institute);

        return isset($map[$moduleKey]) && $map[$moduleKey]->is_grant;
    }

    /**
     * Fail-closed subscription check (SEC-01).
     *
     * Authoritative columns (institute_subscriptions table only):
     *   - status: ENUM('active','expired','cancelled') — only 'active' counts.
     *   - end_date: DATE — must be NULL or >= today.
     *
     * Notes:
     *   - 'trialing' does not exist at subscription level (the ENUM has no such
     *     value; trial exists only at entitlement level / billing_cycle='trial'),
     *     so it is never treated as active here.
     *   - No institutes-table columns are read here; the subscription row above
     *     is the single source of truth.
     *   - Fail-closed: missing row, non-'active' status, past end_date, or ANY
     *     exception returns FALSE, so resolveEnabled() falls back to FREE.
     */
    private function isSubscriptionActive(Institute $institute): bool
    {
        try {
            $sub = DB::table('institute_subscriptions')->where('institute_id', $institute->id)->orderByDesc('id')->first();
            if (! $sub) {
                return false;
            }
            if (($sub->status ?? null) !== 'active') {
                return false;
            }
            $endDate = $sub->end_date ?? null;
            if ($endDate !== null && $endDate !== '' && Carbon::parse((string) $endDate)->startOfDay()->lt(Carbon::today())) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * Trial access (package trial days).
     *
     * While an institute holds an active trial subscription
     * (billing_cycle='trial', status='active', end_date NULL/future) for a
     * tier package offered in its industry, it resolves modules/features
     * from the trial package — but institutes.package_id stays FREE, so
     * billing/entitlement remains free. Expired trials fall back to FREE.
     */
    public function activeTrialPackageId(Institute $institute): ?int
    {
        try {
            $trial = DB::table('institute_subscriptions')
                ->where('institute_id', $institute->id)
                ->where('billing_cycle', 'trial')
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first();

            if (! $trial || ! $trial->package_id) {
                return null;
            }

            $endDate = $trial->end_date ?? null;
            if ($endDate !== null && $endDate !== '' && Carbon::parse((string) $endDate)->startOfDay()->lt(Carbon::today())) {
                return null;
            }

            $package = SubscriptionPackage::find($trial->package_id);
            if (! $package || ($package->status ?? null) !== 'active' || $this->isFreePackage((int) $package->id)) {
                return null;
            }

            $industryKey = $institute->industry ?? '';
            if ($industryKey === '') {
                return null;
            }

            $map = $this->industryPackageMap($industryKey);
            if ($map === null || ! isset($map[$package->id])) {
                return null;
            }

            return (int) $package->id;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Days left on the running trial, null when no trial is active.
     */
    public function trialDaysLeft(Institute $institute): ?int
    {
        try {
            $trial = DB::table('institute_subscriptions')
                ->where('institute_id', $institute->id)
                ->where('billing_cycle', 'trial')
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first();

            if (! $trial || $this->activeTrialPackageId($institute) === null) {
                return null;
            }

            $endDate = $trial->end_date ?? null;
            if ($endDate === null || $endDate === '') {
                return null;
            }

            return (int) floor((strtotime((string) $endDate) - strtotime(date('Y-m-d'))) / 86400);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Start a trial subscription for a tier package.
     *
     * Window comes from package_industries.trial_days (per industry).
     * The institute's package_id is untouched (stays FREE) — access
     * resolves from the trial package via activeTrialPackageId().
     *
     * @throws ValidationException
     */
    public function startTrial(Institute $institute, int $packageId, ?int $actorId = null): object
    {
        $package = SubscriptionPackage::find($packageId);
        if (! $package || ($package->status ?? null) !== 'active') {
            throw ValidationException::withMessages(['package_id' => 'Selected package is not available.']);
        }
        if ($this->isFreePackage($packageId)) {
            throw ValidationException::withMessages(['package_id' => 'FREE package does not need a trial.']);
        }

        $industryKey = $institute->industry ?? '';
        if ($industryKey === '') {
            throw ValidationException::withMessages(['industry' => 'Institute industry is not set.']);
        }

        $map = $this->industryPackageMap($industryKey);
        if ($map === null || ! isset($map[$packageId])) {
            throw ValidationException::withMessages(['package_id' => 'Package is not offered in this industry.']);
        }

        $trialDays = $this->packageTrialDays($packageId, $institute);

        if ($trialDays === null || (int) $trialDays <= 0) {
            throw ValidationException::withMessages(['package_id' => 'Trial is not offered for this package.']);
        }

        $id = DB::table('institute_subscriptions')->insertGetId([
            'institute_id' => $institute->id,
            'package_id' => $packageId,
            'billing_cycle' => 'trial',
            'price_paid' => 0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays((int) $trialDays)->toDateString(),
            'payment_reference' => 'trial',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $this->logAccess(
            $institute->id,
            $package->slug,
            'trial_started',
            $actorId,
            null,
            'trialing',
            $institute->package_id,
            "Trial started for {$package->slug} ({$trialDays} days)"
        );

        $this->flushCache($institute->id);
        $this->flushFeatureCache($institute->id);

        return DB::table('institute_subscriptions')->where('id', $id)->first();
    }

    /**
     * Effective package id for feature resolution: trial package wins
     * while a trial runs and the institute itself is entitled as FREE.
     */
    private function effectiveFeaturePackageId(Institute $institute): ?int
    {
        $packageId = $institute->package_id;
        if ($packageId === null) {
            $packageId = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->value('id');
        }

        if ($packageId !== null && $this->isFreePackage((int) $packageId)) {
            $trialPackageId = $this->activeTrialPackageId($institute);
            if ($trialPackageId !== null) {
                return $trialPackageId;
            }
        }

        return $packageId !== null ? (int) $packageId : null;
    }

    private function isFreePackage(int $packageId): bool
    {
        $freeId = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->value('id');

        return $freeId !== null && (int) $freeId === $packageId;
    }

    /**
     * Get all active sub-modules for a given parent key, ordered by sort_order.
     */
    public function getSubModules(string $parentKey): Collection
    {
        return ModuleRegistry::where('parent_key', $parentKey)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Shorthand for medical sub-modules.
     */
    public function getMedicalSubModules(): Collection
    {
        return $this->getSubModules('medical');
    }

    /**
     * Check if a specific feature is enabled for an institute.
     *
     * featureKey format: '<module>.<capability>'
     *   e.g. 'medical.pharmacy', 'medical.laboratory'
     *
     * Resolution chain (Phase 5a — feature entitlement only):
     *   1. Parent module must be enabled (existing isEnabled).
     *   2. Feature must exist in feature_registry with
     *      status='active'.
     *   3. Package must have the feature enabled in
     *      package_features.
     *
     * Fail-closed: any missing row → false.
     *
     * NOTE: This method does NOT enforce anything yet. It
     * is a pure query. Phase 5b middleware will call it.
     *
     * NOTE: PlatformAdmin bypass is NOT in this method — it
     * belongs in the middleware layer (consistent with
     * existing CheckModuleAccess / MedicalModuleAccess).
     *
     * @param  string  $featureKey  e.g. 'medical.pharmacy'
     */
    public function isFeatureEnabled(Institute $institute, string $featureKey): bool
    {
        if ($featureKey === '' || ! str_contains($featureKey, '.')) {
            return false;
        }

        $map = $this->computeFeatureAccessMap($institute);

        return $map[$featureKey] ?? false;
    }

    /**
     * Get the full feature-access map for an institute, cached.
     *
     * Use this in controllers/views for batch feature lookups.
     * isFeatureEnabled() uses the uncached version for backward compatibility.
     *
     * @return array<string, bool>
     */
    public function getFeatureAccessMap(Institute $institute): array
    {
        $scope = $this->resolveScopedPackage($institute);
        $scopeHash = $scope?->scope_hash ?? 'global';
        $cacheKey = $this->featureCachePrefix.$institute->id.':'.$scopeHash;

        return Cache::remember($cacheKey, 3600, function () use ($institute) {
            return $this->computeFeatureAccessMap($institute);
        });
    }

    /**
     * Compute the full feature-access map for an institute.
     *
     * For each feature in feature_registry:
     *   Gate 1: parent module enabled (isEnabled — includes industry veto)
     *   Gate 2: feature registry active
     *   Gate 3: package_feature enabled
     *   Gate 3.5: institute_feature_override wins
     *   Gate 4: tenant_access_grants (additive, after overrides,
     *           B76: respects Gate 1 — a grant never enables a feature
     *           whose parent module is disabled)
     *   Gate 5: tenant_access_denials (subtractive, wins over everything)
     *
     * Resolution: (Base ∪ Overrides ∪ Grants) − Denials.
     * Only rows with status='active' and expires_at NULL/future apply.
     * Tier grants (B75) resolve tier slug → tier package features
     * (scope-aware with legacy fallback); unknown tier slugs log
     * a warning and have no effect. Unknown feature keys not in
     * the registry are skipped (Gate 2 preserved — never invented).
     *
     * B77: empty scoped feature set falls back to legacy
     * package_features (documented limitation — scope is additive;
     * there is no "explicitly empty" scope).
     *
     * @return array<string, bool>
     */
    private function computeFeatureAccessMap(Institute $institute): array
    {
        return $this->computeFeatureAccessMapWithMeta($institute)['map'];
    }

    /**
     * B100: single computation returning the feature-access map PLUS
     * the meta counts, derived from the SAME loaded grant/denial
     * collections that Gate 4 / Gate 5 evaluate (no duplicate queries,
     * no drift between the map and the counts).
     *
     * Phase 10: also returns per-feature denial reasons for observability.
     *
     * Uncached by design: meta must reflect current rows. Callers that
     * need the cached map keep using getFeatureAccessMap() (cache shape
     * unchanged); callers that need map + meta use
     * getFeatureAccessMapWithMeta().
     *
     * Gate order, resolution formula and fail-closed rules are identical
     * to computeFeatureAccessMap() — see its docblock.
     *
     * @return array{map: array<string, bool>, grants_applied: int, denials_applied: int, reasons: array<string, string>}
     */
    private function computeFeatureAccessMapWithMeta(Institute $institute): array
    {
        $allFeatures = FeatureRegistry::where('status', 'active')
            ->orderBy('module_key')
            ->orderBy('sort_order')
            ->get();

        $scope = $this->resolveScopedPackage($institute);

        $packageFeatureKeys = [];
        if ($scope) {
            $scopedKeys = $this->getScopedFeatureKeys($scope);
            if (! empty($scopedKeys)) {
                $packageId = $this->effectiveFeaturePackageId($institute);
                if ($packageId !== null) {
                    $pkgKeys = PackageFeature::where('package_id', $packageId)
                        ->where('enabled', true)
                        ->pluck('feature_key')
                        ->flip()
                        ->keys()
                        ->all();
                    $packageFeatureKeys = array_values(array_intersect($scopedKeys, $pkgKeys));
                } else {
                    $packageFeatureKeys = $scopedKeys;
                }
            }
        }

        if (empty($packageFeatureKeys)) {
            $packageId = $this->effectiveFeaturePackageId($institute);

            if ($packageId !== null) {
                $packageFeatureKeys = PackageFeature::where('package_id', $packageId)
                    ->where('enabled', true)
                    ->pluck('feature_key')
                    ->flip()
                    ->keys()
                    ->all();
            }
        }

        $overrides = InstituteFeatureOverride::where('institute_id', $institute->id)
            ->get()
            ->keyBy('feature_key');

        // Tenant module toggles (Settings → Modules) keyed by module_key.
        // A feature whose key equals a module key (e.g. medical.bloodbank)
        // must not outlive its own module switch.
        $moduleOverrides = InstituteModuleOverride::where('institute_id', $institute->id)
            ->get()
            ->keyBy('module_key');

        $map = [];
        $reasons = [];

        // Phase 10: use resolveEnabledWithReasons to get module-level reasons
        $moduleResolution = $this->resolveEnabledWithReasons($institute);

        foreach ($allFeatures as $feature) {
            $key = $feature->feature_key;
            $moduleKey = explode('.', $key, 2)[0];

            // Gate 1: parent module must be enabled
            if (! $this->isEnabled($institute, $moduleKey)) {
                $map[$key] = false;
                $reasons[$key] = $moduleResolution['reasons'][$moduleKey] ?? AccessDecisionContext::REASON_MODULE_NOT_ENABLED;

                continue;
            }

            // Gate 1.5: an explicit tenant module toggle on this exact key
            // denies the feature (additive grants below may still restore it).
            if (isset($moduleOverrides[$key]) && ! (bool) $moduleOverrides[$key]->enabled) {
                $map[$key] = false;
                $reasons[$key] = AccessDecisionContext::REASON_MODULE_NOT_ENABLED;

                continue;
            }

            // Gate 3: package-level enabled state
            $packageEnabled = in_array($key, $packageFeatureKeys, true);

            // Gate 3.5: institute-level override wins
            if ($overrides->has($key)) {
                $overrideVal = (bool) $overrides->get($key)->enabled;
                $map[$key] = $overrideVal;
                $reasons[$key] = $overrideVal
                    ? AccessDecisionContext::REASON_OVERRIDE_ENABLED
                    : AccessDecisionContext::REASON_OVERRIDE_DISABLED;

                continue;
            }

            $map[$key] = $packageEnabled;
            if (! $packageEnabled) {
                $reasons[$key] = AccessDecisionContext::REASON_PACKAGE_FEATURE_NOT_ENTITLED;
            } else {
                $reasons[$key] = AccessDecisionContext::REASON_PACKAGE_INCLUDED;
            }
        }

        // Gate 4: Super admin grants (additive — applied after package + institute overrides)
        $grants = TenantAccessGrant::where('institute_id', $institute->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        foreach ($grants as $grant) {
            if ($grant->grant_type === 'feature') {
                // B76: respect Gate 1 — parent module must be enabled.
                $moduleKey = explode('.', (string) $grant->grant_key, 2)[0];
                if (! $this->isEnabled($institute, $moduleKey)) {
                    continue;
                }
                if (array_key_exists($grant->grant_key, $map)) {
                    $map[$grant->grant_key] = true;
                    $reasons[$grant->grant_key] = AccessDecisionContext::REASON_GRANT_APPLIED;
                }
            } elseif ($grant->grant_type === 'module') {
                // B76: respect Gate 1 — the granted module itself must be
                // enabled for this institute (industry veto included).
                if (! $this->isEnabled($institute, (string) $grant->grant_key)) {
                    continue;
                }
                // All features in this module
                $moduleFeatures = FeatureRegistry::where('module_key', $grant->grant_key)
                    ->pluck('feature_key');
                foreach ($moduleFeatures as $fk) {
                    if (array_key_exists($fk, $map)) {
                        $map[$fk] = true;
                        $reasons[$fk] = AccessDecisionContext::REASON_GRANT_APPLIED;
                    }
                }
            } elseif ($grant->grant_type === 'tier') {
                // B75: tier grant = unlock all features the tier package
                // provides (scope-aware, legacy fallback). Additive, Gate 2
                // preserved (unknown keys skipped), B76: each feature still
                // requires its parent module to be enabled.
                $tierPackage = SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower((string) $grant->grant_key)])->first();
                if (! $tierPackage) {
                    Log::warning('TenantAccessGrant tier grant skipped: unknown tier package', [
                        'institute_id' => $institute->id,
                        'grant_id' => $grant->id,
                        'grant_key' => $grant->grant_key,
                    ]);

                    continue;
                }

                foreach ($this->getTierFeatureKeys($institute, $tierPackage) as $fk) {
                    if (! array_key_exists($fk, $map)) {
                        continue;
                    }
                    $parentModule = explode('.', (string) $fk, 2)[0];
                    if (! $this->isEnabled($institute, $parentModule)) {
                        continue;
                    }
                    $map[$fk] = true;
                    $reasons[$fk] = AccessDecisionContext::REASON_TIER_GRANT_APPLIED;
                }
            }
        }

        // Gate 5: Super admin denials (subtractive — applied LAST, wins over everything)
        $denials = TenantAccessDenial::where('institute_id', $institute->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        foreach ($denials as $denial) {
            if ($denial->deny_type === 'feature') {
                if (array_key_exists($denial->deny_key, $map)) {
                    $map[$denial->deny_key] = false;
                    $reasons[$denial->deny_key] = AccessDecisionContext::REASON_DENIAL_APPLIED;
                }
            } elseif ($denial->deny_type === 'module') {
                $moduleFeatures = FeatureRegistry::where('module_key', $denial->deny_key)
                    ->pluck('feature_key');
                foreach ($moduleFeatures as $fk) {
                    if (array_key_exists($fk, $map)) {
                        $map[$fk] = false;
                        $reasons[$fk] = AccessDecisionContext::REASON_DENIAL_APPLIED;
                    }
                }
            }
        }

        // B100: meta counts come from the SAME loaded collections Gate 4
        // / Gate 5 just evaluated — these queries already filter to
        // status='active' and unexpired rows, so the counts are exactly
        // the applicable rows by construction.
        return [
            'map' => $map,
            'grants_applied' => $grants->count(),
            'denials_applied' => $denials->count(),
            'reasons' => $reasons,
        ];
    }

    /**
     * B100: map + meta in a single computation (uncached — meta must
     * reflect current rows). The cached map path is untouched:
     * getFeatureAccessMap() keeps its key, TTL and shape.
     *
     * Phase 10: also returns per-feature denial reasons.
     *
     * @return array{map: array<string, bool>, grants_applied: int, denials_applied: int, reasons: array<string, string>}
     */
    public function getFeatureAccessMapWithMeta(Institute $institute): array
    {
        return $this->computeFeatureAccessMapWithMeta($institute);
    }

    /**
     * Flush the feature-access cache for an institute.
     *
     * getFeatureAccessMap() keys include the scope hash
     * (feature_access:{id}:{scopeHash}), so every scope variant for the
     * institute's package must be forgotten — otherwise package changes
     * leave stale entitlement maps behind.
     */
    public function flushFeatureCache(int $instituteId): void
    {
        Cache::forget($this->featureCachePrefix.$instituteId);
        // Also flush scope-aware keys (wildcard not possible, but GLOBAL is common)
        Cache::forget($this->featureCachePrefix.$instituteId.':global');

        try {
            $institute = Institute::withoutGlobalScopes()->find($instituteId);
            $packageId = $institute?->package_id
                ?? SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->value('id');

            if ($packageId !== null) {
                $hashes = PackageScope::where('package_id', $packageId)
                    ->pluck('scope_hash')
                    ->unique();
                foreach ($hashes as $hash) {
                    Cache::forget($this->featureCachePrefix.$instituteId.':'.$hash);
                }
            }
        } catch (\Throwable $e) {
            // Cache hygiene must never break the write path.
        }
    }

    /**
     * Resolve the effective package scope for an institute.
     * Walk fallback chain: exact → broader → GLOBAL.
     *
     * Returns the most specific PackageScope row, or null
     * if no scope exists (fail-closed).
     */
    public function resolveScopedPackage(Institute $institute): ?PackageScope
    {
        $packageId = $institute->package_id;
        if ($packageId === null) {
            $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->first();
            $packageId = $free?->id;
        }
        if ($packageId === null) {
            return null;
        }

        $countryId = $institute->country_id;
        $industryId = $institute->industry_id;
        $subIndustryId = $institute->sub_industry_id;

        // B101: [null, null, S] (sub-only) sits between the
        // industry-level candidates and GLOBAL, so sub-only scopes are
        // reachable for country-carrying institutes. Purely additive:
        // every pre-existing candidate keeps its position, and when the
        // sub id is null the new row dedup-collapses into GLOBAL below.
        $candidates = [
            [$countryId, $industryId, $subIndustryId],
            [$countryId, $industryId, null],
            [$countryId, null, $subIndustryId],
            [$countryId, null, null],
            [null, $industryId, $subIndustryId],
            [null, $industryId, null],
            [null, null, $subIndustryId],
            [null, null, null],
        ];

        $seen = [];
        $unique = [];
        foreach ($candidates as $c) {
            $key = implode('|', array_map(fn ($v) => $v ?? 'G', $c));
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $c;
            }
        }

        foreach ($unique as [$cid, $iid, $sid]) {
            $scope = PackageScope::where('package_id', $packageId)
                ->where('country_id', $cid)
                ->where('industry_id', $iid)
                ->where('sub_industry_id', $sid)
                ->where('status', 'active')
                ->first();

            if ($scope) {
                return $scope;
            }
        }

        return null;
    }

    /**
     * Resolve the effective package scope for an institute against an
     * explicit tier package (B75 — tier grants).
     *
     * Same fallback chain as resolveScopedPackage() (exact → broader →
     * GLOBAL, B101 sub-only step included) but keyed on the tier
     * package id, using the institute's own country/industry/sub-industry
     * for scope matching.
     *
     * Returns null when no scope row exists (caller falls back to
     * legacy package_features).
     */
    public function resolveScopedPackageForPackage(Institute $institute, SubscriptionPackage $tierPackage): ?PackageScope
    {
        $countryId = $institute->country_id;
        $industryId = $institute->industry_id;
        $subIndustryId = $institute->sub_industry_id;

        // B101: kept in lockstep with resolveScopedPackage().
        $candidates = [
            [$countryId, $industryId, $subIndustryId],
            [$countryId, $industryId, null],
            [$countryId, null, $subIndustryId],
            [$countryId, null, null],
            [null, $industryId, $subIndustryId],
            [null, $industryId, null],
            [null, null, $subIndustryId],
            [null, null, null],
        ];

        $seen = [];
        $unique = [];
        foreach ($candidates as $c) {
            $key = implode('|', array_map(fn ($v) => $v ?? 'G', $c));
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $c;
            }
        }

        foreach ($unique as [$cid, $iid, $sid]) {
            $scope = PackageScope::where('package_id', $tierPackage->id)
                ->where('country_id', $cid)
                ->where('industry_id', $iid)
                ->where('sub_industry_id', $sid)
                ->where('status', 'active')
                ->first();

            if ($scope) {
                return $scope;
            }
        }

        return null;
    }

    /**
     * All feature keys a tier package provides for an institute (B75).
     *
     * Scope-aware: when a scope resolves for (tier package × institute
     * locale) and holds scoped features, those win (with parent
     * inheritance). Otherwise legacy package_features for the tier
     * package is the source of truth.
     *
     * @return array<int, string>
     */
    public function getTierFeatureKeys(Institute $institute, SubscriptionPackage $tierPackage): array
    {
        $scope = $this->resolveScopedPackageForPackage($institute, $tierPackage);

        if ($scope) {
            $scoped = $this->getScopedFeatureKeys($scope);
            if (! empty($scoped)) {
                return $scoped;
            }
        }

        return PackageFeature::where('package_id', $tierPackage->id)
            ->where('enabled', true)
            ->pluck('feature_key')
            ->toArray();
    }

    /**
     * Get all enabled feature keys for a scope, with inherit-from-parent logic.
     */
    private function getScopedFeatureKeys(PackageScope $scope): array
    {
        $directFeatures = PackageScopedFeature::where('package_scope_id', $scope->id)
            ->where('enabled', true)
            ->pluck('feature_key')
            ->toArray();

        if (! $scope->inherit_from_parent) {
            return $directFeatures;
        }

        $parent = $this->resolveParentScope($scope);
        if (! $parent) {
            return $directFeatures;
        }

        $parentFeatures = $this->getScopedFeatureKeys($parent);

        $disabled = PackageScopedFeature::where('package_scope_id', $scope->id)
            ->where('enabled', false)
            ->pluck('feature_key')
            ->toArray();

        return array_values(array_diff(
            array_unique(array_merge($parentFeatures, $directFeatures)),
            $disabled
        ));
    }

    /**
     * Get all enabled module keys for a scope, with inherit-from-parent logic.
     * Mirrors getScopedFeatureKeys() (Phase 6 — module configuration scope).
     */
    private function getScopedModuleKeys(PackageScope $scope): array
    {
        $directModules = PackageScopedModule::where('package_scope_id', $scope->id)
            ->where('enabled', true)
            ->pluck('module_key')
            ->toArray();

        if (! $scope->inherit_from_parent) {
            return $directModules;
        }

        $parent = $this->resolveParentScope($scope);
        if (! $parent) {
            return $directModules;
        }

        $parentModules = $this->getScopedModuleKeys($parent);

        $disabled = PackageScopedModule::where('package_scope_id', $scope->id)
            ->where('enabled', false)
            ->pluck('module_key')
            ->toArray();

        return array_values(array_diff(
            array_unique(array_merge($parentModules, $directModules)),
            $disabled
        ));
    }

    /**
     * Package id used for package-layer resolution: lapsed or untitled
     * tenants fall back to FREE, and a running trial wins while the institute
     * itself is entitled as FREE.
     */
    private function packageIdForResolution(Institute $institute): ?int
    {
        $packageId = $institute->package_id;
        if (! $this->isSubscriptionActive($institute) || $packageId === null) {
            $packageId = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->value('id');
        }

        if ($packageId === null) {
            return null;
        }

        // Trial: a tenant entitled as FREE resolves tier modules while an
        // active trial subscription runs (package_id itself stays FREE).
        if ($this->isFreePackage($packageId)) {
            $trialPackageId = $this->activeTrialPackageId($institute);
            if ($trialPackageId !== null) {
                return $trialPackageId;
            }
        }

        return (int) $packageId;
    }

    /**
     * Package × industry matrix as a lookup set (module_key => true).
     *
     * Null when the industry/package has no explicit matrix configuration,
     * so tenants without a matrix keep the legacy behaviour. Mirrors the
     * package-layer resolution used by resolvePackageModules(), which is what
     * Settings renders: everything outside this set reads as "Upgrade
     * required" there and cannot be tenant-enabled.
     *
     * @return array<string, bool>|null
     */
    private function industryMatrixModuleSet(Institute $institute): ?array
    {
        $industryKey = $institute->industry ?? '';
        if ($industryKey === '') {
            return null;
        }

        $packageId = $this->packageIdForResolution($institute);
        if ($packageId === null) {
            return null;
        }

        $modules = $this->resolveIndustryPackageModules(
            $this->applyIndustryPackageAllowList($packageId, $industryKey),
            $industryKey
        );

        return $modules === null ? null : array_fill_keys($modules, true);
    }

    /**
     * Resolve the package-level module base for an institute (Phase 6).
     *
     * Dual-read with legacy fallback:
     *   - Effective package: institute package when subscription active,
     *     FREE package when subscription inactive/expired or package null
     *     (FREE fallback behavior unchanged).
     *   - When a scope resolves for the effective package and holds
     *     scoped modules, those win; otherwise legacy package_modules.
     *
     * @return array<int, string>
     */
    private function resolvePackageModules(Institute $institute): array
    {
        $packageId = $this->packageIdForResolution($institute);

        if ($packageId === null) {
            return [];
        }

        // Per-industry package configuration (package_industries +
        // package_industry_modules). Only kicks in once an industry has been
        // configured; unconfigured industries keep the legacy behaviour below.
        $industryKey = $institute->industry ?? '';
        if ($industryKey !== '') {
            $packageId = $this->applyIndustryPackageAllowList($packageId, $industryKey);

            $industryModules = $this->resolveIndustryPackageModules($packageId, $industryKey);
            if ($industryModules !== null) {
                return $industryModules;
            }
        }

        // Resolve scope for the EFFECTIVE package (not necessarily the
        // institute's own package_id, which may be a lapsed paid tier).
        $probe = clone $institute;
        $probe->setAttribute('package_id', $packageId);
        $scope = $this->resolveScopedPackage($probe);

        if ($scope) {
            $scopedModules = $this->getScopedModuleKeys($scope);
            if (! empty($scopedModules)) {
                return $scopedModules;
            }
        }

        // Legacy fallback — package_modules stays the source of truth
        // until scoped rows exist for the effective scope.
        return PackageModule::where('package_id', $packageId)
            ->where('enabled', true)
            ->pluck('module_key')
            ->toArray();
    }

    /**
     * Canonical industry key for package-layer lookups.
     *
     * Tenant-facing industry is 'healthcare' (institutes.industry,
     * config/industry-modules.php, industry_subcategories), while the
     * package layer historically uses 'medical' (package_industries,
     * package_industry_modules, IndustryPackageModuleMap, module keys
     * medical.*). Both spellings exist in DB/tests, so every lookup below
     * dual-reads both keys and unions them — no rename needed.
     */
    private function normalizeIndustryKey(string $industryKey): string
    {
        return $industryKey === 'healthcare' ? 'medical' : $industryKey;
    }

    /**
     * All DB keys to check for an industry (raw + canonical alias, deduped).
     *
     * @return array<int, string>
     */
    private function industryLookupKeys(string $industryKey): array
    {
        return array_values(array_unique([$industryKey, $this->normalizeIndustryKey($industryKey)]));
    }

    /**
     * Per-industry package allow-list (package_industries).
     *
     * When the industry has at least one mapped package, a tenant holding a
     * package that is NOT offered there falls back to the FREE package.
     * Industries without any mapping keep their current behaviour.
     */
    private function applyIndustryPackageAllowList(int $packageId, string $industryKey): int
    {
        $map = $this->industryPackageMap($industryKey);
        if ($map === null || isset($map[$packageId])) {
            return $packageId;
        }

        $freeId = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->value('id');

        return $freeId !== null ? (int) $freeId : $packageId;
    }

    /**
     * Active package ids offered in an industry, or null when the industry
     * has no package_industries configuration at all.
     *
     * @return array<int, bool>|null
     */
    private function industryPackageMap(string $industryKey): ?array
    {
        $memoKey = $this->normalizeIndustryKey($industryKey);
        if (array_key_exists($memoKey, $this->industryPackageMapMemo)) {
            return $this->industryPackageMapMemo[$memoKey];
        }

        if (! Schema::hasTable('package_industries')) {
            return $this->industryPackageMapMemo[$memoKey] = null;
        }

        $ids = DB::table('package_industries')
            ->whereIn('industry_key', $this->industryLookupKeys($industryKey))
            ->where('is_active', true)
            ->pluck('package_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // No configuration for this industry → do not gate anything.
        if ($ids === []) {
            return $this->industryPackageMapMemo[$memoKey] = null;
        }

        return $this->industryPackageMapMemo[$memoKey] = array_fill_keys($ids, true);
    }

    /**
     * Explicit per-industry module set for a package, or null when the
     * package has no custom module configuration for this industry
     * (legacy package_modules / scoped modules then apply).
     *
     * @return array<int, string>|null
     */
    private function resolveIndustryPackageModules(int $packageId, string $industryKey): ?array
    {
        $memoKey = $packageId.'|'.$this->normalizeIndustryKey($industryKey);
        if (array_key_exists($memoKey, $this->industryPackageModulesMemo)) {
            return $this->industryPackageModulesMemo[$memoKey];
        }

        if (! Schema::hasTable('package_industry_modules')) {
            return $this->industryPackageModulesMemo[$memoKey] = null;
        }

        // Configuration only applies while the package is offered in the industry.
        $map = $this->industryPackageMap($industryKey);
        if ($map === null || ! isset($map[$packageId])) {
            return $this->industryPackageModulesMemo[$memoKey] = null;
        }

        $rows = DB::table('package_industry_modules')
            ->where('package_id', $packageId)
            ->whereIn('industry_key', $this->industryLookupKeys($industryKey))
            ->get();

        // No explicit configuration yet → legacy package_modules apply.
        if ($rows->isEmpty()) {
            return $this->industryPackageModulesMemo[$memoKey] = null;
        }

        $modules = [];
        foreach ($rows as $row) {
            // Category wins when present (mandatory/default = on;
            // optional/hidden = off). Null category = legacy boolean.
            $category = $row->category ?? null;
            if ($category !== null) {
                if (in_array($category, ['mandatory', 'default'], true)) {
                    $modules[] = $row->module_key;
                }

                continue;
            }
            if ($row->enabled) {
                $modules[] = $row->module_key;
            }
        }

        return $this->industryPackageModulesMemo[$memoKey] = $modules;
    }

    /**
     * Upgrade-locked modules for a package × industry matrix page.
     *
     * A module is locked when it is OFF here (category optional/hidden or
     * missing) but ON (mandatory/default) in a HIGHER-tier package of the
     * same industry. Tier rank comes from the package slug suffix:
     * free 0 < starter|basic 1 < growth|advanced 2 < enterprise|premium 3.
     * Purely derived — nothing is persisted.
     *
     * @return array<string, array<int, string>> module_key => unlocking package slugs
     */
    public function upgradeUnlockedBy(int $packageId, string $industryKey): array
    {
        $package = SubscriptionPackage::find($packageId);
        if (! $package) {
            return [];
        }
        $myRank = self::packageTierRank($package->slug);

        $slugs = DB::table('subscription_packages')->pluck('slug', 'id')->all();

        $higherIds = [];
        foreach ($slugs as $id => $slug) {
            if ((int) $id !== $packageId && self::packageTierRank($slug) > $myRank) {
                $higherIds[] = (int) $id;
            }
        }
        if ($higherIds === []) {
            return [];
        }

        $industryKeys = $this->industryLookupKeys($industryKey);

        $onHere = DB::table('package_industry_modules')
            ->where('package_id', $packageId)
            ->whereIn('industry_key', $industryKeys)
            ->whereIn('category', ['mandatory', 'default'])
            ->pluck('module_key')
            ->flip();

        $rows = DB::table('package_industry_modules')
            ->whereIn('package_id', $higherIds)
            ->whereIn('industry_key', $industryKeys)
            ->whereIn('category', ['mandatory', 'default'])
            ->select('package_id', 'module_key')
            ->get();

        $locked = [];
        foreach ($rows as $row) {
            if (isset($onHere[$row->module_key])) {
                continue;
            }
            $locked[$row->module_key][] = $slugs[$row->package_id] ?? ('#'.$row->package_id);
        }
        foreach ($locked as &$list) {
            $list = array_values(array_unique($list));
            sort($list);
        }
        unset($list);

        ksort($locked);

        return $locked;
    }

    public static function packageTierRank(string $slug): int
    {
        $s = strtolower($slug);
        if (str_ends_with($s, 'enterprise') || str_ends_with($s, 'premium')) {
            return 3;
        }
        if (str_ends_with($s, 'growth') || str_ends_with($s, 'advanced')) {
            return 2;
        }
        if (str_ends_with($s, 'starter') || str_ends_with($s, 'basic')) {
            return 1;
        }

        return 0;
    }

    /**
     * Industry-matrix category for one package × industry × module.
     *
     * Returns null when the matrix has no explicit configuration for this
     * package × industry (legacy boolean behavior applies) or the module
     * has no row. Otherwise one of mandatory|default|optional|hidden.
     */
    public function industryMatrixCategory(int $packageId, string $industryKey, string $moduleKey): ?string
    {
        if (! Schema::hasTable('package_industry_modules')) {
            return null;
        }

        $category = DB::table('package_industry_modules')
            ->where('package_id', $packageId)
            ->whereIn('industry_key', $this->industryLookupKeys($industryKey))
            ->where('module_key', $moduleKey)
            ->value('category');

        return is_string($category) && $category !== '' ? $category : null;
    }

    /**
     * Whether the package × industry matrix is explicitly configured
     * (any rows at all). Unconfigured industries keep legacy behavior.
     */
    public function hasIndustryMatrix(int $packageId, string $industryKey): bool
    {
        if (! Schema::hasTable('package_industry_modules')) {
            return false;
        }

        return DB::table('package_industry_modules')
            ->where('package_id', $packageId)
            ->whereIn('industry_key', $this->industryLookupKeys($industryKey))
            ->exists();
    }

    /**
     * Walk one level up the scope hierarchy.
     */
    public function resolveParentScope(PackageScope $scope): ?PackageScope
    {
        $cid = $scope->country_id;
        $iid = $scope->industry_id;
        $sid = $scope->sub_industry_id;

        $candidates = [];
        if ($sid !== null) {
            $candidates[] = [$cid, $iid, null];
            $candidates[] = [$cid, null, null];
            $candidates[] = [null, $iid, null];
            $candidates[] = [null, null, null];
        } elseif ($iid !== null) {
            $candidates[] = [$cid, null, null];
            $candidates[] = [null, null, null];
        } elseif ($cid !== null) {
            $candidates[] = [null, null, null];
        }

        foreach ($candidates as [$c, $i, $s]) {
            $parent = PackageScope::where('package_id', $scope->package_id)
                ->where('country_id', $c)
                ->where('industry_id', $i)
                ->where('sub_industry_id', $s)
                ->where('status', 'active')
                ->first();
            if ($parent) {
                return $parent;
            }
        }

        return null;
    }

    /**
     * Ensure a scope row exists for the institute's current
     * (package, country, industry, sub-industry) combination.
     * Creates one inheriting from parent if missing.
     * Idempotent.
     */
    public function ensureScopeExistsForInstitute(Institute $institute): ?PackageScope
    {
        $packageId = $institute->package_id
            ?? SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->value('id');
        if (! $packageId) {
            return null;
        }

        $existing = PackageScope::where('package_id', $packageId)
            ->where('country_id', $institute->country_id)
            ->where('industry_id', $institute->industry_id)
            ->where('sub_industry_id', $institute->sub_industry_id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($institute, $packageId) {
            $scope = PackageScope::create([
                'package_id' => $packageId,
                'country_id' => $institute->country_id,
                'industry_id' => $institute->industry_id,
                'sub_industry_id' => $institute->sub_industry_id,
                'inherit_from_parent' => true,
                'status' => 'active',
            ]);

            if ($scope->country_id || $scope->industry_id || $scope->sub_industry_id) {
                $parent = $this->resolveParentScope($scope);
                if ($parent) {
                    $parentFeatures = PackageScopedFeature::where(
                        'package_scope_id', $parent->id
                    )->get();
                    foreach ($parentFeatures as $pf) {
                        PackageScopedFeature::create([
                            'package_scope_id' => $scope->id,
                            'feature_key' => $pf->feature_key,
                            'enabled' => $pf->enabled,
                        ]);
                    }

                    // Phase 6: also copy parent scope's modules (not just features)
                    $parentModules = PackageScopedModule::where(
                        'package_scope_id', $parent->id
                    )->get();
                    foreach ($parentModules as $pm) {
                        PackageScopedModule::create([
                            'package_scope_id' => $scope->id,
                            'module_key' => $pm->module_key,
                            'enabled' => $pm->enabled,
                        ]);
                    }
                }
            }

            return $scope;
        });
    }

    /**
     * Resolve the effective price for an institute.
     * Uses scope fallback chain. Returns array:
     *   ['monthly' => float, 'yearly' => float, 'currency' => string]
     */
    public function resolveScopedPrice(Institute $institute): array
    {
        $industryPrice = $this->resolveIndustryPackagePrice($institute);
        if ($industryPrice !== null) {
            return $industryPrice;
        }

        $scope = $this->resolveScopedPackage($institute);
        if (! $scope) {
            $package = SubscriptionPackage::find($institute->package_id);

            return [
                'monthly' => (float) ($package?->price_monthly ?? 0),
                'yearly' => (float) ($package?->price_yearly ?? 0),
                // 9b-3: fallback via locale config (default 'BDT', unchanged).
                'currency' => config('locale.currency.default_code', 'BDT'),
            ];
        }

        return [
            'monthly' => $scope->effectiveMonthlyPrice(),
            'yearly' => $scope->effectiveYearlyPrice(),
            // 9b-3: fallback via locale config (default 'BDT', unchanged).
            'currency' => $scope->effectiveCurrency() ?? config('locale.currency.default_code', 'BDT'),
        ];
    }

    /**
     * Per-industry price override from package_industries, when the industry
     * configures a price for the institute's package.
     *
     * @return array{monthly: float, yearly: float, currency: string}|null
     */
    private function resolveIndustryPackagePrice(Institute $institute): ?array
    {
        $packageId = $institute->package_id;
        $industryKey = $institute->industry ?? '';

        if ($packageId === null || $industryKey === '') {
            return null;
        }

        $map = $this->industryPackageMap($industryKey);
        if ($map === null || ! isset($map[$packageId])) {
            return null;
        }

        if (! Schema::hasTable('package_industries')) {
            return null;
        }

        $rows = DB::table('package_industries')
            ->where('package_id', $packageId)
            ->whereIn('industry_key', $this->industryLookupKeys($industryKey))
            ->get();

        $row = $rows->first(fn ($r) => $r->price_monthly !== null || $r->price_yearly !== null);

        if ($row === null
            || ($row->price_monthly === null && $row->price_yearly === null)) {
            return null;
        }

        $package = SubscriptionPackage::find($packageId);

        $monthly = (float) ($row->price_monthly ?? $package?->price_monthly ?? 0);
        $yearly = (float) ($row->price_yearly ?? $package?->price_yearly ?? 0);
        $currency = $row->currency ?? config('locale.currency.default_code', 'BDT');

        // Country scope wins over the industry row: country price, then
        // country discount, then country trial context (each falls back
        // to the industry value when the country row is missing/empty).
        $countryRow = $this->countryPriceRow($packageId, $institute);
        if ($countryRow) {
            if ($countryRow->price_monthly !== null) {
                $monthly = (float) $countryRow->price_monthly;
            }
            if ($countryRow->price_yearly !== null) {
                $yearly = (float) $countryRow->price_yearly;
            }
            if (! empty($countryRow->currency_code)) {
                $currency = $countryRow->currency_code;
            }
        }

        // Discount: country first, then industry. Active iff percent > 0
        // and not expired; expired/missing rows fall back to full price.
        $discount = 0.0;
        $endsAt = null;
        if ($countryRow && isset($countryRow->discount_percent) && (float) $countryRow->discount_percent > 0) {
            $discount = (float) $countryRow->discount_percent;
            $endsAt = $countryRow->discount_ends_at ?? null;
        } elseif (isset($row->discount_percent)) {
            $discount = (float) $row->discount_percent;
            $endsAt = $row->discount_ends_at ?? null;
        }

        if ($discount > 0 && ($endsAt === null || $endsAt === '' || strtotime((string) $endsAt) >= strtotime(date('Y-m-d')))) {
            $factor = max(0, 1 - $discount / 100);
            $monthly = round($monthly * $factor, 2);
            $yearly = round($yearly * $factor, 2);
        }

        return [
            'monthly' => $monthly,
            'yearly' => $yearly,
            'currency' => $currency,
        ];
    }

    /**
     * Institute's country ISO2 (country_id → countries.iso2, fallback to
     * country name via country_currency_map). Null when unresolvable.
     */
    public function instituteCountryCode(Institute $institute): ?string
    {
        try {
            if ($institute->country_id) {
                $iso = DB::table('countries')->where('id', $institute->country_id)->value('iso2');
                if ($iso) {
                    return (string) $iso;
                }
            }

            $name = trim((string) ($institute->country ?? ''));
            if ($name !== '') {
                $code = DB::table('country_currency_map')->where('country_name', $name)->value('country_code');
                if ($code) {
                    return (string) $code;
                }
            }
        } catch (\Throwable $e) {
            // fail-closed to null (caller falls back to industry values)
        }

        return null;
    }

    /**
     * Active country price row for a package + institute, or null.
     */
    private function countryPriceRow(int $packageId, Institute $institute): ?object
    {
        try {
            if (! Schema::hasTable('package_country_prices')) {
                return null;
            }

            $code = $this->instituteCountryCode($institute);
            if ($code === null) {
                return null;
            }

            $row = DB::table('package_country_prices')
                ->where('package_id', $packageId)
                ->where('country_code', $code)
                ->where('is_active', true)
                ->first();

            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Trial window for a package: country row first, then industry row.
     *
     * Null = inherit from the next level (country → industry → none).
     * 0 = explicitly blocked at that level (does NOT fall through).
     * Positive = trial offered for that many days.
     */
    public function packageTrialDays(int $packageId, Institute $institute): ?int
    {
        $industryKey = $institute->industry ?? '';

        if (Schema::hasTable('package_country_prices')
            && Schema::hasColumn('package_country_prices', 'trial_days')) {
            $countryRow = $this->countryPriceRow($packageId, $institute);
            if ($countryRow && isset($countryRow->trial_days) && $countryRow->trial_days !== null) {
                return (int) $countryRow->trial_days;
            }
        }

        if ($industryKey !== '' && Schema::hasTable('package_industries') && Schema::hasColumn('package_industries', 'trial_days')) {
            $days = DB::table('package_industries')
                ->where('package_id', $packageId)
                ->whereIn('industry_key', $this->industryLookupKeys($industryKey))
                ->whereNotNull('trial_days')
                ->orderByDesc('trial_days')
                ->value('trial_days');

            if ($days !== null) {
                return (int) $days;
            }
        }

        return null;
    }

    /**
     * Flush feature-access cache for all institutes matching a scope.
     */
    public function flushFeatureCacheForScope(PackageScope $scope): void
    {
        $query = Institute::where('package_id', $scope->package_id);
        if ($scope->country_id !== null) {
            $query->where('country_id', $scope->country_id);
        }
        if ($scope->industry_id !== null) {
            $query->where('industry_id', $scope->industry_id);
        }
        if ($scope->sub_industry_id !== null) {
            $query->where('sub_industry_id', $scope->sub_industry_id);
        }

        $query->pluck('id')->each(function ($id) use ($scope) {
            $this->flushFeatureCache($id);
            Cache::forget($this->featureCachePrefix.$id.':'.$scope->scope_hash);
            Cache::forget($this->featureCachePrefix.$id.':global');
        });
    }

    /**
     * Flush module-access cache for all institutes matching a scope.
     * Call when PackageScopedModule rows change (Phase 6).
     */
    public function flushModuleCacheForScope(PackageScope $scope): void
    {
        $query = Institute::where('package_id', $scope->package_id);
        if ($scope->country_id !== null) {
            $query->where('country_id', $scope->country_id);
        }
        if ($scope->industry_id !== null) {
            $query->where('industry_id', $scope->industry_id);
        }
        if ($scope->sub_industry_id !== null) {
            $query->where('sub_industry_id', $scope->sub_industry_id);
        }

        $query->pluck('id')->each(function ($id) {
            $this->flushCache($id);
        });
    }
}
