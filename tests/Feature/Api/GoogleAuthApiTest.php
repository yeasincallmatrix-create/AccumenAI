<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * POST /api/v1/auth/google — mobile Google sign-in for global accounts.
 */
class GoogleAuthApiTest extends TestCase
{
    use DatabaseTransactions;

    private const AUDIENCE = 'mobile-test-client.apps.googleusercontent.com';

    protected string $password = 'secret12345';

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();

        config([
            'services.google.client_id' => self::AUDIENCE,
            'services.google.mobile_client_id' => null,
        ]);

        $country = Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'Google Test '.mt_rand(1000, 9999),
            'slug' => 'google-test-'.mt_rand(1000, 9999).uniqid(),
            'industry' => 'education',
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        InstituteSetting::withoutGlobalScopes()->create([
            'institute_id' => $this->institute->id,
            'ai_config' => ['enabled' => false, 'features' => [], 'daily_limit' => 0, 'monthly_limit' => 0],
        ]);

        TenantContext::clear();
    }

    private function account(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Google Owner',
            'first_name' => 'Google',
            'last_name' => 'Owner',
            'email' => 'google-owner-'.uniqid().'@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'account_type' => 'owner',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function membership(User $user): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'branch_id' => Branch::create([
                'institute_id' => $this->institute->id,
                'name' => 'Google Test Branch',
                'status' => 'active',
            ])->id,
            'status' => 'active',
        ]);
    }

    private function claims(array $overrides = []): array
    {
        return array_merge([
            'iss' => 'accounts.google.com',
            'aud' => self::AUDIENCE,
            'sub' => 'google-sub-'.mt_rand(1000, 9999),
            'email' => 'nobody@example.test',
            'email_verified' => 'true',
            'exp' => now()->addHour()->getTimestamp(),
            'picture' => 'https://example.test/avatar.png',
            'name' => 'Google Owner',
        ], $overrides);
    }

    private function fakeGoogle(array $claims, int $status = 200): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response($claims, $status),
        ]);
    }

    private function attempt(array $body): TestResponse
    {
        return $this->postJson('/api/v1/auth/google', array_merge([
            'device_name' => 'google-test-device',
        ], $body));
    }

    public function test_sign_in_issues_token_for_owner_account(): void
    {
        $user = $this->account();
        $this->membership($user);
        $this->fakeGoogle($this->claims(['email' => $user->email]));

        $token = $this->attempt(['id_token' => 'valid-id-token'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.institute_id', $this->institute->id)
            ->assertJsonPath('data.user.role', 'institute-owner')
            ->assertJsonPath('data.user.email', $user->email)
            ->json('data.token');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.institute.id', $this->institute->id);

        $user->refresh();
        $this->assertNotNull($user->google_id);
    }

    public function test_linking_google_identity_resets_lockout(): void
    {
        $user = $this->account(['locked_until' => now()->subMinutes(5)]);
        $this->membership($user);
        $this->fakeGoogle($this->claims(['email' => $user->email]));

        $this->attempt(['id_token' => 'valid-id-token'])->assertOk();

        $user->refresh();
        $this->assertNull($user->locked_until);
        $this->assertSame(0, (int) $user->failed_login_count);
        $this->assertNotNull($user->google_id);
    }

    public function test_unknown_google_email_is_refused(): void
    {
        $this->fakeGoogle($this->claims(['email' => 'stranger@example.test']));

        $this->attempt(['id_token' => 'valid-id-token'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'GOOGLE_NO_ACCOUNT');
    }

    public function test_account_without_membership_is_refused(): void
    {
        $user = $this->account();
        $this->fakeGoogle($this->claims(['email' => $user->email]));

        $this->attempt(['id_token' => 'valid-id-token'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'NO_WORKSPACE');
    }

    public function test_wrong_audience_is_refused(): void
    {
        $user = $this->account();
        $this->membership($user);
        $this->fakeGoogle($this->claims([
            'email' => $user->email,
            'aud' => 'someone-elses-client.apps.googleusercontent.com',
        ]));

        $this->attempt(['id_token' => 'valid-id-token'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'GOOGLE_AUTH_FAILED');
    }

    public function test_unverified_email_is_refused(): void
    {
        $user = $this->account();
        $this->membership($user);
        $this->fakeGoogle($this->claims([
            'email' => $user->email,
            'email_verified' => 'false',
        ]));

        $this->attempt(['id_token' => 'valid-id-token'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'GOOGLE_AUTH_FAILED');
    }

    public function test_expired_token_is_refused(): void
    {
        $user = $this->account();
        $this->membership($user);
        $this->fakeGoogle($this->claims([
            'email' => $user->email,
            'exp' => now()->subMinute()->getTimestamp(),
        ]));

        $this->attempt(['id_token' => 'valid-id-token'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'GOOGLE_AUTH_FAILED');
    }

    public function test_google_rejecting_the_token_is_refused(): void
    {
        $user = $this->account();
        $this->membership($user);
        $this->fakeGoogle(['error' => 'invalid_token'], 400);

        $this->attempt(['id_token' => 'tampered'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'GOOGLE_AUTH_FAILED');
    }

    public function test_inactive_account_is_refused(): void
    {
        $user = $this->account(['status' => 'inactive']);
        $this->membership($user);
        $this->fakeGoogle($this->claims(['email' => $user->email]));

        $this->attempt(['id_token' => 'valid-id-token'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_soft_deleted_account_is_refused(): void
    {
        $user = $this->account();
        $this->membership($user);
        $user->delete();
        $this->fakeGoogle($this->claims(['email' => $user->email]));

        $this->attempt(['id_token' => 'valid-id-token'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');
    }

    public function test_sign_in_is_unavailable_when_not_configured(): void
    {
        config(['services.google.client_id' => null, 'services.google.mobile_client_id' => null]);

        $this->attempt(['id_token' => 'valid-id-token'])
            ->assertStatus(501)
            ->assertJsonPath('error.code', 'GOOGLE_NOT_CONFIGURED');
    }

    public function test_missing_id_token_is_validated(): void
    {
        $this->postJson('/api/v1/auth/google', ['device_name' => 'google-test-device'])
            ->assertStatus(422);
    }
}
