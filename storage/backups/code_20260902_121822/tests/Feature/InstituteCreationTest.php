<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Http\Controllers\InstituteOnboardingController;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstituteCreationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function owner(string $email = 'multi-owner@example.test'): User
    {
        $u = (new UserAccountService)->registerOwner([
            'name' => 'Multi Owner',
            'first_name' => 'Multi',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $u->forceFill(['email_verified_at' => now()])->save();
        return $u->fresh();
    }

    protected function staff(string $email = 'multi-staff@example.test'): User
    {
        return (new UserAccountService)->createStaffFromInvitation([
            'name' => 'Multi Staff',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function ownerRoleId(): int
    {
        return Role::where('slug', 'institute-owner')->firstOrFail()->id;
    }

    protected function withSelection(string $industry = 'education', ?string $sub = 'school', string $country = 'Bangladesh'): array
    {
        return [
            InstituteOnboardingController::SESSION_KEY => [
                'country' => $country,
                'industry' => $industry,
                'sub_industry' => $sub,
            ],
        ];
    }

    public function test_owner_creates_first_institute_and_becomes_owner(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'web')
            ->withSession($this->withSelection())
            ->post('/workspace/create', ['name' => 'Owner Alpha Institute'])
            ->assertRedirect('/');

        $institute = Institute::where('slug', 'owner-alpha-institute')->firstOrFail();

        $this->assertSame('education', $institute->industry);
        $this->assertSame('school', $institute->sub_industry);
        $this->assertSame('Bangladesh', $institute->country);
        $this->assertNull(session(InstituteOnboardingController::SESSION_KEY));

        $this->assertDatabaseHas('institution_user', [
            'user_id' => $owner->id,
            'institution_id' => $institute->id,
            'role_id' => $this->ownerRoleId(),
            'branch_id' => null,
            'status' => 'active',
        ]);

        $this->assertSame($institute->id, Workspace::id());
        // TenantContext is set via middleware on the next request; verify via session + follow-up request
        $this->assertSame($institute->id, session(Workspace::SESSION_KEY));
        $this->get('/')->assertOk();
        $this->assertSame($institute->id, TenantContext::id());
    }

    public function test_same_owner_creates_second_institute_reusing_user_record(): void
    {
        $owner = $this->owner('multi-owner2@example.test');

        $this->actingAs($owner, 'web')->withSession($this->withSelection())->post('/workspace/create', ['name' => 'Owner Institute One'])->assertRedirect('/');
        $this->actingAs($owner, 'web')->withSession($this->withSelection('healthcare', null, 'France'))->post('/workspace/create', ['name' => 'Owner Institute Two'])->assertRedirect('/');

        $one = Institute::where('slug', 'owner-institute-one')->firstOrFail();
        $two = Institute::where('slug', 'owner-institute-two')->firstOrFail();

        $this->assertSame('healthcare', $two->industry);
        $this->assertSame('France', $two->country);
        $this->assertNull($two->sub_industry);

        // Never a second user row for the same email.
        $this->assertSame(1, User::query()->where('email', $owner->email)->count());
        $this->assertSame(1, DB::table('users')->where('email', $owner->email)->count());

        $memberships = Membership::query()
            ->where('user_id', $owner->id)
            ->orderBy('institution_id')
            ->get();

        $this->assertSame(2, $memberships->count());
        $this->assertEqualsCanonicalizing([$one->id, $two->id], $memberships->pluck('institution_id')->all());
        $this->assertTrue($memberships->every(fn ($m) => $m->role->slug === 'institute-owner'));
        $this->assertTrue($memberships->every(fn ($m) => $m->branch_id === null));
        $this->assertTrue($memberships->every(fn ($m) => $m->status === 'active'));
    }

    public function test_duplicate_membership_in_same_institute_is_rejected(): void
    {
        $owner = $this->owner('dup-owner@example.test');
        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $service = new MembershipService;

        $service->assign($owner, $institute->id, $this->ownerRoleId());

        $this->expectException(QueryException::class);

        $service->assign($owner, $institute->id, $this->ownerRoleId());
    }

    public function test_staff_account_cannot_create_institute(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'web')
            ->get('/workspace/create')
            ->assertForbidden();

        $this->actingAs($staff, 'web')
            ->post('/workspace/create', ['name' => 'Staff Should Fail'])
            ->assertForbidden();
    }

    public function test_guest_cannot_create_institute(): void
    {
        $this->get('/workspace/create')->assertRedirect('/login');
        $this->post('/workspace/create', ['name' => 'Guest Should Fail'])->assertRedirect('/login');
    }
}
