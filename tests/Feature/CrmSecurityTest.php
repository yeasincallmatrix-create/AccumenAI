<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\CrmContact;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\CrmContactService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Step 31 — CRM Core security: tenant isolation, branch isolation and the
 * crm.view/create/update/delete permission matrix for default roles.
 */
class CrmSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------ Fixtures

    private function country(): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(string $name = 'Sec Inst'): Institute
    {
        $country = $this->country();

        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function user(Institute $institute, string $roleSlug, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => $prefix,
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function contact(Institute $institute, int $actorId, array $overrides = []): CrmContact
    {
        return app(CrmContactService::class)->create(array_merge([
            'first_name' => 'Secure',
            'last_name' => 'Contact',
            'email' => 'secure'.uniqid().'@example.com',
        ], $overrides), $institute->id, null, $actorId);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'New',
            'last_name' => 'Person',
            'email' => 'newperson'.uniqid().'@example.com',
        ], $overrides);
    }

    // ------------------------------------------------- Permission matrix

    public function test_teacher_without_crm_permission_is_forbidden(): void
    {
        $institute = $this->institute();
        $teacher = $this->user($institute, 'teacher', 'teacher');

        $this->actingAs($teacher, 'institute_user')
            ->get(route('crm.dashboard'))
            ->assertForbidden();

        $this->actingAs($teacher, 'institute_user')
            ->get(route('crm.contacts.index'))
            ->assertForbidden();
    }

    public function test_accountant_can_view_but_not_create(): void
    {
        $institute = $this->institute();
        $accountant = $this->user($institute, 'accountant', 'accountant');

        $this->actingAs($accountant, 'institute_user')
            ->get(route('crm.contacts.index'))
            ->assertOk();

        $this->actingAs($accountant, 'institute_user')
            ->get(route('crm.contacts.create'))
            ->assertForbidden();

        $this->actingAs($accountant, 'institute_user')
            ->post(route('crm.contacts.store'), $this->payload())
            ->assertForbidden();
    }

    public function test_receptionist_can_view_and_create_but_not_update(): void
    {
        $institute = $this->institute();
        $receptionist = $this->user($institute, 'receptionist', 'receptionist');

        $this->actingAs($receptionist, 'institute_user')
            ->get(route('crm.contacts.create'))
            ->assertOk();

        $response = $this->actingAs($receptionist, 'institute_user')
            ->post(route('crm.contacts.store'), $this->payload())
            ->assertRedirect();

        $contact = CrmContact::query()->where('institute_id', $institute->id)->firstOrFail();

        $this->actingAs($receptionist, 'institute_user')
            ->get(route('crm.contacts.edit', $contact))
            ->assertForbidden();

        $this->actingAs($receptionist, 'institute_user')
            ->put(route('crm.contacts.update', $contact), $this->payload(['email' => $contact->email]))
            ->assertForbidden();

        $this->actingAs($receptionist, 'institute_user')
            ->delete(route('crm.contacts.destroy', $contact))
            ->assertForbidden();
    }

    public function test_branch_manager_can_view_create_update_but_not_delete(): void
    {
        $institute = $this->institute();
        $manager = $this->user($institute, 'branch-manager', 'manager');

        $this->actingAs($manager, 'institute_user')
            ->get(route('crm.contacts.index'))
            ->assertOk();

        $this->actingAs($manager, 'institute_user')
            ->post(route('crm.contacts.store'), $this->payload())
            ->assertRedirect();

        $contact = CrmContact::query()->where('institute_id', $institute->id)->firstOrFail();

        $this->actingAs($manager, 'institute_user')
            ->put(route('crm.contacts.update', $contact), $this->payload(['email' => $contact->email]))
            ->assertRedirect();

        $this->actingAs($manager, 'institute_user')
            ->delete(route('crm.contacts.destroy', $contact))
            ->assertForbidden();
    }

    public function test_owner_has_full_delete_access(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $contact = $this->contact($institute, (int) $owner->id);

        $this->actingAs($owner, 'institute_user')
            ->delete(route('crm.contacts.destroy', $contact))
            ->assertRedirect();
    }

    // ------------------------------------------------- Tenant isolation

    public function test_cross_tenant_contact_is_not_visible(): void
    {
        $a = $this->institute('Inst A');
        $b = $this->institute('Inst B');
        $ownerA = $this->user($a, 'institute-owner', 'owner-a');
        $ownerB = $this->user($b, 'institute-owner', 'owner-b');
        $contactA = $this->contact($a, (int) $ownerA->id);

        $this->actingAs($ownerB, 'institute_user')
            ->get(route('crm.contacts.show', $contactA))
            ->assertNotFound();

        $this->actingAs($ownerB, 'institute_user')
            ->get(route('crm.contacts.index'))
            ->assertDontSee($contactA->email);
    }

    public function test_cross_tenant_update_and_delete_are_blocked(): void
    {
        $a = $this->institute('Inst A');
        $b = $this->institute('Inst B');
        $ownerA = $this->user($a, 'institute-owner', 'owner-a');
        $ownerB = $this->user($b, 'institute-owner', 'owner-b');
        $contactA = $this->contact($a, (int) $ownerA->id);

        $this->actingAs($ownerB, 'institute_user')
            ->put(route('crm.contacts.update', $contactA), $this->payload(['email' => $contactA->email]))
            ->assertNotFound();

        $this->actingAs($ownerB, 'institute_user')
            ->delete(route('crm.contacts.destroy', $contactA))
            ->assertNotFound();
    }

    public function test_cross_tenant_assignment_is_blocked(): void
    {
        $a = $this->institute('Inst A');
        $b = $this->institute('Inst B');
        $ownerA = $this->user($a, 'institute-owner', 'owner-a');
        $ownerB = $this->user($b, 'institute-owner', 'owner-b');
        $contactA = $this->contact($a, (int) $ownerA->id);

        $this->actingAs($ownerB, 'institute_user')
            ->post(route('crm.contacts.assign', $contactA), ['assigned_user_id' => $ownerB->id])
            ->assertNotFound();
    }

    // ------------------------------------------------- Branch isolation

    public function test_branch_manager_does_not_see_other_branch_contact(): void
    {
        $institute = $this->institute();
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $managerA = $this->user($institute, 'branch-manager', 'manager-a', $branchA);

        $contactA = app(CrmContactService::class)->create([
            'first_name' => 'Branch',
            'last_name' => 'One',
            'email' => 'branchone'.uniqid().'@example.com',
        ], $institute->id, (int) $branchA->id, (int) $owner->id);

        $contactB = app(CrmContactService::class)->create([
            'first_name' => 'Branch',
            'last_name' => 'Two',
            'email' => 'branchtwo'.uniqid().'@example.com',
        ], $institute->id, (int) $branchB->id, (int) $owner->id);

        $this->actingAs($managerA, 'institute_user')
            ->get(route('crm.contacts.show', $contactA))
            ->assertOk();

        $this->actingAs($managerA, 'institute_user')
            ->get(route('crm.contacts.show', $contactB))
            ->assertNotFound();

        $this->actingAs($managerA, 'institute_user')
            ->get(route('crm.contacts.index'))
            ->assertSee($contactA->email)
            ->assertDontSee($contactB->email);
    }

    public function test_owner_sees_all_branches(): void
    {
        $institute = $this->institute();
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $contactA = app(CrmContactService::class)->create([
            'first_name' => 'Branch',
            'last_name' => 'One',
            'email' => 'branchone'.uniqid().'@example.com',
        ], $institute->id, (int) $branchA->id, (int) $owner->id);

        $contactB = app(CrmContactService::class)->create([
            'first_name' => 'Branch',
            'last_name' => 'Two',
            'email' => 'branchtwo'.uniqid().'@example.com',
        ], $institute->id, (int) $branchB->id, (int) $owner->id);

        $this->actingAs($owner, 'institute_user')
            ->get(route('crm.contacts.index'))
            ->assertSee($contactA->email)
            ->assertSee($contactB->email);
    }

    public function test_branch_manager_created_contact_is_scoped_to_their_branch(): void
    {
        $institute = $this->institute();
        $branchA = $this->branch($institute, 'Branch A');
        $managerA = $this->user($institute, 'branch-manager', 'manager-a', $branchA);

        $this->actingAs($managerA, 'institute_user')
            ->post(route('crm.contacts.store'), $this->payload())
            ->assertRedirect();

        $contact = CrmContact::query()->where('institute_id', $institute->id)->firstOrFail();
        $this->assertSame((int) $contact->branch_id, (int) $branchA->id);
    }

    public function test_institute_wide_contact_is_visible_to_branch_user(): void
    {
        $institute = $this->institute();
        $branchA = $this->branch($institute, 'Branch A');
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $managerA = $this->user($institute, 'branch-manager', 'manager-a', $branchA);

        $wide = $this->contact($institute, (int) $owner->id);

        $this->actingAs($managerA, 'institute_user')
            ->get(route('crm.contacts.show', $wide))
            ->assertOk();
    }

    public function test_cross_tenant_activity_and_task_targets_are_404(): void
    {
        $a = $this->institute('Inst A');
        $b = $this->institute('Inst B');
        $ownerA = $this->user($a, 'institute-owner', 'owner-a');
        $ownerB = $this->user($b, 'institute-owner', 'owner-b');
        $contactA = $this->contact($a, (int) $ownerA->id);

        $this->actingAs($ownerB, 'institute_user')
            ->postJson(route('crm.activities.store'), [
                'subject_type' => 'contact',
                'subject_id' => $contactA->id,
                'summary' => 'foreign',
            ])
            ->assertStatus(404);

        $this->actingAs($ownerB, 'institute_user')
            ->postJson(route('crm.tasks.store'), [
                'subject_type' => 'contact',
                'subject_id' => $contactA->id,
                'title' => 'foreign',
            ])
            ->assertStatus(404);
    }
}
