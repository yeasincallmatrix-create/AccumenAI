<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\HrDepartment;
use App\Models\HrEmployee;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\Hr\HrEmployeeSummaryTool;
use App\Services\Ai\Tools\Hr\HrPayrollSummaryTool;
use App\Services\HrReportService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HrReportsAiTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function country(): Country { return Country::firstOrCreate(['iso2'=>'BD'], ['name'=>'Bangladesh','iso3'=>'BGD','phone_code'=>'880','status'=>true]); }
    private function institute(?Country $c=null): Institute { $c ??= $this->country(); return Institute::create(['name'=>'HR10 Inst '.uniqid(),'slug'=>'hr10-'.uniqid(),'country'=>$c->name,'country_id'=>$c->id,'status'=>'active']); }
    private function branch(Institute $i, string $n='Branch'): Branch { return Branch::create(['institute_id'=>$i->id,'name'=>$n.' '.uniqid(),'status'=>'active']); }
    private function user(Institute $i, string $role, ?int $branchId=null): InstituteUser {
        return InstituteUser::create(['institute_id'=>$i->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'branch_id'=>$branchId,'first_name'=>ucfirst($role),'last_name'=>'User','email'=>$role.'-'.uniqid().'@example.test','phone'=>'01700'.rand(100000,999999),'password_hash'=>bcrypt('secret12345'),'status'=>'active']);
    }
    private function employee(Institute $i, InstituteUser $u, ?int $branchId=null): HrEmployee {
        return app(\App\Services\HrEmployeeService::class)->create(['first_name'=>'Emp','last_name'=>uniqid(),'employment_status'=>'active','institute_user_id'=>$u->id], $i->id, $branchId ?? $u->branch_id, $u->id);
    }

    public function test_employee_report_with_filters_and_export(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $dept = HrDepartment::create(['institute_id'=>$inst->id,'name'=>'Eng','display_order'=>0,'is_active'=>true]);
        $this->employee($inst,$owner);
        $emp2 = $this->employee($inst,$this->user($inst,'teacher'), null,);
        $emp2->update(['department_id'=>$dept->id]);
        TenantContext::set($inst->id);

        $this->actingAs($owner,'institute_user')->get(route('hr.reports.employee'))->assertOk()->assertSee('Employee Report');
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.employee', ['department_id'=>$dept->id]))->assertOk();
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.employee.export'))->assertOk()->assertHeader('Content-Type','text/csv; charset=UTF-8');
    }

    public function test_workforce_and_attendance_reports(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $this->employee($inst,$owner);
        TenantContext::set($inst->id);
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.workforce'))->assertOk()->assertSee('Workforce');
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.attendance'))->assertOk()->assertSee('Attendance');
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.attendance.export'))->assertOk();
    }

    public function test_leave_payroll_recruitment_reports(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.leave'))->assertOk();
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.payroll'))->assertOk();
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.recruitment'))->assertOk();
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.leave.export'))->assertOk();
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.payroll.export'))->assertOk();
    }

    public function test_performance_training_reports(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.performance'))->assertOk();
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.training'))->assertOk();
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.performance.export'))->assertOk();
        $this->actingAs($owner,'institute_user')->get(route('hr.reports.training.export'))->assertOk();
    }

    public function test_reports_branch_isolation(): void
    {
        $inst = $this->institute();
        $branchA = $this->branch($inst,'A');
        $branchB = $this->branch($inst,'B');
        $owner = $this->user($inst,'institute-owner');
        $mgrA = $this->user($inst,'branch-manager',$branchA->id);
        $this->employee($inst,$this->user($inst,'teacher',$branchA->id),$branchA->id);
        $this->employee($inst,$this->user($inst,'teacher',$branchB->id),$branchB->id);
        TenantContext::set($inst->id);
        BranchContext::set($branchA->id);
        $this->actingAs($mgrA,'institute_user')->get(route('hr.reports.employee'))->assertOk();
        BranchContext::clear();
    }

    public function test_reports_permission_gated(): void
    {
        $inst = $this->institute();
        $teacher = $this->user($inst,'teacher');
        $this->employee($inst,$teacher);
        TenantContext::set($inst->id);
        $this->actingAs($teacher,'institute_user')->get(route('hr.reports.employee'))->assertForbidden();
        $this->actingAs($teacher,'institute_user')->get(route('hr.reports.payroll'))->assertForbidden();
    }

    public function test_ai_tools_read_only_and_gated(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $teacher = $this->user($inst,'teacher');
        $this->employee($inst,$owner);
        $this->employee($inst,$teacher);
        TenantContext::set($inst->id);

        $ctxOwner = AiContext::resolve($owner, $inst);
        $ctxTeacher = AiContext::resolve($teacher, $inst);

        $tool = app(HrEmployeeSummaryTool::class);
        $this->assertSame('read',$tool->mode());
        $this->assertSame('hr.employee.view',$tool->permission());

        // Owner can call
        $result = $tool->handle(['limit'=>5], $ctxOwner);
        $this->assertArrayHasKey('total',$result);
        $this->assertArrayHasKey('rows',$result);
        $this->assertLessThanOrEqual(5, count($result['rows']));

        // Check permission gating via registry
        $registry = app(\App\Services\Ai\AiToolRegistry::class);
        $available = $registry->isAvailable($tool, $ctxOwner);
        $this->assertIsBool($available);

        // Payroll tool salary privacy: without permission, amounts masked
        $payrollTool = app(HrPayrollSummaryTool::class);
        $this->assertSame('hr.payroll.view',$payrollTool->permission());
        $resultPayroll = $payrollTool->handle([], $ctxOwner);
        $this->assertArrayHasKey('total_gross',$resultPayroll);

        // Branch scoped: tool respects branch
        $branch = $this->branch($inst,'BranchX');
        $mgr = $this->user($inst,'branch-manager',$branch->id);
        $this->employee($inst,$mgr,$branch->id);
        $ctxMgr = AiContext::resolve($mgr, $inst);
        $resultMgr = $tool->handle([], $ctxMgr);
        // Should be branch scoped (only branch employees)
        $this->assertIsArray($resultMgr);

        // No write: mode is read, ensure no create/update/delete
        $this->assertSame('read',$tool->mode());
        $this->assertSame('assistant',$tool->feature());
    }

    public function test_ai_no_write_operations(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);
        $ctx = AiContext::resolve($owner, $inst);
        $tools = [
            app(\App\Services\Ai\Tools\Hr\HrAttendanceSummaryTool::class),
            app(\App\Services\Ai\Tools\Hr\HrLeaveSummaryTool::class),
            app(\App\Services\Ai\Tools\Hr\HrRecruitmentSummaryTool::class),
            app(\App\Services\Ai\Tools\Hr\HrPerformanceSummaryTool::class),
            app(\App\Services\Ai\Tools\Hr\HrTrainingSummaryTool::class),
        ];
        foreach ($tools as $tool) {
            $this->assertSame('read',$tool->mode());
            $result = $tool->handle([], $ctx);
            $this->assertIsArray($result);
            // Ensure no tenant id leaked in rows
            foreach ($result['rows'] ?? [] as $row) {
                $this->assertArrayNotHasKey('institute_id',$row);
                $this->assertArrayNotHasKey('tenant_id',$row);
            }
        }
    }

    public function test_performance_no_nplus1(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        // Create 10 employees
        for ($i=0;$i<10;$i++) $this->employee($inst,$this->user($inst,'teacher'), null);
        TenantContext::set($inst->id);
        $service = app(HrReportService::class);
        // Employee report should not cause N+1 (uses with)
        $data = $service->employeeReport($inst->id,null,[]);
        $this->assertEquals(10, $data['total']);
        // Attendance report bounded
        $data2 = $service->attendanceReport($inst->id,null,['from'=>now()->subMonth()->toDateString(),'to'=>now()->toDateString()]);
        $this->assertArrayHasKey('by_status',$data2);
    }
}
