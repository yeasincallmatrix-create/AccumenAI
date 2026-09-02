<?php

namespace Tests\Feature;

use App\Exceptions\AccountTypeMismatchException;
use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AccountTypeEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    protected function institute(string $name): Institute
    {
        return Institute::where('name', $name)->firstOrFail();
    }

    protected function role(string $slug): int
    {
        return Role::where('slug', $slug)->firstOrFail()->id;
    }

    protected function ownerUser(string $email = 'owner-t@example.test'): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Owner T',
            'email' => $email,
            'password_hash' => bcrypt('secret12345'),
        ]);
    }

    protected function staffUser(string $email = 'staff-t@example.test'): User
    {
        return (new UserAccountService)->createStaffFromInvitation([
            'name' => 'Staff T',
            'email' => $email,
            'password_hash' => bcrypt('secret12345'),
        ]);
    }

    public function test_owner_account_cannot_receive_staff_membership(): void
    {
        $this->expectException(AccountTypeMismatchException::class);
        (new MembershipService)->assign($this->ownerUser(), $this->institute('MAWA ACADEMY')->id, $this->role('accountant'));
    }

    public function test_staff_account_cannot_receive_owner_membership(): void
    {
        $this->expectException(AccountTypeMismatchException::class);
        (new MembershipService)->assign($this->staffUser(), $this->institute('MAWA ACADEMY')->id, $this->role('institute-owner'));
    }

    public function test_model_event_blocks_forbidden_membership(): void
    {
        $user = $this->staffUser();
        $this->expectException(AccountTypeMismatchException::class);
        Membership::create([
            'user_id' => $user->id,
            'institution_id' => $this->institute('MAWA ACADEMY')->id,
            'role_id' => $this->role('institute-owner'),
            'status' => 'active',
        ]);
    }

    public function test_owner_account_can_own_multiple_organizations(): void
    {
        $user = $this->ownerUser();
        $service = new MembershipService;

        $service->assign($user, $this->institute('MAWA ACADEMY')->id, $this->role('institute-owner'));
        $service->assign($user, $this->institute('Tutu Center')->id, $this->role('institute-owner'));

        $this->assertSame(2, $user->memberships()->count());
        $this->assertTrue($user->memberships()->get()->every(fn ($m) => $m->isOwner()));
    }

    public function test_staff_account_can_work_across_organizations_with_different_roles(): void
    {
        $user = $this->staffUser();
        $service = new MembershipService;

        $service->assign($user, $this->institute('MAWA ACADEMY')->id, $this->role('accountant'));
        $service->assign($user, $this->institute('Tutu Center')->id, $this->role('branch-manager'));

        $this->assertSame(2, $user->memberships()->count());
        $roles = $user->memberships()->with('role')->get()->pluck('role.slug')->all();
        $this->assertEqualsCanonicalizing(['accountant', 'branch-manager'], $roles);
    }

    public function test_change_role_enforces_owner_staff_rule(): void
    {
        $user = $this->staffUser();
        $service = new MembershipService;
        $membership = $service->assign($user, $this->institute('MAWA ACADEMY')->id, $this->role('accountant'));

        $this->expectException(AccountTypeMismatchException::class);
        $service->changeRole($membership, $this->role('institute-owner'));
    }

    public function test_account_type_cannot_be_converted_when_memberships_contradict(): void
    {
        $user = $this->ownerUser();
        $user->memberships()->create([
            'institution_id' => $this->institute('MAWA ACADEMY')->id,
            'role_id' => $this->role('institute-owner'),
            'status' => 'active',
        ]);

        $this->expectException(AccountTypeMismatchException::class);
        $user->forceFill(['account_type' => 'staff'])->save();
    }

    public function test_cross_owner_staff_scenario(): void
    {
        // Owner A -> Organization A -> Owner
        // Owner B -> Organization B -> Owner
        // Staff C -> Organization A -> Accountant
        // Staff C -> Organization B -> Branch Manager
        $ownerA = $this->ownerUser('owner-a@example.test');
        $ownerB = $this->ownerUser('owner-b@example.test');
        $staffC = $this->staffUser('staff-c@example.test');

        $orgA = $this->institute('MAWA ACADEMY');
        $orgB = $this->institute('Tutu Center');

        $service = new MembershipService;

        $service->assign($ownerA, $orgA->id, $this->role('institute-owner'));
        $service->assign($ownerB, $orgB->id, $this->role('institute-owner'));

        $service->assign($staffC, $orgA->id, $this->role('accountant'));
        $service->assign($staffC, $orgB->id, $this->role('branch-manager'));

        $this->assertTrue($ownerA->isOwnerAccount());
        $this->assertTrue($ownerB->isOwnerAccount());
        $this->assertTrue($staffC->isStaffAccount());

        $this->assertSame(1, $ownerA->memberships()->count());
        $this->assertSame(1, $ownerB->memberships()->count());
        $this->assertSame(2, $staffC->memberships()->count());

        $this->assertTrue($ownerA->memberships()->first()->isOwner());
        $this->assertTrue($ownerB->memberships()->first()->isOwner());

        // Staff C is staff at BOTH organizations, never owner.
        $staffRoles = $staffC->memberships()->with('role')->get()->pluck('role.slug')->all();
        $this->assertEqualsCanonicalizing(['accountant', 'branch-manager'], $staffRoles);
        $this->assertFalse($staffC->memberships()->get()->contains(fn ($m) => $m->isOwner()));

        // Staff C remains staff even though it works at orgs owned by
        // different owner accounts.
        $this->assertSame('staff', $staffC->account_type);
    }

    public function test_same_person_can_have_separate_owner_and_staff_accounts(): void
    {
        // Rahim — STAFF ACCOUNT (works at MAWA as accountant)
        $rahimStaff = $this->staffUser('rahim.staff@example.test');

        $service = new MembershipService;
        $service->assign($rahimStaff, $this->institute('MAWA ACADEMY')->id, $this->role('accountant'));

        // Rahim — separate OWNER ACCOUNT (owns Tutu Center)
        $rahimOwner = $this->ownerUser('rahim.owner@example.test');
        $service->assign($rahimOwner, $this->institute('Tutu Center')->id, $this->role('institute-owner'));

        // Two distinct accounts, never merged.
        $this->assertNotSame($rahimStaff->id, $rahimOwner->id);
        $this->assertNotSame($rahimStaff->email, $rahimOwner->email);
        $this->assertSame('staff', $rahimStaff->account_type);
        $this->assertSame('owner', $rahimOwner->account_type);

        // No conversion: staff account is still staff, still only has its
        // staff membership; owner account only owns.
        $this->assertSame(1, $rahimStaff->memberships()->count());
        $this->assertSame(1, $rahimOwner->memberships()->count());
        $this->assertFalse($rahimStaff->memberships()->first()->isOwner());
        $this->assertTrue($rahimOwner->memberships()->first()->isOwner());
    }
}
