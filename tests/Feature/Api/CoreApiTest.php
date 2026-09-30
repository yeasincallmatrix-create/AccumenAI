<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\MobileDevice;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Core mobile API v1 — auth, me, modules, sync, devices.
 */
class CoreApiTest extends TestCase
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
            'name' => 'V1 Test '.mt_rand(1000, 9999),
            'slug' => 'v1-test-'.mt_rand(1000, 9999).uniqid(),
            'industry' => 'education',
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
            'first_name' => 'V1',
            'last_name' => 'Owner',
            'email' => 'v1-owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        TenantContext::clear();
    }

    private function login(
        ?string $email = null,
        ?string $password = null,
        ?string $device = 'test-device',
        array $extra = []
    ): \Illuminate\Testing\TestResponse {
        $payload = array_merge([
            'email' => $email ?? $this->owner->email,
            'password' => $password ?? $this->password,
        ], $device !== null ? ['device_name' => $device] : [], $extra);

        return $this->postJson('/api/v1/auth/login', $payload);
    }

    private function authHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    // 1
    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok');
    }

    // 2
    public function test_login_returns_token_and_user(): void
    {
        $this->login()
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'token', 'expires_at',
                    'user' => ['id', 'name', 'email', 'institute_id', 'role', 'locale'],
                ],
            ]);
    }

    // 3
    public function test_login_rejects_invalid_credentials(): void
    {
        $this->login(password: 'wrong-password')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    // 4
    public function test_login_rejects_inactive_user(): void
    {
        $email = 'v1-inactive-'.uniqid().'@example.test';
        InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $this->owner->role_id,
            'first_name' => 'In', 'last_name' => 'Active',
            'email' => $email,
            'phone' => '01701'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'inactive',
        ]);

        $this->login(email: $email)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    // 5
    public function test_login_requires_device_name(): void
    {
        $this->login(device: null)->assertStatus(422);
    }

    // 6
    public function test_refresh_rolls_token(): void
    {
        $old = $this->login()->json('data.token');

        $res = $this->postJson('/api/v1/auth/refresh', [], $this->authHeaders($old))
            ->assertOk()
            ->assertJsonPath('success', true);

        $new = $res->json('data.token');
        $this->assertNotSame($old, $new);

        // Guards are memoized per test app instance — drop them so the
        // revoked token is re-resolved against the database.
        Auth::forgetGuards();

        $this->getJson('/api/v1/me', $this->authHeaders($old))->assertStatus(401);
        $this->getJson('/api/v1/me', $this->authHeaders($new))->assertOk();
    }

    // 7
    public function test_logout_revokes_token(): void
    {
        $token = $this->login()->json('data.token');

        $this->postJson('/api/v1/auth/logout', [], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('success', true);

        Auth::forgetGuards();

        $this->getJson('/api/v1/me', $this->authHeaders($token))->assertStatus(401);
    }

    // 8
    public function test_me_requires_auth(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
    }

    // 9
    public function test_me_returns_profile_and_permissions(): void
    {
        $token = $this->login()->json('data.token');

        $this->getJson('/api/v1/me', $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $this->owner->email)
            ->assertJsonPath('data.institute.id', $this->institute->id)
            ->assertJsonPath('data.permissions', ['*']);
    }

    // 10
    public function test_me_update_changes_locale(): void
    {
        $token = $this->login()->json('data.token');

        $this->patchJson('/api/v1/me', ['locale' => 'bn'], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.user.locale', 'bn');

        $this->assertSame('bn', $this->owner->fresh()->preferred_language);
    }

    // 11
    public function test_institutes_scoped_to_user(): void
    {
        $token = $this->login()->json('data.token');

        $res = $this->getJson('/api/v1/institutes', $this->authHeaders($token))->assertOk();

        $this->assertCount(1, $res->json('data.institutes'));
        $this->assertSame($this->institute->id, $res->json('data.institutes.0.id'));
    }

    // 12
    public function test_modules_returns_active_only(): void
    {
        $token = $this->login()->json('data.token');

        $victim = DB::table('module_registry')->where('status', 'active')->first();
        DB::table('module_registry')->where('key', $victim->key)->update(['status' => 'inactive']);

        $keys = $this->getJson('/api/v1/modules', $this->authHeaders($token))
            ->assertOk()
            ->json('data.modules.*.key');

        $this->assertNotContains($victim->key, $keys);
        $this->assertNotEmpty($keys);
    }

    // 13
    public function test_modules_scoped_by_institute_package(): void
    {
        $token = $this->login()->json('data.token');

        $packageId = DB::table('subscription_packages')->insertGetId([
            'name' => 'V1 Test',
            'slug' => 'v1-test-'.uniqid(),
            'status' => 'active',
        ]);
        $this->institute->forceFill(['package_id' => $packageId])->save();

        $keys = DB::table('module_registry')->where('status', 'active')->limit(3)->pluck('key');
        $this->assertCount(3, $keys);

        DB::table('package_industry_modules')->insert([
            ['package_id' => $packageId, 'industry_key' => 'education', 'module_key' => $keys[0], 'enabled' => 1, 'category' => 'mandatory'],
            ['package_id' => $packageId, 'industry_key' => 'education', 'module_key' => $keys[1], 'enabled' => 1, 'category' => 'default'],
            ['package_id' => $packageId, 'industry_key' => 'education', 'module_key' => $keys[2], 'enabled' => 1, 'category' => 'hidden'],
        ]);

        $scoped = $this->getJson(
            "/api/v1/institutes/{$this->institute->id}/modules",
            $this->authHeaders($token)
        )->assertOk()->json('data.modules.*.key');

        $this->assertContains($keys[0], $scoped);
        $this->assertContains($keys[1], $scoped);
        $this->assertNotContains($keys[2], $scoped);

        $this->getJson("/api/v1/institutes/".($this->institute->id + 999999).'/modules', $this->authHeaders($token))
            ->assertStatus(403);
    }

    // 14
    public function test_sync_delta_returns_changed_records_only(): void
    {
        $token = $this->login()->json('data.token');

        $old = Branch::create([
            'institute_id' => $this->institute->id,
            'code' => 'O'.rand(100, 999),
            'name' => 'Old Branch',
        ]);
        DB::table('branches')->where('id', $old->id)->update([
            'updated_at' => now()->subDays(10),
        ]);

        $new = Branch::create([
            'institute_id' => $this->institute->id,
            'code' => 'N'.rand(100, 999),
            'name' => 'New Branch',
        ]);

        $res = $this->getJson(
            '/api/v1/sync/delta?since='.urlencode(now()->subDays(5)->toISOString()).'&entities=branches',
            $this->authHeaders($token)
        )->assertOk();

        $ids = collect($res->json('data.entities.branches'))->pluck('id')->all();
        $this->assertNotContains($old->id, $ids);
        $this->assertContains($new->id, $ids);
        $this->assertNotEmpty($res->json('meta.next_since'));
    }

    // 15
    public function test_sync_delta_respects_since_timestamp(): void
    {
        $token = $this->login()->json('data.token');

        Branch::create([
            'institute_id' => $this->institute->id,
            'code' => 'F'.rand(100, 999),
            'name' => 'Future Check',
        ]);

        $res = $this->getJson(
            '/api/v1/sync/delta?since='.urlencode(now()->addHour()->toISOString()),
            $this->authHeaders($token)
        )->assertOk();

        $this->assertSame([], $res->json('data.entities.branches'));
        $this->assertSame([], $res->json('data.entities.notifications'));
    }

    // 16
    public function test_sync_push_is_idempotent_by_client_id(): void
    {
        $token = $this->login()->json('data.token');

        $body = ['operations' => [[
            'entity' => 'branches',
            'action' => 'upsert',
            'client_id' => 'c-'.uniqid(),
            'payload' => ['code' => 'P'.rand(100, 999), 'name' => 'Pushed'],
        ]]];

        $first = $this->postJson('/api/v1/sync/push', $body, $this->authHeaders($token))
            ->assertOk()->json('data.results.0');
        $second = $this->postJson('/api/v1/sync/push', $body, $this->authHeaders($token))
            ->assertOk()->json('data.results.0');

        $this->assertSame('created', $first['status']);
        $this->assertSame($first['server_id'], $second['server_id']);
        $this->assertSame(1, Branch::where('institute_id', $this->institute->id)
            ->where('code', $body['operations'][0]['payload']['code'])->count());
    }

    // 17
    public function test_device_register_stores_fcm_token(): void
    {
        $token = $this->login()->json('data.token');
        $fcm = 'fcm-'.uniqid();

        $this->postJson('/api/v1/devices/register', [
            'fcm_token' => $fcm,
            'platform' => 'android',
            'device_name' => 'Pixel Test',
            'app_version' => '0.1.0',
        ], $this->authHeaders($token))
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertTrue(MobileDevice::where('institute_id', $this->institute->id)
            ->where('fcm_token', $fcm)->exists());

        $this->deleteJson('/api/v1/devices/'.$fcm, [], $this->authHeaders($token))->assertOk();
        $this->assertNotNull(
            MobileDevice::where('fcm_token', $fcm)->first()->revoked_at
        );
    }

    // 18
    public function test_idempotency_key_returns_cached_response(): void
    {
        $token = $this->login()->json('data.token');
        $key = 'idem-'.uniqid();

        $body = ['operations' => [[
            'entity' => 'branches',
            'action' => 'upsert',
            'client_id' => 'k-'.uniqid(),
            'payload' => ['code' => 'K'.rand(100, 999), 'name' => 'Keyed'],
        ]]];

        $headers = array_merge($this->authHeaders($token), ['Idempotency-Key' => $key]);

        $first = $this->postJson('/api/v1/sync/push', $body, $headers)->assertOk();
        $second = $this->postJson('/api/v1/sync/push', $body, $headers)->assertOk();

        $this->assertSame($first->json('data'), $second->json('data'));

        $other = $body;
        $other['operations'][0]['payload']['name'] = 'Changed';
        $this->postJson('/api/v1/sync/push', $other, $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }

    // 19
    public function test_rate_limit_returns_429(): void
    {
        $token = $this->login()->json('data.token');

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/me', $this->authHeaders($token))->assertOk();
        }

        $this->getJson('/api/v1/me', $this->authHeaders($token))->assertStatus(429);
    }

    // 20
    public function test_validation_error_uses_standard_envelope(): void
    {
        $token = $this->login()->json('data.token');

        $this->getJson('/api/v1/sync/delta?entities=nope', $this->authHeaders($token))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }
}
