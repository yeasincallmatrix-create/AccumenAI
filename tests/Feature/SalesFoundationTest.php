<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\Sales\SalesNumberingService;
use App\Services\Sales\SalesSettingsService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SalesFoundationTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function country(): Country
    {
        return Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]);
    }

    private function institute(?Country $c = null): Institute
    {
        $c ??= $this->country();
        return Institute::create(['name' => 'Sales Inst '.uniqid(), 'slug' => 'sales-'.uniqid(), 'country' => $c->name, 'country_id' => $c->id, 'status' => 'active']);
    }

    private function branch(Institute $i, string $name = 'Branch'): Branch
    {
        return Branch::create(['institute_id' => $i->id, 'name' => $name.' '.uniqid(), 'status' => 'active']);
    }

    private function user(Institute $i, string $role, ?int $branchId = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $i->id, 'role_id' => Role::where('slug', $role)->firstOrFail()->id, 'branch_id' => $branchId,
            'first_name' => ucfirst($role), 'last_name' => 'User',
            'email' => $role.'-'.uniqid().'@example.test', 'phone' => '01700'.rand(100000,999999),
            'password_hash' => bcrypt('secret12345'), 'status' => 'active',
        ]);
    }

    public function test_guest_cannot_access_sales_settings(): void
    {
        $this->get(route('sales.settings.index'))->assertRedirect();
        $this->put(route('sales.settings.update'), [])->assertRedirect();
    }

    public function test_unauthorized_role_cannot_access_sales(): void
    {
        $inst = $this->institute();
        $teacher = $this->user($inst, 'teacher');
        $receptionist = $this->user($inst, 'receptionist');
        TenantContext::set($inst->id);
        $this->actingAs($teacher, 'institute_user')->get(route('sales.settings.index'))->assertForbidden();
        $this->actingAs($receptionist, 'institute_user')->get(route('sales.settings.index'))->assertForbidden();
        $this->actingAs($teacher, 'institute_user')->put(route('sales.settings.update'), ['enabled' => false])->assertForbidden();
    }

    public function test_owner_and_admin_can_manage_sales(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $admin = $this->user($inst, 'institute-admin');
        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')->get(route('sales.settings.index'))->assertOk()->assertSee('Sales Foundation');
        $this->actingAs($owner, 'institute_user')->put(route('sales.settings.update'), [
            'enabled' => true, 'quotation_enabled' => true, 'sales_order_enabled' => true, 'delivery_enabled' => true,
            'invoice_integration' => true, 'default_currency' => 'USD', 'default_payment_terms' => 'net_30',
            'default_tax_behavior' => 'exclusive', 'default_discount_behavior' => 'per_line',
            'numbering' => ['quotation' => ['prefix' => 'QUO-', 'padding' => 5]],
        ])->assertRedirect();
        $this->assertDatabaseHas('institute_settings', ['institute_id' => $inst->id]);

        $settings = app(SalesSettingsService::class)->get($inst->id);
        $this->assertEquals('USD', $settings['default_currency']);
        $this->assertTrue($settings['quotation_enabled']);

        $this->actingAs($admin, 'institute_user')->get(route('sales.settings.index'))->assertOk();
        $this->actingAs($admin, 'institute_user')->put(route('sales.settings.update'), ['enabled' => false])->assertRedirect();
        $this->assertFalse(app(SalesSettingsService::class)->get($inst->id)['enabled']);
    }

    public function test_branch_manager_limited_access(): void
    {
        $inst = $this->institute();
        $branchA = $this->branch($inst, 'A');
        $branchB = $this->branch($inst, 'B');
        $managerA = $this->user($inst, 'branch-manager', $branchA->id);
        $managerB = $this->user($inst, 'branch-manager', $branchB->id);
        TenantContext::set($inst->id);
        BranchContext::set($branchA->id);
        $this->actingAs($managerA, 'institute_user')->get(route('sales.settings.index'))->assertOk();
        // branch manager has sales.view but not sales.manage? In our grants, branch-manager has view/create/update but not manage, so update should be forbidden (requires sales.manage)
        $this->actingAs($managerA, 'institute_user')->put(route('sales.settings.update'), ['enabled' => true])->assertForbidden();
        BranchContext::set($branchB->id);
        $this->actingAs($managerB, 'institute_user')->get(route('sales.settings.index'))->assertOk();
        BranchContext::clear();
    }

    public function test_cross_tenant_isolation(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $ownerA = $this->user($a, 'institute-owner');
        $ownerB = $this->user($b, 'institute-owner');
        TenantContext::set($a->id);
        $this->actingAs($ownerA, 'institute_user')->put(route('sales.settings.update'), [
            'enabled' => true, 'default_currency' => 'EUR',
        ])->assertRedirect();
        TenantContext::set($b->id);
        $this->actingAs($ownerB, 'institute_user')->get(route('sales.settings.index'))->assertOk();
        $settingsB = app(SalesSettingsService::class)->get($b->id);
        $this->assertEquals('BDT', $settingsB['default_currency']); // not EUR, isolated
        TenantContext::set($a->id);
        $settingsA = app(SalesSettingsService::class)->get($a->id);
        $this->assertEquals('EUR', $settingsA['default_currency']);
    }

    public function test_cross_branch_numbering_isolation(): void
    {
        $inst = $this->institute();
        $branchA = $this->branch($inst, 'A');
        $branchB = $this->branch($inst, 'B');
        $svc = app(SalesNumberingService::class);
        TenantContext::set($inst->id);
        $n1 = $svc->nextNumber($inst->id, $branchA->id, 'quotation');
        $n2 = $svc->nextNumber($inst->id, $branchA->id, 'quotation');
        $n3 = $svc->nextNumber($inst->id, $branchB->id, 'quotation');
        $this->assertNotEquals($n1, $n2);
        $this->assertStringContains('QUO-', $n1);
        // Branch B starts at 1 even though branch A is at 2
        $this->assertEquals('QUO-00001', $n3);
        // Different types isolated
        $so1 = $svc->nextNumber($inst->id, null, 'sales_order');
        $this->assertStringContains('SO-', $so1);
    }

    public function test_request_id_spoofing_is_ignored(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $ownerA = $this->user($a, 'institute-owner');
        TenantContext::set($a->id);
        // Try to spoof institute_id via request input - should be ignored, uses TenantContext
        $this->actingAs($ownerA, 'institute_user')->put(route('sales.settings.update'), [
            'institute_id' => $b->id,
            'branch_id' => 9999,
            'enabled' => true,
            'default_currency' => 'JPY',
        ])->assertRedirect();
        $settingsA = app(SalesSettingsService::class)->get($a->id);
        $this->assertEquals('JPY', $settingsA['default_currency']);
        $settingsB = app(SalesSettingsService::class)->get($b->id);
        $this->assertNotEquals('JPY', $settingsB['default_currency']);
        $this->assertEquals('BDT', $settingsB['default_currency']);
    }

    public function test_numbering_service_is_centralized_and_not_in_controller(): void
    {
        $inst = $this->institute();
        $this->assertTrue(class_exists(SalesNumberingService::class));
        $ref = new \ReflectionClass(SalesNumberingService::class);
        $this->assertTrue($ref->hasMethod('nextNumber'));
        $this->assertTrue($ref->hasMethod('preview'));
        $this->assertTrue($ref->hasMethod('configure'));
        // Ensure controller does not contain numbering logic
        $controller = file_get_contents(app_path('Http/Controllers/Sales/SalesSettingsController.php'));
        $this->assertStringNotContains('next_number', strtolower($controller));
        $this->assertStringContains('SalesNumberingService', $controller);
    }

    public function test_audit_logging_for_sales_settings(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user')->put(route('sales.settings.update'), ['enabled' => false])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'sales_settings_updated']);
        // Check institute_settings actually updated
        $this->assertDatabaseHas('institute_settings', ['institute_id' => $inst->id]);
    }

    public function test_sales_module_sidebar_visibility(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $teacher = $this->user($inst, 'teacher');
        TenantContext::set($inst->id);
        // Owner sees sales
        $this->actingAs($owner, 'institute_user')->get(route('dashboard'))->assertOk()->assertSee('Sales');
        // Teacher does not have sales.view, so should not see (but our test for teacher unauthorized already covers)
        // We check directly via permission
        $this->assertTrue($owner->hasPermission('sales.view'));
        $this->assertFalse($teacher->hasPermission('sales.view'));
    }

    public function test_inventory_integration_point_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\Sales\SalesInventoryIntegration::class));
        $svc = app(\App\Services\Sales\SalesInventoryIntegration::class);
        $this->assertTrue(method_exists($svc, 'findItem'));
        $this->assertTrue(method_exists($svc, 'checkStock'));
        $this->assertTrue(method_exists($svc, 'availableItems'));
        // Ensure no sales inventory tables created (should not exist)
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('sales_inventory_items'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('sales_stock_levels'));
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(str_contains($haystack, $needle), "Failed asserting that '{$haystack}' contains '{$needle}'");
    }

    private function assertStringNotContains(string $needle, string $haystack): void
    {
        $this->assertFalse(str_contains($haystack, $needle), "Failed asserting that '{$haystack}' does not contain '{$needle}'");
    }
}
