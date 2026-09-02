<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\CrmContact;
use App\Models\CustomerGroup;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStockLevel;
use App\Models\InventoryWarehouse;
use App\Models\Journal;
use App\Models\Party;
use App\Models\Role;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SalesCustomerProductTest extends TestCase
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
        return Institute::create([
            'name' => 'Sales Inst '.uniqid(),
            'slug' => 'sales-'.uniqid(),
            'country' => $c->name,
            'country_id' => $c->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $i, string $name = 'Branch'): Branch
    {
        return Branch::create(['institute_id' => $i->id, 'name' => $name.' '.uniqid(), 'status' => 'active']);
    }

    private function user(Institute $i, string $role, ?int $branchId = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $i->id,
            'role_id' => Role::where('slug', $role)->firstOrFail()->id,
            'branch_id' => $branchId,
            'first_name' => ucfirst($role),
            'last_name' => 'User',
            'email' => $role.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    private function partyCustomer(Institute $i, ?int $branchId = null, array $overrides = []): Party
    {
        return Party::create(array_merge([
            'institute_id' => $i->id,
            'branch_id' => $branchId,
            'type' => 'customer',
            'name' => 'Customer '.uniqid(),
            'phone' => '017'.rand(10000000, 99999999),
            'email' => 'customer-'.uniqid().'@example.test',
            'is_active' => true,
            'credit_limit' => 0,
        ], $overrides));
    }

    private function inventoryCategory(Institute $i, ?int $branchId = null): InventoryCategory
    {
        return \App\Models\InventoryCategory::create([
            'institute_id' => $i->id,
            'branch_id' => $branchId,
            'name' => 'Cat '.uniqid(),
            'is_active' => true,
        ]);
    }

    private function inventoryItem(Institute $i, ?int $branchId = null, array $overrides = []): InventoryItem
    {
        $cat = $this->inventoryCategory($i, $branchId);
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

    // ------------------------------------------------ Customer lookup

    public function test_customer_lookup_text_search_and_pagination(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $c1 = $this->partyCustomer($inst, null, ['name' => 'Alpha Traders', 'phone' => '01711111111']);
        $this->partyCustomer($inst, null, ['name' => 'Beta Corp', 'phone' => '01722222222']);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.customers.search', ['q' => 'Alpha']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['name' => 'Alpha Traders']);

        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.customers.search', ['q' => '01711111111']))
            ->assertOk()
            ->assertJsonFragment(['phone' => '01711111111']);

        // Pagination
        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.customers.search', ['per_page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);

        // Resolve single
        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.customers.show', $c1->id))
            ->assertOk()
            ->assertJsonPath('data.name', 'Alpha Traders')
            ->assertJsonPath('data.billing.name', 'Alpha Traders');
    }

    public function test_crm_linked_customer(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $party = $this->partyCustomer($inst, null, ['name' => 'CRM Linked', 'email' => 'crm-linked-'.uniqid().'@example.test', 'phone' => '01733333333']);

        // Create CRM contact with same email/phone
        $contact = CrmContact::create([
            'institute_id' => $inst->id,
            'branch_id' => null,
            'first_name' => 'CRM',
            'last_name' => 'Linked',
            'email' => $party->email,
            'phone' => $party->phone,
            'is_customer' => true,
            'is_prospect' => false,
            'status' => 'active',
        ]);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.customers.show', $party->id))
            ->assertOk()
            ->assertJsonPath('data.crm_contact.id', $contact->id)
            ->assertJsonPath('data.crm_contact.name', $contact->displayName());

        // CRM search alternative
        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.crmCustomers.search', ['q' => 'CRM']))
            ->assertOk()
            ->assertJsonFragment(['name' => $contact->displayName()]);
    }

    // ------------------------------------------------ Product / Service lookup

    public function test_product_lookup_text_and_sku_search(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $p1 = $this->inventoryItem($inst, null, ['name' => 'Super Widget', 'sku' => 'SW-1001', 'selling_price' => 250, 'unit' => 'pcs', 'item_type' => 'stock_item']);
        $this->inventoryItem($inst, null, ['name' => 'Mega Gadget', 'sku' => 'MG-2002']);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.items.search', ['q' => 'Widget']))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Super Widget']);

        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.items.search', ['q' => 'SW-1001']))
            ->assertOk()
            ->assertJsonFragment(['sku' => 'SW-1001']);

        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.items.show', $p1->id))
            ->assertOk()
            ->assertJsonPath('data.sku', 'SW-1001')
            ->assertJsonPath('data.selling_price', '250.0000');
    }

    public function test_service_item_lookup_non_stockable(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $service = $this->inventoryItem($inst, null, ['name' => 'Consulting Service', 'sku' => 'SRV-001', 'item_type' => 'other', 'selling_price' => 5000, 'unit' => 'hour']);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.items.search', ['q' => 'Consulting']))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Consulting Service'])
            ->assertJsonPath('data.0.is_stockable', false);

        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.items.availability', ['item' => $service->id]))
            ->assertOk()
            ->assertJsonPath('data.is_stockable', false)
            ->assertJsonPath('data.is_service', true)
            ->assertJsonPath('data.branch_available', true)
            ->assertJsonPath('data.stock.available', true);
    }

    public function test_stockable_vs_non_stockable_availability(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $branch = $this->branch($inst, 'Main');
        $stockItem = $this->inventoryItem($inst, $branch->id, ['name' => 'Stock Item', 'item_type' => 'stock_item', 'selling_price' => 100]);
        $serviceItem = $this->inventoryItem($inst, $branch->id, ['name' => 'Service Item', 'item_type' => 'other', 'selling_price' => 200]);

        $wh = $this->warehouse($inst, $branch->id);
        InventoryStockLevel::create([
            'institute_id' => $inst->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $wh->id,
            'item_id' => $stockItem->id,
            'quantity' => 5,
        ]);

        TenantContext::set($inst->id);
        BranchContext::set($branch->id);

        // Stockable with sufficient stock
        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.items.availability', ['item' => $stockItem->id, 'qty' => 3]))
            ->assertOk()
            ->assertJsonPath('data.is_stockable', true)
            ->assertJsonPath('data.stock.on_hand', 5)
            ->assertJsonPath('data.stock.available', true);

        // Stockable insufficient
        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.items.availability', ['item' => $stockItem->id, 'qty' => 10]))
            ->assertOk()
            ->assertJsonPath('data.stock.available', false);

        // Service always available
        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.items.availability', ['item' => $serviceItem->id, 'qty' => 100]))
            ->assertOk()
            ->assertJsonPath('data.is_stockable', false)
            ->assertJsonPath('data.stock.available', true);

        BranchContext::clear();
    }

    // ------------------------------------------------ Tenant isolation

    public function test_tenant_isolation_customer_and_product(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $ownerA = $this->user($a, 'institute-owner');
        $ownerB = $this->user($b, 'institute-owner');

        $partyA = $this->partyCustomer($a, null, ['name' => 'Tenant A Customer']);
        $itemA = $this->inventoryItem($a, null, ['name' => 'Tenant A Product']);

        TenantContext::set($a->id);
        $this->actingAs($ownerA, 'institute_user')->getJson(route('sales.customers.show', $partyA->id))->assertOk();
        $this->actingAs($ownerA, 'institute_user')->getJson(route('sales.items.show', $itemA->id))->assertOk();

        TenantContext::set($b->id);
        // Cross-tenant via ID bypass should 404
        $this->actingAs($ownerB, 'institute_user')->getJson(route('sales.customers.show', $partyA->id))->assertNotFound();
        $this->actingAs($ownerB, 'institute_user')->getJson(route('sales.items.show', $itemA->id))->assertNotFound();

        // Search should not leak
        $this->actingAs($ownerB, 'institute_user')->getJson(route('sales.customers.search', ['q' => 'Tenant A Customer']))->assertOk()->assertJsonPath('meta.total', 0);
        $this->actingAs($ownerB, 'institute_user')->getJson(route('sales.items.search', ['q' => 'Tenant A Product']))->assertOk()->assertJsonPath('meta.total', 0);
    }

    // ------------------------------------------------ Branch isolation

    public function test_branch_isolation(): void
    {
        $inst = $this->institute();
        $branchA = $this->branch($inst, 'Branch A');
        $branchB = $this->branch($inst, 'Branch B');
        $owner = $this->user($inst, 'institute-owner');
        $mgrA = $this->user($inst, 'branch-manager', $branchA->id);
        $mgrB = $this->user($inst, 'branch-manager', $branchB->id);

        $partyA = $this->partyCustomer($inst, $branchA->id, ['name' => 'Branch A Customer']);
        $partyShared = $this->partyCustomer($inst, null, ['name' => 'Shared Customer']);
        $itemA = $this->inventoryItem($inst, $branchA->id, ['name' => 'Branch A Item']);
        $itemShared = $this->inventoryItem($inst, null, ['name' => 'Shared Item']);

        // Branch A manager can see branch A and shared, but not branch B
        $partyB = $this->partyCustomer($inst, $branchB->id, ['name' => 'Branch B Customer']);
        $itemB = $this->inventoryItem($inst, $branchB->id, ['name' => 'Branch B Item']);

        TenantContext::set($inst->id);
        BranchContext::set($branchA->id);

        $this->actingAs($mgrA, 'institute_user')->getJson(route('sales.customers.search'))->assertOk()->assertJsonFragment(['name' => 'Branch A Customer'])->assertJsonFragment(['name' => 'Shared Customer'])->assertJsonMissing(['name' => 'Branch B Customer']);
        $this->actingAs($mgrA, 'institute_user')->getJson(route('sales.customers.show', $partyA->id))->assertOk();
        $this->actingAs($mgrA, 'institute_user')->getJson(route('sales.customers.show', $partyB->id))->assertNotFound();
        $this->actingAs($mgrA, 'institute_user')->getJson(route('sales.customers.show', $partyShared->id))->assertOk();

        $this->actingAs($mgrA, 'institute_user')->getJson(route('sales.items.search'))->assertOk()->assertJsonFragment(['name' => 'Branch A Item'])->assertJsonFragment(['name' => 'Shared Item'])->assertJsonMissing(['name' => 'Branch B Item']);
        $this->actingAs($mgrA, 'institute_user')->getJson(route('sales.items.show', $itemA->id))->assertOk();
        $this->actingAs($mgrA, 'institute_user')->getJson(route('sales.items.show', $itemB->id))->assertNotFound();
        $this->actingAs($mgrA, 'institute_user')->getJson(route('sales.items.show', $itemShared->id))->assertOk();

        // Branch B manager opposite
        BranchContext::set($branchB->id);
        $this->actingAs($mgrB, 'institute_user')->getJson(route('sales.customers.show', $partyB->id))->assertOk();
        $this->actingAs($mgrB, 'institute_user')->getJson(route('sales.customers.show', $partyA->id))->assertNotFound();

        // Owner (no branch) sees all
        BranchContext::clear();
        $this->actingAs($owner, 'institute_user')->getJson(route('sales.customers.search'))->assertOk()->assertJsonFragment(['name' => 'Branch A Customer'])->assertJsonFragment(['name' => 'Branch B Customer']);
        $this->actingAs($owner, 'institute_user')->getJson(route('sales.items.search'))->assertOk()->assertJsonFragment(['name' => 'Branch A Item'])->assertJsonFragment(['name' => 'Branch B Item']);

        BranchContext::clear();
    }

    // ------------------------------------------------ Permissions

    public function test_permission_checks(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $receptionist = $this->user($inst, 'receptionist'); // no sales access
        $teacher = $this->user($inst, 'teacher'); // no sales access

        TenantContext::set($inst->id);

        $this->actingAs($receptionist, 'institute_user')->getJson(route('sales.customers.search'))->assertForbidden();
        $this->actingAs($teacher, 'institute_user')->getJson(route('sales.items.search'))->assertForbidden();

        // Owner can
        $this->actingAs($owner, 'institute_user')->getJson(route('sales.customers.search'))->assertOk();
        $this->actingAs($owner, 'institute_user')->getJson(route('sales.items.search'))->assertOk();

        // Branch manager can view
        $branch = $this->branch($inst);
        $mgr = $this->user($inst, 'branch-manager', $branch->id);
        BranchContext::set($branch->id);
        $this->actingAs($mgr, 'institute_user')->getJson(route('sales.customers.search'))->assertOk();
        $this->actingAs($mgr, 'institute_user')->getJson(route('sales.items.search'))->assertOk();
        BranchContext::clear();

        // Accountant can view
        $accountant = $this->user($inst, 'accountant');
        $this->actingAs($accountant, 'institute_user')->getJson(route('sales.customers.search'))->assertOk();
    }

    // ------------------------------------------------ No mutations

    public function test_no_inventory_mutation_on_availability_check(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $branch = $this->branch($inst);
        $item = $this->inventoryItem($inst, $branch->id, ['item_type' => 'stock_item']);
        $wh = $this->warehouse($inst, $branch->id);
        $level = InventoryStockLevel::create([
            'institute_id' => $inst->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $wh->id,
            'item_id' => $item->id,
            'quantity' => 10,
        ]);

        TenantContext::set($inst->id);
        BranchContext::set($branch->id);

        $beforeQty = (float) $level->fresh()->quantity;
        $beforeMovements = \App\Models\InventoryMovement::withoutGlobalScopes()->where('institute_id', $inst->id)->where('item_id', $item->id)->count();
        $beforeJournals = Journal::withoutGlobalScopes()->where('institute_id', $inst->id)->count();

        $this->actingAs($owner, 'institute_user')->getJson(route('sales.items.availability', ['item' => $item->id, 'qty' => 5]))->assertOk();

        $this->assertEquals($beforeQty, (float) $level->fresh()->quantity);
        $this->assertEquals($beforeMovements, \App\Models\InventoryMovement::withoutGlobalScopes()->where('institute_id', $inst->id)->where('item_id', $item->id)->count());
        $this->assertEquals($beforeJournals, Journal::withoutGlobalScopes()->where('institute_id', $inst->id)->count());

        BranchContext::clear();
    }

    public function test_no_finance_mutation_on_customer_lookup(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $party = $this->partyCustomer($inst);

        TenantContext::set($inst->id);

        $beforeJournals = Journal::withoutGlobalScopes()->where('institute_id', $inst->id)->count();
        $beforeParties = Party::withoutGlobalScopes()->where('institute_id', $inst->id)->count();

        $this->actingAs($owner, 'institute_user')->getJson(route('sales.customers.search'))->assertOk();
        $this->actingAs($owner, 'institute_user')->getJson(route('sales.customers.show', $party->id))->assertOk();
        $this->actingAs($owner, 'institute_user')->getJson(route('sales.crmCustomers.search'))->assertOk();

        $this->assertEquals($beforeJournals, Journal::withoutGlobalScopes()->where('institute_id', $inst->id)->count());
        $this->assertEquals($beforeParties, Party::withoutGlobalScopes()->where('institute_id', $inst->id)->count());
    }

    // ------------------------------------------------ Availability details

    public function test_item_availability_returns_correct_payload(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $cat = $this->inventoryCategory($inst);
        $item = $this->inventoryItem($inst, null, [
            'name' => 'Taxable Product',
            'sku' => 'TAX-001',
            'unit' => 'pcs',
            'selling_price' => 150,
            'item_type' => 'stock_item',
            'category_id' => $cat->id,
        ]);
        // Assign tax group if exists
        $taxGroup = \App\Models\TaxGroup::first();
        if ($taxGroup) {
            $item->update(['tax_group_id' => $taxGroup->id]);
            $item->refresh();
        }

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->getJson(route('sales.items.availability', ['item' => $item->id]))
            ->assertOk()
            ->assertJsonPath('data.identity.sku', 'TAX-001')
            ->assertJsonPath('data.unit', 'pcs')
            ->assertJsonPath('data.selling_price', 150)
            ->assertJsonStructure(['data' => ['identity', 'type', 'is_stockable', 'is_service', 'selling_price', 'unit', 'tax', 'discount_eligible', 'branch_available', 'stock']]);
    }
}
