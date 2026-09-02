<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStockLevel;
use App\Models\InventoryWarehouse;
use App\Models\Journal;
use App\Models\Party;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\TaxGroup;
use App\Services\Purchase\PurchaseOrderService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------ helpers

    private function country(): Country
    {
        return Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]);
    }

    private function institute(?Country $c = null): Institute
    {
        $c ??= $this->country();

        return Institute::create([
            'name' => 'PO Inst '.uniqid(),
            'slug' => 'po-'.uniqid(),
            'country' => $c->name,
            'country_id' => $c->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $i, string $name = 'Branch'): Branch
    {
        return Branch::create(['institute_id' => $i->id, 'name' => $name.' '.uniqid(), 'status' => 'active']);
    }

    private function user(Institute $i, string $roleSlug, ?int $branchId = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $i->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'branch_id' => $branchId,
            'first_name' => ucfirst(str_replace('-', '', $roleSlug)),
            'last_name' => 'User',
            'email' => $roleSlug.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    private function supplier(Institute $i, ?int $branchId = null, array $overrides = []): Party
    {
        return Party::create(array_merge([
            'institute_id' => $i->id,
            'branch_id' => $branchId,
            'type' => 'supplier',
            'name' => 'Supplier '.uniqid(),
            'phone' => '017'.rand(10000000, 99999999),
            'email' => 'sup-'.uniqid().'@example.test',
            'is_active' => true,
            'credit_limit' => 0,
        ], $overrides));
    }

    private function warehouse(Institute $i, ?int $branchId = null): InventoryWarehouse
    {
        return InventoryWarehouse::create([
            'institute_id' => $i->id,
            'branch_id' => $branchId,
            'name' => 'WH '.uniqid(),
            'code' => 'WH-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function item(Institute $i, ?int $branchId = null, array $overrides = []): InventoryItem
    {
        $cat = InventoryCategory::create(['institute_id' => $i->id, 'branch_id' => $branchId, 'name' => 'Cat '.uniqid(), 'is_active' => true]);

        return InventoryItem::create(array_merge([
            'institute_id' => $i->id,
            'branch_id' => $branchId,
            'category_id' => $cat->id,
            'item_type' => 'stock_item',
            'name' => 'Product '.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()),
            'unit' => 'pcs',
            'selling_price' => 100,
            'purchase_price' => 50,
            'is_active' => true,
        ], $overrides));
    }

    private function taxGroup(Institute $i, float $rate = 15): TaxGroup
    {
        return TaxGroup::create(['institute_id' => $i->id, 'name' => 'VAT '.uniqid(), 'type' => 'vat', 'rate' => $rate, 'is_active' => true]);
    }

    private function currency(): Currency
    {
        return Currency::firstOrCreate(['code' => 'BDT'], ['name' => 'Taka', 'symbol' => '৳', 'is_active' => true, 'decimal_places' => 2]);
    }

    private function poData(Party $supplier, ?InventoryWarehouse $warehouse = null, ?array $lines = null, array $extra = []): array
    {
        return array_merge([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse?->id,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'currency_id' => $this->currency()->id,
            'notes' => 'Test notes',
            'terms_conditions' => 'Test terms',
            'discount_type' => 'fixed',
            'discount_amount' => 0,
            'lines' => $lines ?? [
                ['description' => 'Service A', 'quantity' => 2, 'unit_price' => 100, 'discount_amount' => 0, 'discount_type' => 'fixed'],
                ['description' => 'Service B', 'quantity' => 1, 'unit_price' => 50, 'discount_amount' => 0],
            ],
        ], $extra);
    }

    private function service(): PurchaseOrderService
    {
        return app(PurchaseOrderService::class);
    }

    /**
     * Ensure the legacy purchase.manage permission exists and is granted to
     * accountant (and teacher test needs it removed). The app's PurchaseOrderService
     * checks purchase.manage but newer migrations use purchase_order.* slugs.
     * We create purchase.manage on-the-fly for the accountant success case.
     */
    private function ensurePurchaseManageForAccountant(): void
    {
        $perm = Permission::firstOrCreate(['slug' => 'purchase.manage'], ['name' => 'Purchase Manage', 'module' => 'purchase']);
        $role = Role::where('slug', 'accountant')->first();
        if ($role) {
            DB::table('role_permissions')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $perm->id], []);
        }
        // also ensure purchase_order.approve exists for accountant via earlier migration already, but ensure purchase.manage for service check
    }

    // ------------------------------------------------------------ tests

    public function test_can_create_purchase_order_draft(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $supplier = $this->supplier($inst, $branch->id);
        $wh = $this->warehouse($inst, $branch->id);

        $order = $this->service()->create($this->poData($supplier, $wh), $inst->id, $branch->id, $this->user($inst, 'institute-owner')->id);

        $this->assertSame('draft', $order->status);
        $this->assertNotEmpty($order->order_number);
        $this->assertStringStartsWith('PO-', $order->order_number);
        $this->assertEquals($supplier->id, $order->supplier_id);
        $this->assertEquals($wh->id, $order->warehouse_id);
        $this->assertCount(2, $order->lines);
        $this->assertEquals(250, (float) $order->subtotal);
        $this->assertEquals(250, (float) $order->grand_total);
    }

    public function test_calculation_accuracy(): void
    {
        $inst = $this->institute();
        $supplier = $this->supplier($inst);
        $tax = $this->taxGroup($inst, 10);

        // qty*price - discount + tax
        // Line1: 5 * 100 = 500 -10% (=50) => 450 net +10% tax (=45) => 495
        // Line2: 2 * 200 = 400 - fixed 20 => 380 net + no tax => 380
        // subtotal 900, discount 70, tax 45, grand 875
        $order = $this->service()->create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'lines' => [
                ['description' => 'Line1', 'quantity' => 5, 'unit_price' => 100, 'discount_amount' => 10, 'discount_type' => 'percent', 'tax_group_id' => $tax->id],
                ['description' => 'Line2', 'quantity' => 2, 'unit_price' => 200, 'discount_amount' => 20, 'discount_type' => 'fixed'],
            ],
        ], $inst->id, null, $this->user($inst, 'institute-owner')->id);

        $this->assertEquals(900, (float) $order->subtotal);
        $this->assertEquals(70, (float) $order->discount_amount);
        $this->assertEquals(45, (float) $order->tax_amount);
        $this->assertEquals(875, (float) $order->grand_total);

        // per-line check
        $l1 = $order->lines->firstWhere('description', 'Line1');
        $this->assertNotNull($l1);
        $this->assertEquals(495, (float) $l1->line_total);
        $this->assertEquals(45, (float) $l1->tax_amount);
        $this->assertEquals(50, (float) $l1->discount_amount);

        $l2 = $order->lines->firstWhere('description', 'Line2');
        $this->assertEquals(380, (float) $l2->line_total);
    }

    public function test_submit_transitions_draft_to_submitted(): void
    {
        $inst = $this->institute();
        $supplier = $this->supplier($inst);
        $actor = $this->user($inst, 'institute-owner');

        $order = $this->service()->create($this->poData($supplier), $inst->id, null, $actor->id);
        $this->assertSame('draft', $order->status);

        $order = $this->service()->submit($order, $actor->id);

        $this->assertSame('submitted', $order->status);
        $this->assertNotNull($order->submitted_at);
    }

    public function test_approve_requires_permission(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $supplier = $this->supplier($inst, $branch->id);
        $owner = $this->user($inst, 'institute-owner');
        $teacher = $this->user($inst, 'teacher', $branch->id);
        $accountant = $this->user($inst, 'accountant', $branch->id);
        $this->ensurePurchaseManageForAccountant();

        $order = $this->service()->create($this->poData($supplier), $inst->id, $branch->id, $owner->id);
        $order = $this->service()->submit($order, $owner->id);

        // Teacher cannot approve via service
        try {
            $this->service()->approve($order, $teacher->id);
            $this->fail('Teacher should not be able to approve');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('permission', strtolower($e->getMessage()));
            $this->assertSame('submitted', $order->fresh()->status);
        }

        // Also via HTTP, teacher gets 403 (route middleware checks purchase.manage)
        // Ensure purchase.manage not granted to teacher
        TenantContext::set($inst->id);
        BranchContext::set($branch->id);
        $this->actingAs($teacher, 'institute_user')
            ->post(route('purchase.orders.approve', $order))
            ->assertForbidden();

        // Accountant can approve via service (has purchase.manage now)
        $order = $this->service()->approve($order->fresh(), $accountant->id);
        $this->assertSame('approved', $order->status);
        $this->assertEquals($accountant->id, $order->approved_by);

        // Accountant via HTTP also works for a second order: need to handle route permission mismatch
        // Route uses purchase.manage (we just ensured accountant has it) so should redirect
        // For purchase routes, also controller checks purchase.manage via service (same)
        // To avoid middleware mismatch with purchase_order.approve vs purchase.manage, we already granted purchase.manage
        $order2 = $this->service()->create($this->poData($supplier), $inst->id, $branch->id, $owner->id);
        $order2 = $this->service()->submit($order2, $owner->id);
        // grant accountant also purchase_order.approve for route if needed (it currently checks purchase.manage, not purchase_order.approve)
        $this->actingAs($accountant, 'institute_user')
            ->post(route('purchase.orders.approve', $order2))
            ->assertRedirect();
        $this->assertSame('approved', $order2->fresh()->status);

        BranchContext::clear();
    }

    public function test_approved_order_cannot_be_edited(): void
    {
        $inst = $this->institute();
        $supplier = $this->supplier($inst);
        $owner = $this->user($inst, 'institute-owner');
        // use second owner as approver (isOwner=true passes permission)
        $approver = $this->user($inst, 'institute-owner');

        $order = $this->service()->create($this->poData($supplier), $inst->id, null, $owner->id);
        $order = $this->service()->submit($order, $owner->id);
        $order = $this->service()->approve($order, $approver->id);

        try {
            $this->service()->update($order, ['notes' => 'hack', 'lines' => [['description' => 'Hack', 'quantity' => 1, 'unit_price' => 1]]], $owner->id);
            $this->fail('Approved order should not be editable');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Only draft', $e->getMessage());
        }

        // Also via HTTP
        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user')
            ->put(route('purchase.orders.update', $order), [
                'supplier_id' => $supplier->id,
                'order_date' => now()->toDateString(),
                'lines' => [['description' => 'Hack', 'quantity' => 1, 'unit_price' => 1]],
            ])
            ->assertSessionHasErrors();
    }

    public function test_cancel_draft(): void
    {
        $inst = $this->institute();
        $supplier = $this->supplier($inst);
        $actor = $this->user($inst, 'institute-owner');

        $order = $this->service()->create($this->poData($supplier), $inst->id, null, $actor->id);
        $order = $this->service()->cancel($order, $actor->id);

        $this->assertSame('cancelled', $order->status);
    }

    public function test_cancel_submitted(): void
    {
        $inst = $this->institute();
        $supplier = $this->supplier($inst);
        $actor = $this->user($inst, 'institute-owner');

        $order = $this->service()->create($this->poData($supplier), $inst->id, null, $actor->id);
        $order = $this->service()->submit($order, $actor->id);
        $order = $this->service()->cancel($order, $actor->id);

        $this->assertSame('cancelled', $order->status);
    }

    public function test_cancel_approved(): void
    {
        $inst = $this->institute();
        $supplier = $this->supplier($inst);
        $owner = $this->user($inst, 'institute-owner');
        $approver = $this->user($inst, 'institute-owner');

        $order = $this->service()->create($this->poData($supplier), $inst->id, null, $owner->id);
        $order = $this->service()->submit($order, $owner->id);
        $order = $this->service()->approve($order, $approver->id);
        $order = $this->service()->cancel($order, $owner->id);

        $this->assertSame('cancelled', $order->status);
    }

    public function test_cannot_cancel_fully_received_or_closed(): void
    {
        $inst = $this->institute();
        $supplier = $this->supplier($inst);
        $owner = $this->user($inst, 'institute-owner');
        $approver = $this->user($inst, 'institute-owner');

        $order = $this->service()->create($this->poData($supplier), $inst->id, null, $owner->id);
        $order = $this->service()->submit($order, $owner->id);
        $order = $this->service()->approve($order, $approver->id);

        // Simulate fully_received via direct status transition (approved -> fully_received is allowed)
        $order->update(['status' => PurchaseOrder::STATUS_FULLY_RECEIVED]);
        $order->refresh();

        try {
            $this->service()->cancel($order, $owner->id);
            $this->fail('Fully received should not be cancellable');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('cannot be cancelled', strtolower($e->getMessage()));
        }

        // closed also cannot cancel
        $closed = $this->service()->create($this->poData($supplier), $inst->id, null, $owner->id);
        $closed = $this->service()->submit($closed, $owner->id);
        $closed = $this->service()->approve($closed, $approver->id);
        $closed->update(['status' => PurchaseOrder::STATUS_FULLY_RECEIVED]);
        $closed = $this->service()->close($closed->fresh(), $approver->id);
        $this->assertSame('closed', $closed->status);

        try {
            $this->service()->cancel($closed, $owner->id);
            $this->fail('Closed should not be cancellable');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('cannot be cancelled', strtolower($e->getMessage()));
        }
    }

    public function test_reject_submitted(): void
    {
        $inst = $this->institute();
        $supplier = $this->supplier($inst);
        $owner = $this->user($inst, 'institute-owner');
        $approver = $this->user($inst, 'institute-owner');

        $order = $this->service()->create($this->poData($supplier), $inst->id, null, $owner->id);
        $order = $this->service()->submit($order, $owner->id);
        $order = $this->service()->reject($order, $approver->id);

        $this->assertSame('cancelled', $order->status);

        // Only submitted can be rejected, draft should fail
        $draft = $this->service()->create($this->poData($supplier), $inst->id, null, $owner->id);
        try {
            $this->service()->reject($draft, $approver->id);
            $this->fail('Draft should not be rejectable');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_tenant_isolation(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $supplierA = $this->supplier($a);
        $supplierB = $this->supplier($b);
        $service = $this->service();

        $order = $service->create($this->poData($supplierA), $a->id, null, $this->user($a, 'institute-owner')->id);

        TenantContext::set($b->id);
        $this->assertNull(PurchaseOrder::query()->find($order->id));
        // cross-tenant edit should be invisible via 404 HTTP
        $bOwner = $this->user($b, 'institute-owner');
        $this->actingAs($bOwner, 'institute_user')
            ->get(route('purchase.orders.show', $order))
            ->assertNotFound();
        $this->actingAs($bOwner, 'institute_user')
            ->post(route('purchase.orders.submit', $order))
            ->assertNotFound();

        TenantContext::set($a->id);
        $this->assertNotNull(PurchaseOrder::query()->find($order->id));

        // create with other institute supplier must fail
        try {
            $service->create($this->poData($supplierB), $a->id, null, $this->user($a, 'institute-owner')->id);
            $this->fail('Cross-tenant supplier should be rejected');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_branch_isolation(): void
    {
        $inst = $this->institute();
        $branchA = $this->branch($inst, 'A');
        $branchB = $this->branch($inst, 'B');
        $supplierA = $this->supplier($inst, $branchA->id);
        $service = $this->service();

        $order = $service->create($this->poData($supplierA), $inst->id, $branchA->id, $this->user($inst, 'institute-owner')->id);

        TenantContext::set($inst->id);
        BranchContext::set($branchB->id);
        $this->assertNull(PurchaseOrder::query()->find($order->id));

        $mgrB = $this->user($inst, 'branch-manager', $branchB->id);
        $this->actingAs($mgrB, 'institute_user')
            ->get(route('purchase.orders.show', $order))
            ->assertNotFound();

        BranchContext::set($branchA->id);
        $this->assertNotNull(PurchaseOrder::query()->find($order->id));

        // branch manager from B cannot see A via HTTP submit
        BranchContext::set($branchB->id);
        $this->actingAs($mgrB, 'institute_user')
            ->post(route('purchase.orders.submit', $order))
            ->assertNotFound();
    }

    public function test_supplier_validation(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $aOwner = $this->user($a, 'institute-owner');
        $bSupplier = $this->supplier($b);
        $inactiveSupplier = $this->supplier($a, null, ['is_active' => false]);
        $customer = Party::create([
            'institute_id' => $a->id,
            'branch_id' => null,
            'type' => 'customer',
            'name' => 'Customer '.uniqid(),
            'phone' => '017'.rand(10000000, 99999999),
            'is_active' => true,
            'credit_limit' => 0,
        ]);

        // foreign institute supplier
        try {
            $this->service()->create($this->poData($bSupplier), $a->id, null, $aOwner->id);
            $this->fail('Foreign supplier should be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('supplier_id', $e->errors());
        }

        // inactive supplier
        try {
            $this->service()->create($this->poData($inactiveSupplier), $a->id, null, $aOwner->id);
            $this->fail('Inactive supplier should be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('supplier_id', $e->errors());
        }

        // customer type not supplier
        try {
            $this->service()->create($this->poData($customer), $a->id, null, $aOwner->id);
            $this->fail('Customer type should be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('supplier_id', $e->errors());
        }

        // null supplier_id
        try {
            $data = $this->poData($this->supplier($a));
            unset($data['supplier_id']);
            $this->service()->create($data, $a->id, null, $aOwner->id);
            $this->fail('Missing supplier should be rejected');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_item_validation(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $supplier = $this->supplier($a);
        $aOwner = $this->user($a, 'institute-owner');
        $itemB = $this->item($b);
        $inactiveItem = $this->item($a, null, ['is_active' => false]);

        // foreign institute item
        try {
            $this->service()->create([
                'supplier_id' => $supplier->id,
                'order_date' => now()->toDateString(),
                'lines' => [['inventory_item_id' => $itemB->id, 'description' => $itemB->name, 'quantity' => 1, 'unit_price' => 100]],
            ], $a->id, null, $aOwner->id);
            $this->fail('Foreign item should be rejected');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        // inactive item
        try {
            $this->service()->create([
                'supplier_id' => $supplier->id,
                'order_date' => now()->toDateString(),
                'lines' => [['inventory_item_id' => $inactiveItem->id, 'description' => $inactiveItem->name, 'quantity' => 1, 'unit_price' => 100]],
            ], $a->id, null, $aOwner->id);
            $this->fail('Inactive item should be rejected');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        // missing description and no item
        try {
            $this->service()->create([
                'supplier_id' => $supplier->id,
                'order_date' => now()->toDateString(),
                'lines' => [['quantity' => 1, 'unit_price' => 100]],
            ], $a->id, null, $aOwner->id);
            $this->fail('Missing description should be rejected');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        // invalid tax_group
        $foreignTax = $this->taxGroup($b, 10);
        try {
            $this->service()->create([
                'supplier_id' => $supplier->id,
                'order_date' => now()->toDateString(),
                'lines' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 100, 'tax_group_id' => $foreignTax->id]],
            ], $a->id, null, $aOwner->id);
            $this->fail('Foreign tax group should be rejected');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        // branch scoped item isolation
        $branchA = $this->branch($a, 'A');
        $branchB = $this->branch($a, 'B');
        $branchItem = $this->item($a, $branchA->id);
        $supplierB = $this->supplier($a, $branchB->id);
        try {
            $this->service()->create([
                'supplier_id' => $supplierB->id,
                'order_date' => now()->toDateString(),
                'lines' => [['inventory_item_id' => $branchItem->id, 'description' => $branchItem->name, 'quantity' => 1, 'unit_price' => 50]],
            ], $a->id, $branchB->id, $aOwner->id);
            $this->fail('Branch isolated item should be rejected');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_no_inventory_mutation_on_approve(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $supplier = $this->supplier($inst, $branch->id);
        $wh = $this->warehouse($inst, $branch->id);
        $item = $this->item($inst, $branch->id, ['purchase_price' => 10]);
        $level = InventoryStockLevel::create([
            'institute_id' => $inst->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $wh->id,
            'item_id' => $item->id,
            'quantity' => 20,
            'avg_cost' => 10,
        ]);
        $owner = $this->user($inst, 'institute-owner');
        $approver = $this->user($inst, 'institute-owner');

        $beforeQty = (float) $level->fresh()->quantity;
        $beforeMovements = InventoryMovement::withoutGlobalScopes()->where('institute_id', $inst->id)->where('item_id', $item->id)->count();

        $order = $this->service()->create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $wh->id,
            'order_date' => now()->toDateString(),
            'lines' => [['inventory_item_id' => $item->id, 'description' => $item->name, 'quantity' => 5, 'unit_price' => 100]],
        ], $inst->id, $branch->id, $owner->id);

        $this->assertEquals($beforeQty, (float) $level->fresh()->quantity);
        $this->assertEquals($beforeMovements, InventoryMovement::withoutGlobalScopes()->where('institute_id', $inst->id)->where('item_id', $item->id)->count());

        $order = $this->service()->submit($order, $owner->id);
        $order = $this->service()->approve($order, $approver->id);

        $this->assertEquals($beforeQty, (float) $level->fresh()->quantity, 'Approve must not mutate stock level');
        $this->assertEquals($beforeMovements, InventoryMovement::withoutGlobalScopes()->where('institute_id', $inst->id)->where('item_id', $item->id)->count(), 'Approve must not create movements');
        $this->assertEquals(20, (float) $level->fresh()->quantity);
    }

    public function test_no_accounting_mutation_on_create_and_approve(): void
    {
        $inst = $this->institute();
        $supplier = $this->supplier($inst);
        $owner = $this->user($inst, 'institute-owner');
        $approver = $this->user($inst, 'institute-owner');

        $beforeJournals = Journal::withoutGlobalScopes()->where('institute_id', $inst->id)->count();

        // Ensure JournalPostingService is not called: we spy via mock expectation
        $mock = \Mockery::mock(\App\Services\Accounting\JournalPostingService::class);
        $mock->shouldNotReceive('create');
        $mock->shouldNotReceive('post');
        $mock->shouldNotReceive('reverse');
        $this->app->instance(\App\Services\Accounting\JournalPostingService::class, $mock);

        $order = $this->service()->create($this->poData($supplier), $inst->id, null, $owner->id);
        $this->assertEquals($beforeJournals, Journal::withoutGlobalScopes()->where('institute_id', $inst->id)->count(), 'Create must not post journals');

        $order = $this->service()->submit($order, $owner->id);
        $this->assertEquals($beforeJournals, Journal::withoutGlobalScopes()->where('institute_id', $inst->id)->count(), 'Submit must not post journals');

        $order = $this->service()->approve($order, $approver->id);
        $this->assertEquals($beforeJournals, Journal::withoutGlobalScopes()->where('institute_id', $inst->id)->count(), 'Approve must not post journals');

        \Mockery::close();
    }

    public function test_discount_and_tax_calculation_variants(): void
    {
        $inst = $this->institute();
        $supplier = $this->supplier($inst);
        $tax5 = $this->taxGroup($inst, 5);
        $tax15 = $this->taxGroup($inst, 15);
        $actor = $this->user($inst, 'institute-owner');

        // fixed discount + tax 5%
        // 10*50=500 -20 fixed=480 +5% (24)=504
        $o1 = $this->service()->create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'lines' => [['description' => 'A', 'quantity' => 10, 'unit_price' => 50, 'discount_amount' => 20, 'discount_type' => 'fixed', 'tax_group_id' => $tax5->id]],
        ], $inst->id, null, $actor->id);
        $this->assertEquals(500, (float) $o1->subtotal);
        $this->assertEquals(20, (float) $o1->discount_amount);
        $this->assertEquals(24, (float) $o1->tax_amount);
        $this->assertEquals(504, (float) $o1->grand_total);

        // percent discount 10% + tax 15%
        // 2*100=200 -10% (20)=180 +15% (27)=207
        $o2 = $this->service()->create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'lines' => [['description' => 'B', 'quantity' => 2, 'unit_price' => 100, 'discount_amount' => 10, 'discount_type' => 'percent', 'tax_group_id' => $tax15->id]],
        ], $inst->id, null, $actor->id);
        $this->assertEquals(200, (float) $o2->subtotal);
        $this->assertEquals(20, (float) $o2->discount_amount);
        $this->assertEquals(27, (float) $o2->tax_amount);
        $this->assertEquals(207, (float) $o2->grand_total);

        // discount_rate variant (service handles discount_rate)
        // 4*25=100 -25% via discount_rate=25 => 75 +0 tax =>75
        $o3 = $this->service()->create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'lines' => [['description' => 'C', 'quantity' => 4, 'unit_price' => 25, 'discount_rate' => 25, 'discount_type' => 'percent']],
        ], $inst->id, null, $actor->id);
        $this->assertEquals(100, (float) $o3->subtotal);
        $this->assertEquals(25, (float) $o3->discount_amount);
        $this->assertEquals(75, (float) $o3->grand_total);

        // discount exceeding line subtotal capped
        // 1*10=10 -20 fixed capped to 10 => net 0 => grand 0
        $o4 = $this->service()->create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'lines' => [['description' => 'D', 'quantity' => 1, 'unit_price' => 10, 'discount_amount' => 20, 'discount_type' => 'fixed']],
        ], $inst->id, null, $actor->id);
        $this->assertEquals(10, (float) $o4->subtotal);
        $this->assertEquals(10, (float) $o4->discount_amount);
        $this->assertEquals(0, (float) $o4->grand_total);

        // multi-line with mixed tax
        $o5 = $this->service()->create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'lines' => [
                ['description' => 'L1', 'quantity' => 1, 'unit_price' => 100, 'tax_group_id' => $tax5->id],
                ['description' => 'L2', 'quantity' => 1, 'unit_price' => 100, 'discount_amount' => 10],
            ],
        ], $inst->id, null, $actor->id);
        // L1 with tax5 (5%) => 105
        $this->assertEquals(200, (float) $o5->subtotal);
        $l1 = $o5->lines->firstWhere('description', 'L1');
        $this->assertEquals(5, (float) $l1->tax_amount);
    }

    public function test_lifecycle_invalid_transition(): void
    {
        $inst = $this->institute();
        $supplier = $this->supplier($inst);
        $owner = $this->user($inst, 'institute-owner');
        $approver = $this->user($inst, 'institute-owner');

        $order = $this->service()->create($this->poData($supplier), $inst->id, null, $owner->id);

        // draft cannot approve directly
        try {
            $this->service()->approve($order, $approver->id);
            $this->fail('Draft should not be approvable directly');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Cannot transition', $e->getMessage());
            $this->assertSame('draft', $order->fresh()->status);
        }

        // draft cannot reject
        try {
            $this->service()->reject($order, $approver->id);
            $this->fail('Draft should not be rejectable');
        } catch (ValidationException $e) {
            $this->assertSame('draft', $order->fresh()->status);
        }

        // draft cannot close
        try {
            $this->service()->close($order, $approver->id);
            $this->fail('Draft should not be closable');
        } catch (ValidationException $e) {
            $this->assertSame('draft', $order->fresh()->status);
        }

        // submitted cannot close (only fully_received)
        $order = $this->service()->submit($order, $owner->id);
        try {
            $this->service()->close($order, $approver->id);
            $this->fail('Submitted should not be closable');
        } catch (ValidationException $e) {
            $this->assertSame('submitted', $order->fresh()->status);
        }

        // cannot submit twice
        try {
            $this->service()->submit($order, $owner->id);
            $this->fail('Double submit should fail');
        } catch (ValidationException $e) {
            $this->assertSame('submitted', $order->fresh()->status);
        }

        // approved cannot submit again
        $order = $this->service()->approve($order, $approver->id);
        try {
            $this->service()->submit($order, $owner->id);
            $this->fail('Approved should not be submittable');
        } catch (ValidationException $e) {
            $this->assertSame('approved', $order->fresh()->status);
        }
    }

    public function test_unauthorized_controller_actions(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $supplier = $this->supplier($inst, $branch->id);
        $owner = $this->user($inst, 'institute-owner');
        $teacher = $this->user($inst, 'teacher', $branch->id);
        $receptionist = $this->user($inst, 'receptionist', $branch->id);

        $order = $this->service()->create($this->poData($supplier), $inst->id, $branch->id, $owner->id);

        TenantContext::set($inst->id);
        BranchContext::set($branch->id);

        // teacher has no purchase.view/create/manage
        $this->actingAs($teacher, 'institute_user')->get(route('purchase.orders.index'))->assertForbidden();
        $this->actingAs($teacher, 'institute_user')->get(route('purchase.orders.show', $order))->assertForbidden();

        // receptionist also forbidden
        $this->actingAs($receptionist, 'institute_user')->get(route('purchase.orders.index'))->assertForbidden();

        // owner can view
        $this->actingAs($owner, 'institute_user')->get(route('purchase.orders.index'))->assertOk();
        $this->actingAs($owner, 'institute_user')->get(route('purchase.orders.show', $order))->assertOk();

        // submit requires purchase.update? Actually route needs purchase.update, teacher forbidden
        $order2 = $this->service()->create($this->poData($supplier), $inst->id, $branch->id, $owner->id);
        $this->actingAs($teacher, 'institute_user')->post(route('purchase.orders.submit', $order2))->assertForbidden();

        // owner can submit
        $this->actingAs($owner, 'institute_user')->post(route('purchase.orders.submit', $order2))->assertRedirect();
        $this->assertSame('submitted', $order2->fresh()->status);
    }
}
