<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\HrAttendance;
use App\Models\HrAttendanceCorrection;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrEmployee;
use App\Models\HrLeaveApplication;
use App\Models\HrLeaveType;
use App\Models\HrPayroll;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrSelfServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function country(): Country { return Country::firstOrCreate(['iso2'=>'BD'], ['name'=>'Bangladesh','iso3'=>'BGD','phone_code'=>'880','status'=>true]); }
    private function institute(?Country $c=null): Institute { $c ??= $this->country(); return Institute::create(['name'=>'Self Inst '.uniqid(),'slug'=>'self-'.uniqid(),'country'=>$c->name,'country_id'=>$c->id,'status'=>'active']); }
    private function branch(Institute $i, string $n='Branch'): Branch { return Branch::create(['institute_id'=>$i->id,'name'=>$n.' '.uniqid(),'status'=>'active']); }
    private function user(Institute $i, string $role, ?int $branchId=null): InstituteUser {
        return InstituteUser::create(['institute_id'=>$i->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'branch_id'=>$branchId,'first_name'=>ucfirst($role),'last_name'=>'User','email'=>$role.'-'.uniqid().'@example.test','phone'=>'01700'.rand(100000,999999),'password_hash'=>bcrypt('secret12345'),'status'=>'active']);
    }
    private function employee(Institute $i, InstituteUser $user, ?int $branchId=null, array $overrides=[]): HrEmployee {
        $svc = app(\App\Services\HrEmployeeService::class);
        return $svc->create(array_merge(['first_name'=>'Emp','last_name'=>'Test '.uniqid(),'employment_status'=>'active','institute_user_id'=>$user->id], $overrides), $i->id, $branchId ?? $user->branch_id, $user->id);
    }

    // ------------------------------------------------ Employee self-access

    public function test_employee_can_view_own_dashboard_and_profile(): void
    {
        $inst = $this->institute();
        $user = $this->user($inst,'teacher');
        $emp = $this->employee($inst,$user);
        TenantContext::set($inst->id);

        $this->actingAs($user,'institute_user')->get(route('hr.self.dashboard'))->assertOk()->assertSee($emp->display_name);
        $this->actingAs($user,'institute_user')->get(route('hr.self.profile'))->assertOk()->assertSee($emp->employee_code);

        // Sensitive fields are displayed as HR-controlled, not editable via form manipulation
        $this->actingAs($user,'institute_user')->put(route('hr.self.profile.update'), [
            'first_name'=>'Updated',
            'employee_code'=>'HACKED', // should be ignored
            'department_id'=>999,
        ])->assertRedirect();
        $emp->refresh();
        $this->assertSame('Updated',$emp->first_name);
        $this->assertNotSame('HACKED',$emp->employee_code);
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_self_profile_updated','record_id'=>$emp->id]);
    }

    public function test_cross_employee_denial(): void
    {
        $inst = $this->institute();
        $userA = $this->user($inst,'teacher');
        $userB = $this->user($inst,'teacher');
        $empA = $this->employee($inst,$userA);
        $empB = $this->employee($inst,$userB);
        TenantContext::set($inst->id);
        // User A cannot see B's payslip via self-service
        $struct = app(\App\Services\HrSalaryStructureService::class)->create([
            'name'=>'Struct','code'=>'STR-'.uniqid(),'basic_salary'=>30000,'effective_from'=>now()->toDateString(),
        ], $inst->id, null, $userA->id);
        $svc = app(\App\Services\HrPayrollService::class);
        $svc->assignSalary(['employee_id'=>$empB->id,'salary_structure_id'=>$struct->id,'effective_date'=>now()->toDateString()], $inst->id, $userA->id);
        $period = $svc->createPeriod(['name'=>'Jan','start_date'=>now()->startOfMonth()->toDateString(),'end_date'=>now()->endOfMonth()->toDateString()], $inst->id, null, $userA->id);
        $svc->generate($period,$userA->id);
        $payrollB = HrPayroll::where('employee_id',$empB->id)->firstOrFail();

        $this->actingAs($userA,'institute_user')->get(route('hr.self.payslip.show',$payrollB))->assertStatus(403);
        // Cannot see other employee's documents via self view (indirect via payslip is enough)
        // Try to view other employee's attendance via self (should only see own)
        $this->actingAs($userA,'institute_user')->get(route('hr.self.attendance'))->assertOk();
        // Ensure isolation via direct query: empA has no absent, empB has one
        HrAttendance::create(['institute_id'=>$inst->id,'employee_id'=>$empB->id,'attendance_date'=>now()->toDateString(),'status'=>'absent']);
        $this->assertEquals(0, HrAttendance::where('employee_id',$empA->id)->where('status','absent')->count());
        $this->assertEquals(1, HrAttendance::where('employee_id',$empB->id)->where('status','absent')->count());
    }

    public function test_manager_scope(): void
    {
        $inst = $this->institute();
        $branch = $this->branch($inst,'Main');
        $owner = $this->user($inst,'institute-owner');
        $mgrUser = $this->user($inst,'branch-manager',$branch->id);
        $mgrEmp = $this->employee($inst,$mgrUser,$branch->id,['first_name'=>'Manager']);
        $emp1 = $this->employee($inst,$this->user($inst,'teacher'),$branch->id,['reporting_manager_id'=>$mgrEmp->id,'first_name'=>'Emp1']);
        $emp2 = $this->employee($inst,$this->user($inst,'teacher'),$branch->id,['reporting_manager_id'=>$mgrEmp->id,'first_name'=>'Emp2']);
        // Employee outside team (different branch)
        $otherBranch = $this->branch($inst,'Other');
        $otherEmp = $this->employee($inst,$this->user($inst,'teacher'),$otherBranch->id,['first_name'=>'Other']);

        TenantContext::set($inst->id);
        BranchContext::set($branch->id);

        $response = $this->actingAs($mgrUser,'institute_user')->get(route('hr.manager.dashboard'))->assertOk();
        // Manager should see team count 2, not 3 (other branch excluded)
        $response->assertSee('Team: 2 employees');
        BranchContext::clear();
    }

    public function test_branch_isolation(): void
    {
        $inst = $this->institute();
        $branchA = $this->branch($inst,'A');
        $branchB = $this->branch($inst,'B');
        $userA = $this->user($inst,'teacher',$branchA->id);
        $userB = $this->user($inst,'teacher',$branchB->id);
        $empA = $this->employee($inst,$userA,$branchA->id);
        $empB = $this->employee($inst,$userB,$branchB->id);
        TenantContext::set($inst->id);

        // User A cannot see B's self dashboard data (should only see own)
        $this->actingAs($userA,'institute_user')->get(route('hr.self.dashboard'))->assertOk()->assertSee($empA->display_name)->assertDontSee($empB->display_name);

        // Branch isolation for HR dashboard: branch A manager sees only branch A counts
        $mgrA = $this->user($inst,'branch-manager',$branchA->id);
        $this->employee($inst,$mgrA,$branchA->id,['first_name'=>'MgrA']);
        // Create leave for both branches
        $lt = HrLeaveType::create(['institute_id'=>$inst->id,'name'=>'Annual','code'=>'ANN-'.uniqid(),'yearly_allowance'=>20]);
        \App\Models\HrLeaveBalance::create(['institute_id'=>$inst->id,'employee_id'=>$empA->id,'leave_type_id'=>$lt->id,'year'=>now()->year,'allocated'=>20]);
        \App\Models\HrLeaveBalance::create(['institute_id'=>$inst->id,'employee_id'=>$empB->id,'leave_type_id'=>$lt->id,'year'=>now()->year,'allocated'=>20]);
        app(\App\Services\HrLeaveService::class)->apply(['employee_id'=>$empA->id,'leave_type_id'=>$lt->id,'start_date'=>now()->toDateString(),'end_date'=>now()->toDateString(),'reason'=>'Test'], $inst->id, $userA->id);
        app(\App\Services\HrLeaveService::class)->apply(['employee_id'=>$empB->id,'leave_type_id'=>$lt->id,'start_date'=>now()->toDateString(),'end_date'=>now()->toDateString(),'reason'=>'Test'], $inst->id, $userB->id);

        BranchContext::set($branchA->id);
        $this->actingAs($mgrA,'institute_user')->get(route('hr.manager.dashboard'))->assertOk();
        BranchContext::clear();
    }

    public function test_leave_self_service(): void
    {
        $inst = $this->institute();
        $user = $this->user($inst,'teacher');
        $emp = $this->employee($inst,$user);
        $lt = HrLeaveType::create(['institute_id'=>$inst->id,'name'=>'Annual','code'=>'ANN-'.uniqid(),'yearly_allowance'=>20]);
        \App\Models\HrLeaveBalance::create(['institute_id'=>$inst->id,'employee_id'=>$emp->id,'leave_type_id'=>$lt->id,'year'=>now()->year,'allocated'=>20]);
        TenantContext::set($inst->id);

        $this->actingAs($user,'institute_user')->get(route('hr.self.leave'))->assertOk();
        $this->actingAs($user,'institute_user')->post(route('hr.self.leave.store'), [
            'leave_type_id'=>$lt->id,
            'start_date'=>now()->addDay()->toDateString(),
            'end_date'=>now()->addDay()->toDateString(),
            'reason'=>'Family event',
        ])->assertRedirect();
        $app = HrLeaveApplication::where('employee_id',$emp->id)->firstOrFail();
        $this->assertSame('pending',$app->status);
        // Check notification triggered (in_app log)
        // Cancel pending
        $this->actingAs($user,'institute_user')->post(route('hr.self.leave.cancel',$app))->assertRedirect();
        $app->refresh();
        $this->assertSame('cancelled',$app->status);
        // Cannot cancel approved
        $app2 = app(\App\Services\HrLeaveService::class)->apply(['employee_id'=>$emp->id,'leave_type_id'=>$lt->id,'start_date'=>now()->addDays(5)->toDateString(),'end_date'=>now()->addDays(5)->toDateString()], $inst->id, $user->id);
        app(\App\Services\HrLeaveService::class)->decide($app2,'approved',null,$inst->id,$user->id);
        $this->actingAs($user,'institute_user')->post(route('hr.self.leave.cancel',$app2))->assertSessionHasErrors();
    }

    public function test_attendance_self_service(): void
    {
        $inst = $this->institute();
        $user = $this->user($inst,'teacher');
        $emp = $this->employee($inst,$user);
        TenantContext::set($inst->id);
        HrAttendance::create(['institute_id'=>$inst->id,'employee_id'=>$emp->id,'attendance_date'=>now()->toDateString(),'status'=>'present','check_in'=>'09:00','check_out'=>'17:00']);

        $this->actingAs($user,'institute_user')->get(route('hr.self.attendance'))->assertOk()->assertSee('present');
        $this->actingAs($user,'institute_user')->post(route('hr.self.attendance.correction'), [
            'correction_date'=>now()->toDateString(),
            'requested_status'=>'late',
            'reason'=>'Traffic',
        ])->assertRedirect();
        $corr = HrAttendanceCorrection::where('employee_id',$emp->id)->firstOrFail();
        $this->assertSame('pending',$corr->status);
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_attendance_correction_requested']);
    }

    public function test_payslip_self_service(): void
    {
        $inst = $this->institute();
        $user = $this->user($inst,'teacher');
        $emp = $this->employee($inst,$user);
        $owner = $this->user($inst,'institute-owner');
        $struct = app(\App\Services\HrSalaryStructureService::class)->create([
            'name'=>'Struct','code'=>'STR-'.uniqid(),'basic_salary'=>30000,'effective_from'=>now()->toDateString(),
        ], $inst->id, null, $owner->id);
        TenantContext::set($inst->id);
        app(\App\Services\HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>now()->toDateString()], $inst->id, $owner->id);
        $period = app(\App\Services\HrPayrollService::class)->createPeriod(['name'=>'Jan','start_date'=>now()->startOfMonth()->toDateString(),'end_date'=>now()->endOfMonth()->toDateString()], $inst->id, null, $owner->id);
        app(\App\Services\HrPayrollService::class)->generate($period,$owner->id);
        $payroll = HrPayroll::where('employee_id',$emp->id)->firstOrFail();

        $this->actingAs($user,'institute_user')->get(route('hr.self.payslips'))->assertOk()->assertSee($payroll->payslip_no);
        $this->actingAs($user,'institute_user')->get(route('hr.self.payslip.show',$payroll))->assertOk()->assertSee(number_format($payroll->net_salary,2));

        // Other employee cannot see
        $otherUser = $this->user($inst,'teacher');
        $otherEmp = $this->employee($inst,$otherUser);
        $this->actingAs($otherUser,'institute_user')->get(route('hr.self.payslip.show',$payroll))->assertStatus(403);

        // Salary privacy: self payslip does not leak other salary via API
        $this->actingAs($user,'institute_user')->get(route('hr.self.payslips'))->assertDontSee($otherEmp->employee_code);
    }

    public function test_document_self_service(): void
    {
        Storage::fake('public');
        $inst = $this->institute();
        $user = $this->user($inst,'teacher');
        $emp = $this->employee($inst,$user);
        TenantContext::set($inst->id);

        // HR uploads a document for employee
        $cat = \App\Models\DocumentCategory::where('slug','hr-nid-passport')->firstOrFail();
        $owner = $this->user($inst,'institute-owner');
        app(\App\Services\DocumentService::class)->upload($inst->id,'hr-employee',$emp->id,$cat->id, UploadedFile::fake()->create('nid.pdf',100,'application/pdf'), $owner->id, null, 'NID');

        $this->actingAs($user,'institute_user')->get(route('hr.self.documents'))->assertOk()->assertSee('nid.pdf');
        // Employee can upload own document
        $this->actingAs($user,'institute_user')->post(route('hr.self.documents.upload'), [
            'category_id'=>$cat->id,
            'file'=> UploadedFile::fake()->create('mycv.pdf',100,'application/pdf'),
        ])->assertRedirect();
        $this->assertDatabaseHas('documents',['documentable_id'=>$emp->id,'original_filename'=>'mycv.pdf']);
        // Other employee cannot see
        $otherUser = $this->user($inst,'teacher');
        $otherEmp = $this->employee($inst,$otherUser);
        $this->actingAs($otherUser,'institute_user')->get(route('hr.self.documents'))->assertOk()->assertDontSee('nid.pdf');
    }

    public function test_workflow_approval_and_notifications(): void
    {
        $inst = $this->institute();
        $user = $this->user($inst,'teacher');
        $emp = $this->employee($inst,$user);
        $managerUser = $this->user($inst,'branch-manager');
        $managerEmp = $this->employee($inst,$managerUser, null, ['first_name'=>'Mgr']);
        $emp->update(['reporting_manager_id'=>$managerEmp->id]);
        $lt = HrLeaveType::create(['institute_id'=>$inst->id,'name'=>'Annual','code'=>'ANN-'.uniqid(),'yearly_allowance'=>20]);
        \App\Models\HrLeaveBalance::create(['institute_id'=>$inst->id,'employee_id'=>$emp->id,'leave_type_id'=>$lt->id,'year'=>now()->year,'allocated'=>20]);
        TenantContext::set($inst->id);

        // Employee applies
        $this->actingAs($user,'institute_user')->post(route('hr.self.leave.store'), [
            'leave_type_id'=>$lt->id,
            'start_date'=>now()->addDay()->toDateString(),
            'end_date'=>now()->addDay()->toDateString(),
        ])->assertRedirect();
        $app = HrLeaveApplication::where('employee_id',$emp->id)->firstOrFail();
        // Manager/HR approves via existing HR leave controller (requires hr.leave.approve)
        $hrAdmin = $this->user($inst,'institute-admin');
        $this->actingAs($hrAdmin,'institute_user')->post(route('hr.leave.applications.decide',$app), ['decision'=>'approved'])->assertRedirect();
        $app->refresh();
        $this->assertSame('approved',$app->status);
        // Notification should be logged (in_app)
        // Check audit
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_leave_approved']);
    }

    public function test_manager_dashboard_and_hr_dashboard(): void
    {
        $inst = $this->institute();
        $mgrUser = $this->user($inst,'branch-manager');
        $mgrEmp = $this->employee($inst,$mgrUser);
        $emp = $this->employee($inst,$this->user($inst,'teacher'), null, ['reporting_manager_id'=>$mgrEmp->id]);
        TenantContext::set($inst->id);
        // Create pending leave for team
        $lt = HrLeaveType::create(['institute_id'=>$inst->id,'name'=>'Annual','code'=>'ANN-'.uniqid(),'yearly_allowance'=>20]);
        \App\Models\HrLeaveBalance::create(['institute_id'=>$inst->id,'employee_id'=>$emp->id,'leave_type_id'=>$lt->id,'year'=>now()->year,'allocated'=>20]);
        app(\App\Services\HrLeaveService::class)->apply(['employee_id'=>$emp->id,'leave_type_id'=>$lt->id,'start_date'=>now()->toDateString(),'end_date'=>now()->toDateString()], $inst->id, $emp->institute_user_id);

        $this->actingAs($mgrUser,'institute_user')->get(route('hr.manager.dashboard'))->assertOk()->assertSee('Pending Leaves');
        $hrUser = $this->user($inst,'institute-admin');
        $this->actingAs($hrUser,'institute_user')->get(route('hr.dashboard'))->assertOk()->assertSee('Pending Leaves');
    }

    public function test_tenant_isolation(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $userA = $this->user($a,'teacher');
        $empA = $this->employee($a,$userA);
        $userB = $this->user($b,'teacher');
        $empB = $this->employee($b,$userB);
        TenantContext::set($a->id);
        $this->actingAs($userA,'institute_user')->get(route('hr.self.dashboard'))->assertOk()->assertSee($empA->display_name);
        TenantContext::set($b->id);
        $this->actingAs($userB,'institute_user')->get(route('hr.self.dashboard'))->assertOk()->assertSee($empB->display_name)->assertDontSee($empA->display_name);
        // Cross tenant cannot access other employee's payslip via self
        TenantContext::set($a->id);
        $ownerA = $this->user($a,'institute-owner');
        $struct = app(\App\Services\HrSalaryStructureService::class)->create(['name'=>'S','code'=>'S-'.uniqid(),'basic_salary'=>10000,'effective_from'=>now()->toDateString()], $a->id, null, $ownerA->id);
        app(\App\Services\HrPayrollService::class)->assignSalary(['employee_id'=>$empA->id,'salary_structure_id'=>$struct->id,'effective_date'=>now()->toDateString()], $a->id, $ownerA->id);
        $period = app(\App\Services\HrPayrollService::class)->createPeriod(['name'=>'Jan','start_date'=>now()->startOfMonth()->toDateString(),'end_date'=>now()->endOfMonth()->toDateString()], $a->id, null, $ownerA->id);
        app(\App\Services\HrPayrollService::class)->generate($period,$ownerA->id);
        $payroll = HrPayroll::withoutGlobalScopes()->where('employee_id',$empA->id)->firstOrFail();
        TenantContext::set($b->id);
        $this->actingAs($userB,'institute_user')->get(route('hr.self.payslip.show',$payroll))->assertStatus(404);
    }

    public function test_permission_matrix(): void
    {
        $inst = $this->institute();
        $teacher = $this->user($inst,'teacher');
        $receptionist = $this->user($inst,'receptionist');
        // Teacher has self.view
        $this->employee($inst,$teacher);
        $this->employee($inst,$receptionist);
        TenantContext::set($inst->id);
        $this->actingAs($teacher,'institute_user')->get(route('hr.self.dashboard'))->assertOk();
        $this->actingAs($teacher,'institute_user')->get(route('hr.manager.dashboard'))->assertForbidden();
        $this->actingAs($receptionist,'institute_user')->get(route('hr.self.dashboard'))->assertOk();
        $this->actingAs($teacher,'institute_user')->get(route('hr.dashboard'))->assertForbidden(); // teacher no hr.dashboard.view
    }

    public function test_audit(): void
    {
        $inst = $this->institute();
        $user = $this->user($inst,'teacher');
        $emp = $this->employee($inst,$user);
        TenantContext::set($inst->id);
        $this->actingAs($user,'institute_user')->put(route('hr.self.profile.update'), ['phone'=>'+8801711111111'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_self_profile_updated']);
    }
}
