<?php

namespace App\Services;

use App\Models\Institute;
use App\Models\InstituteModuleEntitlement;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleAccessLog;
use App\Models\ModuleRegistry;
use App\Models\PackageModule;
use App\Models\SubscriptionPackage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

    /**
     * For education industry these modules are disabled by default regardless of package.
     * Admin can re-enable via InstituteModuleOverride (enableModule) or entitlement grant.
     * See resolveEnabled() step 1b and isEducationIndustry().
     */
    protected const EDUCATION_DISABLED_MODULES = ['sales', 'purchase', 'hr', 'crm'];

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

        InstituteModuleOverride::where('institute_id', $institute->id)->delete();

        $this->flushCache($institute->id);
    }

    public function resolveEnabled(Institute $institute): array
    {
        $allModules = ModuleRegistry::all()->keyBy('key');

        // SaaS subscription enforcement — expired/cancelled falls back to FREE (P0 fix, Step 60)
        // Legacy institutes without package are also treated as FREE tier
        if (! $this->isSubscriptionActive($institute)) {
            $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->first();
            $packageModules = $free ? PackageModule::where('package_id', $free->id)->where('enabled', true)->pluck('module_key')->toArray() : [];
        } else {
            $packageModules = [];
            if ($institute->package_id) {
                $packageModules = PackageModule::where('package_id', $institute->package_id)
                    ->where('enabled', true)
                    ->pluck('module_key')
                    ->toArray();
            } else {
                // Legacy / null package => FREE base
                $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->first();
                $packageModules = $free ? PackageModule::where('package_id', $free->id)->where('enabled', true)->pluck('module_key')->toArray() : [];
            }
        }

        // Education industry: disable sales/purchase/hr/crm by default regardless of package.
        // Package base is filtered; override/entitlement can still re-enable (steps 2 & 3).
        if ($this->isEducationIndustry($institute)) {
            $packageModules = array_values(array_diff($packageModules, self::EDUCATION_DISABLED_MODULES));
        }

        $overrides = InstituteModuleOverride::where('institute_id', $institute->id)
            ->get()
            ->keyBy('module_key');

        // Individual entitlements (63A/63B) — active grants/denials, deterministic latest wins
        $entitlementMap = $this->getActiveEntitlementMap($institute);

        $result = [];

        foreach ($allModules as $key => $module) {
            // 1. Package base
            $baseState = in_array($key, $packageModules, true);

            // 2. Legacy permanent override
            if ($overrides->has($key)) {
                $override = $overrides->get($key);
                $finalState = (bool) $override->enabled;
            } else {
                $finalState = $baseState;
            }

            // 3. Active individual entitlement (grant/deny) — takes precedence over override
            if (isset($entitlementMap[$key])) {
                $ent = $entitlementMap[$key];
                $finalState = (bool) $ent->is_grant;
            }

            // 4. Industry compatibility — entitlements cannot bypass industry rules
            if ($finalState && ! $this->isIndustryCompatible($institute, $key)) {
                $finalState = false;
            }

            // 5. Dependency closure — preserve existing checkDependencies, run after entitlement
            $missingDeps = $this->checkDependencies($key, array_keys(array_filter($result)));
            if (! empty($missingDeps)) {
                $finalState = false;
            }

            $result[$key] = $finalState;
        }

        return $result;
    }

    /**
     * Build map of active entitlements for institute: module_key => latest active entitlement.
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

    protected function isIndustryCompatible(Institute $institute, string $moduleKey): bool
    {
        // Currently only education is industry-gated (CheckModuleAccess:53). Preserve hierarchy.
        // Do not invent new mappings; use existing industry_rules via CheckModuleAccess logic.
        if ($moduleKey === 'education' && $institute->industry !== null && $institute->industry !== 'education') {
            return false;
        }
        // All other modules are core (industry null) — compatible with any industry
        return true;
    }

    protected function isEducationIndustry(Institute $institute): bool
    {
        return ($institute->industry ?? null) === 'education';
    }

    public function logAccess(
        int $instituteId,
        string $moduleKey,
        string $action,
        ?int $actorId,
        ?string $previousState,
        ?string $newState,
        ?int $packageId,
        ?string $notes
    ): void {
        ModuleAccessLog::create([
            'institute_id' => $instituteId,
            'module_key' => $moduleKey,
            'action' => $action,
            'actor_id' => $actorId,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'package_id' => $packageId,
            'notes' => $notes,
        ]);
    }

    public function flushCache(int $instituteId): void
    {
        Cache::forget($this->cachePrefix.$instituteId);
    }

    public function removeOverride(Institute $institute, string $moduleKey): void
    {
        InstituteModuleOverride::where('institute_id', $institute->id)
            ->where('module_key', $moduleKey)
            ->delete();

        $this->flushCache($institute->id);
    }

    /**
     * Grant or deny a module via individual entitlement (63B).
     * Validates module exists, respects industry, creates entitlement row.
     */
    public function grantModule(Institute $institute, string $moduleKey, array $attributes = [], $actorId = null): InstituteModuleEntitlement
    {
        $module = ModuleRegistry::where('key', $moduleKey)->first();
        if (! $module) {
            throw \Illuminate\Validation\ValidationException::withMessages(['module_key' => "Module {$moduleKey} does not exist."]);
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
            throw \Illuminate\Validation\ValidationException::withMessages(['module_key' => 'Module is incompatible with institute industry.']);
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

            return $entitlement->fresh();
        });
    }

    public function isEntitled(Institute $institute, string $moduleKey): bool
    {
        $map = $this->getActiveEntitlementMap($institute);
        return isset($map[$moduleKey]) && $map[$moduleKey]->is_grant;
    }

    private function isSubscriptionActive(Institute $institute): bool
    {
        try {
            $sub = DB::table('institute_subscriptions')->where('institute_id', $institute->id)->orderByDesc('id')->first();
            if ($sub) {
                if (isset($sub->status) && $sub->status !== 'active' && $sub->status !== 'trialing') {
                    return false;
                }
                $expires = $sub->ends_at ?? $sub->expires_at ?? $sub->expired_at ?? null;
                if ($expires && strtotime((string) $expires) < time()) {
                    return false;
                }
            }
            // Fallback to institutes table columns if present
            if (isset($institute->subscription_status) && $institute->subscription_status && ! in_array($institute->subscription_status, ['active','trialing'], true)) {
                return false;
            }
            $exp = $institute->subscription_expires_at ?? $institute->expires_at ?? $institute->subscription_ends_at ?? null;
            if ($exp && strtotime((string) $exp) < time()) {
                return false;
            }
        } catch (\Throwable $e) {
            // Table/column missing — treat as active (graceful)
        }

        return true;
    }
}
