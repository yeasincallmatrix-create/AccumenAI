<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Dealership\Attendance;
use App\Models\Dealership\Brand;
use App\Models\Dealership\Customer;
use App\Models\Dealership\Product;
use App\Models\Dealership\ReportSnapshot;
use App\Models\Dealership\SalesForce;
use App\Models\Dealership\SrCollection;
use App\Models\Dealership\SrCommission;
use App\Models\Dealership\SrOrder;
use App\Models\Dealership\SrTarget;
use App\Models\Institute;
use App\Services\Dealership\Reports\CollectionReportService;
use App\Services\Dealership\Reports\DashboardService;
use App\Services\Dealership\Reports\SalesReportService;
use App\Services\Dealership\Reports\SrSalesReportService;
use App\Services\Dealership\Reports\TargetReportService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Database\Seeders\DealershipPhase4PermissionsSeeder;
use Database\Seeders\DealershipPhase4RegistrySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DealershipPhase4Test extends TestCase
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

    private function makeSalesForce(Institute $inst, string $suffix): SalesForce
    {
        return SalesForce::create([
            'institute_id' => $inst->id,
            'employee_code' => 'SR4-'.$suffix,
            'name' => 'Rep '.$suffix,
        ]);
    }

    private function makeCustomer(Institute $inst, string $suffix): Customer
    {
        return Customer::create([
            'institute_id' => $inst->id,
            'code' => 'C4-'.$suffix,
            'name' => 'Shop '.$suffix,
        ]);
    }

    private function makeProduct(Institute $inst, string $suffix): Product
    {
        $brand = Brand::create(['institute_id' => $inst->id, 'code' => 'B4-'.$suffix, 'name' => 'Brand '.$suffix]);

        return Product::create([
            'institute_id' => $inst->id, 'brand_id' => $brand->id,
            'sku' => 'P4-'.$suffix, 'name' => 'Item '.$suffix,
        ]);
    }

    private function makeOrder(Institute $inst, Customer $c, SalesForce $sr, float $total, string $status = 'approved', string $channel = 'retail'): SrOrder
    {
        return SrOrder::create([
            'institute_id' => $inst->id,
            'order_no' => 'SO4-'.uniqid(),
            'customer_id' => $c->id,
            'sales_force_id' => $sr->id,
            'channel' => $channel,
            'subtotal' => $total,
            'total' => $total,
            'status' => $status,
        ]);
    }

    public function test_registry_has_five_new_children()
    {
        foreach ([
            'dealership.sr_reports',
            'dealership.sales_reports',
            'dealership.collection_reports',
            'dealership.target_reports',
            'dealership.dashboard',
        ] as $key) {
            $this->assertTrue(DB::table('module_registry')->where('key', $key)->exists(), "Missing: {$key}");
        }

        $total = DB::table('module_registry')
            ->where('key', 'like', 'dealership%')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(22, $total, 'dealership registry must be 1 parent + 21 children');
    }

    public function test_children_inherit_type_and_is_core()
    {
        $children = DB::table('module_registry')->where('parent_key', 'dealership')->get();
        $this->assertCount(21, $children);

        foreach ($children as $child) {
            $this->assertEquals('core', $child->type, $child->key);
            $this->assertEquals(1, (int) $child->is_core, $child->key);
        }
    }

    public function test_registry_seeder_idempotent_run_twice()
    {
        (new DealershipPhase4RegistrySeeder)->run();
        (new DealershipPhase4RegistrySeeder)->run();

        $count = DB::table('module_registry')
            ->where('parent_key', 'dealership')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(21, $count);
    }

    public function test_permissions_count_is_43_and_idempotent()
    {
        (new DealershipPhase4PermissionsSeeder)->run();
        (new DealershipPhase4PermissionsSeeder)->run();

        $count = DB::table('permissions')->where('module', 'dealership')->count();
        $this->assertEquals(43, $count);
        $this->assertTrue(DB::table('permissions')->where('slug', 'dashboard.view')->exists());
        $this->assertTrue(DB::table('permissions')->where('slug', 'sr_reports.export')->exists());
    }

    public function test_report_snapshots_table_has_institute_id_not_null()
    {
        $this->assertTrue(Schema::hasColumn('dealership_report_snapshots', 'institute_id'));

        $nullable = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'dealership_report_snapshots')
            ->where('column_name', 'institute_id')
            ->value('is_nullable');
        $this->assertEquals('NO', $nullable);
    }

    public function test_snapshot_composite_unique_includes_institute_id()
    {
        $cols = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'dealership_report_snapshots')
            ->where('index_name', 'drs_scope_unique')
            ->orderBy('seq_in_index')
            ->pluck('column_name')
            ->all();

        $this->assertContains('institute_id', $cols);
        $this->assertContains('report_key', $cols);
        $this->assertContains('scope_hash', $cols);
    }

    public function test_tenant_scope_isolates_snapshots_between_tenants()
    {
        $a = $this->makeInstitute('Tenant SA '.uniqid());
        $b = $this->makeInstitute('Tenant SB '.uniqid());

        TenantContext::set($a->id);
        ReportSnapshot::create([
            'report_key' => 'sr_sales_summary', 'scope_hash' => hash('sha256', 'a'),
            'payload' => ['x' => 1], 'generated_at' => now(),
        ]);

        TenantContext::set($b->id);
        ReportSnapshot::create([
            'report_key' => 'sr_sales_summary', 'scope_hash' => hash('sha256', 'b'),
            'payload' => ['x' => 2], 'generated_at' => now(),
        ]);

        TenantContext::set($a->id);
        $this->assertEquals(1, ReportSnapshot::count());

        TenantContext::set($b->id);
        $this->assertEquals(1, ReportSnapshot::count());

        TenantContext::clear();
        $this->assertEquals(2, ReportSnapshot::count());
    }

    public function test_sr_report_service_aggregates_correctly()
    {
        $inst = $this->makeInstitute('Dealer SR '.uniqid());
        $c = $this->makeCustomer($inst, uniqid());
        $sr1 = $this->makeSalesForce($inst, 'R1'.uniqid());
        $sr2 = $this->makeSalesForce($inst, 'R2'.uniqid());

        $this->makeOrder($inst, $c, $sr1, 100);
        $this->makeOrder($inst, $c, $sr1, 200);
        $this->makeOrder($inst, $c, $sr2, 500);

        $report = app(SrSalesReportService::class)->calculate([], $inst->id);

        $this->assertEquals(3, $report['totals']['orders']);
        $this->assertEquals(800.0, $report['totals']['amount']);

        $bySr = collect($report['rows'])->keyBy('sales_force_id');
        $this->assertEquals(300.0, $bySr[$sr1->id]['amount']);
        $this->assertEquals(2, $bySr[$sr1->id]['orders']);
        $this->assertEquals(500.0, $bySr[$sr2->id]['amount']);
    }

    public function test_sales_report_groups_by_channel()
    {
        $inst = $this->makeInstitute('Dealer CH '.uniqid());
        $c = $this->makeCustomer($inst, uniqid());
        $sr = $this->makeSalesForce($inst, uniqid());
        $p = $this->makeProduct($inst, uniqid());

        $o1 = $this->makeOrder($inst, $c, $sr, 200, 'approved', 'retail');
        $o1->items()->create(['institute_id' => $inst->id, 'product_id' => $p->id, 'qty' => 2, 'unit_price' => 100, 'line_total' => 200]);
        $o2 = $this->makeOrder($inst, $c, $sr, 50, 'approved', 'retail');
        $o2->items()->create(['institute_id' => $inst->id, 'product_id' => $p->id, 'qty' => 1, 'unit_price' => 50, 'line_total' => 50]);
        $o3 = $this->makeOrder($inst, $c, $sr, 300, 'approved', 'wholesale');
        $o3->items()->create(['institute_id' => $inst->id, 'product_id' => $p->id, 'qty' => 3, 'unit_price' => 100, 'line_total' => 300]);

        $report = app(SalesReportService::class)->calculate(['group_by' => 'channel'], $inst->id);

        $byChannel = collect($report['rows'])->keyBy('channel');
        $this->assertEquals(250.0, $byChannel['retail']['amount']);
        $this->assertEquals(300.0, $byChannel['wholesale']['amount']);
    }

    public function test_collection_report_aging_buckets()
    {
        $inst = $this->makeInstitute('Dealer AG '.uniqid());
        $c = $this->makeCustomer($inst, uniqid());
        $sr = $this->makeSalesForce($inst, uniqid());

        foreach ([0 => 100.0, 40 => 200.0, 100 => 300.0] as $daysAgo => $amount) {
            SrCollection::create([
                'institute_id' => $inst->id,
                'receipt_no' => 'RC4-'.uniqid(),
                'customer_id' => $c->id,
                'sales_force_id' => $sr->id,
                'method' => 'cash',
                'amount' => $amount,
                'collected_on' => now()->subDays($daysAgo)->toDateString(),
                'status' => 'pending',
            ]);
        }

        $report = app(CollectionReportService::class)->calculate([], $inst->id);

        $this->assertEquals(100.0, $report['aging']['current']['amount']);
        $this->assertEquals(200.0, $report['aging']['d31_60']['amount']);
        $this->assertEquals(0, $report['aging']['d61_90']['count']);
        $this->assertEquals(300.0, $report['aging']['d91_plus']['amount']);
    }

    public function test_target_report_variance_calculation()
    {
        $inst = $this->makeInstitute('Dealer TV '.uniqid());
        $c = $this->makeCustomer($inst, uniqid());
        $sr = $this->makeSalesForce($inst, uniqid());

        SrTarget::create([
            'institute_id' => $inst->id,
            'sales_force_id' => $sr->id,
            'period_type' => 'monthly',
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->addDays(30)->toDateString(),
            'target_amount' => 1000,
        ]);
        $this->makeOrder($inst, $c, $sr, 700);

        $report = app(TargetReportService::class)->calculate([], $inst->id);

        $this->assertCount(1, $report['rows']);
        $this->assertEquals(1000.0, $report['rows'][0]['target']);
        $this->assertEquals(700.0, $report['rows'][0]['achieved']);
        $this->assertEquals(-300.0, $report['rows'][0]['variance']);
        $this->assertEquals(70.0, $report['rows'][0]['achievement_pct']);
    }

    public function test_dashboard_kpi_rollup_returns_expected_keys()
    {
        $inst = $this->makeInstitute('Dealer DB '.uniqid());
        $c = $this->makeCustomer($inst, uniqid());
        $sr = $this->makeSalesForce($inst, uniqid());

        $this->makeOrder($inst, $c, $sr, 1000);
        SrCollection::create([
            'institute_id' => $inst->id, 'receipt_no' => 'RC4-'.uniqid(),
            'customer_id' => $c->id, 'sales_force_id' => $sr->id,
            'method' => 'cash', 'amount' => 400,
            'collected_on' => now()->toDateString(), 'status' => 'cleared',
        ]);
        SrTarget::create([
            'institute_id' => $inst->id, 'sales_force_id' => $sr->id,
            'period_type' => 'monthly', 'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->addDays(30)->toDateString(), 'target_amount' => 2000,
        ]);
        SrCommission::create([
            'institute_id' => $inst->id, 'sales_force_id' => $sr->id,
            'period_start' => now()->subDays(30)->toDateString(), 'period_end' => now()->addDays(30)->toDateString(),
            'base_amount' => 1000, 'commission_rate' => 5, 'commission_amount' => 50, 'status' => 'pending',
        ]);
        Attendance::create([
            'institute_id' => $inst->id, 'sales_force_id' => $sr->id,
            'attendance_date' => now()->toDateString(), 'status' => 'present',
        ]);

        $kpis = app(DashboardService::class)->calculate([], $inst->id);

        foreach ([
            'total_orders', 'total_sales', 'total_collected', 'collection_rate',
            'pending_commission', 'target_total', 'achieved_total', 'present_today',
        ] as $key) {
            $this->assertArrayHasKey($key, $kpis, "missing KPI: {$key}");
        }

        $this->assertEquals(1, $kpis['total_orders']);
        $this->assertEquals(1000.0, $kpis['total_sales']);
        $this->assertEquals(400.0, $kpis['total_collected']);
        $this->assertEquals(50.0, $kpis['pending_commission']);
        $this->assertEquals(1, $kpis['present_today']);
    }
}
