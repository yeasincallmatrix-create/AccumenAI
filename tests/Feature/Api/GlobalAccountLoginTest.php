<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Global accounts (users — owner/staff) on the mobile v1 surface.
 *
 * Complements CoreApiTest, which covers legacy institute_users accounts.
 */
class GlobalAccountLoginTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();

        $country = \App\Models\Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'Global Test '.mt_rand(1000, 9999),
            'slug' => 'global-test-'.mt_rand(1000, 9999).uniqid(),
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

    private function branch(): Branch
    {
        return Branch::create([
            'institute_id' => $this->institute->id,
            'name' => 'Global Test Branch',
            'status' => 'active',
        ]);
    }

    private function account(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Global Owner',
            'first_name' => 'Global',
            'last_name' => 'Owner',
            'email' => 'global-owner-'.uniqid().'@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'account_type' => 'owner',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function membership(User $user, string $roleSlug = 'institute-owner'): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'branch_id' => $this->branch()->id,
            'status' => 'active',
        ]);
    }

    private function login(User $user, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/auth/login', array_merge([
            'email' => $user->email,
            'password' => $this->password,
            'device_name' => 'global-test-device',
        ], $extra));
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_owner_account_logs_in_and_reads_profile(): void
    {
        $user = $this->account();
        $this->membership($user);

        $token = $this->login($user)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.institute_id', $this->institute->id)
            ->assertJsonPath('data.user.role', 'institute-owner')
            ->assertJsonPath('data.user.email', $user->email)
            ->json('data.token');

        $this->withHeaders($this->bearer($token))->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'Global Owner')
            ->assertJsonPath('data.institute.id', $this->institute->id)
            ->assertJsonPath('data.permissions', ['*']);

        $this->withHeaders($this->bearer($token))->patchJson('/api/v1/me', ['locale' => 'bn'])
            ->assertOk()
            ->assertJsonPath('data.user.locale', 'bn');
    }

    public function test_deactivated_owner_token_is_rejected_mid_session(): void
    {
        $user = $this->account();
        $this->membership($user);

        $token = $this->login($user)->assertOk()->json('data.token');

        $user->forceFill(['status' => 'inactive'])->save();

        $this->withHeaders($this->bearer($token))->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_owner_token_reaches_modules_and_institutes(): void
    {
        $user = $this->account();
        $this->membership($user);

        $token = $this->login($user)->assertOk()->json('data.token');

        $this->withHeaders($this->bearer($token))->getJson('/api/v1/modules')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.institute_id', $this->institute->id);

        $this->withHeaders($this->bearer($token))->getJson('/api/v1/institutes')
            ->assertOk()
            ->assertJsonPath('data.institutes.0.id', $this->institute->id);
    }

    public function test_owner_token_pulls_sync_delta(): void
    {
        $user = $this->account();
        $this->membership($user);

        $token = $this->login($user)->assertOk()->json('data.token');

        $this->withHeaders($this->bearer($token))
            ->getJson('/api/v1/sync/delta')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['entities']]);
    }

    public function test_account_without_membership_cannot_log_in(): void
    {
        $user = $this->account();

        $this->login($user)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'NO_WORKSPACE')
            ->assertJsonPath('error.message', 'No active institute workspace.');
    }

    public function test_wrong_password_is_rejected_like_legacy_accounts(): void
    {
        $user = $this->account();
        $this->membership($user);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'global-test-device',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_inactive_account_is_rejected(): void
    {
        $user = $this->account(['status' => 'inactive']);
        $this->membership($user);

        $this->login($user)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_unverified_email_is_rejected(): void
    {
        // User::creating auto-verifies in the testing env, so un-set it after.
        $user = $this->account();
        $user->forceFill(['email_verified_at' => null])->save();
        $this->membership($user);

        $this->login($user)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_staff_account_uses_membership_role(): void
    {
        $user = $this->account([
            'first_name' => 'Global',
            'last_name' => 'Staff',
            'account_type' => 'staff',
        ]);
        $this->membership($user, 'accountant');

        $this->login($user)
            ->assertOk()
            ->assertJsonPath('data.user.role', 'accountant')
            ->assertJsonPath('data.user.name', 'Global Staff');
    }

    public function test_me_works_with_no_leftover_tenant_state(): void
    {
        $user = $this->account();
        $this->membership($user);

        $token = $this->login($user)->assertOk()->json('data.token');

        // A stateless API request starts with nothing pinned: no session
        // workspace, no tenant left over from an earlier request in the same
        // PHP process. The token alone must be enough to reach /me.
        TenantContext::clear();
        BranchContext::clear();
        session()->forget(\App\Support\Workspace::SESSION_KEY);

        $this->withHeaders($this->bearer($token))->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.institute.id', $this->institute->id);
    }

    public function test_refresh_rolls_a_global_account_token(): void
    {
        $user = $this->account();
        $this->membership($user);

        $old = $this->login($user)->assertOk()->json('data.token');

        $new = $this->postJson('/api/v1/auth/refresh', [], $this->bearer($old))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.token');

        $this->assertNotSame($old, $new);

        $this->withHeaders($this->bearer($new))->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }
}
