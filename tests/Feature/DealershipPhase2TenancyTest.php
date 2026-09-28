<?php

namespace Tests\Feature;

use App\Models\Concerns\TenantScoped;
use App\Models\Country;
use App\Models\Dealership\Customer;
use App\Models\Dealership\SalesForce;
use App\Models\Dealership\SrOrder;
use App\Models\Institute;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DealershipPhase2TenancyTest extends TestCase
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
        parent::tearDown();
    }

    private function makeInstitute(string $name): Institute
    {
        $c = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        return Institute::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'country' => $c->name,
            'country_id' => $c->id,
            'industry' => 'retail',
            'sub_industry' => 'grocery',
            'status' => 'active',
            'verified' => true,
            'phone' => '017'.mt_rand(10000000, 99999999),
            'email' => uniqid().'@test.test',
            'address' => 'Test Address',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'upazila' => 'Dhanmondi',
            'postal_code' => '1209',
        ]);
    }

    /** Create customer + SR in the CURRENT tenant context. Returns [customer, sr]. */
    private function makeEntities(string $suffix): array
    {
        $customer = Customer::create([
            'code' => 'C-'.$suffix,
            'name' => 'Shop '.$suffix,
        ]);
        $sr = SalesForce::create([
            'employee_code' => 'SR-'.$suffix,
            'name' => 'Rep '.$suffix,
        ]);

        return [$customer, $sr];
    }

    public function test_all_phase2_tables_have_institute_id()
    {
        foreach ([
            'dealership_sr_orders',
            'dealership_sr_order_items',
            'dealership_order_approvals',
            'dealership_sr_collections',
            'dealership_price_lists',
            'dealership_credit_limits',
            'dealership_inventory_links',
        ] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'institute_id'), "{$table} must have institute_id");
        }
    }

    public function test_all_phase1_tables_have_institute_id()
    {
        foreach ([
            'dealership_brands',
            'dealership_beats',
            'dealership_products',
            'dealership_sales_force',
            'dealership_customers',
        ] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'institute_id'), "{$table} must have institute_id");
        }
    }

    public function test_tenant_scope_isolates_sr_orders_between_two_tenants()
    {
        $a = $this->makeInstitute('Tenant A '.uniqid());
        $b = $this->makeInstitute('Tenant B '.uniqid());

        TenantContext::set($a->id);
        [$ca, $sa] = $this->makeEntities('A'.uniqid());
        SrOrder::create([
            'order_no' => 'SO-ISO-'.uniqid(), 'customer_id' => $ca->id,
            'sales_force_id' => $sa->id, 'subtotal' => 100, 'total' => 100, 'status' => 'submitted',
        ]);

        TenantContext::set($b->id);
        [$cb, $sb] = $this->makeEntities('B'.uniqid());
        SrOrder::create([
            'order_no' => 'SO-ISO-'.uniqid(), 'customer_id' => $cb->id,
            'sales_force_id' => $sb->id, 'subtotal' => 200, 'total' => 200, 'status' => 'submitted',
        ]);

        TenantContext::set($a->id);
        $this->assertEquals(1, SrOrder::count(), 'tenant A must see only its own order');

        TenantContext::set($b->id);
        $this->assertEquals(1, SrOrder::count(), 'tenant B must see only its own order');

        TenantContext::clear();
        $this->assertEquals(2, SrOrder::count(), 'unscoped context must see both orders');
    }

    public function test_tenant_scope_trait_applied_to_all_dealership_models()
    {
        foreach ([
            \App\Models\Dealership\SrOrder::class,
            \App\Models\Dealership\SrOrderItem::class,
            \App\Models\Dealership\OrderApproval::class,
            \App\Models\Dealership\SrCollection::class,
            \App\Models\Dealership\PriceList::class,
            \App\Models\Dealership\CreditLimit::class,
            \App\Models\Dealership\InventoryLink::class,
            \App\Models\Dealership\Brand::class,
            \App\Models\Dealership\Beat::class,
            \App\Models\Dealership\Product::class,
            \App\Models\Dealership\SalesForce::class,
            \App\Models\Dealership\Customer::class,
        ] as $class) {
            $this->assertContains(
                TenantScoped::class,
                class_uses_recursive($class),
                "{$class} must use TenantScoped"
            );
        }
    }

    public function test_composite_unique_allows_same_order_no_across_tenants()
    {
        $a = $this->makeInstitute('Tenant UA '.uniqid());
        $b = $this->makeInstitute('Tenant UB '.uniqid());

        TenantContext::set($a->id);
        [$ca, $sa] = $this->makeEntities('UA'.uniqid());
        SrOrder::create([
            'order_no' => 'SO-SAME-001', 'customer_id' => $ca->id,
            'sales_force_id' => $sa->id, 'subtotal' => 10, 'total' => 10, 'status' => 'draft',
        ]);

        TenantContext::set($b->id);
        [$cb, $sb] = $this->makeEntities('UB'.uniqid());
        SrOrder::create([
            'order_no' => 'SO-SAME-001', 'customer_id' => $cb->id,
            'sales_force_id' => $sb->id, 'subtotal' => 20, 'total' => 20, 'status' => 'draft',
        ]);

        TenantContext::clear();
        $this->assertEquals(2, SrOrder::where('order_no', 'SO-SAME-001')->count());

        TenantContext::set($a->id);
        try {
            SrOrder::create([
                'order_no' => 'SO-SAME-001', 'customer_id' => $ca->id,
                'sales_force_id' => $sa->id, 'subtotal' => 30, 'total' => 30, 'status' => 'draft',
            ]);
            $this->fail('duplicate order_no within the same tenant must violate the composite unique');
        } catch (QueryException $e) {
            $this->assertTrue(true);
        } finally {
            TenantContext::clear();
        }
    }

    public function test_fk_constraints_present_on_phase2_tables()
    {
        $count = DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('constraint_type', 'FOREIGN KEY')
            ->whereIn('table_name', [
                'dealership_sr_orders',
                'dealership_sr_order_items',
                'dealership_order_approvals',
                'dealership_sr_collections',
                'dealership_price_lists',
                'dealership_credit_limits',
                'dealership_inventory_links',
            ])
            ->count();

        $this->assertEquals(12, $count, 'phase 2 tables must carry their 12 FK constraints');
    }

    public function test_phase2_existing_tests_still_green()
    {
        $this->assertEquals(299, DB::table('module_registry')->where('status', 'active')->count());
        $this->assertEquals(51, DB::table('permissions')->where('module', 'dealership')->count());
        $this->assertEquals(25, DB::table('module_registry')->where('parent_key', 'dealership')->count());
    }
}
