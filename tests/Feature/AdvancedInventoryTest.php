<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Institute;
use App\Models\InventoryItem;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\InventoryStockLevel;
use App\Models\InventoryWarehouse;
use App\Models\Role;
use App\Models\User;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryReportService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AdvancedInventoryTest extends \Tests\TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
        parent::tearDown();
    }

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Inv Owner',
            'first_name' => 'Inv',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    protected function institute(string $name): Institute
    {
        return Institute::create([
            'name' => $name.' '.uniqid(),
            'slug' => \Illuminate\Support\Str::slug($name.' '.uniqid()),
            'status' => 'active',
        ]);
    }

    protected function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->firstOrFail()->id;
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    private function createTestData(Institute $institute): void
    {
        $warehouse = InventoryWarehouse::create([
            'institute_id' => $institute->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'institute_id' => $institute->id,
            'item_type' => 'stock_item',
            'sku' => 'SKU-TEST-'.uniqid(),
            'name' => 'Test Item',
            'barcode' => 'BARCODE-'.uniqid(),
            'reorder_level' => 10,
            'is_active' => true,
        ]);

        InventoryStockLevel::create([
            'institute_id' => $institute->id,
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'quantity' => 5,
            'avg_cost' => 10,
        ]);

        InventoryBatch::create([
            'institute_id' => $institute->id,
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-001',
            'quantity' => 5,
            'unit_cost' => 10,
            'expiry_date' => now()->addDays(15),
        ]);

        InventoryMovement::create([
            'institute_id' => $institute->id,
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'movement_type' => 'receipt',
            'quantity' => 10,
            'unit_cost' => 10,
            'movement_no' => 'IVM-'.uniqid(),
            'occurred_at' => now(),
            'status' => 'posted',
        ]);
    }

    public function test_stock_ledger_renders(): void
    {
        $inst = $this->institute('Adv Inv Ledger');
        $owner = $this->owner('adv-ledger@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->createTestData($inst);

        $this->asUser($owner, $membership->institution_id)
            ->get(route('inventory.stock-ledger'))
            ->assertOk()
            ->assertSee('Stock Ledger');
    }

    public function test_low_stock_renders(): void
    {
        $inst = $this->institute('Adv Inv Low');
        $owner = $this->owner('adv-low@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->createTestData($inst);

        $this->asUser($owner, $membership->institution_id)
            ->get(route('inventory.low-stock'))
            ->assertOk()
            ->assertSee('Low Stock Items');
    }

    public function test_batch_tracker_renders(): void
    {
        $inst = $this->institute('Adv Inv Batch');
        $owner = $this->owner('adv-batch@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->createTestData($inst);

        $this->asUser($owner, $membership->institution_id)
            ->get(route('inventory.batches'))
            ->assertOk()
            ->assertSee('Batch Tracker');
    }

    public function test_barcode_search_renders(): void
    {
        $inst = $this->institute('Adv Inv Barcode');
        $owner = $this->owner('adv-barcode@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->createTestData($inst);

        $this->asUser($owner, $membership->institution_id)
            ->get(route('inventory.barcode-search'))
            ->assertOk()
            ->assertSee('Barcode Search');
    }

    public function test_stock_ledger_service_returns_movements(): void
    {
        $inst = $this->institute('Adv Inv Svc Mov');
        $warehouse = InventoryWarehouse::create([
            'institute_id' => $inst->id,
            'name' => 'Store',
            'code' => 'WH-S',
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'institute_id' => $inst->id,
            'item_type' => 'stock_item',
            'sku' => 'SVC-MOV-'.uniqid(),
            'name' => 'Service Item',
            'is_active' => true,
        ]);

        InventoryMovement::create([
            'institute_id' => $inst->id,
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'movement_type' => 'receipt',
            'quantity' => 20,
            'unit_cost' => 5,
            'movement_no' => 'IVM-SVC-'.uniqid(),
            'occurred_at' => now(),
            'status' => 'posted',
        ]);

        InventoryMovement::create([
            'institute_id' => $inst->id,
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'movement_type' => 'issue',
            'quantity' => -5,
            'unit_cost' => 5,
            'movement_no' => 'IVM-SVC-'.uniqid(),
            'occurred_at' => now(),
            'status' => 'posted',
        ]);

        $service = app(InventoryReportService::class);
        $results = $service->movements($inst->id, null)->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('movement_type', 'receipt'));
        $this->assertTrue($results->contains('movement_type', 'issue'));
    }

    public function test_low_stock_service_returns_items(): void
    {
        $inst = $this->institute('Adv Inv Svc Low');
        $warehouse = InventoryWarehouse::create([
            'institute_id' => $inst->id,
            'name' => 'Depot',
            'code' => 'WH-D',
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'institute_id' => $inst->id,
            'item_type' => 'stock_item',
            'sku' => 'SVC-LOW-'.uniqid(),
            'name' => 'Low Stock Item',
            'reorder_level' => 50,
            'is_active' => true,
        ]);

        InventoryStockLevel::create([
            'institute_id' => $inst->id,
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'quantity' => 3,
            'avg_cost' => 10,
        ]);

        $service = app(InventoryReportService::class);
        $results = $service->lowStock($inst->id, null);

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('status', $results[0]);
        $this->assertContains($results[0]['status'], ['low_stock', 'out_of_stock']);
        $this->assertLessThanOrEqual($results[0]['reorder_level'], $results[0]['quantity']);
    }
}
