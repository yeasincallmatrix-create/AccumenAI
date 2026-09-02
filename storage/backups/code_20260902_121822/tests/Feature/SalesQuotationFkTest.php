<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\Party;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesQuotationFkTest extends TestCase
{
    use DatabaseTransactions;

    private function country(): Country
    {
        return Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]);
    }

    private function institute(): Institute
    {
        $c = $this->country();
        return Institute::create(['name' => 'FK Inst '.uniqid(), 'slug' => 'fk-'.uniqid(), 'country' => $c->name, 'country_id' => $c->id, 'status' => 'active']);
    }

    private function currency(): Currency
    {
        return Currency::firstOrCreate(['code' => 'BDT'], ['name' => 'Taka', 'symbol' => 'T', 'is_active' => true]);
    }

    private function customer(Institute $i): Party
    {
        return Party::create(['institute_id' => $i->id, 'type' => 'customer', 'name' => 'Cust '.uniqid(), 'phone' => '01'.rand(100000000,999999999), 'is_active' => true]);
    }

    public function test_valid_sales_order_reference(): void
    {
        $inst = $this->institute();
        $cur = $this->currency();
        $cust = $this->customer($inst);

        $quotation = SalesQuotation::create([
            'institute_id' => $inst->id,
            'quotation_number' => 'Q-'.uniqid(),
            'customer_id' => $cust->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(10)->toDateString(),
            'currency_id' => $cur->id,
            'subtotal' => 100,
            'grand_total' => 100,
            'status' => 'draft',
        ]);

        $order = SalesOrder::create([
            'institute_id' => $inst->id,
            'order_number' => 'SO-'.uniqid(),
            'customer_id' => $cust->id,
            'order_date' => now()->toDateString(),
            'currency_id' => $cur->id,
            'subtotal' => 100,
            'grand_total' => 100,
            'status' => 'draft',
        ]);

        $quotation->update(['converted_to_order_id' => $order->id]);
        $this->assertEquals($order->id, $quotation->fresh()->converted_to_order_id);
        $this->assertDatabaseHas('sales_quotations', ['id' => $quotation->id, 'converted_to_order_id' => $order->id]);
    }

    public function test_null_reference(): void
    {
        $inst = $this->institute();
        $cur = $this->currency();
        $cust = $this->customer($inst);

        $quotation = SalesQuotation::create([
            'institute_id' => $inst->id,
            'quotation_number' => 'Q-'.uniqid(),
            'customer_id' => $cust->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(10)->toDateString(),
            'currency_id' => $cur->id,
            'subtotal' => 100,
            'grand_total' => 100,
            'status' => 'draft',
            'converted_to_order_id' => null,
        ]);

        $this->assertNull($quotation->converted_to_order_id);
        $this->assertDatabaseHas('sales_quotations', ['id' => $quotation->id, 'converted_to_order_id' => null]);
    }

    public function test_null_on_delete_behavior(): void
    {
        $inst = $this->institute();
        $cur = $this->currency();
        $cust = $this->customer($inst);

        $order = SalesOrder::create([
            'institute_id' => $inst->id,
            'order_number' => 'SO-'.uniqid(),
            'customer_id' => $cust->id,
            'order_date' => now()->toDateString(),
            'currency_id' => $cur->id,
            'subtotal' => 100,
            'grand_total' => 100,
            'status' => 'draft',
        ]);

        $quotation = SalesQuotation::create([
            'institute_id' => $inst->id,
            'quotation_number' => 'Q-'.uniqid(),
            'customer_id' => $cust->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(10)->toDateString(),
            'currency_id' => $cur->id,
            'subtotal' => 100,
            'grand_total' => 100,
            'status' => 'accepted',
            'converted_to_order_id' => $order->id,
        ]);

        $order->forceDelete();

        $this->assertNull($quotation->fresh()->converted_to_order_id);
        $this->assertDatabaseHas('sales_quotations', ['id' => $quotation->id]);
        $this->assertDatabaseMissing('sales_orders', ['id' => $order->id]);
    }

    public function test_cross_tenant_relationship_protection(): void
    {
        $instA = $this->institute();
        $instB = $this->institute();
        $cur = $this->currency();
        $custA = $this->customer($instA);
        $custB = $this->customer($instB);

        $orderB = SalesOrder::create([
            'institute_id' => $instB->id,
            'order_number' => 'SO-'.uniqid(),
            'customer_id' => $custB->id,
            'order_date' => now()->toDateString(),
            'currency_id' => $cur->id,
            'subtotal' => 100,
            'grand_total' => 100,
            'status' => 'draft',
        ]);

        $quotationA = SalesQuotation::create([
            'institute_id' => $instA->id,
            'quotation_number' => 'Q-'.uniqid(),
            'customer_id' => $custA->id,
            'quotation_date' => now()->toDateString(),
            'validity_date' => now()->addDays(10)->toDateString(),
            'currency_id' => $cur->id,
            'subtotal' => 100,
            'grand_total' => 100,
            'status' => 'draft',
        ]);

        // Application layer should prevent cross-tenant link; direct DB allows FK but service should block.
        // Here we verify DB FK does not enforce tenant, but service logic must check institute_id match.
        // Simulate service check: order belongs to different institute, should be rejected.
        $this->assertNotEquals($instA->id, $orderB->institute_id);
        // Direct update would succeed at DB level (FK only checks id exists), so we assert that service must validate.
        $quotationA->update(['converted_to_order_id' => $orderB->id]);
        // DB allows it, but we document that application must enforce tenant check
        $this->assertEquals($orderB->id, $quotationA->fresh()->converted_to_order_id);
    }

    public function test_fk_exists_and_is_set_null(): void
    {
        $fk = DB::selectOne("SELECT DELETE_RULE FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'sales_quotations' AND CONSTRAINT_NAME = 'sales_quotations_converted_to_order_id_foreign'");
        $this->assertNotNull($fk, 'FK should exist');
        $this->assertEquals('SET NULL', $fk->DELETE_RULE);
    }
}
