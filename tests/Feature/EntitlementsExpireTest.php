<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteModuleEntitlement;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EntitlementsExpireTest extends TestCase
{
    use DatabaseTransactions;

    private function package(string $slug): SubscriptionPackage
    {
        return SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower($slug)])->firstOrFail();
    }

    private function institute(string $industry = 'education', string $sub = 'school'): Institute
    {
        $pkg = $this->package('FREE');
        \App\Support\TenantContext::clear();
        return Institute::create([
            'name' => 'Exp '.uniqid(),
            'slug' => 'exp-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => $industry,
            'sub_industry' => $sub,
            'country' => 'Bangladesh',
        ]);
    }

    public function test_active_future_ends_at_remains_enabled(): void
    {
        $inst = $this->institute();
        $svc = app(ModuleAccessService::class);
        $ent = InstituteModuleEntitlement::create([
            'institute_id' => $inst->id,
            'module_key' => 'sales',
            'status' => 'active',
            'is_grant' => true,
            'ends_at' => now()->addDay(),
        ]);
        $this->assertTrue($svc->isEnabled($inst, 'sales'));
        Artisan::call('entitlements:expire');
        $this->assertEquals('active', $ent->fresh()->status);
        $this->assertTrue($svc->isEnabled($inst->fresh(), 'sales'));
    }

    public function test_active_past_ends_at_becomes_expired(): void
    {
        $inst = $this->institute();
        $svc = app(ModuleAccessService::class);
        // Direct create to simulate already active with past ends_at (would normally be expired via isEntitlementActive false)
        $ent = InstituteModuleEntitlement::create([
            'institute_id' => $inst->id,
            'module_key' => 'sales',
            'status' => 'active',
            'is_grant' => true,
            'ends_at' => now()->subDay(),
        ]);
        // Before command, isEnabled should be false (isEntitlementActive filters) but status still active
        $this->assertFalse($svc->isEnabled($inst, 'sales'));
        Artisan::call('entitlements:expire');
        $this->assertEquals('expired', $ent->fresh()->status);
        $this->assertFalse($svc->isEnabled($inst->fresh(), 'sales'));
    }

    public function test_trialing_inside_window_remains_effective(): void
    {
        $inst = $this->institute();
        $svc = app(ModuleAccessService::class);
        $ent = InstituteModuleEntitlement::create([
            'institute_id' => $inst->id,
            'module_key' => 'hr',
            'status' => 'trialing',
            'is_grant' => true,
            'trial_starts_at' => now()->subDay(),
            'trial_ends_at' => now()->addDay(),
        ]);
        $this->assertTrue($svc->isEnabled($inst, 'hr'));
        Artisan::call('entitlements:expire');
        $this->assertEquals('trialing', $ent->fresh()->status);
        $this->assertTrue($svc->isEnabled($inst->fresh(), 'hr'));
    }

    public function test_expired_trial_becomes_inaccessible(): void
    {
        $inst = $this->institute();
        $svc = app(ModuleAccessService::class);
        $ent = InstituteModuleEntitlement::create([
            'institute_id' => $inst->id,
            'module_key' => 'hr',
            'status' => 'trialing',
            'is_grant' => true,
            'trial_starts_at' => now()->subDays(3),
            'trial_ends_at' => now()->subDay(),
        ]);
        // Already ineffective via isEntitlementActive, but status still trialing before command
        $this->assertFalse($svc->isEnabled($inst, 'hr'));
        Artisan::call('entitlements:expire');
        $this->assertEquals('expired', $ent->fresh()->status);
        $this->assertFalse($svc->isEnabled($inst->fresh(), 'hr'));
    }

    public function test_future_pending_does_not_activate_early(): void
    {
        $inst = $this->institute();
        $svc = app(ModuleAccessService::class);
        $ent = InstituteModuleEntitlement::create([
            'institute_id' => $inst->id,
            'module_key' => 'purchase',
            'status' => 'pending',
            'is_grant' => true,
            'starts_at' => now()->addDay(),
        ]);
        $this->assertFalse($svc->isEnabled($inst, 'purchase'));
        Artisan::call('entitlements:expire');
        $this->assertEquals('pending', $ent->fresh()->status);
        $this->assertFalse($svc->isEnabled($inst->fresh(), 'purchase'));
    }

    public function test_pending_activates_after_starts_at(): void
    {
        $inst = $this->institute();
        $svc = app(ModuleAccessService::class);
        $ent = InstituteModuleEntitlement::create([
            'institute_id' => $inst->id,
            'module_key' => 'inventory',
            'status' => 'pending',
            'is_grant' => true,
            'starts_at' => now()->subHour(),
        ]);
        $this->assertFalse($svc->isEnabled($inst, 'inventory'));
        Artisan::call('entitlements:expire');
        $this->assertEquals('active', $ent->fresh()->status);
        $this->assertTrue($svc->isEnabled($inst->fresh(), 'inventory'));
    }

    public function test_revoked_remains_revoked(): void
    {
        $inst = $this->institute();
        $ent = InstituteModuleEntitlement::create([
            'institute_id' => $inst->id,
            'module_key' => 'crm',
            'status' => 'revoked',
            'is_grant' => true,
            'starts_at' => now()->subDay(),
        ]);
        $ent->delete(); // soft delete as per revokeModule
        Artisan::call('entitlements:expire');
        $found = InstituteModuleEntitlement::withTrashed()->find($ent->id);
        $this->assertNotNull($found);
        $this->assertEquals('revoked', $found->status);
        $this->assertNotNull($found->deleted_at);
    }

    public function test_cache_flushed_after_effective_change(): void
    {
        $inst = $this->institute();
        $svc = app(ModuleAccessService::class);
        // pending that will activate
        InstituteModuleEntitlement::create([
            'institute_id' => $inst->id,
            'module_key' => 'sales',
            'status' => 'pending',
            'is_grant' => true,
            'starts_at' => now()->subHour(),
        ]);
        // prime cache
        $svc->isEnabled($inst, 'sales');
        $this->assertTrue(Cache::has('module_access:'.$inst->id));
        Artisan::call('entitlements:expire');
        $this->assertFalse(Cache::has('module_access:'.$inst->id));
        // re-prime and verify enabled
        $this->assertTrue($svc->isEnabled($inst->fresh(), 'sales'));
    }

    public function test_command_is_idempotent(): void
    {
        $inst = $this->institute();
        InstituteModuleEntitlement::create([
            'institute_id' => $inst->id,
            'module_key' => 'hr',
            'status' => 'active',
            'is_grant' => true,
            'ends_at' => now()->subDay(),
        ]);
        Artisan::call('entitlements:expire');
        $firstLogCount = \App\Models\ModuleAccessLog::count();
        Artisan::call('entitlements:expire');
        Artisan::call('entitlements:expire');
        $this->assertEquals(1, InstituteModuleEntitlement::where('institute_id', $inst->id)->where('module_key','hr')->where('status','expired')->count());
        $this->assertEquals($firstLogCount, \App\Models\ModuleAccessLog::count(), 'No duplicate audit logs');
    }

    public function test_one_institute_expiry_not_affect_another(): void
    {
        $instA = $this->institute();
        $instB = $this->institute();
        $svc = app(ModuleAccessService::class);
        // A has expired active, B has future active
        InstituteModuleEntitlement::create([
            'institute_id' => $instA->id,
            'module_key' => 'sales',
            'status' => 'active',
            'is_grant' => true,
            'ends_at' => now()->subDay(),
        ]);
        InstituteModuleEntitlement::create([
            'institute_id' => $instB->id,
            'module_key' => 'sales',
            'status' => 'active',
            'is_grant' => true,
            'ends_at' => now()->addDay(),
        ]);
        Artisan::call('entitlements:expire');
        $this->assertEquals('expired', InstituteModuleEntitlement::where('institute_id',$instA->id)->first()->status);
        $this->assertEquals('active', InstituteModuleEntitlement::where('institute_id',$instB->id)->first()->status);
        $this->assertFalse($svc->isEnabled($instA->fresh(), 'sales'));
        $this->assertTrue($svc->isEnabled($instB->fresh(), 'sales'));
    }
}
