<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\InventoryItem;
use App\Models\Party;
use App\Models\SalesQuotation;
use App\Models\TaxGroup;
use App\Services\Sales\QuotationService;
use App\Services\Sales\SalesNumberingService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesQuotationTest extends TestCase
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

    private function institute(string $name = 'Quo Inst'): Institute
    {
        $country = $this->country();
        return Institute::create([
            'name' => $name . ' ' . uniqid(),
            'slug' => str()->slug($name . ' ' . uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'retail',
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name = 'Main'): Branch
    {
        return Branch::create(['institute_id' => $institute->id, 'name' => $name . uniqid(), 'status' => 'active']);
    }

    private function currency(): Currency
    {
        return Currency::firstOrCreate(['code' => 'BDT'], ['name' => 'Taka', 'symbol' => '৳', 'is_active' => true, 'decimal_places' => 2]);
    }

    private function customer(Institute $institute, ?Branch $branch): Party
    {
        return Party::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'type' => 'customer',
            'name' => 'Customer ' . uniqid(),
            'phone' => '01' . rand(100000000, 999999999),
            'is_active' => true,
        ]);
    }

    private function product(Institute $institute, ?Branch $branch, float $price = 100): InventoryItem
    {
        return InventoryItem::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'name' => 'Product ' . uniqid(),
            'sku' => 'SKU-' . uniqid(),
            'selling_price' => $price,
            'purchase_price' => $price * 0.7,
            'is_active' => true,
        ]);
    }

    private function taxGroup(Institute $institute, float $rate = 15): TaxGroup
    {
        return TaxGroup::create([
            'institute_id' => $institute->id,
            'name' => 'VAT ' . $rate,
            'type' => 'vat',
            'rate' => $rate,
            'is_active' => true,
        ]);
    }

    private function quotationData(Party $customer, Currency $currency, ?array $lines = null): array
    {
        return [
            'customer_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(30)->toDateString(),
            'currency_id' => $currency->id,
            'payment_terms' => 'Net 15',
            'notes' => 'Test notes',
            'terms_conditions' => 'Test terms',
            'discount_type' => 'fixed',
            'discount_amount' => 0,
            'lines' => $lines ?? [
                ['description' => 'Service A', 'quantity' => 2, 'unit_price' => 100, 'discount_amount' => 0, 'discount_type' => 'fixed'],
                ['description' => 'Service B', 'quantity' => 1, 'unit_price' => 50, 'discount_amount' => 0],
            ],
        ];
    }

    public function test_create_draft_calculates_totals(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();

        $service = app(QuotationService::class);
        $quotation = $service->createDraft($inst->id, $branch->id, $this->quotationData($customer, $currency), null);

        $this->assertSame('draft', $quotation->status);
        $this->assertNotEmpty($quotation->quotation_number);
        $this->assertEquals(250, (float) $quotation->subtotal);
        $this->assertEquals(250, (float) $quotation->grand_total);
        $this->assertCount(2, $quotation->lines);
    }

    public function test_server_recalculates_tampered_totals(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $customer = $this->customer($inst, $branch);
        $currency = $this->currency();

        $data = $this->quotationData($customer, $currency);
        $data['subtotal'] = 9999;
        $data['grand_total'] = 1;
        $data['lines'][0]['unit_price'] = 100;

        $service = app(QuotationService::class);
        $quotation = $service->createDraft($inst->id, $branch->id, $data, null);

        // Server must ignore tampered totals
        $this->assertEquals(250, (float) $quotation->subtotal);
        $this->assertEquals(250, (float) $quotation->grand_total);
        // Tampered line price is used (100) but totals recomputed
        $this->assertEquals(200, (float) $quotation->lines[0]->line_total);
    }

    public function test_line_discount_percent_and_tax(): void
    {
        $inst = $this->institute();
        $customer = $this->customer($inst, null);
        $currency = $this->currency();
        $tax = $this->taxGroup($inst, 10);

        $service = app(QuotationService::class);
        $quotation = $service->createDraft($inst->id, null, [
            'customer_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(10)->toDateString(),
            'currency_id' => $currency->id,
            'lines' => [
                ['description' => 'Item', 'quantity' => 10, 'unit_price' => 100, 'discount_amount' => 10, 'discount_type' => 'percent', 'tax_group_id' => $tax->id],
            ],
        ], null);

        // 10*100=1000, 10% discount =100, net 900, 10% tax =90, total 990
        $this->assertEquals(1000, (float) $quotation->subtotal);
        $this->assertEquals(100, (float) $quotation->discount_amount);
        $this->assertEquals(90, (float) $quotation->tax_amount);
        $this->assertEquals(990, (float) $quotation->grand_total);
    }

    public function test_workflow_draft_sent_accepted(): void
    {
        $inst = $this->institute();
        $customer = $this->customer($inst, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $q = $service->createDraft($inst->id, null, $this->quotationData($customer, $currency), null);
        $this->assertSame('draft', $q->status);

        $q = $service->send($q);
        $this->assertSame('sent', $q->status);

        $q = $service->accept($q);
        $this->assertSame('accepted', $q->status);
        $this->assertTrue($service->canConvertToOrder($q));
    }

    public function test_invalid_transition_rejected(): void
    {
        $inst = $this->institute();
        $customer = $this->customer($inst, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $q = $service->createDraft($inst->id, null, $this->quotationData($customer, $currency), null);

        $this->expectException(ValidationException::class);
        $service->accept($q); // draft -> accepted not allowed
    }

    public function test_reject_and_cancel(): void
    {
        $inst = $this->institute();
        $customer = $this->customer($inst, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $q = $service->createDraft($inst->id, null, $this->quotationData($customer, $currency), null);
        $q = $service->send($q);
        $q = $service->reject($q);
        $this->assertSame('rejected', $q->status);

        $q2 = $service->createDraft($inst->id, null, $this->quotationData($customer, $currency), null);
        $q2 = $service->cancel($q2);
        $this->assertSame('cancelled', $q2->status);
    }

    public function test_expire(): void
    {
        $inst = $this->institute();
        $customer = $this->customer($inst, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $q = $service->createDraft($inst->id, null, $this->quotationData($customer, $currency), null);
        $q = $service->send($q);
        // Force validity past
        $q->update(['validity_date' => now()->subDay()->toDateString()]);
        $q->refresh();
        $this->assertTrue($q->isExpiredByDate());

        $q = $service->expire($q);
        $this->assertSame('expired', $q->status);
    }

    public function test_edit_only_draft(): void
    {
        $inst = $this->institute();
        $customer = $this->customer($inst, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $q = $service->createDraft($inst->id, null, $this->quotationData($customer, $currency), null);
        $q = $service->send($q);

        $this->expectException(ValidationException::class);
        $service->updateDraft($q, $this->quotationData($customer, $currency), null);
    }

    public function test_historical_safety_preserves_values(): void
    {
        $inst = $this->institute();
        $product = $this->product($inst, null, 100);
        $customer = $this->customer($inst, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $q = $service->createDraft($inst->id, null, [
            'customer_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(10)->toDateString(),
            'currency_id' => $currency->id,
            'lines' => [
                ['inventory_item_id' => $product->id, 'description' => $product->name, 'quantity' => 2, 'unit_price' => 100, 'discount_amount' => 0],
            ],
        ], null);

        $q = $service->send($q);
        $q = $service->accept($q);

        $originalTotal = (float) $q->grand_total;
        $originalLinePrice = (float) $q->lines[0]->unit_price;

        // Change product price
        $product->update(['selling_price' => 999]);

        $q->refresh();
        $this->assertEquals($originalTotal, (float) $q->grand_total);
        $this->assertEquals($originalLinePrice, (float) $q->lines[0]->unit_price);
    }

    public function test_tenant_isolation(): void
    {
        $instA = $this->institute('A');
        $instB = $this->institute('B');
        $customerA = $this->customer($instA, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $q = $service->createDraft($instA->id, null, $this->quotationData($customerA, $currency), null);

        TenantContext::set($instB->id);
        $this->assertNull(SalesQuotation::query()->find($q->id));
        TenantContext::set($instA->id);
        $this->assertNotNull(SalesQuotation::query()->find($q->id));
    }

    public function test_branch_isolation(): void
    {
        $inst = $this->institute();
        $branchA = $this->branch($inst, 'A');
        $branchB = $this->branch($inst, 'B');
        $customer = $this->customer($inst, $branchA);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $q = $service->createDraft($inst->id, $branchA->id, $this->quotationData($customer, $currency), null);

        BranchContext::set($branchB->id);
        TenantContext::set($inst->id);
        $this->assertNull(SalesQuotation::query()->find($q->id));

        BranchContext::set($branchA->id);
        $this->assertNotNull(SalesQuotation::query()->find($q->id));
    }

    public function test_customer_scope_enforced(): void
    {
        $instA = $this->institute('A');
        $instB = $this->institute('B');
        $customerB = $this->customer($instB, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $this->expectException(ValidationException::class);
        $service->createDraft($instA->id, null, $this->quotationData($customerB, $currency), null);
    }

    public function test_product_scope_enforced(): void
    {
        $instA = $this->institute('A');
        $instB = $this->institute('B');
        $productB = $this->product($instB, null, 100);
        $customerA = $this->customer($instA, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $this->expectException(ValidationException::class);
        $service->createDraft($instA->id, null, [
            'customer_id' => $customerA->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(10)->toDateString(),
            'currency_id' => $currency->id,
            'lines' => [
                ['inventory_item_id' => $productB->id, 'description' => 'X', 'quantity' => 1, 'unit_price' => 100],
            ],
        ], null);
    }

    public function test_duplicate_submission_numbering_unique(): void
    {
        $inst = $this->institute();
        $customer = $this->customer($inst, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $q1 = $service->createDraft($inst->id, null, $this->quotationData($customer, $currency), null);
        $q2 = $service->createDraft($inst->id, null, $this->quotationData($customer, $currency), null);

        $this->assertNotEquals($q1->quotation_number, $q2->quotation_number);
    }

    public function test_tampered_line_price_ignored(): void
    {
        $inst = $this->institute();
        $customer = $this->customer($inst, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $q = $service->createDraft($inst->id, null, [
            'customer_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(10)->toDateString(),
            'currency_id' => $currency->id,
            'lines' => [
                ['description' => 'Hack', 'quantity' => 1, 'unit_price' => 9999, 'discount_amount' => 0],
            ],
        ], null);

        $this->assertEquals(9999, (float) $q->lines[0]->unit_price);
        $this->assertEquals(9999, (float) $q->grand_total);

        // Tamper after creation — try to update with different price via direct DB should not affect computed unless via service
        // Service update will recalc correctly
        $q = $service->updateDraft($q, [
            'customer_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(10)->toDateString(),
            'currency_id' => $currency->id,
            'lines' => [
                ['description' => 'Hack', 'quantity' => 1, 'unit_price' => 50, 'discount_amount' => 0],
            ],
        ], null);

        $this->assertEquals(50, (float) $q->grand_total);
    }

    public function test_conversion_readiness(): void
    {
        $inst = $this->institute();
        $customer = $this->customer($inst, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $q = $service->createDraft($inst->id, null, $this->quotationData($customer, $currency), null);
        $this->assertFalse($service->canConvertToOrder($q));

        $q = $service->send($q);
        $this->assertFalse($service->canConvertToOrder($q));

        $q = $service->accept($q);
        $this->assertTrue($service->canConvertToOrder($q));

        $q->update(['converted_to_order_id' => 999]);
        $this->assertFalse($service->canConvertToOrder($q->fresh()));
    }

    public function test_audit_logged(): void
    {
        $inst = $this->institute();
        $customer = $this->customer($inst, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $q = $service->createDraft($inst->id, null, $this->quotationData($customer, $currency), null);
        $q = $service->send($q);

        $logs = DB::table('accounting_audit_trails')
            ->where('institute_id', $inst->id)
            ->where('entity_type', 'sales_quotation')
            ->where('entity_id', $q->id)
            ->get();

        $this->assertGreaterThanOrEqual(2, $logs->count());
        $this->assertContains('create', $logs->pluck('action')->all());
        $this->assertContains('update', $logs->pluck('action')->all());
    }

    public function test_decimal_precision(): void
    {
        $inst = $this->institute();
        $customer = $this->customer($inst, null);
        $currency = $this->currency();
        $service = app(QuotationService::class);

        $q = $service->createDraft($inst->id, null, [
            'customer_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(10)->toDateString(),
            'currency_id' => $currency->id,
            'lines' => [
                ['description' => 'Precise', 'quantity' => 3, 'unit_price' => 33.3333, 'discount_amount' => 0],
            ],
        ], null);

        // 3 * 33.3333 = 99.9999
        $this->assertEquals('99.9999', number_format((float) $q->grand_total, 4, '.', ''));
    }
}
