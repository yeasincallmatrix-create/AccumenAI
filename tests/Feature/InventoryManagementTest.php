<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InventoryItem;
use App\Models\InventoryWarehouse;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Inventory\InventoryItemService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class InventoryManagementTest extends \Tests\TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Inv Mgmt Owner',
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

    protected function setupAccounting(Institute $institute, ?int $branchId = null): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branchId);
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    public function test_items_index_renders(): void
    {
        $inst = $this->institute('Inv Items');
        $owner = $this->owner('inv-items-index@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('inventory.items.index'))
            ->assertOk()
            ->assertSee('Inventory Items');
    }

    public function test_items_create_renders(): void
    {
        $inst = $this->institute('Inv Items Create');
        $owner = $this->owner('inv-items-create@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('inventory.items.create'))
            ->assertOk()
            ->assertSee('New Inventory Item');
    }

    public function test_warehouses_index_renders(): void
    {
        $inst = $this->institute('Inv WH');
        $owner = $this->owner('inv-wh-index@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('inventory.warehouses.index'))
            ->assertOk()
            ->assertSee('Warehouses');
    }

    public function test_transfers_index_renders(): void
    {
        $inst = $this->institute('Inv Transfer');
        $owner = $this->owner('inv-transfer-index@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('inventory.transfers.index'))
            ->assertOk()
            ->assertSee('Stock Transfers');
    }

    public function test_adjustments_index_renders(): void
    {
        $inst = $this->institute('Inv Adj');
        $owner = $this->owner('inv-adj-index@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('inventory.adjustments.index'))
            ->assertOk()
            ->assertSee('Stock Adjustments');
    }

    public function test_item_store_creates_item(): void
    {
        $inst = $this->institute('Inv Store Item');
        $owner = $this->owner('inv-store-item@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->post(route('inventory.items.store'), [
                'item_type' => 'stock_item',
                'name' => 'Test Keyboard',
                'sku' => 'KB-TEST-'.uniqid(),
                'unit' => 'pc',
                'purchase_price' => 25.00,
                'selling_price' => 40.00,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_items', [
            'institute_id' => $inst->id,
            'name' => 'Test Keyboard',
        ]);
    }

    public function test_warehouse_store_creates_warehouse(): void
    {
        $inst = $this->institute('Inv Store WH');
        $owner = $this->owner('inv-store-wh@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->post(route('inventory.warehouses.store'), [
                'name' => 'Main Store',
                'code' => 'WH-'.strtoupper(uniqid()),
                'location' => 'Dhaka',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_warehouses', [
            'institute_id' => $inst->id,
            'name' => 'Main Store',
        ]);
    }

    public function test_items_search_filters(): void
    {
        $inst = $this->institute('Inv Search');
        $owner = $this->owner('inv-search@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));
        $svc = app(InventoryItemService::class);

        $svc->createItem($inst->id, null, ['item_type' => 'stock_item', 'sku' => 'FIND-ME', 'name' => 'Unique Widget']);
        $svc->createItem($inst->id, null, ['item_type' => 'consumable', 'sku' => 'OTHER-1', 'name' => 'Other Item']);

        $this->asUser($owner, $membership->institution_id)
            ->get(route('inventory.items.index', ['q' => 'Unique Widget']))
            ->assertOk()
            ->assertSee('Unique Widget')
            ->assertDontSee('Other Item');
    }

    public function test_tenant_isolation_inventory_items(): void
    {
        $instA = $this->institute('Inv Iso A');
        $instB = $this->institute('Inv Iso B');
        $ownerA = $this->owner('inv-iso-a@example.test');
        (new MembershipService)->assign($ownerA, $instA->id, $this->roleId('institute-owner'));

        $svc = app(InventoryItemService::class);
        $svc->createItem($instA->id, null, ['item_type' => 'stock_item', 'sku' => 'ISO-A', 'name' => 'Item A']);
        $svc->createItem($instB->id, null, ['item_type' => 'stock_item', 'sku' => 'ISO-B', 'name' => 'Item B']);

        $this->asUser($ownerA, $instA->id)
            ->get(route('inventory.items.index'))
            ->assertOk()
            ->assertSee('Item A')
            ->assertDontSee('Item B');
    }

    public function test_tenant_isolation_inventory_warehouses(): void
    {
        $instA = $this->institute('Inv WH Iso A');
        $instB = $this->institute('Inv WH Iso B');
        $ownerA = $this->owner('inv-wh-iso-a@example.test');
        (new MembershipService)->assign($ownerA, $instA->id, $this->roleId('institute-owner'));

        $svc = app(InventoryItemService::class);
        $svc->createWarehouse($instA->id, null, ['name' => 'WH A', 'code' => 'WHA']);
        $svc->createWarehouse($instB->id, null, ['name' => 'WH B', 'code' => 'WHB']);

        $this->asUser($ownerA, $instA->id)
            ->get(route('inventory.warehouses.index'))
            ->assertOk()
            ->assertSee('WH A')
            ->assertDontSee('WH B');
    }
}
