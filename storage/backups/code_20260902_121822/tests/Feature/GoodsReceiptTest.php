<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\InventoryItem;
use App\Models\InventoryWarehouse;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Role;
use App\Services\Accounting\AccountingSetupService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GoodsReceiptTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private Institute $institute;
    private InstituteUser $owner;
    private Party $supplier;
    private InventoryWarehouse $warehouse;
    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();

        $country = \App\Models\Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'GR Test ' . mt_rand(1000, 9999),
            'slug' => 'gr-test-' . mt_rand(1000, 9999),
            'industry' => 'retail',
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        \App\Models\InstituteSetting::withoutGlobalScopes()->create([
            'institute_id' => $this->institute->id,
            'ai_config' => ['enabled' => false, 'features' => [], 'daily_limit' => 0, 'monthly_limit' => 0],
        ]);

        app(AccountingSetupService::class)->setupForInstitute($this->institute->id, null);

        $role = Role::where('slug', 'institute-owner')->firstOrFail();

        $this->owner = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'GR',
            'last_name' => 'Owner',
            'email' => 'gr-test-owner-' . uniqid() . '@example.test',
            'phone' => '01700' . rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $this->supplier = Party::create([
            'institute_id' => $this->institute->id,
            'name' => 'Test Supplier ' . mt_rand(1000, 9999),
            'type' => 'supplier',
            'is_active' => true,
        ]);

        $this->warehouse = InventoryWarehouse::create([
            'institute_id' => $this->institute->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-' . mt_rand(1000, 9999),
            'is_active' => true,
        ]);

        $this->item = InventoryItem::create([
            'institute_id' => $this->institute->id,
            'name' => 'Test Item ' . mt_rand(1000, 9999),
            'sku' => 'TI-' . uniqid(),
            'purchase_price' => 100,
            'selling_price' => 150,
            'is_active' => true,
        ]);

        TenantContext::clear();
    }

    private function loginAsOwner(): string
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->owner->email,
            'password' => $this->password,
            'institute_id' => $this->institute->id,
        ]);

        return $response->json('data.token');
    }

    private function auth(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function createApprovedPo(int $qty = 10, float $price = 50): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'PO-TEST-' . mt_rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_APPROVED,
            'subtotal' => $qty * $price,
            'grand_total' => $qty * $price,
            'created_by' => $this->owner->id,
            'approved_by' => $this->owner->id,
            'approved_at' => now(),
        ]);

        PurchaseOrderLine::create([
            'institute_id' => $this->institute->id,
            'order_id' => $order->id,
            'inventory_item_id' => $this->item->id,
            'description' => $this->item->name,
            'quantity' => $qty,
            'unit_price' => $price,
            'line_total' => $qty * $price,
            'sort_order' => 0,
        ]);

        return $order->fresh('lines');
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->postJson('/api/goods-receipts')->assertStatus(401);
    }

    public function test_create_draft_goods_receipt(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $response = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'receipt_date' => now()->toDateString(),
            'lines' => [
                [
                    'purchase_order_line_id' => $po->lines->first()->id,
                    'received_quantity' => 5,
                    'unit_cost' => 50,
                ],
            ],
        ], $this->auth($token));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonStructure([
                'data' => [
                    'id', 'receipt_number', 'purchase_order_id', 'status',
                    'items' => [['id', 'received_quantity', 'unit_cost']],
                ],
            ]);

        $this->assertDatabaseHas('goods_receipts', [
            'institute_id' => $this->institute->id,
            'purchase_order_id' => $po->id,
            'status' => 'draft',
        ]);
    }

    public function test_create_fails_without_lines(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [],
        ], $this->auth($token))->assertStatus(422);
    }

    public function test_create_fails_on_draft_po(): void
    {
        $token = $this->loginAsOwner();

        $order = PurchaseOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'PO-D-' . mt_rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'subtotal' => 0,
            'grand_total' => 0,
        ]);

        $poLine = PurchaseOrderLine::create([
            'institute_id' => $this->institute->id,
            'order_id' => $order->id,
            'inventory_item_id' => $this->item->id,
            'description' => $this->item->name,
            'quantity' => 10,
            'unit_price' => 50,
            'line_total' => 500,
            'sort_order' => 0,
        ]);

        $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $order->id,
            'lines' => [
                ['purchase_order_line_id' => $poLine->id, 'received_quantity' => 5, 'unit_cost' => 50],
            ],
        ], $this->auth($token))->assertStatus(422);
    }

    public function test_confirm_draft_receipt(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $res = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                [
                    'purchase_order_line_id' => $po->lines->first()->id,
                    'received_quantity' => 5,
                    'unit_cost' => 50,
                ],
            ],
        ], $this->auth($token));

        $grId = $res->json('data.id');

        $this->postJson("/api/goods-receipts/{$grId}/confirm", [], $this->auth($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('goods_receipts', [
            'id' => $grId,
            'status' => 'confirmed',
            'confirmed_by' => $this->owner->id,
        ]);

        $poLine = $po->lines->first()->fresh();
        $this->assertEquals('5.0000', $poLine->received_quantity);
    }

    public function test_partial_receive_updates_po_status(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $res = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                ['purchase_order_line_id' => $po->lines->first()->id, 'received_quantity' => 3, 'unit_cost' => 50],
            ],
        ], $this->auth($token));

        $grId = $res->json('data.id');
        $this->postJson("/api/goods-receipts/{$grId}/confirm", [], $this->auth($token));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        ]);
    }

    public function test_full_receive_updates_po_to_fully_received(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $res = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                ['purchase_order_line_id' => $po->lines->first()->id, 'received_quantity' => 10, 'unit_cost' => 50],
            ],
        ], $this->auth($token));

        $grId = $res->json('data.id');
        $this->postJson("/api/goods-receipts/{$grId}/confirm", [], $this->auth($token));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => PurchaseOrder::STATUS_FULLY_RECEIVED,
        ]);
    }

    public function test_over_receiving_prevented(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo(10, 50);

        $res = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                ['purchase_order_line_id' => $po->lines->first()->id, 'received_quantity' => 15, 'unit_cost' => 50],
            ],
        ], $this->auth($token));

        $grId = $res->json('data.id');
        $this->postJson("/api/goods-receipts/{$grId}/confirm", [], $this->auth($token))
            ->assertStatus(422);
    }

    public function test_multiple_partial_receives_accumulate(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo(10, 50);

        $res1 = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                ['purchase_order_line_id' => $po->lines->first()->id, 'received_quantity' => 4, 'unit_cost' => 50],
            ],
        ], $this->auth($token));
        $gr1Id = $res1->json('data.id');
        $this->postJson("/api/goods-receipts/{$gr1Id}/confirm", [], $this->auth($token));

        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'status' => 'partially_received']);

        $res2 = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                ['purchase_order_line_id' => $po->lines->first()->id, 'received_quantity' => 6, 'unit_cost' => 50],
            ],
        ], $this->auth($token));
        $gr2Id = $res2->json('data.id');
        $this->postJson("/api/goods-receipts/{$gr2Id}/confirm", [], $this->auth($token));

        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'status' => 'fully_received']);

        $poLine = $po->lines->first()->fresh();
        $this->assertEquals('10.0000', $poLine->received_quantity);
    }

    public function test_cancel_draft_receipt(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $res = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                ['purchase_order_line_id' => $po->lines->first()->id, 'received_quantity' => 5, 'unit_cost' => 50],
            ],
        ], $this->auth($token));

        $grId = $res->json('data.id');

        $this->postJson("/api/goods-receipts/{$grId}/cancel", [
            'cancellation_reason' => 'Changed mind',
        ], $this->auth($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('goods_receipts', [
            'id' => $grId,
            'status' => 'cancelled',
            'cancellation_reason' => 'Changed mind',
        ]);
    }

    public function test_cannot_confirm_cancelled_receipt(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $res = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                ['purchase_order_line_id' => $po->lines->first()->id, 'received_quantity' => 5, 'unit_cost' => 50],
            ],
        ], $this->auth($token));

        $grId = $res->json('data.id');
        $this->postJson("/api/goods-receipts/{$grId}/cancel", [], $this->auth($token));

        $this->postJson("/api/goods-receipts/{$grId}/confirm", [], $this->auth($token))
            ->assertStatus(422);
    }

    public function test_list_goods_receipts(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                ['purchase_order_line_id' => $po->lines->first()->id, 'received_quantity' => 5, 'unit_cost' => 50],
            ],
        ], $this->auth($token));

        $this->getJson('/api/goods-receipts', $this->auth($token))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_show_goods_receipt(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $res = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                ['purchase_order_line_id' => $po->lines->first()->id, 'received_quantity' => 5, 'unit_cost' => 50],
            ],
        ], $this->auth($token));

        $grId = $res->json('data.id');

        $this->getJson("/api/goods-receipts/{$grId}", $this->auth($token))
            ->assertOk()
            ->assertJsonPath('data.id', $grId);
    }

    public function test_show_nonexistent_returns_404(): void
    {
        $token = $this->loginAsOwner();

        $this->getJson('/api/goods-receipts/999999', $this->auth($token))
            ->assertStatus(404);
    }

    public function test_list_purchase_orders(): void
    {
        $token = $this->loginAsOwner();
        $this->createApprovedPo();

        $this->getJson('/api/purchase-orders', $this->auth($token))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_show_purchase_order(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $this->getJson("/api/purchase-orders/{$po->id}", $this->auth($token))
            ->assertOk()
            ->assertJsonPath('data.id', $po->id);
    }

    public function test_create_purchase_order(): void
    {
        $token = $this->loginAsOwner();

        $this->postJson('/api/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'inventory_item_id' => $this->item->id,
                    'quantity' => 10,
                    'unit_price' => 50,
                ],
            ],
        ], $this->auth($token))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_submit_purchase_order(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();
        $draftPo = PurchaseOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'PO-SUB-' . mt_rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'subtotal' => 500,
            'grand_total' => 500,
        ]);

        $this->postJson("/api/purchase-orders/{$draftPo->id}/submit", [], $this->auth($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_rejected_quantity_tracked_on_po_line(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $res = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                [
                    'purchase_order_line_id' => $po->lines->first()->id,
                    'received_quantity' => 8,
                    'rejected_quantity' => 2,
                    'unit_cost' => 50,
                ],
            ],
        ], $this->auth($token));

        $grId = $res->json('data.id');
        $this->postJson("/api/goods-receipts/{$grId}/confirm", [], $this->auth($token));

        $poLine = $po->lines->first()->fresh();
        $this->assertEquals('8.0000', $poLine->received_quantity);
        $this->assertEquals('2.0000', $poLine->rejected_quantity);
    }

    public function test_goods_receipt_items_record_ordered_and_previous_quantity(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $res = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                ['purchase_order_line_id' => $po->lines->first()->id, 'received_quantity' => 5, 'unit_cost' => 50],
            ],
        ], $this->auth($token));

        $item = $res->json('data.items.0');
        $this->assertEquals(10, $item['ordered_quantity']);
        $this->assertEquals(0, $item['previously_received_quantity']);
        $this->assertEquals(5, $item['received_quantity']);
    }

    public function test_second_receipt_shows_previous_quantity(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $res1 = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                ['purchase_order_line_id' => $po->lines->first()->id, 'received_quantity' => 4, 'unit_cost' => 50],
            ],
        ], $this->auth($token));
        $this->postJson("/api/goods-receipts/{$res1->json('data.id')}/confirm", [], $this->auth($token));

        $res2 = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                ['purchase_order_line_id' => $po->lines->first()->id, 'received_quantity' => 3, 'unit_cost' => 50],
            ],
        ], $this->auth($token));

        $item = $res2->json('data.items.0');
        $this->assertEquals(10, $item['ordered_quantity']);
        $this->assertEquals(4, $item['previously_received_quantity']);
        $this->assertEquals(3, $item['received_quantity']);
    }

    public function test_stock_increases_only_after_confirm(): void
    {
        $token = $this->loginAsOwner();
        $po = $this->createApprovedPo();

        $res = $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $po->id,
            'lines' => [
                ['purchase_order_line_id' => $po->lines->first()->id, 'received_quantity' => 5, 'unit_cost' => 50],
            ],
        ], $this->auth($token));

        $grId = $res->json('data.id');
        $receipt = GoodsReceipt::find($grId);

        $this->assertDatabaseMissing('inventory_movements', [
            'reference_type' => GoodsReceipt::class,
            'reference_id' => $grId,
        ]);

        $this->postJson("/api/goods-receipts/{$grId}/confirm", [], $this->auth($token));

        $this->assertDatabaseHas('inventory_movements', [
            'reference_type' => GoodsReceipt::class,
            'reference_id' => $grId,
        ]);
    }
}
