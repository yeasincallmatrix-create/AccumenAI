<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteModuleOverride;
use App\Models\InstituteUser;
use App\Models\ModuleAccessLog;
use App\Models\ModuleRegistry;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\Student;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SaaSModuleAccessTest extends TestCase
{
    use DatabaseTransactions;

    private PlatformAdmin $admin;

    private SubscriptionPackage $freePkg;

    private SubscriptionPackage $basicPkg;

    private SubscriptionPackage $proPkg;

    private SubscriptionPackage $enterprisePkg;

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'module-admin-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->freePkg = SubscriptionPackage::where('slug', 'free')->first();
        $this->basicPkg = SubscriptionPackage::where('slug', 'starter')->first();
        $this->proPkg = SubscriptionPackage::where('slug', 'professional')->first();
        $this->enterprisePkg = SubscriptionPackage::where('slug', 'enterprise')->first();

        $this->institute = Institute::create([
            'name' => 'Test Institute',
            'slug' => 'test-institute',
            'status' => 'active',
            'package_id' => $this->basicPkg->id,
        ]);
    }

    // ─── Package Module Seeding ─────────────────────────────

    public function test_module_registry_seeded(): void
    {
        $count = ModuleRegistry::count();
        $this->assertGreaterThanOrEqual(11, $count);
    }

    public function test_free_package_has_crm(): void
    {
        $keys = $this->freePkg->enabledModuleKeys();
        $this->assertContains('crm', $keys);
    }

    public function test_basic_package_has_education(): void
    {
        $keys = $this->basicPkg->enabledModuleKeys();
        $this->assertContains('education', $keys);
        $this->assertContains('crm', $keys);
        $this->assertContains('finance', $keys);
    }

    public function test_enterprise_package_has_all(): void
    {
        $keys = $this->enterprisePkg->enabledModuleKeys();
        $this->assertContains('crm', $keys);
        $this->assertContains('finance', $keys);
        $this->assertContains('accounting', $keys);
        $this->assertContains('education', $keys);
        $this->assertContains('ai', $keys);
        $this->assertContains('inventory', $keys);
        $this->assertContains('hr', $keys);
        $this->assertContains('sales', $keys);
        $this->assertContains('purchase', $keys);
        $this->assertContains('reports', $keys);
        $this->assertContains('notifications', $keys);
    }

    // ─── Module Access Service ──────────────────────────────

    public function test_service_returns_package_modules_for_institute(): void
    {
        $service = app(ModuleAccessService::class);
        $enabled = $service->getEnabledModules($this->institute);

        $this->assertContains('crm', $enabled);
        $this->assertContains('finance', $enabled);
        $this->assertContains('education', $enabled);
    }

    public function test_service_returns_false_for_unentitled_module(): void
    {
        $service = app(ModuleAccessService::class);
        $this->assertFalse($service->isEnabled($this->institute, 'ai'));
    }

    public function test_enable_override_adds_access(): void
    {
        $service = app(ModuleAccessService::class);
        $this->assertFalse($service->isEnabled($this->institute, 'ai'));

        $service->enableModule($this->institute, 'ai', $this->admin->id, 'Business need');
        $this->assertTrue($service->isEnabled($this->institute, 'ai'));
    }

    public function test_disable_override_removes_access(): void
    {
        $service = app(ModuleAccessService::class);
        $this->assertTrue($service->isEnabled($this->institute, 'crm'));

        $service->disableModule($this->institute, 'crm', $this->admin->id, 'Not needed');
        $this->assertFalse($service->isEnabled($this->institute, 'crm'));
    }

    public function test_override_has_priority_over_package(): void
    {
        $service = app(ModuleAccessService::class);
        $service->disableModule($this->institute, 'crm', $this->admin->id, 'Temp disable');
        $this->assertFalse($service->isEnabled($this->institute, 'crm'));

        $service->removeOverride($this->institute, 'crm');
        $this->assertTrue($service->isEnabled($this->institute, 'crm'));
    }

    // ─── Package Upgrade / Downgrade ────────────────────────

    public function test_package_upgrade_enables_new_modules(): void
    {
        $service = app(ModuleAccessService::class);
        $this->institute->update(['package_id' => $this->freePkg->id]);
        $service->flushCache($this->institute->id);

        $freeModules = $service->getEnabledModules($this->institute);
        $this->assertNotContains('education', $freeModules);

        $this->institute->update(['package_id' => $this->enterprisePkg->id]);
        $service->flushCache($this->institute->id);

        $enterpriseModules = $service->getEnabledModules($this->institute);
        $this->assertContains('education', $enterpriseModules);
        $this->assertContains('ai', $enterpriseModules);
    }

    public function test_package_downgrade_restricts_modules(): void
    {
        $service = app(ModuleAccessService::class);
        $this->institute->update(['package_id' => $this->enterprisePkg->id]);
        $service->flushCache($this->institute->id);

        $this->institute->update(['package_id' => $this->freePkg->id]);
        $service->flushCache($this->institute->id);

        $freeModules = $service->getEnabledModules($this->institute);
        $this->assertNotContains('education', $freeModules);
        $this->assertNotContains('ai', $freeModules);
    }

    // ─── Data Safety ────────────────────────────────────────

    public function test_disabling_module_does_not_delete_data(): void
    {
        $service = app(ModuleAccessService::class);
        $studentCount = Student::where('institute_id', $this->institute->id)->count();

        $service->disableModule($this->institute, 'education', $this->admin->id, 'Test disable');

        $this->assertEquals($studentCount, Student::where('institute_id', $this->institute->id)->count());
    }

    // ─── Audit Logging ──────────────────────────────────────

    public function test_enable_override_creates_log(): void
    {
        $service = app(ModuleAccessService::class);
        $service->enableModule($this->institute, 'ai', $this->admin->id, 'Audit test');

        $log = ModuleAccessLog::where('module_key', 'ai')
            ->where('institute_id', $this->institute->id)
            ->where('action', 'enable')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->admin->id, $log->actor_id);
    }

    public function test_disable_override_creates_log(): void
    {
        $service = app(ModuleAccessService::class);
        $service->disableModule($this->institute, 'crm', $this->admin->id, 'Audit disable');

        $log = ModuleAccessLog::where('module_key', 'crm')
            ->where('institute_id', $this->institute->id)
            ->where('action', 'disable')
            ->first();

        $this->assertNotNull($log);
    }

    // ─── Dependency Checking ────────────────────────────────

    public function test_module_with_disabled_dependency_warns(): void
    {
        $service = app(ModuleAccessService::class);
        $module = ModuleRegistry::where('key', 'education')->first();
        $enabledModules = $service->getEnabledModules($this->institute);
        $missing = $service->checkDependencies($module->key, $enabledModules);
        $this->assertEmpty($missing);
    }

    // ─── Admin UI Routes ────────────────────────────────────

    public function test_admin_can_view_module_registry(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.modules.index'));

        $response->assertOk();
        $response->assertSee('Module Registry');
    }

    public function test_admin_can_view_package_modules(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.packages.modules', $this->basicPkg));

        $response->assertOk();
        $response->assertSee($this->basicPkg->name);
    }

    public function test_admin_can_update_package_modules(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.packages.modules.update', $this->basicPkg), [
                'modules' => ['crm', 'finance', 'education'],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('package_modules', [
            'package_id' => $this->basicPkg->id,
            'module_key' => 'crm',
            'enabled' => true,
        ]);
    }

    public function test_admin_can_view_institute_modules(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.institutes.modules', $this->institute));

        $response->assertOk();
        $response->assertSee($this->institute->name);
    }

    public function test_admin_can_set_institute_override(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.institutes.modules.update', $this->institute), [
                'module_key' => 'ai',
                'enabled' => true,
                'reason' => 'Special case',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('institute_module_overrides', [
            'institute_id' => $this->institute->id,
            'module_key' => 'ai',
            'enabled' => true,
        ]);
    }

    public function test_admin_can_remove_institute_override(): void
    {
        InstituteModuleOverride::create([
            'institute_id' => $this->institute->id,
            'module_key' => 'ai',
            'enabled' => true,
            'overridden_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->delete(route('admin.institutes.modules.remove', [$this->institute, 'ai']));

        $response->assertRedirect();
        $this->assertDatabaseMissing('institute_module_overrides', [
            'institute_id' => $this->institute->id,
            'module_key' => 'ai',
        ]);
    }

    public function test_admin_can_view_access_logs(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.modules.access-logs'));

        $response->assertOk();
    }

    // ─── Unauthorized Access ────────────────────────────────

    public function test_unauthenticated_cannot_access_module_routes(): void
    {
        $this->get(route('admin.modules.index'))->assertRedirect();
        $this->put(route('admin.modules.update', 1), ['status' => 'inactive'])->assertRedirect();
    }

    public function test_institute_user_cannot_access_admin_module_routes(): void
    {
        $role = Role::where('slug', 'teacher')->first();

        $user = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'branch_id' => null,
            'role_id' => $role->id,
            'first_name' => 'Staff',
            'last_name' => 'User',
            'email' => 'staff@test.com',
            'phone' => '01700000000',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->actingAs($user, 'institute_user')
            ->get(route('admin.modules.index'))
            ->assertStatus(302);
    }

    // ─── Module Toggle ──────────────────────────────────────

    public function test_admin_can_toggle_module_status(): void
    {
        $module = ModuleRegistry::first();
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.modules.update', $module), ['status' => 'inactive']);

        $response->assertRedirect();
        $module->refresh();
        $this->assertEquals('inactive', $module->status);
    }

    // ─── Full Module Matrix ─────────────────────────────────

    public function test_full_module_matrix_across_packages(): void
    {
        $service = app(ModuleAccessService::class);
        $allModules = ModuleRegistry::where('status', 'active')->pluck('key')->toArray();

        $packages = [
            'free' => $this->freePkg,
            'starter' => $this->basicPkg,
            'professional' => $this->proPkg,
            'enterprise' => $this->enterprisePkg,
        ];

        foreach ($packages as $label => $pkg) {
            $inst = Institute::create([
                'name' => "Institute {$label}",
                'slug' => "institute-{$label}",
                'status' => 'active',
                'package_id' => $pkg->id,
            ]);
            $enabled = $service->getEnabledModules($inst);

            $this->assertNotEmpty($enabled, "{$label} package should have modules");

            foreach ($enabled as $mod) {
                $this->assertContains($mod, $allModules, "{$label} package references valid module: {$mod}");
            }
        }
    }

    // ─── Cross-tenant Isolation ─────────────────────────────

    public function test_institute_overrides_are_isolated(): void
    {
        $otherInstitute = Institute::create([
            'name' => 'Other Institute',
            'slug' => 'other-institute',
            'status' => 'active',
            'package_id' => $this->basicPkg->id,
        ]);

        $service = app(ModuleAccessService::class);

        $service->enableModule($this->institute, 'ai', $this->admin->id, 'Only for this one');

        $this->assertTrue($service->isEnabled($this->institute, 'ai'));
        $this->assertFalse($service->isEnabled($otherInstitute, 'ai'));
    }

    // ─── Cache Behavior ─────────────────────────────────────

    public function test_cache_is_flushed_on_override_change(): void
    {
        $service = app(ModuleAccessService::class);
        $service->enableModule($this->institute, 'ai', $this->admin->id);
        $this->assertTrue($service->isEnabled($this->institute, 'ai'));

        $service->removeOverride($this->institute, 'ai');
        $this->assertFalse($service->isEnabled($this->institute, 'ai'));
    }

    // ─── Edge Cases ─────────────────────────────────────────

    public function test_enable_already_enabled_module_is_idempotent(): void
    {
        $service = app(ModuleAccessService::class);
        $service->enableModule($this->institute, 'crm', $this->admin->id);
        $service->enableModule($this->institute, 'crm', $this->admin->id);

        $count = InstituteModuleOverride::where('institute_id', $this->institute->id)
            ->where('module_key', 'crm')
            ->count();
        $this->assertLessThanOrEqual(2, $count);
    }

    public function test_nonexistent_module_key_rejected(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.institutes.modules.update', $this->institute), [
                'module_key' => 'nonexistent_module',
                'enabled' => true,
            ]);

        $response->assertSessionHasErrors('module_key');
    }
}
