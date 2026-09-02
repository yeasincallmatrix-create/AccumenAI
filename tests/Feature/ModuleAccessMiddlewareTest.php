<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ModuleAccessMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    private function package(string $slug): SubscriptionPackage
    {
        return SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower($slug)])->firstOrFail();
    }

    private function institute(?string $packageSlug = null, ?string $industry = 'education', ?string $subIndustry = 'school'): Institute
    {
        $pkg = $packageSlug ? $this->package($packageSlug) : null;
        return Institute::create([
            'name' => 'Mid Test '.uniqid(),
            'slug' => 'mid-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg?->id,
            'industry' => $industry,
            'sub_industry' => $subIndustry,
            'country' => 'Bangladesh',
        ]);
    }

    private function user(Institute $inst, string $roleSlug, array $perms = []): InstituteUser
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        // Ensure perms for test role if needed
        if ($perms) {
            $permIds = \App\Models\Permission::whereIn('slug', $perms)->pluck('id');
            foreach ($permIds as $pid) {
                \Illuminate\Support\Facades\DB::table('role_permissions')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $pid], []);
            }
        }
        // TenantScoped creating hook forces institute_id to current TenantContext; bypass for test isolation
        $prev = \App\Support\TenantContext::id();
        \App\Support\TenantContext::clear();
        $user = InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $roleSlug.'-'.uniqid().'@test.local',
            'phone' => '017'.rand(10000000, 99999999),
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);
        if ($prev !== null) {
            \App\Support\TenantContext::set($prev);
        }
        return $user;
    }

    // 1. Package module access
    public function test_package_module_access(): void
    {
        $inst = $this->institute('FREE', 'education', 'school'); // FREE has no sales, but has crm
        $user = $this->user($inst, 'institute-owner');
        // FREE + crm should pass (crm is in FREE)
        $this->actingAs($user, 'institute_user')->get(route('crm.dashboard'))->assertOk();
        // FREE without sales should 403
        $this->actingAs($user, 'institute_user')->get(route('sales.reports.dashboard'))->assertForbidden();
    }

    // 2. Individual module grant
    public function test_individual_module_grant(): void
    {
        $inst = $this->institute('FREE', 'education', 'school');
        $user = $this->user($inst, 'institute-owner');
        app(ModuleAccessService::class)->grantModule($inst, 'sales', ['status' => 'active', 'is_grant' => true]);
        $this->actingAs($user, 'institute_user')->get(route('sales.reports.dashboard'))->assertOk();

        // HR grant - verify via service (HTTP also requires permission, service is source of truth)
        $inst2 = $this->institute('FREE', 'education', 'school');
        app(ModuleAccessService::class)->grantModule($inst2, 'hr', ['status' => 'active', 'is_grant' => true]);
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($inst2, 'hr'));
    }

    // 3. Individual module denial
    public function test_individual_module_denial(): void
    {
        $inst = $this->institute('PREMIUM', 'education', 'school'); // PREMIUM has sales
        $user = $this->user($inst, 'institute-owner');
        $this->actingAs($user, 'institute_user')->get(route('sales.reports.dashboard'))->assertOk();
        app(ModuleAccessService::class)->grantModule($inst, 'sales', ['status' => 'active', 'is_grant' => false]);
        $this->actingAs($user, 'institute_user')->get(route('sales.reports.dashboard'))->assertForbidden();
    }

    // 4. Expired entitlement
    public function test_expired_entitlement_blocked(): void
    {
        $inst = $this->institute('FREE', 'education', 'school');
        $user = $this->user($inst, 'institute-owner');
        app(ModuleAccessService::class)->grantModule($inst, 'sales', ['status' => 'active', 'is_grant' => true, 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
        $this->actingAs($user, 'institute_user')->get(route('sales.reports.dashboard'))->assertForbidden();
    }

    // 5. Future entitlement
    public function test_future_entitlement_blocked(): void
    {
        $inst = $this->institute('FREE', 'education', 'school');
        $user = $this->user($inst, 'institute-owner');
        app(ModuleAccessService::class)->grantModule($inst, 'sales', ['status' => 'active', 'is_grant' => true, 'starts_at' => now()->addDay()]);
        $this->actingAs($user, 'institute_user')->get(route('sales.reports.dashboard'))->assertForbidden();
    }

    // 6. Industry-incompatible - verify via service (industry gate in ModuleAccessService)
    public function test_industry_incompatible_blocked(): void
    {
        $inst = $this->institute('FREE', 'retail', 'general_store');
        app(ModuleAccessService::class)->grantModule($inst, 'education', ['status' => 'active', 'is_grant' => true]);
        $this->assertFalse(app(ModuleAccessService::class)->isEnabled($inst, 'education'));
        // Education institute with education grant should pass
        $inst2 = $this->institute('FREE', 'education', 'school');
        app(ModuleAccessService::class)->grantModule($inst2, 'education', ['status' => 'active', 'is_grant' => true]);
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($inst2, 'education'));
    }

    // 7. Permission missing
    public function test_permission_missing_blocked(): void
    {
        $inst = $this->institute('FREE', 'education', 'school');
        // Grant HR entitlement
        app(ModuleAccessService::class)->grantModule($inst, 'hr', ['status' => 'active', 'is_grant' => true]);
        // User with no hr.view (receptionist has no hr perms)
        $receptionist = $this->user($inst, 'receptionist');
        $this->actingAs($receptionist, 'institute_user')->get(route('hr.reports.index'))->assertForbidden();
        // Owner has hr via role? Actually institute-owner has all, so should pass
        $owner = $this->user($inst, 'institute-owner');
        $this->actingAs($owner, 'institute_user')->get(route('hr.reports.index'))->assertOk();
    }

    // 8. Cross-tenant attempt
    public function test_cross_tenant_entitlement_isolation(): void
    {
        $instA = $this->institute('FREE', 'education', 'school');
        $instB = $this->institute('FREE', 'education', 'school');
        $userA = $this->user($instA, 'institute-owner');
        // Grant to A only
        app(ModuleAccessService::class)->grantModule($instA, 'sales', ['status' => 'active', 'is_grant' => true]);
        $this->actingAs($userA, 'institute_user')->get(route('sales.reports.dashboard'))->assertOk();
        // B user should still be blocked, even if attacker sends institute_id param (should be ignored)
        $userB = $this->user($instB, 'institute-owner');
        $this->actingAs($userB, 'institute_user')->get(route('sales.reports.dashboard', ['institute_id' => $instA->id]))->assertForbidden();
        $this->actingAs($userB, 'institute_user')->get(route('sales.reports.dashboard', ['branch_id' => 9999]))->assertForbidden();
    }

    // 9. Direct URL
    public function test_direct_url_bypass_blocked(): void
    {
        $inst = $this->institute('FREE', 'education', 'school');
        $user = $this->user($inst, 'institute-owner');
        // No entitlement
        $this->actingAs($user, 'institute_user')->get('/hr/reports')->assertForbidden(); // hr reports hub via web
        $this->actingAs($user, 'institute_user')->get(route('purchase.reports.dashboard'))->assertForbidden();
        // Grant and then should pass
        app(ModuleAccessService::class)->grantModule($inst, 'hr', ['status' => 'active', 'is_grant' => true]);
        $this->actingAs($user, 'institute_user')->get(route('hr.reports.index'))->assertOk();
        app(ModuleAccessService::class)->grantModule($inst, 'purchase', ['status' => 'active', 'is_grant' => true]);
        $this->actingAs($user, 'institute_user')->get(route('purchase.reports.dashboard'))->assertOk();
        // Expired should block again
        $inst2 = $this->institute('FREE', 'education', 'school');
        $user2 = $this->user($inst2, 'institute-owner');
        app(ModuleAccessService::class)->grantModule($inst2, 'hr', ['status' => 'active', 'is_grant' => true, 'ends_at' => now()->subDay()]);
        $this->actingAs($user2, 'institute_user')->get(route('hr.reports.index'))->assertForbidden();
    }

    // 10. API access
    public function test_api_access_with_entitlement(): void
    {
        $inst = $this->institute('FREE', 'education', 'school');
        $user = $this->user($inst, 'institute-owner');
        // No entitlement -> 403
        $this->actingAs($user, 'sanctum')->getJson('/api/purchase-orders')->assertForbidden();
        // Grant purchase
        app(ModuleAccessService::class)->grantModule($inst, 'purchase', ['status' => 'active', 'is_grant' => true]);
        $this->actingAs($user, 'sanctum')->getJson('/api/purchase-orders')->assertOk();
        // Expired -> 403
        $inst2 = $this->institute('FREE', 'education', 'school');
        $user2 = $this->user($inst2, 'institute-owner');
        app(ModuleAccessService::class)->grantModule($inst2, 'purchase', ['status' => 'active', 'is_grant' => true, 'ends_at' => now()->subDay()]);
        $this->actingAs($user2, 'sanctum')->getJson('/api/purchase-orders')->assertForbidden();
    }

    // 11. Reports Hub
    public function test_reports_hub_respects_entitlement(): void
    {
        $inst = $this->institute('FREE', 'education', 'school');
        $user = $this->user($inst, 'institute-owner');
        // Without sales, hub should not contain sales.dashboard
        $this->actingAs($user, 'institute_user')->get(route('reports.hub'))->assertOk();
        $groups = app(\App\Services\Reports\ReportRegistry::class)::forInstitute($inst, null, $user);
        $keys = array_column($groups, 'key');
        $this->assertNotContains('sales.dashboard', $keys);
        // Grant sales
        app(ModuleAccessService::class)->grantModule($inst, 'sales', ['status' => 'active', 'is_grant' => true]);
        $groups2 = app(\App\Services\Reports\ReportRegistry::class)::forInstitute($inst, null, $user);
        $keys2 = array_column($groups2, 'key');
        $this->assertContains('sales.dashboard', $keys2);
        // Expired should hide
        $inst3 = $this->institute('FREE', 'education', 'school');
        $user3 = $this->user($inst3, 'institute-owner');
        app(ModuleAccessService::class)->grantModule($inst3, 'sales', ['status' => 'active', 'is_grant' => true, 'ends_at' => now()->subDay()]);
        $groups3 = app(\App\Services\Reports\ReportRegistry::class)::forInstitute($inst3, null, $user3);
        $keys3 = array_column($groups3, 'key');
        $this->assertNotContains('sales.dashboard', $keys3);
    }

    // 12. Cache invalidation
    public function test_cache_invalidation(): void
    {
        $inst = $this->institute('FREE', 'education', 'school');
        $user = $this->user($inst, 'institute-owner');
        $svc = app(ModuleAccessService::class);
        // Prime cache via isEnabled
        $this->assertFalse($svc->isEnabled($inst, 'hr'));
        $cacheKey = 'module_access:'.$inst->id;
        $this->assertTrue(Cache::has($cacheKey));
        // Grant -> cache flushed and new value
        $svc->grantModule($inst, 'hr', ['status' => 'active', 'is_grant' => true]);
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertTrue($svc->isEnabled($inst, 'hr'));
        $this->assertTrue(Cache::has($cacheKey));
        // Revoke -> flushed
        $svc->revokeModule($inst, 'hr');
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertFalse($svc->isEnabled($inst, 'hr'));
        // Grant again -> restored
        $svc->grantModule($inst, 'hr', ['status' => 'active', 'is_grant' => true]);
        $this->assertTrue($svc->isEnabled($inst, 'hr'));
    }
}
