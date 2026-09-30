<?php

namespace Tests\Feature\Api;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Universal foundation — /v1/me + /v1/modules shapes for the mobile app.
 */
class UniversalFoundationTest extends TestCase
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
            'name' => 'UF Test '.mt_rand(1000, 9999),
            'slug' => 'uf-test-'.mt_rand(1000, 9999).uniqid(),
            'industry' => 'retail',
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
            'first_name' => 'UF',
            'last_name' => 'Owner',
            'email' => 'uf-owner-'.uniqid().'@example.test',
            'phone' => '01720'.rand(100000, 999999),
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
            'device_name' => 'uf-test',
        ])->assertOk()->json('data.token');
    }

    public function test_me_matches_mobile_shape(): void
    {
        $token = $this->token();

        $res = $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('success', true);

        $data = $res->json('data');

        foreach (['id', 'name', 'email', 'phone', 'locale', 'avatar_url', 'institute_id', 'role'] as $field) {
            $this->assertArrayHasKey($field, $data['user'], "user.$field");
        }

        foreach (['id', 'name', 'slug', 'industry', 'logo_url', 'package'] as $field) {
            $this->assertArrayHasKey($field, $data['institute'], "institute.$field");
        }

        $this->assertIsArray($data['permissions']);
    }

    public function test_me_package_shape_when_present(): void
    {
        $packageId = \Illuminate\Support\Facades\DB::table('subscription_packages')
            ->where('status', 'active')->value('id');

        if ($packageId === null) {
            $this->markTestSkipped('No active subscription package.');
        }

        $this->institute->forceFill(['package_id' => $packageId])->save();

        $token = $this->token();

        $package = $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->json('data.institute.package');

        foreach (['id', 'name', 'tier'] as $field) {
            $this->assertArrayHasKey($field, $package, "package.$field");
        }
    }

    public function test_modules_response_has_meta(): void
    {
        $token = $this->token();

        $res = $this->getJson('/api/v1/modules?enabled=1', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($this->institute->id, $res->json('meta.institute_id'));
        $this->assertTrue($res->json('meta.enabled_only'));
        $this->assertSame(count($res->json('data.modules')), $res->json('meta.total'));
    }
}
