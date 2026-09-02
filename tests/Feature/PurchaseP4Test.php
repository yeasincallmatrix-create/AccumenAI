<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\InventoryItem;
use App\Models\InventoryCategory;
use App\Models\InventoryStockLevel;
use App\Models\InventoryMovement;
use App\Models\InventoryWarehouse;
use App\Models\Journal;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuotation;
use App\Models\Role;
use App\Models\TaxGroup;
use App\Services\Purchase\PurchaseOrderService;
use App\Services\Purchase\PurchaseQuotationService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PurchaseP4Test extends TestCase
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
        return Institute::create(['name' => 'P4 Inst '.uniqid(), 'slug' => 'p4-'.uniqid(), 'country' => $c->name, 'country_id' => $c->id, 'status' => 'active']);
    }

    private function branch(Institute $i): Branch
    {
        return Branch::create(['institute_id' => $i->id, 'name' => 'Br '.uniqid(), 'status' => 'active']);
    }

    private function user(Institute $i, string $role): InstituteUser
    {
        return InstituteUser::create(['institute_id' => $i->id, 'role_id' => Role::where('slug', $role)->firstOrFail()->id, 'first_name' => 'U', 'last_name' => 'User', 'email' => $role.'-'.uniqid().'@test.test', 'phone' => '01700'.rand(100000,999999), 'password_hash' => bcrypt('secret12345'), 'status' => 'active']);
    }

    private function supplier(Institute $i): Party
    {
        return Party::create(['institute_id' => $i->id, 'type' => 'supplier', 'name' => 'Sup '.uniqid(), 'phone' => '017'.rand(10000000,99999999), 'is_active' => true, 'credit_limit' => 0]);
    }

    private function warehouse(Institute $i): InventoryWarehouse
    {
        return InventoryWarehouse::create(['institute_id' => $i->id, 'name' => 'WH '.uniqid(), 'code' => 'WH-'.uniqid(), 'is_active' => true]);
    }

    private function item(Institute $i): InventoryItem
    {
        $cat = InventoryCategory::firstOrCreate(['institute_id' => $i->id, 'name' => 'Cat-'.uniqid()], ['is_active' => true]);
        return InventoryItem::create(['institute_id' => $i->id, 'category_id' => $cat->id, 'name' => 'Item '.uniqid(), 'sku' => 'SKU-'.uniqid(), 'unit' => 'pcs', 'item_type' => 'stock_item', 'purchase_price' => 100, 'selling_price' => 150, 'is_active' => true]);
    }

    public function test_quotation_create_and_numbering(): void
    {
        $inst = $this->institute();
        $sup = $this->supplier($inst);
        $item = $this->item($inst);
        $u = $this->user($inst, 'institute-admin');
        $svc = app(PurchaseQuotationService::class);
        $q = $svc->createDraft($inst->id, null, [
            'supplier_id' => $sup->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(10)->toDateString(),
            'notes' => 'test notes',
            'terms_conditions' => 'test terms',
            'lines' => [['inventory_item_id' => $item->id, 'description' => $item->name, 'quantity' => 5, 'unit_price' => 100, 'discount_amount' => 10, 'tax_rate' => 10]],
        ], $u->id);
        $this->assertStringStartsWith('PQ-', $q->quotation_number);
        $this->assertEquals('draft', $q->status);
        $this->assertEquals(5*100, (float) $q->subtotal);
    }

    public function test_po_create_from_approved_quotation(): void
    {
        $inst = $this->institute();
        $sup = $this->supplier($inst);
        $item = $this->item($inst);
        $wh = $this->warehouse($inst);
        $u = $this->user($inst, 'institute-admin');
        $qSvc = app(PurchaseQuotationService::class);
        $poSvc = app(PurchaseOrderService::class);
        $q = $qSvc->createDraft($inst->id, null, ['supplier_id' => $sup->id, 'quotation_date' => now()->toDateString(), 'lines' => [['inventory_item_id' => $item->id, 'description' => 'd', 'quantity' => 2, 'unit_price' => 50]]], $u->id);
        $qSvc->send($q, $u->id);
        $q->refresh();
        $qSvc->accept($q, $u->id);
        $q->refresh();
        $this->assertEquals('accepted', $q->status);
        $order = $poSvc->createFromQuotation($q, ['order_date' => now()->toDateString(), 'warehouse_id' => $wh->id], $u->id);
        $this->assertEquals($sup->id, $order->supplier_id);
        $this->assertStringStartsWith('PO-', $order->order_number);
        $this->assertEquals(100, (float) $order->grand_total);
        $q->refresh();
        $this->assertEquals($order->id, $q->converted_to_order_id);
    }

    public function test_cannot_create_po_from_draft_quotation(): void
    {
        $inst = $this->institute();
        $sup = $this->supplier($inst);
        $item = $this->item($inst);
        $u = $this->user($inst, 'institute-admin');
        $qSvc = app(PurchaseQuotationService::class);
        $q = $qSvc->createDraft($inst->id, null, ['supplier_id' => $sup->id, 'quotation_date' => now()->toDateString(), 'lines' => [['inventory_item_id' => $item->id, 'description' => 'd', 'quantity' => 1, 'unit_price' => 10]]], $u->id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PurchaseOrderService::class)->createFromQuotation($q, ['order_date' => now()->toDateString()], $u->id);
    }

    public function test_direct_po_creation_still_works(): void
    {
        $inst = $this->institute();
        $sup = $this->supplier($inst);
        $item = $this->item($inst);
        $u = $this->user($inst, 'institute-admin');
        $po = app(PurchaseOrderService::class)->create(['supplier_id' => $sup->id, 'order_date' => now()->toDateString(), 'expected_delivery_date' => now()->addDays(5)->toDateString(), 'notes' => 'n', 'terms_conditions' => 't', 'lines' => [['inventory_item_id' => $item->id, 'description' => 'd', 'quantity' => 3, 'unit_price' => 20, 'discount_amount' => 5, 'tax_rate' => 5]]], $inst->id, null, $u->id);
        $this->assertEquals('draft', $po->status);
        $this->assertEquals(now()->addDays(5)->toDateString(), $po->expected_delivery_date->toDateString());
        $this->assertEquals('n', $po->notes);
    }

    public function test_supplier_preserved_and_no_stock_no_journal(): void
    {
        $inst = $this->institute();
        $sup = $this->supplier($inst);
        $item = $this->item($inst);
        $wh = $this->warehouse($inst);
        $u = $this->user($inst, 'institute-admin');
        $qSvc = app(PurchaseQuotationService::class);
        $q = $qSvc->createDraft($inst->id, null, ['supplier_id' => $sup->id, 'quotation_date' => now()->toDateString(), 'lines' => [['inventory_item_id' => $item->id, 'description' => 'd', 'quantity' => 4, 'unit_price' => 25]]], $u->id);
        $qSvc->send($q, $u->id);
        $q->refresh();
        $qSvc->accept($q, $u->id);
        $q->refresh();
        $movBefore = InventoryMovement::where('institute_id', $inst->id)->count();
        $stockBefore = InventoryStockLevel::where('institute_id', $inst->id)->count();
        $jBefore = Journal::where('institute_id', $inst->id)->count();
        $order = app(PurchaseOrderService::class)->createFromQuotation($q, ['order_date' => now()->toDateString(), 'warehouse_id' => $wh->id], $u->id);
        $this->assertEquals($sup->id, $order->supplier_id);
        $this->assertEquals($movBefore, InventoryMovement::where('institute_id', $inst->id)->count());
        $this->assertEquals($stockBefore, InventoryStockLevel::where('institute_id', $inst->id)->count());
        $this->assertEquals($jBefore, Journal::where('institute_id', $inst->id)->count());
        // also test approval does not create stock/journal (use different approver to satisfy self-approval guard)
        $order2 = app(PurchaseOrderService::class)->create(['supplier_id' => $sup->id, 'order_date' => now()->toDateString(), 'lines' => [['inventory_item_id' => $item->id, 'description' => 'd', 'quantity' => 1, 'unit_price' => 10]]], $inst->id, null, $u->id);
        app(PurchaseOrderService::class)->submit($order2, $u->id);
        $approver = $this->user($inst, 'institute-owner');
        app(PurchaseOrderService::class)->approve($order2, $approver->id);
        $this->assertEquals($movBefore, InventoryMovement::where('institute_id', $inst->id)->count());
        $this->assertEquals($jBefore, Journal::where('institute_id', $inst->id)->count());
    }

    public function test_tenant_and_branch_isolation_for_quotation_convert(): void
    {
        $inst1 = $this->institute();
        $inst2 = $this->institute();
        $sup1 = $this->supplier($inst1);
        $item1 = $this->item($inst1);
        $u1 = $this->user($inst1, 'institute-admin');
        $qSvc = app(PurchaseQuotationService::class);
        $q = $qSvc->createDraft($inst1->id, null, ['supplier_id' => $sup1->id, 'quotation_date' => now()->toDateString(), 'lines' => [['inventory_item_id' => $item1->id, 'description' => 'd', 'quantity' => 1, 'unit_price' => 10]]], $u1->id);
        $qSvc->send($q, $u1->id);
        $q->refresh();
        $qSvc->accept($q, $u1->id);
        $q->refresh();
        $u2 = $this->user($inst2, 'institute-admin');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        // try to convert with wrong institute actor — should fail via branch/tenant check inside service
        app(PurchaseOrderService::class)->createFromQuotation($q, ['order_date' => now()->toDateString()], $u2->id);
    }
}
