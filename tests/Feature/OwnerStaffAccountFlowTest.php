<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * STEP 7 — Owner/Staff Account Flow.
 *
 * End-to-end coverage of the login -> workspace resolution -> picker ->
 * switch -> tenant/branch context flow for owner and staff accounts, plus
 * the defense-in-depth account-type checks at read/switch time.
 */
class OwnerStaffAccountFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    protected function owner(string $email): User
    {
        $user = (new UserAccountService)->registerOwner([
            'name' => 'Flow Owner',
            'first_name' => 'Flow',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        return $user;
    }

    protected function staff(string $email): User
    {
        $user = (new UserAccountService)->createStaffFromInvitation([
            'name' => 'Flow Staff',
            'first_name' => 'Flow',
            'last_name' => 'Staff',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        return $user;
    }

    protected function institute(string $name): Institute
    {
        return Institute::where('name', $name)->firstOrFail();
    }

    protected function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->firstOrFail()->id;
    }

    protected function assign(User $user, Institute $institute, string $roleSlug, array $attributes = []): Membership
    {
        return (new MembershipService)->assign($user, $institute->id, $this->roleId($roleSlug), $attributes);
    }

    public function test_owner_login_with_single_membership_auto_continues_to_dashboard(): void
    {
        $user = $this->owner('flow-owner-1@example.test');
        $mawa = $this->institute('MAWA ACADEMY');
        $this->assign($user, $mawa, 'institute-owner');

        $this->post('/login', ['email' => $user->email, 'password' => $this->password])
            ->assertRedirect('/');

        $this->assertSame($mawa->id, Workspace::id());
        $this->assertSame($mawa->id, TenantContext::id());

        $this->get('/')
            ->assertOk()
            ->assertSee('MAWA ACADEMY');
    }

    public function test_staff_login_with_single_membership_auto_continues_to_dashboard(): void
    {
        $user = $this->staff('flow-staff-1@example.test');
        $mawa = $this->institute('MAWA ACADEMY');
        $this->assign($user, $mawa, 'accountant');

        $this->post('/login', ['email' => $user->email, 'password' => $this->password])
            ->assertRedirect('/');

        $this->assertTrue($user->isStaffAccount());
        $this->assertSame($mawa->id, Workspace::id());
        $this->assertSame($mawa->id, TenantContext::id());

        $this->get('/')
            ->assertOk()
            ->assertSee('MAWA ACADEMY');
    }

    public function test_owner_login_with_multiple_memberships_lands_on_picker(): void
    {
        $user = $this->owner('flow-owner-multi@example.test');
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $this->assign($user, $mawa, 'institute-owner');
        $this->assign($user, $tutu, 'institute-owner');

        $this->post('/login', ['email' => $user->email, 'password' => $this->password])
            ->assertRedirect('/workspace');

        $this->assertNull(Workspace::id());

        $this->get('/workspace')
            ->assertOk()
            ->assertSee('MAWA ACADEMY')
            ->assertSee('Tutu Center');
    }

    public function test_staff_login_with_multiple_memberships_lands_on_picker(): void
    {
        $user = $this->staff('flow-staff-multi@example.test');
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $this->assign($user, $mawa, 'accountant');
        $this->assign($user, $tutu, 'teacher');

        $this->post('/login', ['email' => $user->email, 'password' => $this->password])
            ->assertRedirect('/workspace');

        $this->assertNull(Workspace::id());

        $this->get('/workspace')
            ->assertOk()
            ->assertSee('MAWA ACADEMY')
            ->assertSee('Tutu Center');
    }

    public function test_inactive_membership_excluded_from_login_and_picker(): void
    {
        $user = $this->staff('flow-inactive@example.test');
        $active = $this->institute('MAWA ACADEMY');
        $inactive = $this->institute('Tutu Center');
        $this->assign($user, $active, 'accountant');
        $this->assign($user, $inactive, 'teacher', ['status' => 'suspended']);

        // The only ACTIVE membership is auto-resolved; the suspended one is ignored.
        $this->post('/login', ['email' => $user->email, 'password' => $this->password])
            ->assertRedirect('/');

        $this->assertSame($active->id, Workspace::id());

        $this->get('/workspace')
            ->assertOk()
            ->assertSee('MAWA ACADEMY')
            ->assertDontSee('Tutu Center');
    }

    public function test_unauthorized_institute_switch_is_blocked(): void
    {
        $user = $this->owner('flow-unauth@example.test');
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $this->assign($user, $mawa, 'institute-owner');

        $this->actingAs($user, 'web')
            ->post('/workspace/switch/'.$tutu->id)
            ->assertSessionHasErrors('workspace');

        $this->assertNull(Workspace::id());
    }

    public function test_owner_cannot_enter_staff_membership_via_switch(): void
    {
        $user = $this->owner('flow-owner-forced@example.test');
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $this->assign($user, $mawa, 'institute-owner');

        // Simulate legacy/inconsistent data: the owner also holds a staff role.
        Membership::withoutEvents(fn () => Membership::create([
            'user_id' => $user->id,
            'institution_id' => $tutu->id,
            'role_id' => $this->roleId('accountant'),
            'status' => 'active',
        ]));

        $this->assertFalse(Workspace::verify($tutu->id, $user->id));

        $this->actingAs($user, 'web')
            ->post('/workspace/switch/'.$tutu->id)
            ->assertSessionHasErrors('workspace');

        $this->assertNull(Workspace::id());
    }

    public function test_staff_cannot_enter_owner_membership_via_switch(): void
    {
        $user = $this->staff('flow-staff-forced@example.test');
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $this->assign($user, $mawa, 'accountant');

        // Simulate legacy/inconsistent data: the staff account also owns.
        Membership::withoutEvents(fn () => Membership::create([
            'user_id' => $user->id,
            'institution_id' => $tutu->id,
            'role_id' => $this->roleId('institute-owner'),
            'status' => 'active',
        ]));

        $this->assertFalse(Workspace::verify($tutu->id, $user->id));

        $this->actingAs($user, 'web')
            ->post('/workspace/switch/'.$tutu->id)
            ->assertSessionHasErrors('workspace');

        $this->assertNull(Workspace::id());
    }

    public function test_owner_login_ignores_inconsistent_staff_memberships(): void
    {
        $user = $this->owner('flow-owner-inconsistent@example.test');
        $tutu = $this->institute('Tutu Center');

        Membership::withoutEvents(fn () => Membership::create([
            'user_id' => $user->id,
            'institution_id' => $tutu->id,
            'role_id' => $this->roleId('accountant'),
            'status' => 'active',
        ]));

        // The only membership is inconsistent with the owner account, so no
        // workspace is auto-resolved and the user is sent to the picker.
        $this->post('/login', ['email' => $user->email, 'password' => $this->password])
            ->assertRedirect('/workspace');

        $this->assertNull(Workspace::id());
    }

    public function test_workspace_switch_updates_tenant_and_branch_context(): void
    {
        $user = $this->staff('flow-switch@example.test');
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $branchA = Branch::create(['institute_id' => $mawa->id, 'name' => 'Main Branch', 'status' => 'active']);
        $branchB = Branch::create(['institute_id' => $tutu->id, 'name' => 'Faridpur Branch', 'status' => 'active']);

        $this->assign($user, $mawa, 'branch-manager', ['branch_id' => $branchA->id]);
        $this->assign($user, $tutu, 'teacher', ['branch_id' => $branchB->id]);

        $this->actingAs($user, 'web')->post('/workspace/switch/'.$mawa->id)->assertSessionHasNoErrors();
        $this->assertSame($mawa->id, Workspace::id());
        $this->assertSame($mawa->id, TenantContext::id());
        $this->assertSame($branchA->id, BranchContext::id());

        $this->actingAs($user, 'web')->post('/workspace/switch/'.$tutu->id)->assertSessionHasNoErrors();
        $this->assertSame($tutu->id, Workspace::id());
        $this->assertSame($tutu->id, TenantContext::id());
        $this->assertSame($branchB->id, BranchContext::id());
    }

    public function test_selected_workspace_respects_membership_branch_scope(): void
    {
        $user = $this->staff('flow-branch-scope@example.test');
        $mawa = $this->institute('MAWA ACADEMY');
        $branchA = Branch::create(['institute_id' => $mawa->id, 'name' => 'Main Branch', 'status' => 'active']);
        $branchB = Branch::create(['institute_id' => $mawa->id, 'name' => 'Other Branch', 'status' => 'active']);

        $visible = Student::create([
            'institute_id' => $mawa->id,
            'branch_id' => $branchA->id,
            'student_id_number' => 'SCOPE-VISIBLE-'.uniqid(),
            'first_name' => 'Scope',
            'last_name' => 'Visible',
            'status' => 'active',
            'admission_date' => now(),
        ]);
        $hidden = Student::create([
            'institute_id' => $mawa->id,
            'branch_id' => $branchB->id,
            'student_id_number' => 'SCOPE-HIDDEN-'.uniqid(),
            'first_name' => 'Scope',
            'last_name' => 'Hidden',
            'status' => 'active',
            'admission_date' => now(),
        ]);

        $this->assign($user, $mawa, 'branch-manager', ['branch_id' => $branchA->id]);

        $this->withSession([Workspace::SESSION_KEY => $mawa->id])
            ->actingAs($user, 'web')
            ->get('/')
            ->assertOk();

        // The middleware bound the tenant/branch pair from the active
        // membership, so branch-scoped models only see that branch.
        $this->assertSame($mawa->id, TenantContext::id());
        $this->assertSame($branchA->id, BranchContext::id());
        $this->assertSame(1, Student::query()->where('id', $visible->id)->count());
        $this->assertSame(0, Student::query()->where('id', $hidden->id)->count());
    }

    public function test_role_permissions_remain_enforced_in_workspace(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');

        $accountant = $this->staff('flow-acct@example.test');
        $this->assign($accountant, $mawa, 'accountant');

        $this->withSession([Workspace::SESSION_KEY => $mawa->id])
            ->actingAs($accountant, 'web')
            ->get('/finance')
            ->assertOk();

        $teacher = $this->staff('flow-teacher@example.test');
        $this->assign($teacher, $mawa, 'teacher');

        $this->withSession([Workspace::SESSION_KEY => $mawa->id])
            ->actingAs($teacher, 'web')
            ->get('/finance')
            ->assertForbidden();
    }

    public function test_picker_shows_branch_info_and_all_branches_fallback(): void
    {
        $user = $this->staff('flow-picker-branch@example.test');
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $branch = Branch::create(['institute_id' => $mawa->id, 'name' => 'Main Branch', 'status' => 'active']);

        $this->assign($user, $mawa, 'branch-manager', ['branch_id' => $branch->id]);
        $this->assign($user, $tutu, 'teacher');

        $this->actingAs($user, 'web')
            ->get('/workspace')
            ->assertOk()
            ->assertSee('Branch: Main Branch')
            ->assertSee('All branches');
    }
}