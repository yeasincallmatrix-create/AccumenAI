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
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\TaxGroup;
use App\Services\Sales\QuotationService;
use App\Services\Sales\SalesOrderService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesOrderTest extends TestCase
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
            'name' => 'Order Inst '.uniqid(),
            'slug' => 'order-'.uniqid(),
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

    private function currency(): Currency
    {
        return Currency::firstOrCreate(['code' => 'BDT'], ['name' => 'Taka', 'symbol' => '৳', 'is_active' => true, 'decimal_places' => 2]);
    }

    private function partyCustomer(Institute $i, ?int $branchId = null): Party
    {
        return Party::create([
            'institute_id' => $i->id,
            'branch_id' => $branchId,
            'type' => 'customer',
            'name' => 'Customer '.uniqid(),
            'phone' => '017'.rand(10000000, 99999999),
            'email' => 'cust-'.uniqid().'@example.test',
            'is_active' => true,
            'credit_limit' => 0,
        ]);
    }

    private function inventoryItem(Institute $i, ?int $branchId = null, array $overrides = []): InventoryItem
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

    private function warehouse(Institute $i, ?int $branchId = null): InventoryWarehouse
    {
        return InventoryWarehouse::create(['institute_id' => $i->id, 'branch_id' => $branchId, 'name' => 'WH '.uniqid(), 'code' => 'WH-'.uniqid(), 'is_active' => true]);
    }

    private function orderData(Party $customer, Currency $currency, ?array $lines = null, array $extra = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'currency_id' => $currency->id,
            'payment_terms' => 'Net 15',
            'billing_address' => '123 Billing St',
            'shipping_address' => '456 Shipping Ave',
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

    private function createAcceptedQuotation(Institute $i, ?Branch $branch, Party $customer, Currency $currency): SalesQuotation
    {
        $service = app(QuotationService::class);
        $q = $service->createDraft($i->id, $branch?->id, [
            'customer_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(30)->toDateString(),
            'currency_id' => $currency->id,
            'payment_terms' => 'Net 15',
            'notes' => 'Q notes',
            'terms_conditions' => 'Q terms',
            'discount_type' => 'fixed',
            'discount_amount' => 0,
            'lines' => [
                ['description' => 'Item A', 'quantity' => 2, 'unit_price' => 100, 'discount_amount' => 0],
                ['description' => 'Item B', 'quantity' => 1, 'unit_price' => 50, 'discount_amount' => 0],
            ],
        ], null);
        $q = $service->send($q);
        $q = $service->accept($q);

        return $q->fresh('lines');
    }

    // ------------------------------------------------ Basic creation

    public function test_create_draft_calculates_totals(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $customer = $this->partyCustomer($inst, $branch->id);
        $currency = $this->currency();

        $service = app(SalesOrderService::class);
        $order = $service->createDraft($inst->id, $branch->id, $this->orderData($customer, $currency), null);

        $this->assertSame('draft', $order->status);
        $this->assertNotEmpty($order->order_number);
        $this->assertStringStartsWith('SO-', $order->order_number);
        $this->assertEquals(250, (float) $order->subtotal);
        $this->assertEquals(250, (float) $order->grand_total);
        $this->assertCount(2, $order->lines);
        $this->assertEquals('123 Billing St', $order->billing_address);
        $this->assertEquals('456 Shipping Ave', $order->shipping_address);
    }

    public function test_server_recalculates_tampered_totals(): void
    {
        $inst = $this->institute();
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();

        $data = $this->orderData($customer, $currency);
        $data['subtotal'] = 9999;
        $data['grand_total'] = 1;
        $data['tax_amount'] = 999;

        $service = app(SalesOrderService::class);
        $order = $service->createDraft($inst->id, null, $data, null);

        $this->assertEquals(250, (float) $order->subtotal);
        $this->assertEquals(250, (float) $order->grand_total);
        $this->assertEquals(0, (float) $order->tax_amount);
    }

    public function test_line_discount_percent_and_tax(): void
    {
        $inst = $this->institute();
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $tax = $this->taxGroup($inst, 10);

        $service = app(SalesOrderService::class);
        $order = $service->createDraft($inst->id, null, [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'currency_id' => $currency->id,
            'billing_address' => 'Bill',
            'shipping_address' => 'Ship',
            'lines' => [
                ['description' => 'Item', 'quantity' => 10, 'unit_price' => 100, 'discount_amount' => 10, 'discount_type' => 'percent', 'tax_group_id' => $tax->id],
            ],
        ], null);

        $this->assertEquals(1000, (float) $order->subtotal);
        $this->assertEquals(100, (float) $order->discount_amount);
        $this->assertEquals(90, (float) $order->tax_amount);
        $this->assertEquals(990, (float) $order->grand_total);
    }

    public function test_direct_order_without_quotation(): void
    {
        $inst = $this->institute();
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), null);
        $this->assertNull($order->quotation_id);
        $this->assertSame('draft', $order->status);
    }

    // ------------------------------------------------ Quotation conversion

    public function test_quotation_conversion_preserves_values(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $customer = $this->partyCustomer($inst, $branch->id);
        $currency = $this->currency();
        $product = $this->inventoryItem($inst, $branch->id, ['selling_price' => 100]);

        $q = $this->createAcceptedQuotation($inst, $branch, $customer, $currency);

        $service = app(SalesOrderService::class);
        $order = $service->createFromQuotation($q, ['order_date' => now()->toDateString(), 'expected_delivery_date' => now()->addDays(5)->toDateString()], null);

        $this->assertEquals($q->id, $order->quotation_id);
        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertEquals((float) $q->grand_total, (float) $order->grand_total);
        $this->assertCount($q->lines->count(), $order->lines);
        $this->assertEquals((float) $q->lines[0]->unit_price, (float) $order->lines[0]->unit_price);
        $this->assertEquals((float) $q->lines[0]->quantity, (float) $order->lines[0]->quantity);

        // Quotation not modified except converted_to_order_id
        $q->refresh();
        $this->assertEquals($order->id, $q->converted_to_order_id);
        $this->assertNotNull($q->converted_at);
        $this->assertEquals('accepted', $q->status);
    }

    public function test_quotation_conversion_copies_historical_prices(): void
    {
        $inst = $this->institute();
        $product = $this->inventoryItem($inst, null, ['selling_price' => 100]);
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();

        $qService = app(QuotationService::class);
        $q = $qService->createDraft($inst->id, null, [
            'customer_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(10)->toDateString(),
            'currency_id' => $currency->id,
            'lines' => [['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 2, 'unit_price' => 100, 'discount_amount' => 0]],
        ], null);
        $q = $qService->send($q);
        $q = $qService->accept($q);

        $originalTotal = (float) $q->grand_total;
        $product->update(['selling_price' => 999]);

        $service = app(SalesOrderService::class);
        $order = $service->createFromQuotation($q->fresh('lines'), ['order_date' => now()->toDateString()], null);

        $this->assertEquals($originalTotal, (float) $order->grand_total);
        $this->assertEquals(100, (float) $order->lines[0]->unit_price);
    }

    public function test_duplicate_quotation_conversion_blocked(): void
    {
        $inst = $this->institute();
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $q = $this->createAcceptedQuotation($inst, null, $customer, $currency);

        $service = app(SalesOrderService::class);
        $service->createFromQuotation($q, ['order_date' => now()->toDateString()], null);

        $this->expectException(ValidationException::class);
        $service->createFromQuotation($q->fresh(), ['order_date' => now()->toDateString()], null);
    }

    public function test_non_accepted_quotation_cannot_convert(): void
    {
        $inst = $this->institute();
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $qService = app(QuotationService::class);
        $q = $qService->createDraft($inst->id, null, [
            'customer_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(10)->toDateString(),
            'currency_id' => $currency->id,
            'lines' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 100]],
        ], null);

        $service = app(SalesOrderService::class);
        $this->expectException(ValidationException::class);
        $service->createFromQuotation($q, ['order_date' => now()->toDateString()], null);
    }

    // ------------------------------------------------ Approval workflow

    public function test_workflow_full_lifecycle(): void
    {
        $inst = $this->institute();
        $creator = $this->user($inst, 'institute-owner');
        $approver = $this->user($inst, 'institute-admin');
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), $creator->id);
        $this->assertSame('draft', $order->status);

        $order = $service->submit($order, $creator->id);
        $this->assertSame('pending_approval', $order->status);
        $this->assertNotNull($order->submitted_at);

        $order = $service->approve($order, $approver->id);
        $this->assertSame('approved', $order->status);
        $this->assertEquals($approver->id, $order->approved_by);

        $order = $service->markProcessing($order, $approver->id);
        $this->assertSame('processing', $order->status);

        $order = $service->markReadyForDelivery($order, $approver->id);
        $this->assertSame('ready_for_delivery', $order->status);

        $order = $service->complete($order, $approver->id);
        $this->assertSame('completed', $order->status);
    }

    public function test_reject_and_resubmit(): void
    {
        $inst = $this->institute();
        $creator = $this->user($inst, 'institute-owner');
        $approver = $this->user($inst, 'institute-admin');
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), $creator->id);
        $order = $service->submit($order, $creator->id);
        $order = $service->reject($order, $approver->id);
        $this->assertSame('rejected', $order->status);

        // Rejected can be edited
        $order = $service->updateDraft($order, ['notes' => 'Updated after rejection'], $creator->id);
        $this->assertEquals('Updated after rejection', $order->notes);

        // Resubmit
        $order = $service->submit($order, $creator->id);
        $this->assertSame('pending_approval', $order->status);

        // Cancel from pending
        $order = $service->cancel($order, $creator->id);
        $this->assertSame('cancelled', $order->status);
    }

    public function test_invalid_transitions_rejected(): void
    {
        $inst = $this->institute();
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), null);

        $this->expectException(ValidationException::class);
        $service->approve($order, null); // draft -> approved not allowed
    }

    public function test_self_approval_blocked(): void
    {
        $inst = $this->institute();
        $creator = $this->user($inst, 'institute-owner');
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), $creator->id);
        $order = $service->submit($order, $creator->id);

        $this->expectException(ValidationException::class);
        $service->approve($order, $creator->id);
    }

    // ------------------------------------------------ Editing rules

    public function test_edit_only_draft_or_rejected(): void
    {
        $inst = $this->institute();
        $approver = $this->user($inst, 'institute-admin');
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), null);
        $order = $service->submit($order, null);
        $order = $service->approve($order, $approver->id);

        $this->expectException(ValidationException::class);
        $service->updateDraft($order, ['notes' => 'hack'], null);
    }

    public function test_approved_order_values_not_silently_changed(): void
    {
        $inst = $this->institute();
        $product = $this->inventoryItem($inst, null, ['selling_price' => 100]);
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, null, [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'currency_id' => $currency->id,
            'lines' => [['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 2, 'unit_price' => 100, 'discount_amount' => 0]],
        ], null);
        $order = $service->submit($order, null);
        $approver = $this->user($inst, 'institute-admin');
        $order = $service->approve($order, $approver->id);

        $originalTotal = (float) $order->grand_total;
        $product->update(['selling_price' => 999]);

        $order->refresh();
        $this->assertEquals($originalTotal, (float) $order->grand_total);
        $this->assertEquals(100, (float) $order->lines[0]->unit_price);
    }

    // ------------------------------------------------ Security: tenant/branch/customer/product/id spoofing/tampered

    public function test_tenant_isolation(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $customerA = $this->partyCustomer($a);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($a->id, null, $this->orderData($customerA, $currency), null);

        TenantContext::set($b->id);
        $this->assertNull(SalesOrder::query()->find($order->id));
        TenantContext::set($a->id);
        $this->assertNotNull(SalesOrder::query()->find($order->id));
    }

    public function test_branch_isolation(): void
    {
        $inst = $this->institute();
        $branchA = $this->branch($inst, 'A');
        $branchB = $this->branch($inst, 'B');
        $customerA = $this->partyCustomer($inst, $branchA->id);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, $branchA->id, $this->orderData($customerA, $currency), null);

        BranchContext::set($branchB->id);
        TenantContext::set($inst->id);
        $this->assertNull(SalesOrder::query()->find($order->id));

        BranchContext::set($branchA->id);
        $this->assertNotNull(SalesOrder::query()->find($order->id));
    }

    public function test_customer_scope_enforced(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $customerB = $this->partyCustomer($b);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $this->expectException(ValidationException::class);
        $service->createDraft($a->id, null, $this->orderData($customerB, $currency), null);
    }

    public function test_product_scope_enforced(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $productB = $this->inventoryItem($b);
        $customerA = $this->partyCustomer($a);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $this->expectException(ValidationException::class);
        $service->createDraft($a->id, null, [
            'customer_id' => $customerA->id,
            'order_date' => now()->toDateString(),
            'currency_id' => $currency->id,
            'lines' => [['inventory_item_id' => $productB->id, 'description' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ], null);
    }

    public function test_unauthorized_approval_blocked_via_http(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $owner = $this->user($inst, 'institute-owner');
        $branchMgr = $this->user($inst, 'branch-manager', $branch->id); // has sales.create/update but not manage
        $customer = $this->partyCustomer($inst, $branch->id);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, $branch->id, $this->orderData($customer, $currency), $owner->id);
        $order = $service->submit($order, $owner->id);

        TenantContext::set($inst->id);
        BranchContext::set($branch->id);

        // branch-manager can submit but not approve
        $this->actingAs($branchMgr, 'institute_user')
            ->post(route('sales.orders.approve', $order))
            ->assertForbidden();

        // owner (with manage) approves but self-approval blocked
        $this->actingAs($owner, 'institute_user')
            ->post(route('sales.orders.approve', $order))
            ->assertSessionHasErrors(); // validation exception redirected

        BranchContext::clear();
    }

    public function test_request_id_spoofing_blocked(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $aOwner = $this->user($a, 'institute-owner');
        $customerA = $this->partyCustomer($a);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);
        $order = $service->createDraft($a->id, null, $this->orderData($customerA, $currency), null);

        TenantContext::set($b->id);
        $bOwner = $this->user($b, 'institute-owner');

        $this->actingAs($bOwner, 'institute_user')
            ->get(route('sales.orders.show', $order))
            ->assertNotFound();

        $this->actingAs($bOwner, 'institute_user')
            ->post(route('sales.orders.submit', $order))
            ->assertNotFound();
    }

    public function test_tampered_totals_ignored_on_update(): void
    {
        $inst = $this->institute();
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), null);
        $this->assertEquals(250, (float) $order->grand_total);

        $order = $service->updateDraft($order, [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'currency_id' => $currency->id,
            'subtotal' => 9999,
            'grand_total' => 1,
            'lines' => [['description' => 'Hacked', 'quantity' => 1, 'unit_price' => 9999]],
        ], null);

        $this->assertEquals(9999, (float) $order->lines[0]->unit_price);
        $this->assertEquals(9999, (float) $order->grand_total);
        // But if we tamper totals in request, service ignores header tampered
    }

    public function test_unauthorized_editing_after_approval_blocked_via_http(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $admin = $this->user($inst, 'institute-admin');
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), $owner->id);
        $order = $service->submit($order, $owner->id);
        $order = $service->approve($order, $admin->id);

        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user')
            ->put(route('sales.orders.update', $order), [
                'customer_id' => $customer->id,
                'order_date' => now()->toDateString(),
                'currency_id' => $currency->id,
                'lines' => [['description' => 'Hack', 'quantity' => 1, 'unit_price' => 1]],
            ])
            ->assertSessionHasErrors();
    }

    // ------------------------------------------------ Audit & no inventory/finance mutations

    public function test_audit_logged(): void
    {
        $inst = $this->institute();
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), null);
        $order = $service->submit($order, null);

        $logs = DB::table('accounting_audit_trails')
            ->where('institute_id', $inst->id)
            ->where('entity_type', 'sales_order')
            ->where('entity_id', $order->id)
            ->get();

        $this->assertGreaterThanOrEqual(2, $logs->count());
        $this->assertContains('create', $logs->pluck('action')->all());
        $this->assertContains('update', $logs->pluck('action')->all());
    }

    public function test_no_inventory_reduction_on_creation_or_approval(): void
    {
        $inst = $this->institute();
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
        $customer = $this->partyCustomer($inst, $branch->id);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $beforeQty = (float) $level->fresh()->quantity;
        $beforeMovements = InventoryMovement::withoutGlobalScopes()->where('institute_id', $inst->id)->where('item_id', $item->id)->count();

        $order = $service->createDraft($inst->id, $branch->id, [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'currency_id' => $currency->id,
            'lines' => [['inventory_item_id' => $item->id, 'description' => $item->name, 'quantity' => 5, 'unit_price' => 100]],
        ], null);

        $this->assertEquals($beforeQty, (float) $level->fresh()->quantity);
        $this->assertEquals($beforeMovements, InventoryMovement::withoutGlobalScopes()->where('institute_id', $inst->id)->where('item_id', $item->id)->count());

        $admin = $this->user($inst, 'institute-admin');
        $order = $service->submit($order, null);
        $order = $service->approve($order, $admin->id);

        $this->assertEquals($beforeQty, (float) $level->fresh()->quantity);
        $this->assertEquals($beforeMovements, InventoryMovement::withoutGlobalScopes()->where('institute_id', $inst->id)->where('item_id', $item->id)->count());
    }

    public function test_no_finance_posting_on_approval(): void
    {
        $inst = $this->institute();
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $beforeJournals = Journal::withoutGlobalScopes()->where('institute_id', $inst->id)->count();

        $order = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), null);
        $order = $service->submit($order, null);
        $admin = $this->user($inst, 'institute-admin');
        $order = $service->approve($order, $admin->id);

        $this->assertEquals($beforeJournals, Journal::withoutGlobalScopes()->where('institute_id', $inst->id)->count());
        // Also processing etc should not post
        $order = $service->markProcessing($order, $admin->id);
        $this->assertEquals($beforeJournals, Journal::withoutGlobalScopes()->where('institute_id', $inst->id)->count());
    }

    public function test_branch_scope_create_and_view(): void
    {
        $inst = $this->institute();
        $branchA = $this->branch($inst, 'A');
        $branchB = $this->branch($inst, 'B');
        $customerA = $this->partyCustomer($inst, $branchA->id);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, $branchA->id, $this->orderData($customerA, $currency), null);

        TenantContext::set($inst->id);
        BranchContext::set($branchB->id);
        $this->assertNull(SalesOrder::query()->find($order->id));

        $mgrB = $this->user($inst, 'branch-manager', $branchB->id);
        $this->actingAs($mgrB, 'institute_user')
            ->get(route('sales.orders.show', $order))
            ->assertNotFound();

        BranchContext::set($branchA->id);
        $this->assertNotNull(SalesOrder::query()->find($order->id));
    }

    public function test_permissions_enforced_on_routes(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $receptionist = $this->user($inst, 'receptionist');
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);
        $order = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), null);

        TenantContext::set($inst->id);

        $this->actingAs($receptionist, 'institute_user')->get(route('sales.orders.index'))->assertForbidden();
        $this->actingAs($receptionist, 'institute_user')->get(route('sales.orders.show', $order))->assertForbidden();

        $this->actingAs($owner, 'institute_user')->get(route('sales.orders.index'))->assertOk();
        $this->actingAs($owner, 'institute_user')->get(route('sales.orders.show', $order))->assertOk();
    }

    public function test_numbering_unique(): void
    {
        $inst = $this->institute();
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $o1 = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), null);
        $o2 = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), null);

        $this->assertNotEquals($o1->order_number, $o2->order_number);
    }

    public function test_cancel_from_various_states(): void
    {
        $inst = $this->institute();
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $o = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), null);
        $o = $service->cancel($o, null);
        $this->assertSame('cancelled', $o->status);

        $o2 = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), null);
        $o2 = $service->submit($o2, null);
        $o2 = $service->cancel($o2, null);
        $this->assertSame('cancelled', $o2->status);
    }

    public function test_decimal_precision(): void
    {
        $inst = $this->institute();
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);

        $order = $service->createDraft($inst->id, null, [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'currency_id' => $currency->id,
            'lines' => [['description' => 'Precise', 'quantity' => 3, 'unit_price' => 33.3333, 'discount_amount' => 0]],
        ], null);

        $this->assertEquals('99.9999', number_format((float) $order->grand_total, 4, '.', ''));
    }

    public function test_http_create_and_convert_flow(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $owner = $this->user($inst, 'institute-owner');
        $customer = $this->partyCustomer($inst, $branch->id);
        $currency = $this->currency();
        $q = $this->createAcceptedQuotation($inst, $branch, $customer, $currency);

        TenantContext::set($inst->id);
        BranchContext::set($branch->id);

        // Convert via dedicated route
        $this->actingAs($owner, 'institute_user')
            ->post(route('sales.orders.convert', $q), ['order_date' => now()->toDateString()])
            ->assertRedirect();

        $order = SalesOrder::withoutGlobalScopes()->where('quotation_id', $q->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals($customer->id, $order->customer_id);

        // Second convert should fail (duplicate)
        $this->actingAs($owner, 'institute_user')
            ->post(route('sales.orders.convert', $q), ['order_date' => now()->toDateString()])
            ->assertSessionHasErrors();
    }

    public function test_http_submit_approve_reject_processing_flow(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $admin = $this->user($inst, 'institute-admin');
        $customer = $this->partyCustomer($inst);
        $currency = $this->currency();
        $service = app(SalesOrderService::class);
        $order = $service->createDraft($inst->id, null, $this->orderData($customer, $currency), $owner->id);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')->post(route('sales.orders.submit', $order))->assertRedirect();
        $this->assertSame('pending_approval', $order->fresh()->status);

        // Self-approval blocked
        $this->actingAs($owner, 'institute_user')->post(route('sales.orders.approve', $order))->assertSessionHasErrors();
        $this->assertSame('pending_approval', $order->fresh()->status);

        // Admin approves
        $this->actingAs($admin, 'institute_user')->post(route('sales.orders.approve', $order))->assertRedirect();
        $this->assertSame('approved', $order->fresh()->status);

        $this->actingAs($admin, 'institute_user')->post(route('sales.orders.processing', $order))->assertRedirect();
        $this->assertSame('processing', $order->fresh()->status);

        $this->actingAs($admin, 'institute_user')->post(route('sales.orders.ready', $order))->assertRedirect();
        $this->assertSame('ready_for_delivery', $order->fresh()->status);

        $this->actingAs($admin, 'institute_user')->post(route('sales.orders.complete', $order))->assertRedirect();
        $this->assertSame('completed', $order->fresh()->status);
    }
}
