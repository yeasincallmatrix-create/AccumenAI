<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\HrAttendance;
use App\Models\HrAttendanceCorrection;
use App\Models\HrDepartment;
use App\Models\HrEmployee;
use App\Models\HrHoliday;
use App\Models\HrLeaveApplication;
use App\Models\HrLeaveBalance;
use App\Models\HrLeaveType;
use App\Models\HrWorkShift;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HrAttendanceLeaveTest extends TestCase
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

    private function institute(?Country $c = null): Institute
    {
        $c ??= $this->country();

        return Institute::create(['name' => 'HR4 Inst '.uniqid(), 'slug' => 'hr4-'.uniqid(), 'country' => $c->name, 'country_id' => $c->id, 'status' => 'active']);
    }

    private function branch(Institute $inst, string $name = 'Branch'): Branch
    {
        return Branch::create(['institute_id' => $inst->id, 'name' => $name.' '.uniqid(), 'status' => 'active']);
    }

    private function role(string $slug): Role
    {
        return Role::where('slug', $slug)->firstOrFail();
    }

    private function user(Institute $inst, string $role, ?int $branchId = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $inst->id, 'role_id' => $this->role($role)->id, 'branch_id' => $branchId,
            'first_name' => ucfirst($role), 'last_name' => 'User',
            'email' => $role.'-'.uniqid().'@example.test', 'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password), 'status' => 'active',
        ]);
    }

    private function emp(Institute $inst, ?int $branchId, InstituteUser $actor, array $over = []): HrEmployee
    {
        TenantContext::set($inst->id);
        $svc = app(\App\Services\HrEmployeeService::class);

        return $svc->create(array_merge(['first_name' => 'Emp', 'last_name' => 'Test', 'joining_date' => now()->toDateString()], $over), $inst->id, $branchId, $actor->id);
    }

    public function test_attendance_creation_with_check_in_out(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $owner = $this->user($inst, 'institute-owner');
        $emp = $this->emp($inst, $branch->id, $owner);

        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.mark'), [
            'employee_id' => $emp->id, 'attendance_date' => '2024-05-10', 'status' => 'present', 'check_in' => '09:05', 'check_out' => '17:30',
        ])->assertRedirect();

        $att = HrAttendance::where('employee_id', $emp->id)->where('attendance_date', '2024-05-10')->firstOrFail();
        $this->assertEquals('present', $att->status);
        $this->assertEquals('09:05:00', substr($att->check_in, 0, 8));
        $this->assertNotNull($att->working_minutes);
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_attendance_marked']);
    }

    public function test_shift_calculation_late_and_overtime(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $emp = $this->emp($inst, null, $owner);
        // Create shift 09:00-17:00 grace 10
        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.shifts.store'), [
            'name' => 'Day Shift', 'start_time' => '09:00', 'end_time' => '17:00', 'grace_minutes' => 10, 'working_days' => [1, 2, 3, 4, 5],
        ])->assertRedirect();
        $shift = HrWorkShift::where('institute_id', $inst->id)->firstOrFail();

        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.mark'), [
            'employee_id' => $emp->id, 'attendance_date' => '2024-05-11', 'status' => 'late', 'check_in' => '09:20', 'check_out' => '18:00',
        ])->assertRedirect();

        $att = HrAttendance::where('employee_id', $emp->id)->where('attendance_date', '2024-05-11')->firstOrFail();
        $this->assertEquals('late', $att->status);
        $this->assertEquals(10, $att->late_minutes); // 09:10 expected, 09:20 actual => 10 late
        $this->assertGreaterThan(0, $att->overtime_minutes);
    }

    public function test_attendance_status_handling_and_sources(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $emp = $this->emp($inst, null, $owner);
        $statuses = HrAttendance::STATUSES;
        foreach ($statuses as $status) {
            TenantContext::set($inst->id);
            $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.mark'), [
                'employee_id' => $emp->id, 'attendance_date' => '2024-06-'.str_pad((string) (10 + array_search($status, $statuses)), 2, '0', STR_PAD_LEFT), 'status' => $status, 'source' => 'manual',
            ])->assertRedirect();
            $this->assertDatabaseHas('hr_attendances', ['employee_id' => $emp->id, 'status' => $status]);
        }
    }

    public function test_correction_workflow_preserves_original(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $emp = $this->emp($inst, null, $owner);
        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.mark'), [
            'employee_id' => $emp->id, 'attendance_date' => '2024-05-15', 'status' => 'absent',
        ])->assertRedirect();
        $att = HrAttendance::where('employee_id', $emp->id)->where('attendance_date', '2024-05-15')->firstOrFail();
        $this->assertEquals('absent', $att->status);

        // Request correction to present
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.corrections.request'), [
            'employee_id' => $emp->id, 'correction_date' => '2024-05-15', 'requested_status' => 'present', 'reason' => 'Was present',
        ])->assertRedirect();
        $corr = HrAttendanceCorrection::where('employee_id', $emp->id)->latest('id')->firstOrFail();
        $this->assertEquals('pending', $corr->status);
        $this->assertEquals('absent', $att->fresh()->status); // original preserved until approved

        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.corrections.decide', $corr), ['decision' => 'approved'])->assertRedirect();
        $corr->refresh();
        $this->assertEquals('approved', $corr->status);
        $att->refresh();
        $this->assertEquals('present', $att->status);
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_attendance_correction_approved']);

        // Rejected path: create another correction and reject
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.corrections.request'), [
            'employee_id' => $emp->id, 'correction_date' => '2024-05-16', 'requested_status' => 'late', 'reason' => 'Late claim',
        ])->assertRedirect();
        $c2 = HrAttendanceCorrection::where('employee_id', $emp->id)->latest('id')->firstOrFail();
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.corrections.decide', $c2), ['decision' => 'rejected'])->assertRedirect();
        $c2->refresh();
        $this->assertEquals('rejected', $c2->status);
    }

    public function test_leave_application_and_approval_flow(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $emp = $this->emp($inst, null, $owner);
        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.types.store'), [
            'name' => 'Annual', 'code' => 'annual', 'yearly_allowance' => 12, 'requires_approval' => 1,
        ])->assertRedirect();
        $type = HrLeaveType::where('institute_id', $inst->id)->where('code', 'annual')->firstOrFail();

        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.applications.store'), [
            'employee_id' => $emp->id, 'leave_type_id' => $type->id, 'start_date' => '2024-07-01', 'end_date' => '2024-07-03', 'reason' => 'Vacation',
        ])->assertRedirect();
        $app = HrLeaveApplication::where('employee_id', $emp->id)->latest('id')->firstOrFail();
        $this->assertEquals('pending', $app->status);
        $this->assertEquals(3.0, (float) $app->days_count);
        $balance = HrLeaveBalance::where('employee_id', $emp->id)->where('leave_type_id', $type->id)->where('year', 2024)->firstOrFail();
        $this->assertEquals(3.0, (float) $balance->pending);
        $this->assertEquals(0.0, (float) $balance->used);

        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.applications.decide', $app), ['decision' => 'approved'])->assertRedirect();
        $app->refresh();
        $this->assertEquals('approved', $app->status);
        $balance->refresh();
        $this->assertEquals(0.0, (float) $balance->pending);
        $this->assertEquals(3.0, (float) $balance->used);
        $this->assertEquals(9.0, $balance->remaining());

        // Rejection
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.applications.store'), [
            'employee_id' => $emp->id, 'leave_type_id' => $type->id, 'start_date' => '2024-08-01', 'end_date' => '2024-08-02', 'reason' => 'Another',
        ])->assertRedirect();
        $app2 = HrLeaveApplication::where('employee_id', $emp->id)->where('start_date', '2024-08-01')->firstOrFail();
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.applications.decide', $app2), ['decision' => 'rejected', 'rejection_reason' => 'Busy'])->assertRedirect();
        $app2->refresh();
        $this->assertEquals('rejected', $app2->status);
        $balance->refresh();
        $this->assertEquals(3.0, (float) $balance->used); // not increased
    }

    public function test_leave_balance_tracking(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $emp = $this->emp($inst, null, $owner);
        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.types.store'), [
            'name' => 'Sick', 'code' => 'sick', 'yearly_allowance' => 5,
        ])->assertRedirect();
        $type = HrLeaveType::where('code', 'sick')->where('institute_id', $inst->id)->firstOrFail();

        // Apply and approve 2 days
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.applications.store'), [
            'employee_id' => $emp->id, 'leave_type_id' => $type->id, 'start_date' => '2024-09-01', 'end_date' => '2024-09-02',
        ])->assertRedirect();
        $app = HrLeaveApplication::where('employee_id', $emp->id)->latest('id')->firstOrFail();
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.applications.decide', $app), ['decision' => 'approved'])->assertRedirect();

        $bal = HrLeaveBalance::where('employee_id', $emp->id)->where('leave_type_id', $type->id)->firstOrFail();
        $this->assertEquals(5.0, (float) $bal->allocated);
        $this->assertEquals(2.0, (float) $bal->used);
        $this->assertEquals(3.0, $bal->remaining());
    }

    public function test_attendance_reflects_approved_leave(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $emp = $this->emp($inst, null, $owner);
        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.types.store'), ['name' => 'Casual', 'code' => 'casual', 'yearly_allowance' => 10])->assertRedirect();
        $type = HrLeaveType::where('code', 'casual')->where('institute_id', $inst->id)->firstOrFail();

        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.applications.store'), [
            'employee_id' => $emp->id, 'leave_type_id' => $type->id, 'start_date' => '2024-10-10', 'end_date' => '2024-10-11',
        ])->assertRedirect();
        $app = HrLeaveApplication::where('employee_id', $emp->id)->latest('id')->firstOrFail();
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.applications.decide', $app), ['decision' => 'approved'])->assertRedirect();

        // Check attendance auto-created with status leave
        $this->assertDatabaseHas('hr_attendances', ['employee_id' => $emp->id, 'attendance_date' => '2024-10-10', 'status' => 'leave']);
        $this->assertDatabaseHas('hr_attendances', ['employee_id' => $emp->id, 'attendance_date' => '2024-10-11', 'status' => 'leave']);
    }

    public function test_holiday_handling(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst);
        $owner = $this->user($inst, 'institute-owner');
        $emp = $this->emp($inst, $branch->id, $owner);
        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.holidays.store'), [
            'name' => 'Eid', 'holiday_date' => '2024-06-17', 'branch_id' => $branch->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('hr_holidays', ['holiday_date' => '2024-06-17', 'branch_id' => $branch->id]);

        // Marking attendance on holiday as holiday/weekend is allowed
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.mark'), [
            'employee_id' => $emp->id, 'attendance_date' => '2024-06-17', 'status' => 'holiday',
        ])->assertRedirect();
        $this->assertDatabaseHas('hr_attendances', ['employee_id' => $emp->id, 'attendance_date' => '2024-06-17', 'status' => 'holiday']);
    }

    public function test_no_automatic_false_absences(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $emp = $this->emp($inst, null, $owner);
        TenantContext::set($inst->id);
        // No attendance recorded for 2024-12-25
        $count = HrAttendance::where('employee_id', $emp->id)->where('attendance_date', '2024-12-25')->count();
        $this->assertEquals(0, $count);
        // Reports should not auto-mark absent; unmarked days are not counted
        $this->actingAs($owner, 'institute_user')->get(route('hr.attendance.daily', ['date' => '2024-12-25']))->assertOk();
        // Ensure no absent auto-created
        $this->assertEquals(0, HrAttendance::where('attendance_date', '2024-12-25')->count());
    }

    public function test_tenant_isolation(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $ownerA = $this->user($a, 'institute-owner');
        $ownerB = $this->user($b, 'institute-owner');
        $empA = $this->emp($a, null, $ownerA);
        TenantContext::set($a->id);
        $this->actingAs($ownerA, 'institute_user')->post(route('hr.attendance.mark'), [
            'employee_id' => $empA->id, 'attendance_date' => '2024-05-20', 'status' => 'present',
        ])->assertRedirect();

        TenantContext::set($b->id);
        $this->actingAs($ownerB, 'institute_user')->post(route('hr.attendance.mark'), [
            'employee_id' => $empA->id, 'attendance_date' => '2024-05-21', 'status' => 'present',
        ])->assertStatus(404); // employee not in tenant B

        $this->actingAs($ownerB, 'institute_user')->get(route('hr.attendance.daily', ['date' => '2024-05-20']))->assertOk();
        // B should not see A's attendance via employee filter (employee not in B)
        $this->assertEquals(0, HrAttendance::withoutGlobalScopes()->where('institute_id', $b->id)->count());
    }

    public function test_branch_isolation(): void
    {
        $inst = $this->institute();
        $b1 = $this->branch($inst, 'B1');
        $b2 = $this->branch($inst, 'B2');
        $owner = $this->user($inst, 'institute-owner');
        $mgr1 = $this->user($inst, 'branch-manager', $b1->id);
        $emp1 = $this->emp($inst, $b1->id, $owner);
        $emp2 = $this->emp($inst, $b2->id, $owner);

        TenantContext::set($inst->id);
        // Owner marks both
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.mark'), ['employee_id' => $emp1->id, 'attendance_date' => '2024-05-22', 'status' => 'present'])->assertRedirect();
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.mark'), ['employee_id' => $emp2->id, 'attendance_date' => '2024-05-22', 'status' => 'present'])->assertRedirect();

        // Manager B1 sees only B1
        TenantContext::set($inst->id);
        BranchContext::set($b1->id);
        $this->actingAs($mgr1, 'institute_user')->get(route('hr.attendance.daily', ['date' => '2024-05-22']))->assertOk();
        $visible = HrAttendance::query()->where('attendance_date', '2024-05-22')->get();
        // BranchScoped not on hr_attendances but we filter via employee branch in controller; direct query without filter returns both, but controller filters. So test controller filtered result: we check view contains emp1 but not emp2
        $this->actingAs($mgr1, 'institute_user')->get(route('hr.attendance.daily', ['date' => '2024-05-22']))->assertSee($emp1->employee_code)->assertDontSee($emp2->employee_code);
        BranchContext::clear();
    }

    public function test_permission_matrix(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $teacher = $this->user($inst, 'teacher');
        $emp = $this->emp($inst, null, $owner);

        TenantContext::set($inst->id);
        // Teacher has hr.leave.view/create but not attendance.manage
        $this->actingAs($teacher, 'institute_user')->post(route('hr.attendance.mark'), ['employee_id' => $emp->id, 'attendance_date' => '2024-05-23', 'status' => 'present'])->assertForbidden();
        $this->actingAs($teacher, 'institute_user')->get(route('hr.attendance.daily'))->assertForbidden();
        // Teacher can apply leave (has hr.leave.create)
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.types.store'), ['name' => 'TLeave', 'code' => 'tleave', 'yearly_allowance' => 3])->assertRedirect();
        $type = HrLeaveType::where('code', 'tleave')->firstOrFail();
        $this->actingAs($teacher, 'institute_user')->post(route('hr.leave.applications.store'), ['employee_id' => $emp->id, 'leave_type_id' => $type->id, 'start_date' => '2024-07-10', 'end_date' => '2024-07-10'])->assertRedirect();
        // Teacher cannot approve (needs hr.leave.approve)
        $app = HrLeaveApplication::where('employee_id', $emp->id)->latest('id')->firstOrFail();
        $this->actingAs($teacher, 'institute_user')->post(route('hr.leave.applications.decide', $app), ['decision' => 'approved'])->assertForbidden();
        // Owner can approve
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.applications.decide', $app), ['decision' => 'approved'])->assertRedirect();
    }

    public function test_historical_safety_after_transfer(): void
    {
        $inst = $this->institute();
        $b1 = $this->branch($inst, 'B1');
        $b2 = $this->branch($inst, 'B2');
        $owner = $this->user($inst, 'institute-owner');
        $emp = $this->emp($inst, $b1->id, $owner);
        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.mark'), ['employee_id' => $emp->id, 'attendance_date' => '2024-04-01', 'status' => 'present'])->assertRedirect();
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.types.store'), ['name' => 'Hist', 'code' => 'hist', 'yearly_allowance' => 10])->assertRedirect();
        $type = HrLeaveType::where('code', 'hist')->firstOrFail();
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.applications.store'), ['employee_id' => $emp->id, 'leave_type_id' => $type->id, 'start_date' => '2024-04-02', 'end_date' => '2024-04-02'])->assertRedirect();
        $app = HrLeaveApplication::where('employee_id', $emp->id)->firstOrFail();
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.applications.decide', $app), ['decision' => 'approved'])->assertRedirect();

        // Transfer branch
        $this->actingAs($owner, 'institute_user')->post(route('hr.employees.transfer', $emp), ['effective_date' => '2024-04-03', 'branch_id' => $b2->id])->assertRedirect();

        // Attendance and leave must still exist and still linked to employee (not deleted, institute_id unchanged)
        $this->assertDatabaseHas('hr_attendances', ['employee_id' => $emp->id, 'attendance_date' => '2024-04-01']);
        $this->assertDatabaseHas('hr_leave_applications', ['id' => $app->id, 'employee_id' => $emp->id]);
        $this->assertDatabaseHas('hr_attendances', ['employee_id' => $emp->id, 'attendance_date' => '2024-04-02', 'status' => 'leave']);
        $emp->refresh();
        $this->assertEquals($b2->id, $emp->branch_id);
    }

    public function test_audit_logging(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $emp = $this->emp($inst, null, $owner);
        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.mark'), ['employee_id' => $emp->id, 'attendance_date' => '2024-08-01', 'status' => 'present'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_attendance_marked']);
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.holidays.store'), ['name' => 'TestHol', 'holiday_date' => '2024-08-15'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_holiday_created']);
        $this->actingAs($owner, 'institute_user')->post(route('hr.leave.types.store'), ['name' => 'AuditLT', 'code' => 'auditlt', 'yearly_allowance' => 5])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['module' => 'hr', 'action' => 'hr_leave_type_created']);
    }

    public function test_education_attendance_not_overwritten(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        // Create student academic attendance (Education) - ensure it doesn't interfere with HR
        TenantContext::set($inst->id);
        $hrEmp = $this->emp($inst, null, $owner);
        $this->actingAs($owner, 'institute_user')->post(route('hr.attendance.mark'), ['employee_id' => $hrEmp->id, 'attendance_date' => '2024-09-01', 'status' => 'present'])->assertRedirect();
        // Education attendance table should not have HR employee entry
        $this->assertEquals(0, \App\Models\Attendance::where('institute_id', $inst->id)->where('class_date', '2024-09-01')->count());
        $this->assertEquals(1, HrAttendance::where('institute_id', $inst->id)->where('attendance_date', '2024-09-01')->count());
    }
}
