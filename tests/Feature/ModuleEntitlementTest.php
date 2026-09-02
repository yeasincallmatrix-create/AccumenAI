<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\ModuleRegistry;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModuleEntitlementTest extends TestCase
{
    use DatabaseTransactions;

    private function package(string $slug): SubscriptionPackage
    {
        return SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower($slug)])->firstOrFail();
    }

    private function institute(?string $packageSlug = null, ?string $industry = null, ?string $subIndustry = null): Institute
    {
        $pkg = $packageSlug ? $this->package($packageSlug) : null;
        return Institute::create([
            'name' => 'Ent Test '.uniqid(),
            'slug' => 'ent-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg?->id,
            'industry' => $industry,
            'sub_industry' => $subIndustry,
        ]);
    }

    public function test_free_plus_hr_grant_enabled(): void
    {
        $inst = $this->institute('FREE');
        $svc = app(ModuleAccessService::class);
        $this->assertFalse($svc->isEnabled($inst, 'hr'));
        $svc->grantModule($inst, 'hr', ['status' => 'active', 'is_grant' => true]);
        $this->assertTrue($svc->isEnabled($inst, 'hr'));
    }

    public function test_free_plus_crm_grant_enabled(): void
    {
        $inst = $this->institute('FREE');
        $svc = app(ModuleAccessService::class);
        // FREE already has crm, but grant also works
        $this->assertTrue($svc->isEnabled($inst, 'crm'));
        $svc->grantModule($inst, 'crm', ['status' => 'active', 'is_grant' => true]);
        $this->assertTrue($svc->isEnabled($inst, 'crm'));
    }

    public function test_premium_plus_crm_denial_disabled(): void
    {
        $inst = $this->institute('PREMIUM');
        $svc = app(ModuleAccessService::class);
        $this->assertTrue($svc->isEnabled($inst, 'crm'));
        $svc->grantModule($inst, 'crm', ['status' => 'active', 'is_grant' => false]);
        $this->assertFalse($svc->isEnabled($inst, 'crm'));
    }

    public function test_active_grant_enabled(): void
    {
        $inst = $this->institute('FREE');
        $svc = app(ModuleAccessService::class);
        $svc->grantModule($inst, 'sales', ['status' => 'active', 'is_grant' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->assertTrue($svc->isEnabled($inst, 'sales'));
    }

    public function test_future_grant_disabled(): void
    {
        $inst = $this->institute('FREE');
        $svc = app(ModuleAccessService::class);
        $svc->grantModule($inst, 'sales', ['status' => 'active', 'is_grant' => true, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
        $this->assertFalse($svc->isEnabled($inst, 'sales'));
    }

    public function test_expired_grant_disabled(): void
    {
        $inst = $this->institute('FREE');
        $svc = app(ModuleAccessService::class);
        $svc->grantModule($inst, 'sales', ['status' => 'active', 'is_grant' => true, 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
        $this->assertFalse($svc->isEnabled($inst, 'sales'));
    }

    public function test_revoked_grant_disabled(): void
    {
        $inst = $this->institute('FREE');
        $svc = app(ModuleAccessService::class);
        $ent = $svc->grantModule($inst, 'sales', ['status' => 'active', 'is_grant' => true]);
        $this->assertTrue($svc->isEnabled($inst, 'sales'));
        $svc->revokeModule($inst, 'sales');
        $this->assertFalse($svc->isEnabled($inst, 'sales'));
        $this->assertTrue($ent->fresh()->trashed() || $ent->fresh()->status === 'revoked');
    }

    public function test_trial_during_trial_enabled(): void
    {
        $inst = $this->institute('FREE');
        $svc = app(ModuleAccessService::class);
        $svc->grantModule($inst, 'hr', ['status' => 'trialing', 'is_grant' => true, 'trial_starts_at' => now()->subDay(), 'trial_ends_at' => now()->addDay()]);
        $this->assertTrue($svc->isEnabled($inst, 'hr'));
    }

    public function test_trial_after_trial_disabled(): void
    {
        $inst = $this->institute('FREE');
        $svc = app(ModuleAccessService::class);
        $svc->grantModule($inst, 'hr', ['status' => 'trialing', 'is_grant' => true, 'trial_starts_at' => now()->subDays(2), 'trial_ends_at' => now()->subDay()]);
        $this->assertFalse($svc->isEnabled($inst, 'hr'));
    }

    public function test_payroll_without_hr_disabled_dependency(): void
    {
        // Simulate dependency: make sales depends on inventory for test
        $sales = ModuleRegistry::where('key', 'sales')->first();
        $originalDeps = $sales->dependencies;
        $sales->update(['dependencies' => ['inventory']]);

        $inst = $this->institute('FREE'); // FREE has no inventory, no sales
        $svc = app(ModuleAccessService::class);
        // Grant sales without inventory -> should be disabled due to dependency
        $svc->grantModule($inst, 'sales', ['status' => 'active', 'is_grant' => true]);
        $this->assertFalse($svc->isEnabled($inst, 'sales'), 'sales without inventory should be disabled');

        // Grant inventory as well -> sales should be enabled
        $svc->grantModule($inst, 'inventory', ['status' => 'active', 'is_grant' => true]);
        // Need to flush and re-check (grant already flushed)
        $this->assertTrue($svc->isEnabled($inst, 'inventory'));
        $this->assertTrue($svc->isEnabled($inst, 'sales'));

        // Restore
        $sales->update(['dependencies' => $originalDeps]);
        app(ModuleAccessService::class)->flushCache($inst->id);
    }

    public function test_industry_incompatible_disabled(): void
    {
        $inst = $this->institute('FREE', 'retail', 'general_store');
        $svc = app(ModuleAccessService::class);
        // Retail institute should not get education even if granted
        $svc->grantModule($inst, 'education', ['status' => 'active', 'is_grant' => true]);
        $this->assertFalse($svc->isEnabled($inst, 'education'));

        // Education institute with education grant should be enabled
        $inst2 = $this->institute('FREE', 'education', 'school');
        $svc->grantModule($inst2, 'education', ['status' => 'active', 'is_grant' => true]);
        $this->assertTrue($svc->isEnabled($inst2, 'education'));
    }

    public function test_existing_package_modules_remain_unchanged(): void
    {
        $inst = $this->institute('BASIC'); // BASIC has crm, finance, reports, notifications, education
        $svc = app(ModuleAccessService::class);
        $before = $svc->getEnabledModules($inst);
        $this->assertContains('crm', $before);
        $this->assertContains('finance', $before);
        $this->assertNotContains('hr', $before);

        $packageModulesBefore = DB::table('package_modules')->where('package_id', $inst->package_id)->count();

        $svc->grantModule($inst, 'hr', ['status' => 'active', 'is_grant' => true]);

        $after = $svc->getEnabledModules($inst);
        $this->assertContains('crm', $after);
        $this->assertContains('hr', $after);

        $packageModulesAfter = DB::table('package_modules')->where('package_id', $inst->package_id)->count();
        $this->assertEquals($packageModulesBefore, $packageModulesAfter, 'package_modules must not be modified');

        $instFresh = $inst->fresh();
        $this->assertEquals($inst->package_id, $instFresh->package_id, 'institutes.package_id must not change');
    }

    public function test_existing_overrides_still_work(): void
    {
        $inst = $this->institute('FREE'); // FREE has crm
        $svc = app(ModuleAccessService::class);
        $this->assertTrue($svc->isEnabled($inst, 'crm'));
        // Disable via legacy override
        $svc->disableModule($inst, 'crm');
        $this->assertFalse($svc->isEnabled($inst, 'crm'));
        // Entitlement grant should override disable (entitlement precedence over override)
        $svc->grantModule($inst, 'crm', ['status' => 'active', 'is_grant' => true]);
        $this->assertTrue($svc->isEnabled($inst, 'crm'));
        // Clean up override, entitlement still active
        $svc->removeOverride($inst, 'crm');
        $this->assertTrue($svc->isEnabled($inst, 'crm')); // still via entitlement
        $svc->revokeModule($inst, 'crm');
        // After revoke, FREE package still has crm, so should be enabled via package
        $this->assertTrue($svc->isEnabled($inst, 'crm'));
    }

    public function test_cache_flush_after_grant(): void
    {
        $inst = $this->institute('FREE');
        $svc = app(ModuleAccessService::class);
        // Prime cache
        $this->assertFalse($svc->isEnabled($inst, 'hr'));
        $cacheKey = 'module_access:'.$inst->id;
        $this->assertTrue(Cache::has($cacheKey));
        $svc->grantModule($inst, 'hr', ['status' => 'active', 'is_grant' => true]);
        $this->assertFalse(Cache::has($cacheKey), 'cache must be flushed after grant');
        $this->assertTrue($svc->isEnabled($inst, 'hr'));
    }

    public function test_cache_flush_after_revoke(): void
    {
        $inst = $this->institute('FREE');
        $svc = app(ModuleAccessService::class);
        $svc->grantModule($inst, 'hr', ['status' => 'active', 'is_grant' => true]);
        $this->assertTrue($svc->isEnabled($inst, 'hr'));
        $cacheKey = 'module_access:'.$inst->id;
        $this->assertTrue(Cache::has($cacheKey));
        $svc->revokeModule($inst, 'hr');
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertFalse($svc->isEnabled($inst, 'hr'));
    }

    public function test_tenant_isolation(): void
    {
        $instA = $this->institute('FREE');
        $instB = $this->institute('FREE');
        $svc = app(ModuleAccessService::class);
        $svc->grantModule($instA, 'hr', ['status' => 'active', 'is_grant' => true]);
        $this->assertTrue($svc->isEnabled($instA, 'hr'));
        $this->assertFalse($svc->isEnabled($instB, 'hr'));
    }

    public function test_no_package_modules_modification(): void
    {
        $inst = $this->institute('FREE');
        $countBefore = DB::table('package_modules')->count();
        $svc = app(ModuleAccessService::class);
        $svc->grantModule($inst, 'hr', ['status' => 'active']);
        $svc->grantModule($inst, 'sales', ['status' => 'active', 'is_grant' => false]);
        $svc->revokeModule($inst, 'hr');
        $countAfter = DB::table('package_modules')->count();
        $this->assertEquals($countBefore, $countAfter);
    }
}
