<?php

namespace Tests\Feature\Api;

use App\Models\Dealership\Brand;
use App\Models\Dealership\Customer;
use App\Models\Dealership\Product;
use App\Models\Dealership\SalesForce;
use App\Models\Dealership\SrCollection;
use App\Models\Dealership\SrOrder;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\MobileSyncIdempotency;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile sync extension — dealership delta/pull + idempotent push.
 */
class SyncExtensionTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private Institute $institute;

    private InstituteUser $owner;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
        Cache::flush();

        $country = \App\Models\Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'Sync Ext '.mt_rand(1000, 9999),
            'slug' => 'sync-ext-'.mt_rand(1000, 9999).uniqid(),
            'industry' => 'dealership',
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        \App\Models\InstituteSetting::withoutGlobalScopes()->create([
            'institute_id' => $this->institute->id,
            'ai_config' => ['enabled' => false, 'features' => [], 'daily_limit' => 0, 'monthly_limit' => 0],
        ]);

        $role = Role::where('slug', 'institute-owner')->firstOrFail();

        $this->owner = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'Sync',
            'last_name' => 'Owner',
            'email' => 'sync-ext-'.uniqid().'@example.test',
            'phone' => '01710'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        TenantContext::clear();
    }

    private function token(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $this->owner->email,
            'password' => $this->password,
            'device_name' => 'sync-test',
        ])->assertOk()->json('data.token');
    }

    private function headers(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    private function uuid(): string
    {
        return (string) Str::uuid();
    }

    private function makeBrand(?int $instituteId = null): Brand
    {
        return Brand::create([
            'institute_id' => $instituteId ?? $this->institute->id,
            'code' => 'B'.rand(1000, 9999).uniqid(),
            'name' => 'Brand '.uniqid(),
        ]);
    }

    private function makeProduct(?int $instituteId = null, ?int $brandId = null): Product
    {
        $instituteId ??= $this->institute->id;

        return Product::create([
            'institute_id' => $instituteId,
            'brand_id' => $brandId ?? $this->makeBrand($instituteId)->id,
            'sku' => 'SKU-'.uniqid(),
            'name' => 'Product '.uniqid(),
            'retail_price' => 100,
            'wholesale_price' => 80,
        ]);
    }

    private function makeCustomer(?int $instituteId = null): Customer
    {
        return Customer::create([
            'institute_id' => $instituteId ?? $this->institute->id,
            'code' => 'C'.rand(1000, 9999).uniqid(),
            'name' => 'Customer '.uniqid(),
        ]);
    }

    private function makeSalesForce(?int $instituteId = null): SalesForce
    {
        return SalesForce::create([
            'institute_id' => $instituteId ?? $this->institute->id,
            'employee_code' => 'SR'.rand(1000, 9999).uniqid(),
            'name' => 'SR '.uniqid(),
        ]);
    }

    private function orderPayload(?Customer $customer = null, ?SalesForce $sr = null, ?Product $product = null): array
    {
        $customer ??= $this->makeCustomer();
        $sr ??= $this->makeSalesForce();
        $product ??= $this->makeProduct();

        return [
            'customer_id' => $customer->id,
            'sales_force_id' => $sr->id,
            'channel' => 'retail',
            'items' => [[
                'product_id' => $product->id,
                'qty' => 2,
                'unit_price' => 100,
            ]],
        ];
    }

    private function push(string $token, array $operations, array $extraHeaders = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(
            '/api/v1/sync/push',
            ['operations' => $operations],
            array_merge($this->headers($token), $extraHeaders)
        );
    }

    // 1
    public function test_delta_returns_dealership_products_for_institute(): void
    {
        $token = $this->token();
        $product = $this->makeProduct();

        $res = $this->getJson(
            '/api/v1/sync/delta?entities=dealership_products',
            $this->headers($token)
        )->assertOk();

        $ids = collect($res->json('data.entities.dealership_products'))->pluck('id')->all();
        $this->assertContains($product->id, $ids);
    }

    // 2
    public function test_delta_scopes_by_institute(): void
    {
        $token = $this->token();

        $other = Institute::create([
            'name' => 'Other '.uniqid(), 'slug' => 'other-'.uniqid(),
            'industry' => 'dealership', 'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        $foreign = $this->makeProduct($other->id);
        $local = $this->makeProduct();

        $res = $this->getJson(
            '/api/v1/sync/delta?entities=dealership_products',
            $this->headers($token)
        )->assertOk();

        $ids = collect($res->json('data.entities.dealership_products'))->pluck('id')->all();
        $this->assertContains($local->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    // 3
    public function test_delta_respects_since_cursor(): void
    {
        $token = $this->token();

        $old = $this->makeProduct();
        DB::table('dealership_products')->where('id', $old->id)->update([
            'updated_at' => now()->subDays(40),
        ]);
        $new = $this->makeProduct();

        $res = $this->getJson(
            '/api/v1/sync/delta?since='.urlencode(now()->subDays(5)->toISOString()).'&entities=dealership_products',
            $this->headers($token)
        )->assertOk();

        $ids = collect($res->json('data.entities.dealership_products'))->pluck('id')->all();
        $this->assertNotContains($old->id, $ids);
        $this->assertContains($new->id, $ids);
    }

    // 4
    public function test_delta_default_returns_all_supported_entities(): void
    {
        $token = $this->token();

        $res = $this->getJson('/api/v1/sync/delta', $this->headers($token))->assertOk();

        foreach ([
            'branches', 'notifications',
            'dealership_brands', 'dealership_beats', 'dealership_products',
            'dealership_customers', 'dealership_price_lists', 'dealership_sales_force',
            'module_registry', 'subscription_packages',
        ] as $entity) {
            $this->assertArrayHasKey($entity, $res->json('data.entities'), "missing $entity");
        }
    }

    // 5
    public function test_delta_rejects_window_over_90_days(): void
    {
        $token = $this->token();

        $this->getJson(
            '/api/v1/sync/delta?since='.urlencode(now()->subDays(100)->toISOString()),
            $this->headers($token)
        )
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    // 6
    public function test_delta_cursor_normalized_to_utc(): void
    {
        $token = $this->token();

        $res = $this->getJson('/api/v1/sync/delta', $this->headers($token))->assertOk();

        $this->assertStringEndsWith('Z', $res->json('meta.next_since'));
        $this->assertStringEndsWith('Z', $res->json('meta.server_time'));
    }

    // 7
    public function test_push_create_order_generates_server_id(): void
    {
        $token = $this->token();

        $res = $this->push($token, [[
            'entity' => 'dealership_sr_orders',
            'action' => 'create',
            'client_id' => $this->uuid(),
            'payload' => $this->orderPayload(),
        ]])->assertOk();

        $result = $res->json('data.results.0');
        $this->assertSame('created', $result['status']);
        $this->assertNotNull($result['server_id']);
        $this->assertNull($result['error']);

        $order = SrOrder::find($result['server_id']);
        $this->assertStringStartsWith('SR-'.$this->institute->id.'-', $order->order_no);
        $this->assertSame('submitted', $order->status);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(1, $order->approvals()->where('action', 'submitted')->count());
    }

    // 8
    public function test_push_order_idempotent_by_client_id(): void
    {
        $token = $this->token();
        $clientId = $this->uuid();
        $payload = $this->orderPayload();

        $op = fn () => [[
            'entity' => 'dealership_sr_orders',
            'action' => 'create',
            'client_id' => $clientId,
            'payload' => $payload,
        ]];

        $first = $this->push($token, $op())->assertOk()->json('data.results.0');
        $second = $this->push($token, $op())->assertOk()->json('data.results.0');

        $this->assertSame('created', $first['status']);
        $this->assertSame('duplicate', $second['status']);
        $this->assertSame($first['server_id'], $second['server_id']);
        $this->assertSame(1, SrOrder::where('institute_id', $this->institute->id)->count());
        $this->assertTrue(
            MobileSyncIdempotency::where('institute_id', $this->institute->id)
                ->where('client_id', $clientId)->exists()
        );
    }

    // 9
    public function test_push_collection_generates_receipt_no(): void
    {
        $token = $this->token();
        $customer = $this->makeCustomer();
        $sr = $this->makeSalesForce();

        $res = $this->push($token, [[
            'entity' => 'dealership_sr_collections',
            'action' => 'create',
            'client_id' => $this->uuid(),
            'payload' => [
                'customer_id' => $customer->id,
                'sales_force_id' => $sr->id,
                'method' => 'cash',
                'amount' => 500,
                'collected_on' => now()->toDateString(),
            ],
        ]])->assertOk();

        $result = $res->json('data.results.0');
        $this->assertSame('created', $result['status']);

        $collection = SrCollection::find($result['server_id']);
        $this->assertStringStartsWith('RC-'.$this->institute->id.'-', $collection->receipt_no);
        $this->assertSame('pending', $collection->status);
    }

    // 10
    public function test_push_rejects_unknown_entity(): void
    {
        $token = $this->token();

        $res = $this->push($token, [[
            'entity' => 'dealership_rockets',
            'action' => 'create',
            'client_id' => $this->uuid(),
            'payload' => [],
        ]])->assertOk();

        $result = $res->json('data.results.0');
        $this->assertSame('failed', $result['status']);
        $this->assertSame('UNSUPPORTED_ENTITY', $result['error']['code']);
    }

    // 11
    public function test_push_rejects_missing_client_id(): void
    {
        $token = $this->token();

        $res = $this->push($token, [[
            'entity' => 'dealership_sr_orders',
            'action' => 'create',
            'payload' => $this->orderPayload(),
        ]])->assertOk();

        $result = $res->json('data.results.0');
        $this->assertSame('failed', $result['status']);
        $this->assertSame('CLIENT_ID_REQUIRED', $result['error']['code']);
    }

    // 12
    public function test_push_rejects_invalid_client_id_format(): void
    {
        $token = $this->token();

        $res = $this->push($token, [[
            'entity' => 'dealership_sr_orders',
            'action' => 'create',
            'client_id' => 'not-a-uuid',
            'payload' => $this->orderPayload(),
        ]])->assertOk();

        $result = $res->json('data.results.0');
        $this->assertSame('failed', $result['status']);
        $this->assertSame('INVALID_CLIENT_ID', $result['error']['code']);
    }

    // 13
    public function test_push_order_ignores_client_supplied_institute_id(): void
    {
        $token = $this->token();

        $payload = $this->orderPayload();
        $payload['institute_id'] = 999999;

        $res = $this->push($token, [[
            'entity' => 'dealership_sr_orders',
            'action' => 'create',
            'client_id' => $this->uuid(),
            'payload' => $payload,
        ]])->assertOk();

        $order = SrOrder::find($res->json('data.results.0.server_id'));
        $this->assertSame($this->institute->id, (int) $order->institute_id);
    }

    // 14
    public function test_modules_response_includes_is_enabled_and_permission_slug(): void
    {
        $token = $this->token();

        $modules = $this->getJson('/api/v1/modules', $this->headers($token))
            ->assertOk()
            ->json('data.modules');

        $this->assertNotEmpty($modules);
        foreach ($modules as $module) {
            $this->assertArrayHasKey('is_enabled', $module);
            $this->assertArrayHasKey('permission_slug', $module);
            $this->assertArrayHasKey('is_core', $module);
        }
    }

    // 15
    public function test_modules_filters_by_parent_key_and_type(): void
    {
        $token = $this->token();

        $core = $this->getJson('/api/v1/modules?type=core', $this->headers($token))
            ->assertOk()
            ->json('data.modules');

        $this->assertNotEmpty($core);
        foreach ($core as $module) {
            $this->assertSame('core', $module['type']);
        }

        $parentKey = DB::table('module_registry')
            ->where('status', 'active')
            ->whereNotNull('parent_key')
            ->value('parent_key');

        if ($parentKey !== null) {
            $children = $this->getJson(
                '/api/v1/modules?parent_key='.$parentKey,
                $this->headers($token)
            )->assertOk()->json('data.modules');

            $this->assertNotEmpty($children);
            foreach ($children as $module) {
                $this->assertSame($parentKey, $module['parent_key']);
            }
        } else {
            $this->markTestSkipped('No child modules in registry.');
        }
    }
}
