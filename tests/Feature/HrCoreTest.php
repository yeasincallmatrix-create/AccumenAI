<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrEmployee;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\TeacherProfile;
use App\Services\HrEmployeeService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * HR-1 — Employee Core & Organization.
 *
 * Covers: creation, update, search/filter, department/designation relationships,
 * code uniqueness (tenant-safe), tenant/branch isolation, permission enforcement,
 * soft-delete history, audit logging, cross-tenant/cross-branch 404.
 */
class HrCoreTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function country(): Country
    {
        return Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(?Country $country = null): Institute
    {
        $country ??= $this->country();

        return Institute::create([
            'name' => 'HR Inst '.uniqid(),
            'slug' => 'hr-inst-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name = 'Main Branch'): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name.' '.uniqid(),
            'status' => 'active',
        ]);
    }

    private function role(string $slug): Role
    {
        return Role::where('slug', $slug)->firstOrFail();
    }

    private function user(Institute $institute, string $roleSlug, ?int $branchId = null, ?string $email = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => $this->role($roleSlug)->id,
            'branch_id' => $branchId,
            'first_name' => ucfirst($roleSlug),
            'last_name' => 'User',
            'email' => $email ?? $roleSlug.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    // ------------------------------------------------------------- Creation

    public function test_owner_can_create_employee(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $branch = $this->branch($institute);
        $dept = HrDepartment::create(['institute_id' => $institute->id, 'branch_id' => $branch->id, 'name' => 'Sales', 'display_order' => 0, 'is_active' => true]);
        $des = HrDesignation::create(['institute_id' => $institute->id, 'department_id' => $dept->id, 'name' => 'Executive', 'display_order' => 0, 'is_active' => true]);
        $owner = $this->user($institute, 'institute-owner');

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('hr.employees.store'), [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => '+8801711122233',
                'email' => 'john.doe@example.test',
                'branch_id' => $branch->id,
                'department_id' => $dept->id,
                'designation_id' => $des->id,
                'employment_status' => 'active',
                'employment_type' => 'full_time',
                'joining_date' => now()->toDateString(),
                'address' => 'Dhaka',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hr_employees', [
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
        ]);

        $emp = HrEmployee::where('institute_id', $institute->id)->where('first_name', 'John')->firstOrFail();
        $this->assertMatchesRegularExpression('/^EMP-\d{3,}-\d{5}$/', $emp->employee_code);
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_employee_created', 'record_id' => $emp->id]);
    }

    public function test_employee_code_is_tenant_safe_and_unique(): void
    {
        $country = $this->country();
        $a = $this->institute($country);
        $b = $this->institute($country);
        $ownerA = $this->user($a, 'institute-owner');
        $ownerB = $this->user($b, 'institute-owner');

        TenantContext::set($a->id);
        $this->actingAs($ownerA, 'institute_user')->post(route('hr.employees.store'), [
            'first_name' => 'Alpha', 'last_name' => 'One', 'email' => 'alpha1@example.test',
        ])->assertRedirect();

        TenantContext::set($b->id);
        $this->actingAs($ownerB, 'institute_user')->post(route('hr.employees.store'), [
            'first_name' => 'Beta', 'last_name' => 'One', 'email' => 'beta1@example.test',
        ])->assertRedirect();

        TenantContext::set($a->id);
        $this->actingAs($ownerA, 'institute_user')->post(route('hr.employees.store'), [
            'first_name' => 'Alpha', 'last_name' => 'Two', 'email' => 'alpha2@example.test',
        ])->assertRedirect();

        $codesA = HrEmployee::withoutGlobalScopes()->where('institute_id', $a->id)->pluck('employee_code')->all();
        $codesB = HrEmployee::withoutGlobalScopes()->where('institute_id', $b->id)->pluck('employee_code')->all();

        $this->assertCount(2, $codesA);
        $this->assertCount(1, $codesB);
        $this->assertNotEquals($codesA[0], $codesA[1]);
        // First code of B should be EMP-{B}-00001, not colliding with A's codes.
        $this->assertStringStartsWith('EMP-'.str_pad((string) $b->id, 3, '0', STR_PAD_LEFT).'-', $codesB[0]);
        $this->assertStringStartsWith('EMP-'.str_pad((string) $a->id, 3, '0', STR_PAD_LEFT).'-', $codesA[0]);
    }

    public function test_employee_update(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.store'), [
            'first_name' => 'Update', 'last_name' => 'Me', 'phone' => '+8801711000001', 'employment_status' => 'active',
        ])->assertRedirect();

        $emp = HrEmployee::where('institute_id', $institute->id)->firstOrFail();
        $code = $emp->employee_code;

        $this->actingAs($owner, 'institute_user')->put(route('hr.employees.update', $emp), [
            'first_name' => 'Updated', 'last_name' => 'Me', 'phone' => '+8801711000002', 'employment_status' => 'inactive', 'employment_type' => 'part_time',
        ])->assertRedirect();

        $emp->refresh();
        $this->assertEquals('Updated', $emp->first_name);
        $this->assertEquals('Updated Me', $emp->display_name);
        $this->assertEquals('inactive', $emp->employment_status);
        $this->assertEquals('part_time', $emp->employment_type);
        $this->assertEquals($code, $emp->employee_code, 'code must not change on update');
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_employee_updated', 'record_id' => $emp->id]);
    }

    public function test_employee_search_and_filters(): void
    {
        $institute = $this->institute();
        $branch1 = $this->branch($institute, 'Branch 1');
        $branch2 = $this->branch($institute, 'Branch 2');
        $dept = HrDepartment::create(['institute_id' => $institute->id, 'name' => 'Engineering', 'display_order' => 0, 'is_active' => true]);
        $des = HrDesignation::create(['institute_id' => $institute->id, 'name' => 'Developer', 'display_order' => 0, 'is_active' => true]);
        $owner = $this->user($institute, 'institute-owner');
        TenantContext::set($institute->id);

        // Create employees via service directly to avoid validation noise.
        $svc = app(HrEmployeeService::class);
        $e1 = $svc->create(['first_name' => 'Alice', 'last_name' => 'Smith', 'email' => 'alice@example.test', 'phone' => '+8801711000010', 'employment_status' => 'active', 'employment_type' => 'full_time', 'department_id' => $dept->id, 'designation_id' => $des->id], $institute->id, $branch1->id, $owner->id);
        $e2 = $svc->create(['first_name' => 'Bob', 'last_name' => 'Jones', 'email' => 'bob@example.test', 'phone' => '+8801711000020', 'employment_status' => 'suspended', 'employment_type' => 'contractual'], $institute->id, $branch2->id, $owner->id);

        // Search by name (q)
        $this->actingAs($owner, 'institute_user')->get(route('hr.employees.index', ['q' => 'Alice']))->assertOk()->assertSee('Alice Smith')->assertDontSee('Bob Jones');

        // Search by code
        $this->actingAs($owner, 'institute_user')->get(route('hr.employees.index', ['q' => $e2->employee_code]))->assertOk()->assertSee($e2->employee_code)->assertDontSee($e1->employee_code);

        // Filter by status
        $this->actingAs($owner, 'institute_user')->get(route('hr.employees.index', ['employment_status' => 'suspended']))->assertOk()->assertSee('Bob Jones')->assertDontSee('Alice Smith');

        // Filter by department
        $this->actingAs($owner, 'institute_user')->get(route('hr.employees.index', ['department_id' => $dept->id]))->assertOk()->assertSee('Alice Smith')->assertDontSee('Bob Jones');

        // Filter by phone via search
        $this->actingAs($owner, 'institute_user')->get(route('hr.employees.index', ['q' => '+8801711000020']))->assertOk()->assertSee('Bob Jones');

        // Filter by email via search
        $this->actingAs($owner, 'institute_user')->get(route('hr.employees.index', ['q' => 'alice@example.test']))->assertOk()->assertSee('Alice Smith');
    }

    public function test_department_and_designation_relationships(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        $owner = $this->user($institute, 'institute-owner');
        TenantContext::set($institute->id);

        // Department with branch + parent
        $this->actingAs($owner, 'institute_user')->post(route('hr.departments.store'), [
            'name' => 'Parent Dept', 'branch_id' => $branch->id, 'display_order' => 1,
        ])->assertRedirect();
        $parent = HrDepartment::where('institute_id', $institute->id)->where('name', 'Parent Dept')->firstOrFail();

        $this->actingAs($owner, 'institute_user')->post(route('hr.departments.store'), [
            'name' => 'Child Dept', 'branch_id' => $branch->id, 'parent_department_id' => $parent->id, 'display_order' => 2,
        ])->assertRedirect();

        $child = HrDepartment::where('name', 'Child Dept')->firstOrFail();
        $this->assertEquals($parent->id, $child->parent_department_id);

        // Designation linked to department
        $this->actingAs($owner, 'institute_user')->post(route('hr.designations.store'), [
            'name' => 'Senior Dev', 'department_id' => $child->id, 'display_order' => 0,
        ])->assertRedirect();

        $des = HrDesignation::where('name', 'Senior Dev')->firstOrFail();
        $this->assertEquals($child->id, $des->department_id);

        // Employee belongs to both
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.store'), [
            'first_name' => 'Rel', 'last_name' => 'Test', 'department_id' => $child->id, 'designation_id' => $des->id, 'branch_id' => $branch->id,
        ])->assertRedirect();

        $emp = HrEmployee::where('first_name', 'Rel')->firstOrFail();
        $this->assertEquals($child->id, $emp->department_id);
        $this->assertEquals($des->id, $emp->designation_id);
        $this->assertEquals($branch->id, $emp->branch_id);
        $this->assertEquals('Rel Test', $emp->display_name);

        // Reporting manager
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.store'), [
            'first_name' => 'Manager', 'last_name' => 'One',
        ])->assertRedirect();
        $mgr = HrEmployee::where('first_name', 'Manager')->firstOrFail();
        $this->actingAs($owner, 'institute_user')->put(route('hr.employees.update', $emp), [
            'first_name' => 'Rel', 'last_name' => 'Test', 'reporting_manager_id' => $mgr->id,
        ])->assertRedirect();
        $emp->refresh();
        $this->assertEquals($mgr->id, $emp->reporting_manager_id);
    }

    public function test_tenant_isolation(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $ownerA = $this->user($a, 'institute-owner');
        $ownerB = $this->user($b, 'institute-owner');

        TenantContext::set($a->id);
        $this->actingAs($ownerA, 'institute_user')->post(route('hr.employees.store'), [
            'first_name' => 'TenantA', 'last_name' => 'User',
        ])->assertRedirect();
        $empA = HrEmployee::withoutGlobalScopes()->where('institute_id', $a->id)->firstOrFail();

        // B cannot see A's employee via show (should 404)
        TenantContext::set($b->id);
        $this->actingAs($ownerB, 'institute_user')->get(route('hr.employees.show', $empA))->assertNotFound();

        // Index isolation: B's index should not contain A's employee
        $this->actingAs($ownerB, 'institute_user')->get(route('hr.employees.index'))->assertOk()->assertDontSee($empA->employee_code);

        // Cross-tenant update attempt 404
        $this->actingAs($ownerB, 'institute_user')->put(route('hr.employees.update', $empA), [
            'first_name' => 'Hacked', 'last_name' => 'User',
        ])->assertNotFound();
    }

    public function test_branch_isolation(): void
    {
        $institute = $this->institute();
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $owner = $this->user($institute, 'institute-owner');
        $mgrA = $this->user($institute, 'branch-manager', $branchA->id);
        $mgrB = $this->user($institute, 'branch-manager', $branchB->id);

        // Owner creates employees in both branches
        TenantContext::set($institute->id);
        TenantContext::set($institute->id); // owner context
        $svc = app(HrEmployeeService::class);
        $empA = $svc->create(['first_name' => 'BranchA', 'last_name' => 'Emp'], $institute->id, $branchA->id, $owner->id);
        $empB = $svc->create(['first_name' => 'BranchB', 'last_name' => 'Emp'], $institute->id, $branchB->id, $owner->id);

        // Manager A sees only Branch A employees
        TenantContext::set($institute->id);
        BranchContext::set($branchA->id);
        $this->actingAs($mgrA, 'institute_user')->get(route('hr.employees.index'))->assertOk()->assertSee($empA->employee_code)->assertDontSee($empB->employee_code);
        $this->actingAs($mgrA, 'institute_user')->get(route('hr.employees.show', $empA))->assertOk();
        $this->actingAs($mgrA, 'institute_user')->get(route('hr.employees.show', $empB))->assertNotFound();

        // Manager B sees only Branch B
        BranchContext::set($branchB->id);
        $this->actingAs($mgrB, 'institute_user')->get(route('hr.employees.index'))->assertOk()->assertSee($empB->employee_code)->assertDontSee($empA->employee_code);

        BranchContext::clear();
    }

    public function test_permission_enforcement(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        // receptionist role typically does not have hr.* (only branches.view etc.)
        $receptionist = $this->user($institute, 'receptionist');

        TenantContext::set($institute->id);

        // Receptionist cannot create employee (403)
        $this->actingAs($receptionist, 'institute_user')->get(route('hr.employees.create'))->assertForbidden();
        $this->actingAs($receptionist, 'institute_user')->post(route('hr.employees.store'), [
            'first_name' => 'No', 'last_name' => 'Perm',
        ])->assertForbidden();

        // But owner can
        $this->actingAs($owner, 'institute_user')->get(route('hr.employees.create'))->assertOk();

        // Create one for further checks
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.store'), [
            'first_name' => 'Perm', 'last_name' => 'Test',
        ])->assertRedirect();
        $emp = HrEmployee::where('institute_id', $institute->id)->firstOrFail();

        // Receptionist cannot view (needs hr.employee.view)
        $this->actingAs($receptionist, 'institute_user')->get(route('hr.employees.show', $emp))->assertForbidden();
        $this->actingAs($receptionist, 'institute_user')->get(route('hr.employees.index'))->assertForbidden();

        // Departments: receptionist cannot create
        $this->actingAs($receptionist, 'institute_user')->post(route('hr.departments.store'), ['name' => 'NoPerm'])->assertForbidden();
    }

    public function test_soft_delete_and_history_safety(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.store'), [
            'first_name' => 'Soft', 'last_name' => 'Delete',
        ])->assertRedirect();
        $emp = HrEmployee::where('institute_id', $institute->id)->firstOrFail();
        $code = $emp->employee_code;

        $this->actingAs($owner, 'institute_user')->delete(route('hr.employees.destroy', $emp))->assertRedirect();
        // Soft-deleted: not visible in index (scoped)
        $this->actingAs($owner, 'institute_user')->get(route('hr.employees.index'))->assertOk()->assertDontSee($code);
        // Still exists withTrashed
        $this->assertDatabaseHas('hr_employees', ['id' => $emp->id, 'employee_code' => $code]);
        $trashed = HrEmployee::withoutGlobalScopes()->withTrashed()->where('id', $emp->id)->firstOrFail();
        $this->assertNotNull($trashed->deleted_at);
        // Audit logged
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_employee_deleted', 'record_id' => $emp->id]);

        // Departments soft-delete
        $this->actingAs($owner, 'institute_user')->post(route('hr.departments.store'), ['name' => 'To Delete'])->assertRedirect();
        $dept = HrDepartment::where('institute_id', $institute->id)->where('name', 'To Delete')->firstOrFail();
        $this->actingAs($owner, 'institute_user')->delete(route('hr.departments.destroy', $dept))->assertRedirect();
        $this->assertSoftDeleted('hr_departments', ['id' => $dept->id]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_department_deleted', 'record_id' => $dept->id]);

        // Designations soft-delete
        $this->actingAs($owner, 'institute_user')->post(route('hr.designations.store'), ['name' => 'To Del Des'])->assertRedirect();
        $des = HrDesignation::where('institute_id', $institute->id)->where('name', 'To Del Des')->firstOrFail();
        $this->actingAs($owner, 'institute_user')->delete(route('hr.designations.destroy', $des))->assertRedirect();
        $this->assertSoftDeleted('hr_designations', ['id' => $des->id]);
    }

    public function test_audit_logging(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')->post(route('hr.departments.store'), ['name' => 'Audit Dept'])->assertRedirect();
        $dept = HrDepartment::where('name', 'Audit Dept')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_department_created', 'record_id' => $dept->id]);

        $this->actingAs($owner, 'institute_user')->put(route('hr.departments.update', $dept), ['name' => 'Audit Dept Updated'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_department_updated', 'record_id' => $dept->id]);

        $this->actingAs($owner, 'institute_user')->post(route('hr.designations.store'), ['name' => 'Audit Des'])->assertRedirect();
        $des = HrDesignation::where('name', 'Audit Des')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_designation_created', 'record_id' => $des->id]);

        $this->actingAs($owner, 'institute_user')->put(route('hr.designations.update', $des), ['name' => 'Audit Des 2'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_designation_updated', 'record_id' => $des->id]);
    }

    public function test_unauthorized_cross_tenant_or_branch_access_returns_404(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $branchA = $this->branch($a);
        $ownerA = $this->user($a, 'institute-owner');
        $ownerB = $this->user($b, 'institute-owner');
        $branchMgrB = $this->user($b, 'branch-manager', $branchA->id); // note branch belongs to A, not B — will be rejected at service but test binding

        TenantContext::set($a->id);
        $svc = app(HrEmployeeService::class);
        $emp = $svc->create(['first_name' => 'Cross', 'last_name' => 'Tenant'], $a->id, $branchA->id, $ownerA->id);

        // Owner of B cannot see A's employee (404 not 403 contains)
        TenantContext::set($b->id);
        $this->actingAs($ownerB, 'institute_user')->get(route('hr.employees.show', $emp))->assertNotFound();
        $this->actingAs($ownerB, 'institute_user')->put(route('hr.employees.update', $emp), ['first_name' => 'X', 'last_name' => 'Y'])->assertNotFound();
        $this->actingAs($ownerB, 'institute_user')->delete(route('hr.employees.destroy', $emp))->assertNotFound();

        // Branch isolation also 404 (already covered but assert here)
        $institute = $this->institute();
        $branch1 = $this->branch($institute, 'B1');
        $branch2 = $this->branch($institute, 'B2');
        $owner = $this->user($institute, 'institute-owner');
        $mgr1 = $this->user($institute, 'branch-manager', $branch1->id);
        TenantContext::set($institute->id);
        $emp2 = $svc->create(['first_name' => 'Branch', 'last_name' => 'One'], $institute->id, $branch2->id, $owner->id);
        BranchContext::set($branch1->id);
        $this->actingAs($mgr1, 'institute_user')->get(route('hr.employees.show', $emp2))->assertNotFound();
        BranchContext::clear();
    }

    public function test_education_compatibility_teacher_not_duplicated(): void
    {
        // Ensure existing TeacherProfile flow still works and is not duplicated by HR employee linkage.
        // An HR employee can optionally link to an institute_user (teacher) without creating a duplicate teacher row.
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $teacher = $this->user($institute, 'teacher');
        // TeacherProfile extension (as per existing flow)
        TeacherProfile::create([
            'institute_id' => $institute->id,
            'institute_user_id' => $teacher->id,
            'employment_status' => 'active',
        ]);

        TenantContext::set($institute->id);
        $svc = app(HrEmployeeService::class);
        $emp = $svc->create(['first_name' => 'Teacher', 'last_name' => 'Linked', 'institute_user_id' => $teacher->id], $institute->id, null, $owner->id);

        $this->assertEquals($teacher->id, $emp->institute_user_id);
        $this->assertEquals($teacher->id, $emp->instituteUser->id);
        // Teacher still exists as single row, not duplicated.
        $this->assertEquals(1, InstituteUser::where('id', $teacher->id)->count());
        $this->assertEquals(1, TeacherProfile::where('institute_user_id', $teacher->id)->count());
    }
}
