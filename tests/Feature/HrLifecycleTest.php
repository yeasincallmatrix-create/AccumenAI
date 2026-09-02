<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrEmployee;
use App\Models\HrEmploymentHistory;
use App\Models\HrEmploymentPeriod;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\HrEmployeeService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * HR-2 — Employment Lifecycle.
 *
 * Covers joining, transfers (branch/dept/designation/manager), promotion/demotion,
 * salary reference, resignation (pending/approved/rejected), termination,
 * reactivation/rejoin, historical timeline immutability, periods, tenant/branch isolation,
 * permission enforcement, audit logging.
 */
class HrLifecycleTest extends TestCase
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
        return Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]);
    }

    private function institute(?Country $country = null): Institute
    {
        $country ??= $this->country();

        return Institute::create(['name' => 'HR2 Inst '.uniqid(), 'slug' => 'hr2-inst-'.uniqid(), 'country' => $country->name, 'country_id' => $country->id, 'status' => 'active']);
    }

    private function branch(Institute $institute, string $name = 'Main Branch'): Branch
    {
        return Branch::create(['institute_id' => $institute->id, 'name' => $name.' '.uniqid(), 'status' => 'active']);
    }

    private function role(string $slug): Role
    {
        return Role::where('slug', $slug)->firstOrFail();
    }

    private function user(Institute $institute, string $roleSlug, ?int $branchId = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id, 'role_id' => $this->role($roleSlug)->id, 'branch_id' => $branchId,
            'first_name' => ucfirst($roleSlug), 'last_name' => 'User',
            'email' => $roleSlug.'-'.uniqid().'@example.test', 'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password), 'status' => 'active',
        ]);
    }

    private function createEmployee(Institute $institute, ?int $branchId, InstituteUser $actor, array $overrides = []): HrEmployee
    {
        TenantContext::set($institute->id);
        $data = array_merge(['first_name' => 'Emp', 'last_name' => 'Test', 'joining_date' => now()->toDateString()], $overrides);
        $svc = app(HrEmployeeService::class);

        return $svc->create($data, $institute->id, $branchId, $actor->id);
    }

    public function test_joining_creates_history_and_period(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        $dept = HrDepartment::create(['institute_id' => $institute->id, 'name' => 'Join Dept', 'display_order' => 0, 'is_active' => true]);
        $owner = $this->user($institute, 'institute-owner');

        $emp = $this->createEmployee($institute, $branch->id, $owner, ['department_id' => $dept->id, 'joining_date' => '2024-01-15']);

        $history = HrEmploymentHistory::where('employee_id', $emp->id)->where('event_type', 'joining')->firstOrFail();
        $this->assertEquals('2024-01-15', $history->effective_date->format('Y-m-d'));
        $this->assertEquals($branch->id, $history->new_branch_id);
        $this->assertEquals($dept->id, $history->new_department_id);

        $period = HrEmploymentPeriod::where('employee_id', $emp->id)->where('status', 'active')->firstOrFail();
        $this->assertEquals('2024-01-15', $period->start_date->format('Y-m-d'));
        $this->assertNull($period->end_date);
    }

    public function test_department_transfer_creates_history(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        $deptA = HrDepartment::create(['institute_id' => $institute->id, 'name' => 'Dept A', 'display_order' => 0, 'is_active' => true]);
        $deptB = HrDepartment::create(['institute_id' => $institute->id, 'name' => 'Dept B', 'display_order' => 1, 'is_active' => true]);
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->createEmployee($institute, $branch->id, $owner, ['department_id' => $deptA->id]);

        TenantContext::set($institute->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.transfer', $emp), [
            'effective_date' => '2024-02-01', 'department_id' => $deptB->id, 'reason' => 'Reorg',
        ])->assertRedirect();

        $emp->refresh();
        $this->assertEquals($deptB->id, $emp->department_id);
        $history = HrEmploymentHistory::where('employee_id', $emp->id)->where('event_type', 'department_transfer')->latest('id')->firstOrFail();
        $this->assertEquals($deptA->id, $history->previous_department_id);
        $this->assertEquals($deptB->id, $history->new_department_id);
        $this->assertEquals('Reorg', $history->reason);
        $this->assertEquals('2024-02-01', $history->effective_date->format('Y-m-d'));
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_employment_transfer']);
    }

    public function test_branch_transfer(): void
    {
        $institute = $this->institute();
        $b1 = $this->branch($institute, 'B1');
        $b2 = $this->branch($institute, 'B2');
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->createEmployee($institute, $b1->id, $owner);

        TenantContext::set($institute->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.transfer', $emp), [
            'effective_date' => '2024-03-01', 'branch_id' => $b2->id,
        ])->assertRedirect();

        $emp->refresh();
        $this->assertEquals($b2->id, $emp->branch_id);
        $history = HrEmploymentHistory::where('employee_id', $emp->id)->where('event_type', 'branch_transfer')->latest('id')->firstOrFail();
        $this->assertEquals($b1->id, $history->previous_branch_id);
        $this->assertEquals($b2->id, $history->new_branch_id);
        // Period should still be active (transfer does not close period)
        $this->assertEquals(1, HrEmploymentPeriod::where('employee_id', $emp->id)->where('status', 'active')->count());
    }

    public function test_designation_change(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $des1 = HrDesignation::create(['institute_id' => $institute->id, 'name' => 'Junior', 'display_order' => 0, 'is_active' => true]);
        $des2 = HrDesignation::create(['institute_id' => $institute->id, 'name' => 'Senior', 'display_order' => 1, 'is_active' => true]);
        $emp = $this->createEmployee($institute, null, $owner, ['designation_id' => $des1->id]);

        TenantContext::set($institute->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.transfer', $emp), [
            'effective_date' => '2024-04-01', 'designation_id' => $des2->id,
        ])->assertRedirect();

        $emp->refresh();
        $this->assertEquals($des2->id, $emp->designation_id);
        $history = HrEmploymentHistory::where('employee_id', $emp->id)->where('event_type', 'designation_change')->latest('id')->firstOrFail();
        $this->assertEquals($des1->id, $history->previous_designation_id);
        $this->assertEquals($des2->id, $history->new_designation_id);
    }

    public function test_promotion_with_salary_reference(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $des1 = HrDesignation::create(['institute_id' => $institute->id, 'name' => 'Assoc', 'display_order' => 0, 'is_active' => true]);
        $des2 = HrDesignation::create(['institute_id' => $institute->id, 'name' => 'Senior', 'display_order' => 1, 'is_active' => true]);
        $emp = $this->createEmployee($institute, null, $owner, ['designation_id' => $des1->id]);

        TenantContext::set($institute->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.promote', $emp), [
            'effective_date' => '2024-05-01', 'designation_id' => $des2->id, 'title' => 'Senior Engineer', 'salary_reference' => 'BDT 80k', 'reason' => 'Performance',
        ])->assertRedirect();

        $emp->refresh();
        $this->assertEquals($des2->id, $emp->designation_id);
        $history = HrEmploymentHistory::where('employee_id', $emp->id)->where('event_type', 'promotion')->latest('id')->firstOrFail();
        $this->assertEquals($des1->id, $history->previous_designation_id);
        $this->assertEquals($des2->id, $history->new_designation_id);
        $this->assertEquals('Senior Engineer', $history->title);
        $this->assertEquals('BDT 80k', $history->new_salary_reference);
        $this->assertTrue(HrEmploymentHistory::where('employee_id', $emp->id)->where('event_type', 'salary_reference')->exists());
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_employment_promotion']);
    }

    public function test_resignation_creates_pending_and_closes_period(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->createEmployee($institute, null, $owner, ['joining_date' => '2024-01-01']);
        $periodBefore = HrEmploymentPeriod::where('employee_id', $emp->id)->where('status', 'active')->firstOrFail();

        TenantContext::set($institute->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.resign', $emp), [
            'resignation_date' => '2024-06-01', 'last_working_date' => '2024-06-30', 'reason' => 'Personal',
        ])->assertRedirect();

        $emp->refresh();
        $this->assertEquals('resigned', $emp->employment_status);
        $history = HrEmploymentHistory::where('employee_id', $emp->id)->where('event_type', 'resignation')->latest('id')->firstOrFail();
        $this->assertEquals('pending', $history->approval_status);
        $this->assertEquals('2024-06-01', $history->effective_date->format('Y-m-d'));
        $periodBefore->refresh();
        $this->assertEquals('closed', $periodBefore->status);
        $this->assertEquals('2024-06-30', $periodBefore->end_date->format('Y-m-d'));
        $this->assertEquals('resigned', $periodBefore->end_reason);
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_employment_resignation']);
    }

    public function test_resignation_approval_and_rejection(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->createEmployee($institute, null, $owner);

        TenantContext::set($institute->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.resign', $emp), [
            'resignation_date' => '2024-07-01', 'last_working_date' => '2024-07-31', 'reason' => 'Test',
        ])->assertRedirect();
        $history = HrEmploymentHistory::where('employee_id', $emp->id)->where('event_type', 'resignation')->latest('id')->firstOrFail();

        // Approve
        $this->actingAs($owner, 'institute_user')->post(route('hr.history.resign-decision', $history), ['decision' => 'approved'])->assertRedirect();
        $history->refresh();
        $this->assertEquals('approved', $history->approval_status);
        $this->assertTrue(HrEmploymentHistory::where('event_type', 'resignation_approved')->where('employee_id', $emp->id)->exists());

        // New employee for rejection path
        $emp2 = $this->createEmployee($institute, null, $owner, ['first_name' => 'Reject', 'last_name' => 'Test']);
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.resign', $emp2), [
            'resignation_date' => '2024-08-01', 'last_working_date' => '2024-08-31', 'reason' => 'Reject me',
        ])->assertRedirect();
        $h2 = HrEmploymentHistory::where('employee_id', $emp2->id)->where('event_type', 'resignation')->latest('id')->firstOrFail();
        $this->actingAs($owner, 'institute_user')->post(route('hr.history.resign-decision', $h2), ['decision' => 'rejected'])->assertRedirect();
        $emp2->refresh();
        $this->assertEquals('active', $emp2->employment_status);
        $this->assertTrue(HrEmploymentPeriod::where('employee_id', $emp2->id)->where('status', 'active')->exists());
    }

    public function test_termination_closes_period(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->createEmployee($institute, null, $owner, ['joining_date' => '2024-01-01']);

        TenantContext::set($institute->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.terminate', $emp), [
            'termination_date' => '2024-09-01', 'reason' => 'Misconduct',
        ])->assertRedirect();

        $emp->refresh();
        $this->assertEquals('terminated', $emp->employment_status);
        $history = HrEmploymentHistory::where('employee_id', $emp->id)->where('event_type', 'termination')->latest('id')->firstOrFail();
        $this->assertEquals('Misconduct', $history->reason);
        $period = HrEmploymentPeriod::where('employee_id', $emp->id)->where('status', 'closed')->latest('id')->firstOrFail();
        $this->assertEquals('terminated', $period->end_reason);
        $this->assertEquals('2024-09-01', $period->end_date->format('Y-m-d'));
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_employment_termination']);
    }

    public function test_reactivation_preserves_history_and_opens_new_period(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->createEmployee($institute, null, $owner, ['joining_date' => '2023-01-01']);
        // Terminate
        TenantContext::set($institute->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.terminate', $emp), ['termination_date' => '2024-01-01', 'reason' => 'Test'])->assertRedirect();
        $closedCount = HrEmploymentPeriod::where('employee_id', $emp->id)->count();
        $historyBefore = HrEmploymentHistory::where('employee_id', $emp->id)->count();

        // Reactivate
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.reactivate', $emp), [
            'effective_date' => '2024-06-01', 'reason' => 'Rehired',
        ])->assertRedirect();

        $emp->refresh();
        $this->assertEquals('active', $emp->employment_status);
        $this->assertEquals(2, HrEmploymentPeriod::where('employee_id', $emp->id)->where('status', 'active')->count() + HrEmploymentPeriod::where('employee_id', $emp->id)->where('status', 'closed')->count() - $closedCount + $closedCount); // ensure new period added
        $newPeriod = HrEmploymentPeriod::where('employee_id', $emp->id)->where('status', 'active')->latest('id')->firstOrFail();
        $this->assertEquals('2024-06-01', $newPeriod->start_date->format('Y-m-d'));
        $historyAfter = HrEmploymentHistory::where('employee_id', $emp->id)->count();
        $this->assertGreaterThan($historyBefore, $historyAfter);
        $react = HrEmploymentHistory::where('employee_id', $emp->id)->where('event_type', 'reactivation')->latest('id')->firstOrFail();
        $this->assertEquals('terminated', $react->previous_employment_status);
        $this->assertEquals('active', $react->new_employment_status);
        // Historical records must remain: joining still there
        $this->assertTrue(HrEmploymentHistory::where('employee_id', $emp->id)->where('event_type', 'joining')->exists());
        $this->assertTrue(HrEmploymentHistory::where('employee_id', $emp->id)->where('event_type', 'termination')->exists());
    }

    public function test_historical_timeline_is_immutable_and_chronological(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $dept = HrDepartment::create(['institute_id' => $institute->id, 'name' => 'Timeline Dept', 'display_order' => 0, 'is_active' => true]);
        $emp = $this->createEmployee($institute, null, $owner, ['joining_date' => '2024-01-01', 'department_id' => $dept->id]);

        TenantContext::set($institute->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.promote', $emp), ['effective_date' => '2024-02-01', 'title' => 'Promoted'])->assertRedirect();
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.transfer', $emp), ['effective_date' => '2024-03-01', 'employment_type' => 'contractual'])->assertRedirect();

        $histories = HrEmploymentHistory::where('employee_id', $emp->id)->orderBy('effective_date')->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(3, $histories->count());
        $dates = $histories->pluck('effective_date')->map(fn ($d) => $d->format('Y-m-d'))->toArray();
        $sorted = $dates;
        sort($sorted);
        $this->assertEquals($sorted, $dates);

        // Never overwrite: update transfer should create new row, not overwrite
        $countBefore = HrEmploymentHistory::where('employee_id', $emp->id)->count();
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.transfer', $emp), ['effective_date' => '2024-04-01', 'employment_type' => 'full_time'])->assertRedirect();
        $this->assertEquals($countBefore + 1, HrEmploymentHistory::where('employee_id', $emp->id)->count());
    }

    public function test_employment_periods_total_service_duration(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->createEmployee($institute, null, $owner, ['joining_date' => '2024-01-01']);
        TenantContext::set($institute->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.terminate', $emp), ['termination_date' => '2024-01-11', 'reason' => 'End'])->assertRedirect();
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.reactivate', $emp), ['effective_date' => '2024-02-01'])->assertRedirect();

        $periods = HrEmploymentPeriod::where('employee_id', $emp->id)->orderBy('start_date')->get();
        $this->assertCount(2, $periods);
        $this->assertEquals('closed', $periods[0]->status);
        $this->assertEquals('active', $periods[1]->status);
        $total = HrEmploymentPeriod::totalServiceDays($emp->id, $institute->id);
        $this->assertGreaterThan(10, $total);
    }

    public function test_tenant_isolation_lifecycle(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $ownerA = $this->user($a, 'institute-owner');
        $ownerB = $this->user($b, 'institute-owner');
        $empA = $this->createEmployee($a, null, $ownerA);

        TenantContext::set($b->id);
        $this->actingAs($ownerB, 'institute_user')->post(route('hr.employees.transfer', $empA), ['effective_date' => '2024-05-01', 'employment_type' => 'part_time'])
            ->assertNotFound();
        $this->actingAs($ownerB, 'institute_user')->post(route('hr.employees.promote', $empA), ['effective_date' => '2024-05-01', 'title' => 'X'])
            ->assertNotFound();
        $this->actingAs($ownerB, 'institute_user')->post(route('hr.employees.resign', $empA), ['resignation_date' => '2024-05-01', 'last_working_date' => '2024-05-31'])
            ->assertNotFound();
        $this->actingAs($ownerB, 'institute_user')->post(route('hr.employees.terminate', $empA), ['termination_date' => '2024-05-01', 'reason' => 'X'])
            ->assertNotFound();
    }

    public function test_branch_isolation_transfer_denied_outside_scope(): void
    {
        $institute = $this->institute();
        $b1 = $this->branch($institute, 'B1');
        $b2 = $this->branch($institute, 'B2');
        $owner = $this->user($institute, 'institute-owner');
        $mgr1 = $this->user($institute, 'branch-manager', $b1->id);
        $emp = $this->createEmployee($institute, $b1->id, $owner);

        TenantContext::set($institute->id);
        BranchContext::set($b1->id);
        // Manager in B1 tries to transfer employee to B2 -> should be 403 or 404
        $this->actingAs($mgr1, 'institute_user')->post(route('hr.employees.transfer', $emp), ['effective_date' => '2024-06-01', 'branch_id' => $b2->id])
            ->assertStatus(403);
        BranchContext::clear();

        // Owner can transfer to B2
        TenantContext::set($institute->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.transfer', $emp), ['effective_date' => '2024-06-01', 'branch_id' => $b2->id])
            ->assertRedirect();
        $emp->refresh();
        $this->assertEquals($b2->id, $emp->branch_id);
    }

    public function test_permission_enforcement_lifecycle(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $receptionist = $this->user($institute, 'receptionist');
        $emp = $this->createEmployee($institute, null, $owner);

        TenantContext::set($institute->id);
        $this->actingAs($receptionist, 'institute_user')->post(route('hr.employees.transfer', $emp), ['effective_date' => '2024-07-01', 'employment_type' => 'part_time'])
            ->assertForbidden();
        $this->actingAs($receptionist, 'institute_user')->post(route('hr.employees.promote', $emp), ['effective_date' => '2024-07-01', 'title' => 'X'])
            ->assertForbidden();
        $this->actingAs($receptionist, 'institute_user')->post(route('hr.employees.resign', $emp), ['resignation_date' => '2024-07-01', 'last_working_date' => '2024-07-31'])
            ->assertForbidden();
        $this->actingAs($receptionist, 'institute_user')->post(route('hr.employees.terminate', $emp), ['termination_date' => '2024-07-01', 'reason' => 'X'])
            ->assertForbidden();
        $this->actingAs($receptionist, 'institute_user')->post(route('hr.employees.reactivate', $emp), ['effective_date' => '2024-07-01'])
            ->assertForbidden();

        // Owner succeeds
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.transfer', $emp), ['effective_date' => '2024-07-01', 'employment_type' => 'part_time'])->assertRedirect();
    }

    public function test_audit_logging_lifecycle(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner');
        $emp = $this->createEmployee($institute, null, $owner);

        TenantContext::set($institute->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.promote', $emp), ['effective_date' => '2024-08-01', 'title' => 'Lead'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_employment_promotion']);

        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.transfer', $emp), ['effective_date' => '2024-08-02', 'employment_type' => 'contractual'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_employment_transfer']);

        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.resign', $emp), ['resignation_date' => '2024-08-03', 'last_working_date' => '2024-08-31'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_employment_resignation']);

        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.terminate', $this->createEmployee($institute, null, $owner, ['first_name' => 'Term', 'last_name' => 'Audit'])), ['termination_date' => '2024-08-04', 'reason' => 'Test'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_employment_termination']);
    }
}
