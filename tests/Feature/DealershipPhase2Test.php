<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Dealership\Brand;
use App\Models\Dealership\CreditLimit;
use App\Models\Dealership\Customer;
use App\Models\Dealership\OrderApproval;
use App\Models\Dealership\PriceList;
use App\Models\Dealership\Product;
use App\Models\Dealership\SalesForce;
use App\Models\Dealership\SrCollection;
use App\Models\Dealership\SrOrder;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Database\Seeders\DealershipPhase2PermissionsSeeder;
use Database\Seeders\DealershipPhase2RegistrySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DealershipPhase2Test extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    private function country(): Country
    {
        return Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );
    }

    private function makeInstitute(string $name): Institute
    {
        $c = $this->country();

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
        $u = InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'email' => uniqid().'@test.test',
            'phone' => '017'.mt_rand(10000000, 99999999),
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        return $u->fresh();
    }

    private function ownerContext(): InstituteUser
    {
        return $this->makeOwner($this->makeInstitute('Dealer Test '.uniqid()));
    }

    /** Real FK parent rows (brand, products, customer, SR) for the given institute. */
    private function dealerFixtures(Institute $inst, string $suffix): array
    {
        $brand = Brand::create(['institute_id' => $inst->id, 'code' => 'B-'.$suffix, 'name' => 'Brand '.$suffix]);
        $p1 = Product::create(['institute_id' => $inst->id, 'brand_id' => $brand->id, 'sku' => 'P1-'.$suffix, 'name' => 'P1 '.$suffix]);
        $p2 = Product::create(['institute_id' => $inst->id, 'brand_id' => $brand->id, 'sku' => 'P2-'.$suffix, 'name' => 'P2 '.$suffix]);
        $customer = Customer::create(['institute_id' => $inst->id, 'code' => 'C-'.$suffix, 'name' => 'Shop '.$suffix]);
        $sr = SalesForce::create(['institute_id' => $inst->id, 'employee_code' => 'SR-'.$suffix, 'name' => 'Rep '.$suffix]);

        return [$brand, $p1, $p2, $customer, $sr];
    }

    public function test_registry_has_six_new_children()
    {
        $keys = [
            'dealership.sr_orders',
            'dealership.sr_collection',
            'dealership.order_approval',
            'dealership.inventory_link',
            'dealership.price_lists',
            'dealership.credit_control',
        ];

        foreach ($keys as $key) {
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

        (new DealershipPhase2RegistrySeeder)->run();
    }

    public function test_seeder_is_idempotent_run_twice()
    {
        (new DealershipPhase2RegistrySeeder)->run();
        (new DealershipPhase2RegistrySeeder)->run();

        $count = DB::table('module_registry')
            ->where('parent_key', 'dealership')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(16, $count);
    }

    public function test_permissions_count_is_34_and_idempotent()
    {
        (new DealershipPhase2PermissionsSeeder)->run();
        (new DealershipPhase2PermissionsSeeder)->run();

        $count = DB::table('permissions')->where('module', 'dealership')->count();
        $this->assertEquals(34, $count);
        $this->assertTrue(DB::table('permissions')->where('slug', 'order_approval.approve')->exists());
    }

    public function test_sr_orders_index_returns_200()
    {
        $owner = $this->ownerContext();

        $this->actingAs($owner, 'institute_user')
            ->get(route('dealership.orders.index'))
            ->assertOk();
    }

    public function test_order_approve_writes_audit_row()
    {
        $inst = $this->makeInstitute('Dealer Approve '.uniqid());
        [, , , $customer, $sr] = $this->dealerFixtures($inst, uniqid());
        $owner = $this->makeOwner($inst);

        $order = SrOrder::create([
            'institute_id' => $inst->id,
            'order_no' => 'SO-TEST-'.uniqid(),
            'customer_id' => $customer->id,
            'sales_force_id' => $sr->id,
            'channel' => 'retail',
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($owner, 'institute_user')
            ->post(route('dealership.orders.approve', $order))
            ->assertRedirect();

        $this->assertEquals('approved', $order->fresh()->status);
        $this->assertTrue(
            OrderApproval::where('sr_order_id', $order->id)->where('action', 'approved')->exists(),
            'approve must write an audit row'
        );
    }

    public function test_collection_store_creates_row()
    {
        $inst = $this->makeInstitute('Dealer Collect '.uniqid());
        [, , , $customer, $sr] = $this->dealerFixtures($inst, uniqid());
        $owner = $this->makeOwner($inst);

        $this->actingAs($owner, 'institute_user')
            ->post(route('dealership.collections.store'), [
                'customer_id' => $customer->id,
                'sales_force_id' => $sr->id,
                'method' => 'cash',
                'amount' => 500.50,
                'collected_on' => now()->toDateString(),
            ])
            ->assertRedirect(route('dealership.collections.index'));

        $this->assertTrue(
            SrCollection::where('customer_id', $customer->id)->where('amount', 500.50)->exists()
        );

        $this->actingAs($owner, 'institute_user')
            ->post(route('dealership.collections.store'), [
                'customer_id' => $customer->id,
                'sales_force_id' => $sr->id,
                'method' => 'cash',
                'amount' => 0,
                'collected_on' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertEquals(1, SrCollection::where('customer_id', $customer->id)->count(), 'amount <= 0 must be rejected');
    }

    public function test_credit_block_prevents_new_order()
    {
        $inst = $this->makeInstitute('Dealer Block '.uniqid());
        [, , , $customer, $sr] = $this->dealerFixtures($inst, uniqid());
        $owner = $this->makeOwner($inst);

        CreditLimit::create([
            'institute_id' => $inst->id,
            'customer_id' => $customer->id,
            'credit_limit' => 10000,
            'is_blocked' => true,
        ]);

        $this->actingAs($owner, 'institute_user')
            ->post(route('dealership.orders.store'), [
                'customer_id' => $customer->id,
                'sales_force_id' => $sr->id,
                'channel' => 'retail',
                'items' => [['product_id' => 1, 'qty' => 2, 'unit_price' => 100]],
            ])
            ->assertRedirect();

        $this->assertEquals(0, SrOrder::where('customer_id', $customer->id)->count(), 'blocked customer must not create orders');
    }

    public function test_price_list_effective_date_filter()
    {
        $inst = $this->makeInstitute('Dealer Price '.uniqid());
        [$brand, $p1, $p2] = $this->dealerFixtures($inst, uniqid());
        $owner = $this->makeOwner($inst);

        PriceList::create([
            'institute_id' => $inst->id,
            'brand_id' => $brand->id, 'product_id' => $p1->id, 'channel' => 'retail',
            'price' => 111.11, 'effective_from' => '2026-01-01', 'effective_to' => '2026-12-31',
            'is_active' => true,
        ]);
        PriceList::create([
            'institute_id' => $inst->id,
            'brand_id' => $brand->id, 'product_id' => $p2->id, 'channel' => 'retail',
            'price' => 999.99, 'effective_from' => '2027-01-01', 'effective_to' => '2027-12-31',
            'is_active' => true,
        ]);

        $page = $this->actingAs($owner, 'institute_user')
            ->get(route('dealership.price_lists.index', ['date' => '2026-06-15']))
            ->assertOk();

        $page->assertSee('111.11', false);
        $page->assertDontSee('999.99', false);
    }
}
