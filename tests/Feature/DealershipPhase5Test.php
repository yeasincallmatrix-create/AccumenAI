<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Dealership\ApiToken;
use App\Models\Dealership\PushNotification;
use App\Models\Dealership\SalesForce;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\Dealership\Api\ApiDocGenerator;
use App\Services\Dealership\Api\ApiEndpointRegistrar;
use App\Services\Dealership\Api\ApiTokenService;
use App\Services\Dealership\Api\PushDispatchService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Carbon\Carbon;
use Database\Seeders\DealershipPhase5PermissionsSeeder;
use Database\Seeders\DealershipPhase5RegistrySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DealershipPhase5Test extends TestCase
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
            'employee_code' => 'SR5-'.$suffix,
            'name' => 'Rep '.$suffix,
        ]);
    }

    public function test_registry_has_four_new_children()
    {
        foreach ([
            'dealership.api_tokens',
            'dealership.api_endpoints',
            'dealership.api_docs',
            'dealership.push_notifications',
        ] as $key) {
            $this->assertTrue(DB::table('module_registry')->where('key', $key)->exists(), "Missing: {$key}");
        }

        $total = DB::table('module_registry')
            ->where('key', 'like', 'dealership%')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(26, $total, 'dealership registry must be 1 parent + 25 children');
    }

    public function test_children_inherit_type_and_is_core()
    {
        $children = DB::table('module_registry')->where('parent_key', 'dealership')->get();
        $this->assertCount(25, $children);

        foreach ($children as $child) {
            $this->assertEquals('core', $child->type, $child->key);
            $this->assertEquals(1, (int) $child->is_core, $child->key);
        }
    }

    public function test_registry_seeder_idempotent_run_twice()
    {
        (new DealershipPhase5RegistrySeeder)->run();
        (new DealershipPhase5RegistrySeeder)->run();

        $count = DB::table('module_registry')
            ->where('parent_key', 'dealership')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(25, $count);
    }

    public function test_permissions_count_is_51_and_idempotent()
    {
        (new DealershipPhase5PermissionsSeeder)->run();
        (new DealershipPhase5PermissionsSeeder)->run();

        $count = DB::table('permissions')->where('module', 'dealership')->count();
        $this->assertEquals(51, $count);
        $this->assertTrue(DB::table('permissions')->where('slug', 'api_tokens.manage')->exists());
        $this->assertTrue(DB::table('permissions')->where('slug', 'push_notifications.view')->exists());
    }

    public function test_all_phase5_tables_have_institute_id_not_null()
    {
        foreach ([
            'dealership_api_tokens',
            'dealership_api_endpoints',
            'dealership_push_notifications',
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
        $tokenCols = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'dealership_api_tokens')
            ->where('index_name', 'dat_token_unique')
            ->orderBy('seq_in_index')
            ->pluck('column_name')
            ->all();
        $this->assertContains('institute_id', $tokenCols);

        $epCols = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'dealership_api_endpoints')
            ->where('index_name', 'dae_key_unique')
            ->orderBy('seq_in_index')
            ->pluck('column_name')
            ->all();
        $this->assertContains('institute_id', $epCols);

        $this->assertTrue(
            DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'dealership_push_notifications')
                ->where('index_name', 'dpn_queue_idx')
                ->exists(),
            'push queue index must exist'
        );
    }

    public function test_tenant_scope_isolates_api_tokens_between_two_tenants()
    {
        $a = $this->makeInstitute('Tenant PA '.uniqid());
        $b = $this->makeInstitute('Tenant PB '.uniqid());

        TenantContext::set($a->id);
        $sa = $this->makeSalesForce($a, 'PA'.uniqid());
        app(ApiTokenService::class)->issueToken($sa, 'device A');

        TenantContext::set($b->id);
        $sb = $this->makeSalesForce($b, 'PB'.uniqid());
        app(ApiTokenService::class)->issueToken($sb, 'device B');

        TenantContext::set($a->id);
        $this->assertEquals(1, ApiToken::count());

        TenantContext::set($b->id);
        $this->assertEquals(1, ApiToken::count());

        TenantContext::clear();
        $this->assertEquals(2, ApiToken::count());
    }

    public function test_api_token_service_hashes_plaintext()
    {
        $inst = $this->makeInstitute('Dealer TK '.uniqid());
        $sr = $this->makeSalesForce($inst, uniqid());

        ['plaintext' => $raw, 'token' => $token] = app(ApiTokenService::class)
            ->issueToken($sr, 'SR Mobile', ['orders.view']);

        $this->assertNotEmpty($raw);
        $this->assertEquals(hash('sha256', $raw), $token->fresh()->token_hash);
        $this->assertNotEquals($raw, $token->fresh()->token_hash);
        $this->assertDatabaseMissing('dealership_api_tokens', ['token_hash' => $raw]);
    }

    public function test_api_token_service_rejects_expired()
    {
        $inst = $this->makeInstitute('Dealer EX '.uniqid());
        $sr = $this->makeSalesForce($inst, uniqid());

        ['plaintext' => $raw] = app(ApiTokenService::class)
            ->issueToken($sr, 'old device', [], Carbon::now()->subDay());

        $this->assertNull(app(ApiTokenService::class)->validateToken($raw, $inst->id));
    }

    public function test_api_token_service_rejects_revoked()
    {
        $inst = $this->makeInstitute('Dealer RV '.uniqid());
        $sr = $this->makeSalesForce($inst, uniqid());

        ['plaintext' => $raw, 'token' => $token] = app(ApiTokenService::class)
            ->issueToken($sr, 'lost device');

        $this->assertNotNull(app(ApiTokenService::class)->validateToken($raw, $inst->id));

        app(ApiTokenService::class)->revokeToken($token->fresh());

        $this->assertNull(app(ApiTokenService::class)->validateToken($raw, $inst->id));
    }

    public function test_endpoint_registrar_seeds_seven_defaults_idempotently()
    {
        $inst = $this->makeInstitute('Dealer EP '.uniqid());

        app(ApiEndpointRegistrar::class)->seedDefaults($inst->id);
        app(ApiEndpointRegistrar::class)->seedDefaults($inst->id);

        $count = DB::table('dealership_api_endpoints')->where('institute_id', $inst->id)->count();
        $this->assertEquals(7, $count);
    }

    public function test_push_dispatch_marks_queued_to_sent()
    {
        $inst = $this->makeInstitute('Dealer PD '.uniqid());
        $sr = $this->makeSalesForce($inst, uniqid());

        $svc = app(PushDispatchService::class);
        $svc->queue($sr, 'Target met', 'You hit 100%', ['pct' => 100]);
        $svc->queue($sr, 'New order', 'Order #123');

        $dispatched = $svc->dispatchBatch($inst->id);

        $this->assertEquals(2, $dispatched);
        $this->assertEquals(0, PushNotification::withoutGlobalScope('institute')->where('status', 'queued')->count());
        $this->assertEquals(2, PushNotification::withoutGlobalScope('institute')->where('status', 'sent')->count());
    }

    public function test_push_cancel_only_works_on_queued()
    {
        $inst = $this->makeInstitute('Dealer PC '.uniqid());
        $sr = $this->makeSalesForce($inst, uniqid());
        $owner = $this->makeOwner($inst);

        $queued = app(PushDispatchService::class)->queue($sr, 'Hello', 'World');

        $this->actingAs($owner, 'institute_user')
            ->post(route('dealership.push.cancel', $queued))
            ->assertRedirect();
        $this->assertEquals('cancelled', $queued->fresh()->status);

        $sent = app(PushDispatchService::class)->queue($sr, 'Hi', 'There');
        app(PushDispatchService::class)->dispatchBatch($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('dealership.push.cancel', $sent->fresh()))
            ->assertStatus(422);
    }

    public function test_api_doc_generator_returns_structured_array()
    {
        $inst = $this->makeInstitute('Dealer DG '.uniqid());
        app(ApiEndpointRegistrar::class)->seedDefaults($inst->id);

        $docs = app(ApiDocGenerator::class)->generate($inst->id);

        $this->assertArrayHasKey('version', $docs);
        $this->assertArrayHasKey('generated_at', $docs);
        $this->assertArrayHasKey('endpoint_count', $docs);
        $this->assertArrayHasKey('versions', $docs);
        $this->assertEquals(7, $docs['endpoint_count']);
        $this->assertArrayHasKey('v1', $docs['versions']);
    }
}
