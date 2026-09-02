<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\User;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UnifiedLoginTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function makeUser(string $email): User
    {
        return User::create([
            'name' => 'Global Test',
            'first_name' => 'Global',
            'last_name' => 'Test',
            'email' => $email,
            'email_verified_at' => now(),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'account_type' => 'owner',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        Workspace::clear();
    }

    public function test_global_user_login_resolves_mawa_workspace(): void
    {
        $institute = $this->getInstitute('MAWA ACADEMY');
        $user = $this->makeUser('unified-owner@example.test');

        Membership::create([
            'user_id' => $user->id,
            'institution_id' => $institute->id,
            'role_id' => 1,
            'status' => 'active',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => $this->password])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user, 'web');

        // Single membership -> auto-resolved workspace.
        $this->assertSame($institute->id, Workspace::id());
        $this->assertSame($institute->id, TenantContext::id());

        $this->get('/')
            ->assertOk()
            ->assertSee('MAWA ACADEMY')
            ->assertSee('Institute Owner')
            ->assertSee('Students')
            ->assertDontSee('Platform Admin');
    }

    public function test_global_user_login_with_bad_password_rejected(): void
    {
        $this->post('/login', ['email' => 'unified-owner@example.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    protected function getInstitute(string $name): Institute
    {
        $inst = Institute::where('name', $name)->first();
        if ($inst) return $inst;
        return Institute::create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.uniqid(),
            'email' => strtolower(str_replace(' ', '', $name)).'@test.local',
            'phone' => '+8801'.rand(100000000,999999999),
            'country' => 'Bangladesh',
            'industry' => 'education',
            'sub_industry' => 'school',
            'status' => 'active',
        ]);
    }

    public function test_global_user_with_multiple_memberships_lands_on_picker(): void
    {
        $user = $this->makeUser('unified-multi@example.test');
        $mawa = $this->getInstitute('MAWA ACADEMY');
        $other = $this->getInstitute('Tutu Center');

        Membership::create(['user_id' => $user->id, 'institution_id' => $mawa->id, 'role_id' => 1, 'status' => 'active']);
        Membership::create(['user_id' => $user->id, 'institution_id' => $other->id, 'role_id' => 1, 'status' => 'active']);

        $this->post('/login', ['email' => $user->email, 'password' => $this->password])
            ->assertRedirect('/workspace');

        $this->assertNull(Workspace::id());
    }

    public function test_global_user_without_memberships_lands_on_picker(): void
    {
        $user = $this->makeUser('unified-none@example.test');

        $this->post('/login', ['email' => $user->email, 'password' => $this->password])
            ->assertRedirect('/workspace');
    }

    public function test_workspace_picker_shows_only_active_memberships(): void
    {
        $user = $this->makeUser('unified-picker@example.test');
        $mawa = $this->getInstitute('MAWA ACADEMY');
        $other = $this->getInstitute('Tutu Center');

        Membership::create(['user_id' => $user->id, 'institution_id' => $mawa->id, 'role_id' => 1, 'status' => 'active']);
        Membership::create(['user_id' => $user->id, 'institution_id' => $other->id, 'role_id' => 1, 'status' => 'suspended']);

        $this->actingAs($user, 'web')
            ->get('/workspace')
            ->assertOk()
            ->assertSee('MAWA ACADEMY')
            ->assertDontSee('Tutu Center');
    }

    public function test_workspace_switch_sets_active_workspace(): void
    {
        $user = $this->makeUser('unified-switch@example.test');
        $mawa = $this->getInstitute('MAWA ACADEMY');
        $other = $this->getInstitute('Tutu Center');

        Membership::create(['user_id' => $user->id, 'institution_id' => $mawa->id, 'role_id' => 1, 'status' => 'active']);
        Membership::create(['user_id' => $user->id, 'institution_id' => $other->id, 'role_id' => 1, 'status' => 'active']);

        $this->actingAs($user, 'web')
            ->post('/workspace/switch/'.$other->id)
            ->assertRedirect('/');

        $this->assertSame($other->id, Workspace::id());
        $this->assertSame($other->id, TenantContext::id());
    }
}
