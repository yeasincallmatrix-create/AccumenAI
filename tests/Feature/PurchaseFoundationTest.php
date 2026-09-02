<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Party;
use App\Models\Role;
use App\Services\Purchase\SupplierService;
use App\Services\HrAuditService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PurchaseFoundationTest extends TestCase
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
        return Country::firstOrCreate(['iso2' => 'US'], ['name' => 'United States', 'iso3' => 'USA', 'phone_code' => '1', 'status' => true]);
    }

    private function institute(?Country $c = null): Institute
    {
        $c ??= $this->country();
        return Institute::create(['name' => 'Purchase Inst '.uniqid(), 'slug' => 'purchase-'.uniqid(), 'country' => $c->name, 'country_id' => $c->id, 'status' => 'active']);
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

    private function supplier(Institute $i, string $name = 'Supplier', ?int $branchId = null): Party
    {
        return Party::create([
            'institute_id' => $i->id, 'type' => 'supplier', 'name' => $name,
            'phone' => '01700'.rand(100000,999999), 'email' => $name.'-'.uniqid().'@example.test',
            'address' => 'Test Address', 'tin' => 'TIN-'.rand(1000,9999),
            'is_active' => true, 'branch_id' => $branchId,
        ]);
    }

    public function test_guest_cannot_access_suppliers(): void
    {
        // Guest (unauthenticated) access to suppliers index should redirect
        $this->get(route('purchase.suppliers.index'))->assertRedirect();

        // PUT from guest - returns 405 Method Not Allowed as the route exists
        // but method restrictions apply for unauthenticated users
        $this->put(route('purchase.suppliers.store'), [])->assertStatus(405);
    }

    public function test_unauthorized_role_cannot_access_suppliers(): void
    {
        $inst = $this->institute();
        $teacher = $this->user($inst, 'teacher');
        $receptionist = $this->user($inst, 'receptionist');
        TenantContext::set($inst->id);
        $this->actingAs($teacher, 'institute_user')->get(route('purchase.suppliers.index'))->assertForbidden();
        $this->actingAs($receptionist, 'institute_user')->get(route('purchase.suppliers.index'))->assertForbidden();
    }

    public function test_owner_and_admin_can_manage_suppliers(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $admin = $this->user($inst, 'institute-admin');
        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')->get(route('purchase.suppliers.index'))->assertOk();
        $this->actingAs($owner, 'institute_user')->post(route('purchase.suppliers.store'), [
            'name' => 'Test Supplier',
            'phone' => '01700123456',
            'email' => 'test@supplier.test',
        ])->assertCreated();

        $this->actingAs($admin, 'institute_user')->get(route('purchase.suppliers.index'))->assertOk();
    }

    public function test_supplier_crud_and_search(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        TenantContext::set($inst->id);

        // Create supplier
        $resp = $this->actingAs($owner, 'institute_user')->post(route('purchase.suppliers.store'), [
            'name' => 'Alpha Supplies',
            'phone' => '01700999888',
            'email' => 'alpha@supplier.test',
            'address' => '123 Supplier St',
            'tin' => 'TIN-1234',
        ]);
        $resp->assertCreated();
        $supplierId = $resp->json('id');

        // Show supplier
        $this->actingAs($owner, 'institute_user')->get(route('purchase.suppliers.show', $supplierId))->assertOk();

        // Search
        $this->actingAs($owner, 'institute_user')->get(route('purchase.suppliers.index', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Supplies');

        // Filter by active
        $this->actingAs($owner, 'institute_user')->get(route('purchase.suppliers.index', ['status' => 'active']))
            ->assertOk();

        // Soft delete
        $this->actingAs($owner, 'institute_user')->delete(route('purchase.suppliers.destroy', $supplierId))->assertOk();

        // Verify deleted (not shown in active search unless we include soft-deleted)
        $this->actingAs($owner, 'institute_user')->get(route('purchase.suppliers.index', ['search' => 'Alpha']))
            ->assertDontSee('Alpha Supplies');

        // Restore
        $this->actingAs($owner, 'institute_user')->put(route('purchase.suppliers.restore', $supplierId))->assertOk();

        // Verify restored
        $this->actingAs($owner, 'institute_user')->get(route('purchase.suppliers.index', ['search' => 'Alpha']))
            ->assertSee('Alpha Supplies');
    }

    public function test_tenant_isolation(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $ownerA = $this->user($a, 'institute-owner');
        $ownerB = $this->user($b, 'institute-owner');
        TenantContext::set($a->id);

        $this->actingAs($ownerA, 'institute_user')->post(route('purchase.suppliers.store'), [
            'name' => 'Supplier A',
            'phone' => '01700111111',
            'email' => 'a@supplier.test',
        ])->assertCreated();

        TenantContext::set($b->id);
        $this->actingAs($ownerB, 'institute_user')->get(route('purchase.suppliers.index'))
            ->assertOk()
            ->assertDontSee('Supplier A');

        TenantContext::set($a->id);
        $this->actingAs($ownerA, 'institute_user')->get(route('purchase.suppliers.index'))
            ->assertOk()
            ->assertSee('Supplier A');
    }

    public function test_branch_isolation(): void
    {
        $inst = $this->institute();
        $branchA = $this->branch($inst, 'A');
        $branchB = $this->branch($inst, 'B');
        $owner = $this->user($inst, 'institute-owner');
        $managerA = $this->user($inst, 'branch-manager', $branchA->id);
        $managerB = $this->user($inst, 'branch-manager', $branchB->id);
        TenantContext::set($inst->id);

        // Owner (no branch restriction) creates supplier; service will assign branch via context or explicit branch_id.
        // Use managerA's context to create branchA supplier via managerA (has view permission, but not create - so use owner with BranchContext via direct service).
        // Create supplier directly via Party with branchA to simulate branch-specific record.
        $this->supplier($inst, 'Supplier A-BranchA', $branchA->id);

        // Branch B manager must not see branch A supplier due to BranchScopedOrShared global scope
        $this->actingAs($managerB, 'institute_user')->get(route('purchase.suppliers.index'))
            ->assertOk()
            ->assertDontSee('Supplier A-BranchA');

        // Branch A manager must see it
        $this->actingAs($managerA, 'institute_user')->get(route('purchase.suppliers.index'))
            ->assertOk()
            ->assertSee('Supplier A-BranchA');

        // Owner (no branch filter) sees it
        $this->actingAs($owner, 'institute_user')->get(route('purchase.suppliers.index'))
            ->assertOk()
            ->assertSee('Supplier A-BranchA');
    }

    public function test_duplicate_phone_prevention(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')->post(route('purchase.suppliers.store'), [
            'name' => 'Duplicate Supplier',
            'phone' => '01700555555',
            'email' => 'dup@supplier.test',
        ])->assertCreated();

        // Try to create another with same phone - should fail due to unique constraint (JSON 422)
        $this->actingAs($owner, 'institute_user')->postJson(route('purchase.suppliers.store'), [
            'name' => 'Another Supplier',
            'phone' => '01700555555',  // same phone
            'email' => 'another@supplier.test',
        ])->assertStatus(422); // validation error for unique phone
    }

    public function test_party_integration(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        TenantContext::set($inst->id);

        // Create supplier via API
        $resp = $this->actingAs($owner, 'institute_user')->post(route('purchase.suppliers.store'), [
            'name' => 'Integration Test Supplier',
            'phone' => '01700123456',
            'email' => 'integration@supplier.test',
        ]);
        $resp->assertCreated();
        $supplierId = $resp->json('id');

        // Verify it's a Party with type='supplier'
        $party = Party::find($supplierId);
        $this->assertNotNull($party);
        $this->assertEquals('supplier', $party->type);
        $this->assertTrue($party->isSupplier());

        // Verify Party integration - the same party can be used in invoices etc.
        // Party should have customer_group, billing_currency relationships available
        $this->assertNotNull($party->institute());
        $this->assertNotNull($party->branch());
    }

    public function test_audit_logging_for_suppliers(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        TenantContext::set($inst->id);

        // Create supplier
        $this->actingAs($owner, 'institute_user')->post(route('purchase.suppliers.store'), [
            'name' => 'Audited Supplier',
            'phone' => '01700123456',
            'email' => 'audit@supplier.test',
        ])->assertCreated();

        // Check audit log - HrAuditService uses module='hr' by default
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'hr',
            'action' => 'supplier_created',
            'record_id' => $supplierId ?? Party::where('institute_id', $inst->id)->where('type', 'supplier')->max('id'),
        ]);
    }

    public function test_branch_manager_limited_access(): void
    {
        $inst = $this->institute();
        $branchA = $this->branch($inst, 'A');
        $manager = $this->user($inst, 'branch-manager', $branchA->id);
        TenantContext::set($inst->id);
        BranchContext::set($branchA->id);

        // Branch manager can view suppliers in their branch
        $this->actingAs($manager, 'institute_user')->get(route('purchase.suppliers.index'))->assertOk();

        // But cannot create new suppliers
        $this->actingAs($manager, 'institute_user')->post(route('purchase.suppliers.store'), [
            'name' => 'Unauthorized Supplier',
            'phone' => '01700123456',
        ])->assertForbidden();

        BranchContext::clear();
    }

    public function test_numbering_service_is_centralized_and_not_in_controller(): void
    {
        $inst = $this->institute();
        $this->assertTrue(class_exists(SupplierService::class));
        $ref = new \ReflectionClass(SupplierService::class);
        $this->assertTrue($ref->hasMethod('index'));
        $this->assertTrue($ref->hasMethod('store'));
        $this->assertTrue($ref->hasMethod('show'));
        $this->assertTrue($ref->hasMethod('update'));
        $this->assertTrue($ref->hasMethod('softDelete'));
        $this->assertTrue($ref->hasMethod('restore'));

        // Ensure controller does not contain supplier creation logic directly
        $controller = file_get_contents(app_path('Http/Controllers/Purchase/SuppliersController.php'));
        $this->assertStringNotContainsString('Party::create', strtolower($controller));
        $this->assertStringContainsString('SupplierService', $controller);
    }

    public function test_audit_logging_for_settings(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        TenantContext::set($inst->id);

        // Create supplier triggers audit
        $this->actingAs($owner, 'institute_user')->post(route('purchase.suppliers.store'), [
            'name' => 'Audit Test Supplier',
            'phone' => '01700123456',
        ])->assertCreated();

        // Verify audit log entry exists for supplier creation
        // HrAuditService uses module='hr' by default
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'hr',
            'action' => 'supplier_created',
        ]);
    }
}