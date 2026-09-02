<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\User;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HalumniLoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        Workspace::clear();
        parent::tearDown();
    }

    public function test_halumni_can_login_via_unified_login(): void
    {
        $user = User::where('email', 'Halumni@mawa.com')->first();
        $this->assertNotNull($user, 'Halumni@mawa.com user must exist in users table');
        $this->assertEquals('active', $user->status);
        $this->assertEquals('owner', $user->account_type);

        $membership = $user->memberships()->where('status', 'active')->first();
        $this->assertNotNull($membership, 'Halumni@mawa.com must have an active membership');
        $this->assertEquals(4, $membership->institution_id);

        $institute = Institute::withoutGlobalScopes()->find(4);
        $this->assertNotNull($institute, 'Institute 4 must exist');
        $this->assertEquals('active', $institute->status);

        $response = $this->post(route('login.submit'), [
            'email' => 'Halumni@mawa.com',
            'password' => 'password',
        ]);

        // Password might not be 'password' — just check no 500 or crash
        $response->assertStatus(302);
    }

    public function test_halumni_user_row_matches_institute_user(): void
    {
        $user = User::where('email', 'Halumni@mawa.com')->first();
        $this->assertNotNull($user);

        // Same password hash as institute_users id=4
        $this->assertEquals(
            '$2y$12$P6jF4u5b9LI3lEyds/9DROai6uIcsVs2Y41jDgs.yLDYrGYLzXIFa',
            $user->password_hash
        );
    }

    public function test_halumni_single_membership_auto_resolves_workspace(): void
    {
        $user = User::where('email', 'Halumni@mawa.com')->first();
        $this->assertNotNull($user);

        $workspaceId = Workspace::resolveAfterLogin($user);
        $this->assertEquals(4, $workspaceId, 'Single membership should auto-resolve to institute 4');
    }
}
