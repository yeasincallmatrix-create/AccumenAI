<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Dealership\Attendance;
use App\Models\Dealership\Brand;
use App\Models\Dealership\SalesForce;
use App\Models\Dealership\SrCommission;
use App\Models\Dealership\SrTarget;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Database\Seeders\DealershipPhase3PermissionsSeeder;
use Database\Seeders\DealershipPhase3RegistrySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DealershipPhase3Test extends TestCase
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

    private function makeOwner(Institute $inst): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'email' => uniqid().'@test.test',
            'phone' => '017'.mt_rand(10000000, 99999999),
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'email_verified_at' => now(),
        ])->fresh();
    }

    private function makeSalesForce(Institute $inst, string $suffix): SalesForce
    {
        return SalesForce::create([
            'institute_id' => $inst->id,
            'employee_code' => 'SR3-'.$suffix,
            'name' => 'Rep '.$suffix,
        ]);
    }

    public function test_registry_has_five_new_children()
    {
        foreach ([
            'dealership.sr_targets',
            'dealership.sr_commission',
            'dealership.brand_targets',
            'dealership.incentives',
            'dealership.attendance',
        ] as $key) {
            $this->assertTrue(DB::table('module_registry')->where('key', $key)->exists(), "Missing: {$key}");
        }

        $total = DB::table('module_registry')
            ->where('key', 'like', 'dealership%')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(17, $total, 'dealership registry must be 1 parent + 16 children');
    }

    public function test_children_inherit_type_and_is_core()
    {
        $children = DB::table('module_registry')->where('parent_key', 'dealership')->get();
        $this->assertCount(16, $children);

        foreach ($children as $child) {
            $this->assertEquals('core', $child->type, $child->key);
            $this->assertEquals(1, (int) $child->is_core, $child->key);
        }
    }

    public function test_parent_seeder_guard_raises_if_parent_missing()
    {
        $this->expectException(RuntimeException::class);

        DB::table('module_registry')->where('key', 'dealership')->delete();

        (new DealershipPhase3RegistrySeeder)->run();
    }

    public function test_registry_seeder_idempotent_run_twice()
    {
        (new DealershipPhase3RegistrySeeder)->run();
        (new DealershipPhase3RegistrySeeder)->run();

        $count = DB::table('module_registry')
            ->where('parent_key', 'dealership')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(16, $count);
    }

    public function test_permissions_count_is_34_and_idempotent()
    {
        (new DealershipPhase3PermissionsSeeder)->run();
        (new DealershipPhase3PermissionsSeeder)->run();

        $count = DB::table('permissions')->where('module', 'dealership')->count();
        $this->assertEquals(34, $count);
        $this->assertTrue(DB::table('permissions')->where('slug', 'sr_commission.approve')->exists());
    }

    public function test_all_phase3_tables_have_institute_id_not_null()
    {
        foreach ([
            'dealership_sr_targets',
            'dealership_brand_targets',
            'dealership_sr_commission',
            'dealership_incentives',
            'dealership_attendance',
        ] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'institute_id'), "{$table} must have institute_id");

            $nullable = DB::table('information_schema.columns')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('column_name', 'institute_id')
                ->value('is_nullable');
            $this->assertEquals('NO', $nullable, "{$table}.institute_id must be NOT NULL");
        }
    }

    public function test_composite_uniques_include_institute_id()
    {
        foreach ([
            'dst_scope_unique' => 'dealership_sr_targets',
            'dbt_scope_unique' => 'dealership_brand_targets',
            'dsc_scope_unique' => 'dealership_sr_commission',
            'dat_scope_unique' => 'dealership_attendance',
        ] as $index => $table) {
            $cols = DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->orderBy('seq_in_index')
                ->pluck('column_name')
                ->all();
            $this->assertContains('institute_id', $cols, "{$index} must include institute_id");
        }
    }

    public function test_tenant_scope_isolates_sr_targets_between_two_tenants()
    {
        $a = $this->makeInstitute('Tenant TA '.uniqid());
        $b = $this->makeInstitute('Tenant TB '.uniqid());

        TenantContext::set($a->id);
        $sa = $this->makeSalesForce($a, 'TA'.uniqid());
        SrTarget::create([
            'sales_force_id' => $sa->id, 'period_type' => 'monthly',
            'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'target_amount' => 5000,
        ]);

        TenantContext::set($b->id);
        $sb = $this->makeSalesForce($b, 'TB'.uniqid());
        SrTarget::create([
            'sales_force_id' => $sb->id, 'period_type' => 'monthly',
            'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'target_amount' => 7000,
        ]);

        TenantContext::set($a->id);
        $this->assertEquals(1, SrTarget::count());

        TenantContext::set($b->id);
        $this->assertEquals(1, SrTarget::count());

        TenantContext::clear();
        $this->assertEquals(2, SrTarget::count());
    }

    public function test_sr_commission_approve_transitions_pending_to_approved()
    {
        $inst = $this->makeInstitute('Dealer Comm '.uniqid());
        $sr = $this->makeSalesForce($inst, uniqid());
        $owner = $this->makeOwner($inst);

        $commission = SrCommission::create([
            'institute_id' => $inst->id,
            'sales_force_id' => $sr->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'base_amount' => 10000,
            'commission_rate' => 5,
            'commission_amount' => 500,
            'status' => 'pending',
        ]);

        $this->actingAs($owner, 'institute_user')
            ->post(route('dealership.commissions.approve', $commission))
            ->assertRedirect();

        $fresh = $commission->fresh();
        $this->assertEquals('approved', $fresh->status);
        $this->assertNotNull($fresh->approved_at);
        $this->assertEquals($owner->id, (int) $fresh->approved_by);
    }

    public function test_attendance_duplicate_for_same_sr_date_blocked()
    {
        $inst = $this->makeInstitute('Dealer Att '.uniqid());
        $sr = $this->makeSalesForce($inst, uniqid());
        $owner = $this->makeOwner($inst);

        $payload = [
            'sales_force_id' => $sr->id,
            'attendance_date' => '2026-09-29',
            'status' => 'present',
        ];

        $this->actingAs($owner, 'institute_user')
            ->post(route('dealership.attendance.store'), $payload)
            ->assertRedirect(route('dealership.attendance.index'));

        $this->actingAs($owner, 'institute_user')
            ->post(route('dealership.attendance.store'), $payload)
            ->assertRedirect();

        $this->assertEquals(
            1,
            Attendance::withoutGlobalScope('institute')->where('sales_force_id', $sr->id)->count(),
            'duplicate SR+date must be blocked'
        );
    }

    public function test_sr_target_period_start_after_end_rejected()
    {
        $inst = $this->makeInstitute('Dealer Tgt '.uniqid());
        $sr = $this->makeSalesForce($inst, uniqid());
        $owner = $this->makeOwner($inst);

        $this->actingAs($owner, 'institute_user')
            ->post(route('dealership.targets.store'), [
                'sales_force_id' => $sr->id,
                'period_type' => 'monthly',
                'period_start' => '2026-09-30',
                'period_end' => '2026-09-01',
                'target_amount' => 1000,
            ])
            ->assertRedirect();

        $this->assertEquals(0, SrTarget::withoutGlobalScope('institute')->count(), 'inverted period must be rejected');
    }

    public function test_fk_constraints_present_on_phase3_tables()
    {
        $count = DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('constraint_type', 'FOREIGN KEY')
            ->whereIn('table_name', [
                'dealership_sr_targets',
                'dealership_brand_targets',
                'dealership_sr_commission',
                'dealership_incentives',
                'dealership_attendance',
            ])
            ->count();

        $this->assertEquals(6, $count, 'phase 3 tables must carry 6 FK constraints');
    }
}
