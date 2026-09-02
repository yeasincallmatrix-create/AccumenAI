<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStockLevel;
use App\Models\InventoryWarehouse;
use App\Models\Party;
use App\Models\SalesDelivery;
use App\Models\SalesOrder;
use App\Services\Inventory\InventoryStockService;
use App\Services\Sales\DeliveryService;
use App\Services\Sales\SalesOrderService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesDeliveryTest extends TestCase
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
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(string $name = 'Del Inst'): Institute
    {
        $c = $this->country();
        $inst = Institute::create([
            'name' => $name . ' ' . uniqid(),
            'slug' => str()->slug($name . ' ' . uniqid()),
            'country' => $c->name,
            'country_id' => $c->id,
            'industry' => 'retail',
            'status' => 'active',
        ]);
        app(\App\Services\Accounting\AccountingSetupService::class)->setupForInstitute($inst->id);
        return $inst;
    }

    private function branch(Institute $inst, string $name = 'Main'): Branch
    {
        $branch = Branch::create(['institute_id' => $inst->id, 'name' => $name . uniqid(), 'status' => 'active']);
        app(\App\Services\Accounting\AccountingSetupService::class)->setupForInstitute($inst->id, $branch->id);
        return $branch;
    }

    private function currency(): Currency
    {
        return Currency::firstOrCreate(['code' => 'BDT'], ['name' => 'Taka', 'symbol' => '৳', 'is_active' => true, 'decimal_places' => 2]);
    }

    private function customer(Institute $inst, ?Branch $branch): Party
    {
        return Party::create([
            'institute_id' => $inst->id,
            'branch_id' => $branch?->id,
            'type' => 'customer',
            'name' => 'Cust ' . uniqid(),
            'phone' => '01' . rand(100000000, 999999999),
            'is_active' => true,
        ]);
    }

    private function supplier(Institute $inst, ?Branch $branch): Party
    {
        return Party::create([
            'institute_id' => $inst->id,
            'branch_id' => $branch?->id,
            'type' => 'supplier',
            'name' => 'Sup ' . uniqid(),
            'phone' => '01' . rand(100000000, 999999999),
            'is_active' => true,
        ]);
    }

    private function warehouse(Institute $inst, ?Branch $branch): InventoryWarehouse
    {
        return InventoryWarehouse::create([
            'institute_id' => $inst->id,
            'branch_id' => $branch?->id,
            'name' => 'WH ' . uniqid(),
            'code' => 'WH-' . uniqid(),
            'is_active' => true,
        ]);
    }

    private function product(Institute $inst, ?Branch $branch, string $type = 'stock_item', float $price = 100): InventoryItem
    {
        return InventoryItem::create([
            'institute_id' => $inst->id,
            'branch_id' => $branch?->id,
            'name' => 'Prod ' . uniqid(),
            'sku' => 'SKU-' . uniqid(),
            'item_type' => $type,
            'selling_price' => $price,
            'purchase_price' => $price * 0.7,
            'is_active' => true,
        ]);
    }

    private function receiveStock(Institute $inst, ?Branch $branch, InventoryWarehouse $wh, InventoryItem $item, float $qty, float $cost = 70): void
    {
        $supplier = $this->supplier($inst, $branch);
        app(InventoryStockService::class)->receivePurchase(
            $inst->id,
            $branch?->id,
            $supplier,
            $wh->id,
            [['item_id' => $item->id, 'quantity' => $qty, 'unit_cost' => $cost]],
            null,
            ['reference_type' => 'test_receipt']
        );
    }

    private function createApprovedOrder(Institute $inst, ?Branch $branch, Party $customer, Currency $currency, array $lines): SalesOrder
    {
        $service = app(SalesOrderService::class);
        $order = $service->createDraft($inst->id, $branch?->id, [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'currency_id' => $currency->id,
            'lines' => $lines,
        ], null);
        $order = $service->submit($order);
        $order = $service->approve($order);
        return $order->fresh('lines');
    }

    public function test_full_delivery_with_stockable_product(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $wh = $this->warehouse($inst, $branch);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();
        $product = $this->product($inst, $branch, 'stock_item', 100);
        $this->receiveStock($inst, $branch, $wh, $product, 100);

        $order = $this->createApprovedOrder($inst, $branch, $customer, $currency, [
            ['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 10, 'unit_price' => 100],
        ]);

        $deliveryService = app(DeliveryService::class);
        $delivery = $deliveryService->createDelivery($inst->id, $branch->id, $order->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $wh->id,
            'lines' => [
                ['order_line_id' => $order->lines[0]->id, 'delivery_quantity' => 10],
            ],
        ], null);

        $this->assertSame('draft', $delivery->status);
        $this->assertEquals(10, (float) $delivery->lines[0]->delivery_quantity);

        $delivery = $deliveryService->confirmDelivery($delivery);

        $this->assertSame('confirmed', $delivery->status);
        $this->assertNotNull($delivery->delivered_at);

        // Inventory OUT created
        $movement = InventoryMovement::withoutGlobalScopes()->where('reference_type', 'sales_delivery')->where('reference_id', $delivery->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-10, (float) $movement->quantity);

        $level = InventoryStockLevel::withoutGlobalScopes()->where('institute_id', $inst->id)->where('item_id', $product->id)->first();
        $this->assertEquals(90, (float) $level->quantity);
    }

    public function test_partial_delivery(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $wh = $this->warehouse($inst, $branch);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();
        $product = $this->product($inst, $branch, 'stock_item', 100);
        $this->receiveStock($inst, $branch, $wh, $product, 100);

        $order = $this->createApprovedOrder($inst, $branch, $customer, $currency, [
            ['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 100, 'unit_price' => 10],
        ]);

        $svc = app(DeliveryService::class);
        $d1 = $svc->createDelivery($inst->id, $branch->id, $order->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $wh->id,
            'lines' => [['order_line_id' => $order->lines[0]->id, 'delivery_quantity' => 40]],
        ], null);
        $svc->confirmDelivery($d1);

        $this->assertEquals(60, $svc->remainingQuantityForOrderLine($order->lines[0]->fresh()));

        $d2 = $svc->createDelivery($inst->id, $branch->id, $order->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $wh->id,
            'lines' => [['order_line_id' => $order->lines[0]->id, 'delivery_quantity' => 35]],
        ], null);
        $svc->confirmDelivery($d2);

        $this->assertEquals(25, $svc->remainingQuantityForOrderLine($order->lines[0]->fresh()));
        $this->assertFalse($svc->isOrderFullyDelivered($order->fresh('lines')));
    }

    public function test_multiple_deliveries_until_fully_delivered(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $wh = $this->warehouse($inst, $branch);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();
        $product = $this->product($inst, $branch, 'stock_item', 50);
        $this->receiveStock($inst, $branch, $wh, $product, 100);

        $order = $this->createApprovedOrder($inst, $branch, $customer, $currency, [
            ['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 10, 'unit_price' => 50],
        ]);

        $svc = app(DeliveryService::class);
        foreach ([4, 3, 3] as $qty) {
            $d = $svc->createDelivery($inst->id, $branch->id, $order->id, [
                'delivery_date' => now()->toDateString(),
                'warehouse_id' => $wh->id,
                'lines' => [['order_line_id' => $order->lines[0]->id, 'delivery_quantity' => $qty]],
            ], null);
            $svc->confirmDelivery($d);
        }

        $this->assertTrue($svc->isOrderFullyDelivered($order->fresh('lines')));
        $this->assertEquals(0, $svc->remainingQuantityForOrderLine($order->lines[0]->fresh()));
    }

    public function test_non_stock_service_no_inventory_movement(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $wh = $this->warehouse($inst, $branch);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();
        $serviceItem = $this->product($inst, $branch, 'service_consumable', 200);

        $order = $this->createApprovedOrder($inst, $branch, $customer, $currency, [
            ['inventory_item_id' => $serviceItem->id, 'description' => $serviceItem->name, 'quantity' => 5, 'unit_price' => 200],
        ]);

        $svc = app(DeliveryService::class);
        $delivery = $svc->createDelivery($inst->id, $branch->id, $order->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $wh->id,
            'lines' => [['order_line_id' => $order->lines[0]->id, 'delivery_quantity' => 5]],
        ], null);
        $svc->confirmDelivery($delivery);

        $this->assertSame('confirmed', $delivery->fresh()->status);
        $moves = InventoryMovement::withoutGlobalScopes()->where('reference_type', 'sales_delivery')->where('reference_id', $delivery->id)->count();
        $this->assertEquals(0, $moves);
    }

    public function test_insufficient_stock_blocked(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $wh = $this->warehouse($inst, $branch);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();
        $product = $this->product($inst, $branch, 'stock_item', 100);
        $this->receiveStock($inst, $branch, $wh, $product, 5);

        $order = $this->createApprovedOrder($inst, $branch, $customer, $currency, [
            ['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 10, 'unit_price' => 100],
        ]);

        $svc = app(DeliveryService::class);
        $delivery = $svc->createDelivery($inst->id, $branch->id, $order->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $wh->id,
            'lines' => [['order_line_id' => $order->lines[0]->id, 'delivery_quantity' => 10]],
        ], null);

        $this->expectException(ValidationException::class);
        $svc->confirmDelivery($delivery);
        $this->assertSame('draft', $delivery->fresh()->status);
        $level = InventoryStockLevel::withoutGlobalScopes()->where('item_id', $product->id)->first();
        $this->assertEquals(5, (float) $level->quantity);
    }

    public function test_duplicate_confirmation_blocked(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $wh = $this->warehouse($inst, $branch);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();
        $product = $this->product($inst, $branch, 'stock_item', 100);
        $this->receiveStock($inst, $branch, $wh, $product, 50);

        $order = $this->createApprovedOrder($inst, $branch, $customer, $currency, [
            ['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 10, 'unit_price' => 100],
        ]);

        $svc = app(DeliveryService::class);
        $delivery = $svc->createDelivery($inst->id, $branch->id, $order->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $wh->id,
            'lines' => [['order_line_id' => $order->lines[0]->id, 'delivery_quantity' => 5]],
        ], null);
        $svc->confirmDelivery($delivery);

        $this->expectException(ValidationException::class);
        $svc->confirmDelivery($delivery->fresh());

        $count = InventoryMovement::withoutGlobalScopes()->where('reference_type', 'sales_delivery')->where('reference_id', $delivery->id)->count();
        $this->assertEquals(1, $count);
    }

    public function test_delivery_quantity_exceeds_remaining_blocked(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $wh = $this->warehouse($inst, $branch);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();
        $product = $this->product($inst, $branch, 'stock_item', 100);
        $this->receiveStock($inst, $branch, $wh, $product, 100);

        $order = $this->createApprovedOrder($inst, $branch, $customer, $currency, [
            ['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 10, 'unit_price' => 100],
        ]);

        $svc = app(DeliveryService::class);
        $d1 = $svc->createDelivery($inst->id, $branch->id, $order->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $wh->id,
            'lines' => [['order_line_id' => $order->lines[0]->id, 'delivery_quantity' => 6]],
        ], null);
        $svc->confirmDelivery($d1);

        $this->expectException(ValidationException::class);
        $svc->createDelivery($inst->id, $branch->id, $order->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $wh->id,
            'lines' => [['order_line_id' => $order->lines[0]->id, 'delivery_quantity' => 5]],
        ], null);
    }

    public function test_cancellation_reverses_inventory(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $wh = $this->warehouse($inst, $branch);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();
        $product = $this->product($inst, $branch, 'stock_item', 100);
        $this->receiveStock($inst, $branch, $wh, $product, 50);

        $order = $this->createApprovedOrder($inst, $branch, $customer, $currency, [
            ['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 10, 'unit_price' => 100],
        ]);

        $svc = app(DeliveryService::class);
        $delivery = $svc->createDelivery($inst->id, $branch->id, $order->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $wh->id,
            'lines' => [['order_line_id' => $order->lines[0]->id, 'delivery_quantity' => 10]],
        ], null);
        $svc->confirmDelivery($delivery);
        $this->assertEquals(40, (float) InventoryStockLevel::withoutGlobalScopes()->where('item_id', $product->id)->first()->quantity);

        $svc->cancelDelivery($delivery->fresh());
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertEquals(50, (float) InventoryStockLevel::withoutGlobalScopes()->where('item_id', $product->id)->first()->quantity);
    }

    public function test_cancellation_of_draft_no_inventory(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();
        $product = $this->product($inst, $branch, 'stock_item', 100);

        $order = $this->createApprovedOrder($inst, $branch, $customer, $currency, [
            ['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 10, 'unit_price' => 100],
        ]);

        $svc = app(DeliveryService::class);
        $delivery = $svc->createDelivery($inst->id, $branch->id, $order->id, [
            'delivery_date' => now()->toDateString(),
            'lines' => [['order_line_id' => $order->lines[0]->id, 'delivery_quantity' => 5]],
        ], null);

        $svc->cancelDelivery($delivery);
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertEquals(0, InventoryMovement::withoutGlobalScopes()->where('reference_type', 'sales_delivery')->where('reference_id', $delivery->id)->count());
    }

    public function test_transaction_rollback_on_insufficient_stock(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $wh = $this->warehouse($inst, $branch);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();
        $product = $this->product($inst, $branch, 'stock_item', 100);
        $this->receiveStock($inst, $branch, $wh, $product, 2);

        $order = $this->createApprovedOrder($inst, $branch, $customer, $currency, [
            ['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 10, 'unit_price' => 100],
        ]);

        $svc = app(DeliveryService::class);
        $delivery = $svc->createDelivery($inst->id, $branch->id, $order->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $wh->id,
            'lines' => [['order_line_id' => $order->lines[0]->id, 'delivery_quantity' => 5]],
        ], null);

        try {
            $svc->confirmDelivery($delivery);
            $this->fail('Should have thrown insufficient stock');
        } catch (ValidationException $e) {
            $this->assertSame('draft', $delivery->fresh()->status);
            $this->assertEquals(2, (float) InventoryStockLevel::withoutGlobalScopes()->where('item_id', $product->id)->first()->quantity);
            $this->assertEquals(0, InventoryMovement::withoutGlobalScopes()->where('reference_type', 'sales_delivery')->where('reference_id', $delivery->id)->count());
        }
    }

    public function test_tenant_isolation(): void
    {
        $instA = $this->institute('A');
        $instB = $this->institute('B');
        $customerA = $this->customer($instA, null);
        $currency = $this->currency();
        $productA = $this->product($instA, null);
        $whA = $this->warehouse($instA, null);
        $this->receiveStock($instA, null, $whA, $productA, 50);
        $orderA = $this->createApprovedOrder($instA, null, $customerA, $currency, [
            ['inventory_item_id' => $productA->id, 'description' => $productA->name, 'quantity' => 10, 'unit_price' => 100],
        ]);

        $svc = app(DeliveryService::class);
        $delivery = $svc->createDelivery($instA->id, null, $orderA->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $whA->id,
            'lines' => [['order_line_id' => $orderA->lines[0]->id, 'delivery_quantity' => 5]],
        ], null);

        TenantContext::set($instB->id);
        $this->assertNull(SalesDelivery::query()->find($delivery->id));
        TenantContext::set($instA->id);
        $this->assertNotNull(SalesDelivery::query()->find($delivery->id));
    }

    public function test_branch_isolation(): void
    {
        $inst = $this->institute();
        $branchA = $this->branch($inst, 'A');
        $branchB = $this->branch($inst, 'B');
        $customerA = $this->customer($inst, $branchA);
        $currency = $this->currency();
        $productA = $this->product($inst, $branchA);
        $whA = $this->warehouse($inst, $branchA);
        $this->receiveStock($inst, $branchA, $whA, $productA, 50);
        $orderA = $this->createApprovedOrder($inst, $branchA, $customerA, $currency, [
            ['inventory_item_id' => $productA->id, 'description' => $productA->name, 'quantity' => 10, 'unit_price' => 100],
        ]);

        $svc = app(DeliveryService::class);
        $delivery = $svc->createDelivery($inst->id, $branchA->id, $orderA->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $whA->id,
            'lines' => [['order_line_id' => $orderA->lines[0]->id, 'delivery_quantity' => 5]],
        ], null);

        BranchContext::set($branchB->id);
        TenantContext::set($inst->id);
        $this->assertNull(SalesDelivery::query()->find($delivery->id));
        BranchContext::set($branchA->id);
        $this->assertNotNull(SalesDelivery::query()->find($delivery->id));
    }

    public function test_order_scope_enforced(): void
    {
        $instA = $this->institute('A');
        $instB = $this->institute('B');
        $customerB = $this->customer($instB, null);
        $currency = $this->currency();
        $productB = $this->product($instB, null);
        $whB = $this->warehouse($instB, null);
        $this->receiveStock($instB, null, $whB, $productB, 50);
        $orderB = $this->createApprovedOrder($instB, null, $customerB, $currency, [
            ['inventory_item_id' => $productB->id, 'description' => $productB->name, 'quantity' => 10, 'unit_price' => 100],
        ]);

        $svc = app(DeliveryService::class);
        $this->expectException(ValidationException::class);
        $svc->createDelivery($instA->id, null, $orderB->id, [
            'delivery_date' => now()->toDateString(),
            'lines' => [['order_line_id' => $orderB->lines[0]->id, 'delivery_quantity' => 5]],
        ], null);
    }

    public function test_product_scope_enforced(): void
    {
        $inst = $this->institute();
        $otherInst = $this->institute('Other');
        $branch = $this->branch($inst);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();
        $productOther = $this->product($otherInst, null);
        // Create order with product from other institute — should be blocked at order creation, but test delivery tampering
        // Instead, create valid order then tamper delivery line with other product's order_line
        $product = $this->product($inst, $branch);
        $wh = $this->warehouse($inst, $branch);
        $this->receiveStock($inst, $branch, $wh, $product, 50);
        $order = $this->createApprovedOrder($inst, $branch, $customer, $currency, [
            ['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 10, 'unit_price' => 100],
        ]);

        $svc = app(DeliveryService::class);
        $this->expectException(ValidationException::class);
        // Tamper by using line from other order
        $otherCustomer = $this->customer($otherInst, null);
        $otherWh = $this->warehouse($otherInst, null);
        $this->receiveStock($otherInst, null, $otherWh, $productOther, 50);
        $otherOrder = $this->createApprovedOrder($otherInst, null, $otherCustomer, $currency, [
            ['inventory_item_id' => $productOther->id, 'description' => $productOther->name, 'quantity' => 5, 'unit_price' => 50],
        ]);

        $svc->createDelivery($inst->id, $branch->id, $order->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $wh->id,
            'lines' => [['order_line_id' => $otherOrder->lines[0]->id, 'delivery_quantity' => 5]],
        ], null);
    }

    public function test_historical_order_quantities_preserved(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $wh = $this->warehouse($inst, $branch);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();
        $product = $this->product($inst, $branch, 'stock_item', 100);
        $this->receiveStock($inst, $branch, $wh, $product, 100);

        $order = $this->createApprovedOrder($inst, $branch, $customer, $currency, [
            ['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 10, 'unit_price' => 100],
        ]);

        $svc = app(DeliveryService::class);
        $delivery = $svc->createDelivery($inst->id, $branch->id, $order->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $wh->id,
            'lines' => [['order_line_id' => $order->lines[0]->id, 'delivery_quantity' => 10]],
        ], null);

        $originalOrderedQty = (float) $delivery->lines[0]->ordered_quantity;
        $originalDeliveryQty = (float) $delivery->lines[0]->delivery_quantity;

        // Mutate order line quantity after delivery created
        $order->lines[0]->update(['quantity' => 999]);

        $delivery->refresh();
        $this->assertEquals($originalOrderedQty, (float) $delivery->lines[0]->ordered_quantity);
        $this->assertEquals($originalDeliveryQty, (float) $delivery->lines[0]->delivery_quantity);
    }

    public function test_cross_tenant_inventory_access_blocked(): void
    {
        $instA = $this->institute('A');
        $instB = $this->institute('B');
        $customerA = $this->customer($instA, null);
        $currency = $this->currency();
        $productB = $this->product($instB, null);
        $whB = $this->warehouse($instB, null);
        $this->receiveStock($instB, null, $whB, $productB, 50);
        // Order in A but try to use warehouse from B
        $productA = $this->product($instA, null);
        $orderA = $this->createApprovedOrder($instA, null, $customerA, $currency, [
            ['inventory_item_id' => $productA->id, 'description' => $productA->name, 'quantity' => 10, 'unit_price' => 100],
        ]);

        $svc = app(DeliveryService::class);
        $this->expectException(ValidationException::class);
        $svc->createDelivery($instA->id, null, $orderA->id, [
            'delivery_date' => now()->toDateString(),
            'warehouse_id' => $whB->id,
            'lines' => [['order_line_id' => $orderA->lines[0]->id, 'delivery_quantity' => 5]],
        ], null);
    }
}
