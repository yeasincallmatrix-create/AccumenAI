<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Services\Inventory\InventoryCapabilityService;
use App\Services\Inventory\InventoryItemService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * STEP 16 — Inventory master data (categories, warehouses, items) + the
 * capability engine that drives the global inventory engine.
 *
 * Every record is tenant-scoped (branch_id NULL = institute-wide) and all
 * writes validate tenant ownership so one institute can never touch another's
 * inventory data. Capabilities default from industry_rules.php (retail on,
 * education off) and can be overridden per tenant via the accounting-settings
 * mechanism.
 */
class InventoryMasterDataTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function items(): InventoryItemService
    {
        return app(InventoryItemService::class);
    }

    private function capabilities(): InventoryCapabilityService
    {
        return app(InventoryCapabilityService::class);
    }

    private function country(): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(string $industry = 'retail'): Institute
    {
        $country = $this->country();

        return Institute::create([
            'name' => 'Inventory Inst',
            'slug' => str()->slug('Inventory Inst-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => $industry,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    // ------------------------------------------------------------ Capabilities

    public function test_retail_defaults_enable_the_full_engine(): void
    {
        $institute = $this->institute('retail');

        $this->assertTrue($this->capabilities()->has($institute->id, 'inventory.enabled'));
        $this->assertTrue($this->capabilities()->has($institute->id, 'inventory.sales_issue'));
        $this->assertTrue($this->capabilities()->has($institute->id, 'inventory.purchase_receipt'));
        $this->assertTrue($this->capabilities()->has($institute->id, 'inventory.stock_adjustment'));
        $this->assertTrue($this->capabilities()->has($institute->id, 'inventory.stock_count'));
        $this->assertTrue($this->capabilities()->has($institute->id, 'inventory.stock_transfer'));
        $this->assertTrue($this->capabilities()->has($institute->id, 'inventory.stock_return'));
        $this->assertFalse($this->capabilities()->has($institute->id, 'inventory.wastage'));
    }

    public function test_healthcare_defaults_enable_batch_and_expiry_tracking(): void
    {
        $institute = $this->institute('healthcare');

        $this->assertTrue($this->capabilities()->has($institute->id, 'inventory.batch_tracking'));
        $this->assertTrue($this->capabilities()->has($institute->id, 'inventory.expiry_tracking'));
        $this->assertTrue($this->capabilities()->has($institute->id, 'inventory.wastage'));
        $this->assertFalse($this->capabilities()->has($institute->id, 'inventory.bom'));
    }

    public function test_education_defaults_disable_inventory(): void
    {
        $institute = $this->institute('education');

        $this->assertFalse($this->capabilities()->has($institute->id, 'inventory.enabled'));
        $this->assertFalse($this->capabilities()->has($institute->id, 'inventory.purchase_receipt'));
        $this->assertFalse($this->capabilities()->has($institute->id, 'inventory.sales_issue'));
    }

    public function test_tenant_override_flips_a_capability(): void
    {
        $institute = $this->institute('education');

        $this->capabilities()->set($institute->id, 'inventory.sales_issue', true);
        $this->assertTrue($this->capabilities()->has($institute->id, 'inventory.sales_issue'));
        $this->assertFalse($this->capabilities()->has($institute->id, 'inventory.purchase_receipt'));
    }

    public function test_capability_assert_rejects_when_disabled(): void
    {
        $institute = $this->institute('education');

        try {
            $this->capabilities()->assert($institute->id, 'inventory.sales_issue');
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('not enabled', $e->errors()['capability'][0] ?? '');
        }
    }

    // ------------------------------------------------------------ Categories

    public function test_category_crud_and_unique_name(): void
    {
        $institute = $this->institute();
        $category = $this->items()->createCategory($institute->id, null, ['name' => 'Electronics']);

        $this->assertSame($institute->id, $category->institute_id);
        $this->assertNull($category->branch_id);

        try {
            $this->items()->createCategory($institute->id, null, ['name' => 'Electronics']);
            $this->fail('Expected duplicate-name ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors());
        }

        $updated = $this->items()->updateCategory($category, $institute->id, ['name' => 'Gadgets']);
        $this->assertSame('Gadgets', $updated->name);

        $this->items()->deleteCategory($category, $institute->id);
        $this->assertSoftDeleted($category);
    }

    public function test_category_with_items_cannot_be_deleted(): void
    {
        $institute = $this->institute();
        $category = $this->items()->createCategory($institute->id, null, ['name' => 'Consumables']);
        $item = $this->items()->createItem($institute->id, null, [
            'category_id' => $category->id,
            'item_type' => 'consumable',
            'name' => 'Printer Paper',
        ]);

        $this->assertSame($category->id, $item->category_id);

        try {
            $this->items()->deleteCategory($category, $institute->id);
            $this->fail('Expected delete rejection.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('category', $e->errors());
        }
    }

    // ------------------------------------------------------------ Warehouses

    public function test_warehouse_crud_and_unique_code(): void
    {
        $institute = $this->institute();
        $warehouse = $this->items()->createWarehouse($institute->id, null, [
            'name' => 'Main Store',
            'code' => 'WH-MAIN',
        ]);

        $this->assertSame('WH-MAIN', $warehouse->code);

        try {
            $this->items()->createWarehouse($institute->id, null, ['name' => 'Other', 'code' => 'WH-MAIN']);
            $this->fail('Expected duplicate-code ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }
    }

    // ------------------------------------------------------------ Items

    public function test_item_crud_and_duplicate_sku_rejected(): void
    {
        $institute = $this->institute();
        $item = $this->items()->createItem($institute->id, null, [
            'item_type' => 'stock_item',
            'sku' => 'SKU-100',
            'name' => 'Keyboard',
            'purchase_price' => 20,
            'selling_price' => 35,
            'unit' => 'pc',
        ]);

        $this->assertSame('SKU-100', $item->sku);

        try {
            $this->items()->createItem($institute->id, null, ['item_type' => 'stock_item', 'sku' => 'SKU-100', 'name' => 'Other']);
            $this->fail('Expected duplicate-SKU ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sku', $e->errors());
        }
    }

    public function test_invalid_item_type_rejected(): void
    {
        $institute = $this->institute();

        try {
            $this->items()->createItem($institute->id, null, ['item_type' => 'banana', 'name' => 'Nope']);
            $this->fail('Expected ValidationException for invalid item type.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('item_type', $e->errors());
        }
    }

    // ------------------------------------------------------------ Tenancy

    public function test_items_are_isolated_between_institutes(): void
    {
        $first = $this->institute();
        $second = $this->institute();

        $this->items()->createItem($first->id, null, ['item_type' => 'stock_item', 'sku' => 'A-1', 'name' => 'First item']);

        $secondList = $this->items()->listItems($second->id, null)->get();
        $this->assertEmpty($secondList);
    }

    public function test_foreign_item_cannot_be_updated(): void
    {
        $first = $this->institute();
        $second = $this->institute();

        $item = $this->items()->createItem($first->id, null, ['item_type' => 'stock_item', 'sku' => 'B-1', 'name' => 'First item']);

        try {
            $this->items()->updateItem($item, $second->id, ['name' => 'Hijacked']);
            $this->fail('Expected cross-tenant update rejection.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('record', $e->errors());
        }
    }

    public function test_warehouse_with_stock_cannot_be_deleted(): void
    {
        $institute = $this->institute();
        $warehouse = $this->items()->createWarehouse($institute->id, null, ['name' => 'Store', 'code' => 'S1']);
        $item = $this->items()->createItem($institute->id, null, ['item_type' => 'stock_item', 'sku' => 'C-1', 'name' => 'Item']);

        app(\App\Models\InventoryStockLevel::class)->create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'quantity' => 5,
            'avg_cost' => 10,
        ]);

        try {
            $this->items()->deleteWarehouse($warehouse, $institute->id);
            $this->fail('Expected delete rejection for a warehouse holding stock.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('warehouse', $e->errors());
        }
    }
}