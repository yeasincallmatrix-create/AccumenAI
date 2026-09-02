<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Institute;
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

class WorkspaceContextTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function owner(string $email = 'ws-owner@example.test'): User
    {
        $u = (new UserAccountService)->registerOwner([
            'name' => 'WS Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $u->forceFill(['email_verified_at' => now()])->save();
        return $u->fresh();
    }

    protected function institute(string $name): Institute
    {
        return Institute::where('name', $name)->firstOrFail();
    }

    protected function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->firstOrFail()->id;
    }

    public function test_workspace_a_and_b_set_correct_tenant_context(): void
    {
        $user = $this->owner('ws-context@example.test');
        $a = $this->institute('MAWA ACADEMY');
        $b = $this->institute('Tutu Center');

        (new MembershipService)->assign($user, $a->id, $this->roleId('institute-owner'));
        (new MembershipService)->assign($user, $b->id, $this->roleId('institute-owner'));

        $this->withSession([Workspace::SESSION_KEY => $a->id])
            ->actingAs($user, 'web')
            ->get('/')
            ->assertOk();

        $this->assertSame($a->id, TenantContext::id());
        $this->assertSame($a->id, Workspace::id());

        $this->withSession([Workspace::SESSION_KEY => $b->id])
            ->actingAs($user, 'web')
            ->get('/')
            ->assertOk();

        $this->assertSame($b->id, TenantContext::id());
        $this->assertSame($b->id, Workspace::id());
    }

    public function test_invalid_workspace_switch_is_rejected(): void
    {
        $user = $this->owner('ws-invalid@example.test');
        $a = $this->institute('MAWA ACADEMY');

        (new MembershipService)->assign($user, $a->id, $this->roleId('institute-owner'));

        $this->actingAs($user, 'web')
            ->post('/workspace/switch/999999')
            ->assertSessionHasErrors('workspace');

        $this->assertNull(Workspace::id());
    }

    public function test_switch_between_memberships_changes_context(): void
    {
        $user = $this->owner('ws-switch@example.test');
        $a = $this->institute('MAWA ACADEMY');
        $b = $this->institute('Tutu Center');

        (new MembershipService)->assign($user, $a->id, $this->roleId('institute-owner'));
        (new MembershipService)->assign($user, $b->id, $this->roleId('institute-owner'));

        $this->actingAs($user, 'web')->post('/workspace/switch/'.$a->id)->assertSessionHasNoErrors();
        $this->assertSame($a->id, Workspace::id());
        $this->assertSame($a->id, TenantContext::id());

        $this->actingAs($user, 'web')->post('/workspace/switch/'.$b->id)->assertSessionHasNoErrors();
        $this->assertSame($b->id, Workspace::id());
        $this->assertSame($b->id, TenantContext::id());
    }

    public function test_active_membership_branch_controls_branch_context(): void
    {
        $institute = $this->institute('MAWA ACADEMY');
        $branch = Branch::create([
            'institute_id' => $institute->id,
            'name' => 'Branch Only',
            'status' => 'active',
        ]);

        $staff = (new UserAccountService)->createStaffFromInvitation([
            'name' => 'Branch Staff',
            'email' => 'ws-branch-staff@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $staff->forceFill(['email_verified_at' => now()])->save(); $staff = $staff->fresh();
        (new MembershipService)->assign($staff, $institute->id, $this->roleId('branch-manager'), [
            'branch_id' => $branch->id,
        ]);

        $this->withSession([Workspace::SESSION_KEY => $institute->id])
            ->actingAs($staff, 'web')
            ->get('/')
            ->assertOk();

        $this->assertSame($branch->id, BranchContext::id());
        $this->assertSame($institute->id, TenantContext::id());
    }

    public function test_null_branch_gives_owner_all_branch_access(): void
    {
        $user = $this->owner('ws-allbranch@example.test');
        $institute = $this->institute('MAWA ACADEMY');

        (new MembershipService)->assign($user, $institute->id, $this->roleId('institute-owner'));

        $this->withSession([Workspace::SESSION_KEY => $institute->id])
            ->actingAs($user, 'web')
            ->get('/')
            ->assertOk();

        $this->assertFalse(BranchContext::enabled());
        $this->assertNull(BranchContext::id());
        $this->assertSame($institute->id, TenantContext::id());
    }

    public function test_institute_a_data_is_isolated_from_institute_b(): void
    {
        $a = $this->institute('MAWA ACADEMY');
        $b = $this->institute('Tutu Center');

        $studentA = Student::create([
            'institute_id' => $a->id,
            'student_id_number' => 'ISO-A'.uniqid(),
            'first_name' => 'Alpha',
            'last_name' => 'Isolated',
            'status' => 'active',
            'admission_date' => now(),
        ]);
        $studentB = Student::create([
            'institute_id' => $b->id,
            'student_id_number' => 'ISO-B'.uniqid(),
            'first_name' => 'Beta',
            'last_name' => 'Isolated',
            'status' => 'active',
            'admission_date' => now(),
        ]);

        TenantContext::set($a->id);
        BranchContext::clear();
        $this->assertSame(1, Student::query()->where('id', $studentA->id)->count());
        $this->assertSame(0, Student::query()->where('id', $studentB->id)->count());

        TenantContext::set($b->id);
        $this->assertSame(0, Student::query()->where('id', $studentA->id)->count());
        $this->assertSame(1, Student::query()->where('id', $studentB->id)->count());
    }
}
