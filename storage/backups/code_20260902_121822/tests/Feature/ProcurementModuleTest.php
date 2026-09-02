<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryWarehouse;
use App\Models\Party;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuotation;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Services\Accounting\AccountingSetupService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProcurementModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
        parent::tearDown();
    }

    private function country(): Country
    {
        return Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]);
    }

    private function institute(): Institute
    {
        $inst = Institute::create([
            'name' => 'PM Inst '.uniqid(),
            'slug' => 'pm-'.uniqid(),
            'industry' => 'retail',
            'country' => $this->country()->name,
            'country_id' => $this->country()->id,
            'status' => 'active',
        ]);

        InstituteSetting::withoutGlobalScopes()->create([
            'institute_id' => $inst->id,
            'ai_config' => ['enabled' => false, 'features' => [], 'daily_limit' => 0, 'monthly_limit' => 0],
        ]);

        app(AccountingSetupService::class)->setupForInstitute($inst->id, null);

        return $inst;
    }

    private function owner(Institute $i): InstituteUser
    {
        $role = Role::where('slug', 'institute-owner')->firstOrFail();

        return InstituteUser::create([
            'institute_id' => $i->id,
            'role_id' => $role->id,
            'first_name' => 'PM',
            'last_name' => 'Owner',
            'email' => 'pm-owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    private function supplier(Institute $i): Party
    {
        return Party::create([
            'institute_id' => $i->id,
            'type' => 'supplier',
            'name' => 'Sup '.uniqid(),
            'phone' => '017'.rand(10000000, 99999999),
            'is_active' => true,
            'credit_limit' => 0,
        ]);
    }

    private function warehouse(Institute $i): InventoryWarehouse
    {
        return InventoryWarehouse::create([
            'institute_id' => $i->id,
            'name' => 'WH '.uniqid(),
            'code' => 'WH-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function item(Institute $i): InventoryItem
    {
        $cat = InventoryCategory::firstOrCreate(
            ['institute_id' => $i->id, 'name' => 'Cat-'.uniqid()],
            ['is_active' => true]
        );

        return InventoryItem::create([
            'institute_id' => $i->id,
            'category_id' => $cat->id,
            'name' => 'Item '.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'unit' => 'pcs',
            'item_type' => 'stock_item',
            'purchase_price' => 100,
            'selling_price' => 150,
            'is_active' => true,
        ]);
    }

    // --- Tests ---

    public function test_purchase_requests_index_renders(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->get(route('purchase.requests.index'))
            ->assertOk()
            ->assertSee('Purchase Requests');
    }

    public function test_purchase_request_show_renders(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);
        $item = $this->item($inst);

        $pr = PurchaseRequest::create([
            'institute_id' => $inst->id,
            'request_number' => 'PR-00001',
            'requester_id' => $owner->id,
            'request_date' => now()->toDateString(),
            'status' => 'draft',
            'estimated_total' => 200,
        ]);

        $pr->lines()->create([
            'institute_id' => $inst->id,
            'inventory_item_id' => $item->id,
            'description' => $item->name,
            'quantity' => 2,
            'unit' => 'pcs',
            'estimated_unit_price' => 100,
            'line_total' => 200,
        ]);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->get(route('purchase.requests.show', $pr))
            ->assertOk()
            ->assertSee('PR-00001')
            ->assertSee($item->name);
    }

    public function test_purchase_request_store_creates(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);
        $item = $this->item($inst);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('purchase.requests.store'), [
                'request_date' => now()->toDateString(),
                'required_by_date' => now()->addDays(5)->toDateString(),
                'notes' => 'Test request',
                'lines' => [
                    ['description' => 'Test item', 'quantity' => 3, 'unit' => 'pcs', 'estimated_unit_price' => 50, 'inventory_item_id' => $item->id],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_requests', [
            'institute_id' => $inst->id,
            'status' => 'draft',
        ]);

        $pr = PurchaseRequest::where('institute_id', $inst->id)->latest()->first();
        $this->assertEquals(150.0, (float) $pr->estimated_total);
        $this->assertCount(1, $pr->lines);
    }

    public function test_purchase_request_approve_works(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);

        $pr = PurchaseRequest::create([
            'institute_id' => $inst->id,
            'request_number' => 'PR-00002',
            'requester_id' => $owner->id,
            'request_date' => now()->toDateString(),
            'status' => 'submitted',
            'estimated_total' => 100,
        ]);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('purchase.requests.approve', $pr))
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $pr->id,
            'status' => 'approved',
        ]);

        $this->assertNotNull($pr->fresh()->approved_at);
    }

    public function test_goods_receipt_index_renders(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->get(route('purchase.receipts.index'))
            ->assertOk()
            ->assertSee('Goods Receipt');
    }

    public function test_purchase_invoice_index_renders(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->get(route('purchase.invoices.index'))
            ->assertOk()
            ->assertSee('Purchase Invoice');
    }

    public function test_purchase_quotation_index_renders(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->get(route('purchase.quotations.index'))
            ->assertOk()
            ->assertSee('Purchase Quotation');
    }

    public function test_tenant_isolation_procurement(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $ownerA = $this->owner($a);
        $ownerB = $this->owner($b);

        $pr = PurchaseRequest::create([
            'institute_id' => $a->id,
            'request_number' => 'PR-A-001',
            'requester_id' => $ownerA->id,
            'request_date' => now()->toDateString(),
            'status' => 'draft',
            'estimated_total' => 0,
        ]);

        // Tenant B should not see tenant A's request
        TenantContext::set($b->id);
        $this->assertNull(PurchaseRequest::query()->find($pr->id));

        $this->actingAs($ownerB, 'institute_user')
            ->get(route('purchase.requests.show', $pr))
            ->assertNotFound();

        // Tenant A should see its own request
        TenantContext::set($a->id);
        $this->assertNotNull(PurchaseRequest::query()->find($pr->id));

        $this->actingAs($ownerA, 'institute_user')
            ->get(route('purchase.requests.show', $pr))
            ->assertOk();
    }
}
