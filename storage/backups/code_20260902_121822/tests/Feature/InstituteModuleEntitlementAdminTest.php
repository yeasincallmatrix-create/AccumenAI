<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteModuleEntitlement;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InstituteModuleEntitlementAdminTest extends TestCase
{
    use DatabaseTransactions;

    private function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'admin-'.uniqid().'@test.local',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    private function package(string $slug): SubscriptionPackage
    {
        return SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower($slug)])->firstOrFail();
    }

    private function institute(?string $packageSlug = 'FREE', ?string $industry = 'education', ?string $sub = 'school'): Institute
    {
        $pkg = $packageSlug ? $this->package($packageSlug) : null;
        TenantContext::clear();
        return Institute::create([
            'name' => 'EntAdmin '.uniqid(),
            'slug' => 'ent-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg?->id,
            'industry' => $industry,
            'sub_industry' => $sub,
            'country' => 'Bangladesh',
        ]);
    }

    private function user(Institute $inst, string $roleSlug): InstituteUser
    {
        $prev = TenantContext::id();
        TenantContext::clear();
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $u = InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $roleSlug.'-'.uniqid().'@test.local',
            'phone' => '017'.rand(10000000, 99999999),
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);
        if ($prev !== null) TenantContext::set($prev);
        return $u;
    }

    // 1. Platform Admin can view entitlement page
    public function test_platform_admin_can_view_entitlement_page(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        $this->actingAs($admin, 'platform_admin')->get(route('admin.institutes.entitlements.index', $inst))->assertOk()->assertSee($inst->name)->assertSee('Current Package Modules')->assertSee('Additional Module Entitlements');
    }

    // 2. Platform Admin can grant module
    public function test_platform_admin_can_grant_module(): void
    {
        $inst = $this->institute('FREE', 'education', 'school');
        $admin = $this->platformAdmin();
        $this->actingAs($admin, 'platform_admin')->post(route('admin.institutes.entitlements.store', $inst), [
            'module_key' => 'hr',
            'is_grant' => 1,
            'status' => 'active',
        ])->assertRedirect(route('admin.institutes.entitlements.index', $inst));
        $this->assertDatabaseHas('institute_module_entitlements', ['institute_id' => $inst->id, 'module_key' => 'hr', 'is_grant' => 1]);
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($inst->fresh(), 'hr'));
    }

    // 3. Platform Admin can revoke module
    public function test_platform_admin_can_revoke_module(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        app(ModuleAccessService::class)->grantModule($inst, 'sales', ['status' => 'active', 'is_grant' => true], $admin->id);
        $ent = InstituteModuleEntitlement::where('institute_id', $inst->id)->where('module_key', 'sales')->firstOrFail();
        $this->actingAs($admin, 'platform_admin')->delete(route('admin.institutes.entitlements.destroy', [$inst, $ent]))->assertRedirect(route('admin.institutes.entitlements.index', $inst));
        // Revoked should be soft-deleted and not enabled
        $this->assertFalse(app(ModuleAccessService::class)->isEnabled($inst->fresh(), 'sales'));
    }

    // 4. Institute user cannot access entitlement management
    public function test_institute_user_cannot_access_entitlement_management(): void
    {
        $inst = $this->institute();
        $user = $this->user($inst, 'institute-owner');
        $resp = $this->actingAs($user, 'institute_user')->get(route('admin.institutes.entitlements.index', $inst));
        $this->assertTrue(in_array($resp->status(), [302, 403]), 'Expected 302 redirect or 403');
        $resp2 = $this->actingAs($user, 'institute_user')->post(route('admin.institutes.entitlements.store', $inst), ['module_key'=>'hr','is_grant'=>1,'status'=>'active']);
        $this->assertTrue(in_array($resp2->status(), [302, 403]));
    }

    // 5. Unauthenticated cannot access
    public function test_unauthenticated_cannot_access(): void
    {
        $inst = $this->institute();
        $this->get(route('admin.institutes.entitlements.index', $inst))->assertRedirect(route('admin.login'));
    }

    // 6. Cross-institute manipulation blocked
    public function test_cross_institute_manipulation_blocked(): void
    {
        $instA = $this->institute();
        $instB = $this->institute();
        $admin = $this->platformAdmin();
        app(ModuleAccessService::class)->grantModule($instA, 'hr', ['status'=>'active','is_grant'=>true], $admin->id);
        $entA = InstituteModuleEntitlement::where('institute_id',$instA->id)->firstOrFail();
        // Try to delete entA via instB route -> should 404
        $this->actingAs($admin, 'platform_admin')->delete(route('admin.institutes.entitlements.destroy', [$instB, $entA]))->assertStatus(404);
        $this->assertDatabaseHas('institute_module_entitlements', ['id'=>$entA->id, 'deleted_at'=>null]);
    }

    // 7. Invalid module rejected
    public function test_invalid_module_rejected(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        $this->actingAs($admin, 'platform_admin')->post(route('admin.institutes.entitlements.store', $inst), [
            'module_key' => 'nonexistent_xyz',
            'is_grant' => 1,
            'status' => 'active',
        ])->assertSessionHasErrors('module_key');
    }

    // 8. Industry-incompatible rejected
    public function test_industry_incompatible_rejected(): void
    {
        $inst = $this->institute('FREE', 'retail', 'general_store');
        $admin = $this->platformAdmin();
        $this->actingAs($admin, 'platform_admin')->post(route('admin.institutes.entitlements.store', $inst), [
            'module_key' => 'education',
            'is_grant' => 1,
            'status' => 'active',
        ])->assertSessionHasErrors('module_key');
        $this->assertFalse(app(ModuleAccessService::class)->isEnabled($inst, 'education'));
    }

    // 9. Invalid dates rejected
    public function test_invalid_dates_rejected(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        $this->actingAs($admin, 'platform_admin')->post(route('admin.institutes.entitlements.store', $inst), [
            'module_key' => 'hr',
            'is_grant' => 1,
            'status' => 'active',
            'starts_at' => '2026-08-22',
            'ends_at' => '2026-08-10',
        ])->assertSessionHasErrors('ends_at');
        $this->actingAs($admin, 'platform_admin')->post(route('admin.institutes.entitlements.store', $inst), [
            'module_key' => 'hr',
            'is_grant' => 1,
            'status' => 'trialing',
            'trial_starts_at' => '2026-08-22',
            'trial_ends_at' => '2026-08-10',
        ])->assertSessionHasErrors('trial_ends_at');
    }

    // 10. Invalid pricing rejected
    public function test_invalid_pricing_rejected(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        $this->actingAs($admin, 'platform_admin')->post(route('admin.institutes.entitlements.store', $inst), [
            'module_key' => 'hr',
            'is_grant' => 1,
            'status' => 'active',
            'monthly_price' => -10,
        ])->assertSessionHasErrors('monthly_price');
        $this->actingAs($admin, 'platform_admin')->post(route('admin.institutes.entitlements.store', $inst), [
            'module_key' => 'hr',
            'is_grant' => 1,
            'status' => 'active',
            'discount_percent' => 150,
        ])->assertSessionHasErrors('discount_percent');
        $this->actingAs($admin, 'platform_admin')->post(route('admin.institutes.entitlements.store', $inst), [
            'module_key' => 'hr',
            'is_grant' => 1,
            'status' => 'active',
            'billing_cycle' => 'invalid_cycle',
        ])->assertSessionHasErrors('billing_cycle');
    }

    // 11. Package modules remain unchanged
    public function test_package_modules_remain_unchanged(): void
    {
        $inst = $this->institute('FREE', 'education', 'school');
        $admin = $this->platformAdmin();
        $free = $this->package('FREE');
        $before = \App\Models\PackageModule::where('package_id',$free->id)->pluck('module_key')->toArray();
        $this->actingAs($admin, 'platform_admin')->post(route('admin.institutes.entitlements.store', $inst), [
            'module_key' => 'hr',
            'is_grant' => 1,
            'status' => 'active',
            'monthly_price' => '100.00',
        ])->assertRedirect();
        $after = \App\Models\PackageModule::where('package_id',$free->id)->pluck('module_key')->toArray();
        $this->assertEquals(sort($before)?:$before, sort($after)?:$after);
        $this->assertEquals($before, $after);
        $this->assertEquals($inst->fresh()->package_id, $free->id);
    }

    // 12. Effective access changes after grant
    public function test_effective_access_changes_after_grant(): void
    {
        $inst = $this->institute('FREE', 'education', 'school');
        $admin = $this->platformAdmin();
        $user = $this->user($inst, 'institute-owner');
        $this->assertFalse(app(ModuleAccessService::class)->isEnabled($inst, 'purchase'));
        $this->actingAs($user, 'institute_user')->get(route('purchase.reports.dashboard'))->assertForbidden();
        $this->actingAs($admin, 'platform_admin')->post(route('admin.institutes.entitlements.store', $inst), [
            'module_key' => 'purchase',
            'is_grant' => 1,
            'status' => 'active',
        ])->assertRedirect();
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($inst->fresh(), 'purchase'));
        // Permission owner has purchase.view? institute-owner has all perms, so ok after grant
        $this->actingAs($user, 'institute_user')->get(route('purchase.reports.dashboard'))->assertOk();
    }

    // 13. Effective access changes after revoke
    public function test_effective_access_changes_after_revoke(): void
    {
        $inst = $this->institute('FREE', 'education', 'school');
        $admin = $this->platformAdmin();
        $user = $this->user($inst, 'institute-owner');
        app(ModuleAccessService::class)->grantModule($inst, 'hr', ['status'=>'active','is_grant'=>true], $admin->id);
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($inst, 'hr'));
        $this->actingAs($user, 'institute_user')->get(route('hr.reports.index'))->assertOk();
        $ent = InstituteModuleEntitlement::where('institute_id',$inst->id)->where('module_key','hr')->firstOrFail();
        $this->actingAs($admin, 'platform_admin')->delete(route('admin.institutes.entitlements.destroy', [$inst, $ent]))->assertRedirect();
        $this->assertFalse(app(ModuleAccessService::class)->isEnabled($inst->fresh(), 'hr'));
        $this->actingAs($user, 'institute_user')->get(route('hr.reports.index'))->assertForbidden();
    }

    // 14. Audit record exists
    public function test_audit_record_exists(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        $this->actingAs($admin, 'platform_admin')->post(route('admin.institutes.entitlements.store', $inst), [
            'module_key' => 'hr',
            'is_grant' => 1,
            'status' => 'active',
            'notes' => 'audit test',
        ])->assertRedirect();
        $this->assertDatabaseHas('module_access_logs', ['institute_id'=>$inst->id,'module_key'=>'hr','action'=>'entitlement_granted']);
        $ent = InstituteModuleEntitlement::where('institute_id',$inst->id)->firstOrFail();
        $this->actingAs($admin, 'platform_admin')->delete(route('admin.institutes.entitlements.destroy', [$inst, $ent]))->assertRedirect();
        $this->assertDatabaseHas('module_access_logs', ['institute_id'=>$inst->id,'module_key'=>'hr','action'=>'entitlement_revoked']);
    }

    // 15. Cache invalidation works
    public function test_cache_invalidation_works(): void
    {
        $inst = $this->institute('FREE', 'education', 'school');
        $admin = $this->platformAdmin();
        $svc = app(ModuleAccessService::class);
        $this->assertFalse($svc->isEnabled($inst, 'inventory'));
        $cacheKey = 'module_access:'.$inst->id;
        $this->assertTrue(Cache::has($cacheKey));
        $this->actingAs($admin, 'platform_admin')->post(route('admin.institutes.entitlements.store', $inst), [
            'module_key' => 'inventory',
            'is_grant' => 1,
            'status' => 'active',
        ])->assertRedirect();
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertTrue($svc->isEnabled($inst->fresh(), 'inventory'));
        $ent = InstituteModuleEntitlement::where('institute_id',$inst->id)->where('module_key','inventory')->firstOrFail();
        $this->actingAs($admin, 'platform_admin')->delete(route('admin.institutes.entitlements.destroy', [$inst, $ent]))->assertRedirect();
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertFalse($svc->isEnabled($inst->fresh(), 'inventory'));
    }

    // Extra: tenant isolation via service
    public function test_tenant_isolation(): void
    {
        $instA = $this->institute();
        $instB = $this->institute();
        $admin = $this->platformAdmin();
        $this->actingAs($admin, 'platform_admin')->post(route('admin.institutes.entitlements.store', $instA), [
            'module_key' => 'hr',
            'is_grant' => 1,
            'status' => 'active',
        ])->assertRedirect();
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($instA, 'hr'));
        $this->assertFalse(app(ModuleAccessService::class)->isEnabled($instB, 'hr'));
    }
}
