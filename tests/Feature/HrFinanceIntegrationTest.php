<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\HrEmployee;
use App\Models\HrPayroll;
use App\Models\HrPayrollPeriod;
use App\Models\HrSalaryStructure;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Journal;
use App\Models\Role;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\HrPayrollService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * HR-6 — HR ↔ Finance & Accounting Integration.
 */
class HrFinanceIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function country(): Country { return Country::firstOrCreate(['iso2'=>'BD'],['name'=>'Bangladesh','iso3'=>'BGD','phone_code'=>'880','status'=>true]); }
    private function currency(): Currency { return Currency::firstOrCreate(['code'=>'BDT'],['name'=>'Taka','symbol'=>'৳','is_base'=>true,'is_active'=>true]); }
    private function institute(?Country $c=null): Institute { $c??=$this->country(); $this->currency(); $inst=Institute::create(['name'=>'HR6 Inst '.uniqid(),'slug'=>'hr6-'.uniqid(),'country'=>$c->name,'country_id'=>$c->id,'status'=>'active']); $this->ensureFiscalYear($inst); return $inst; }
    private function ensureFiscalYear(Institute $inst, ?int $branchId=null): FiscalYear {
        $existing=FiscalYear::where('institute_id',$inst->id)->where('branch_id',$branchId)->where('is_current',true)->first();
        if($existing) return $existing;
        $fy=FiscalYear::where('institute_id',$inst->id)->where('status','open')->whereDate('start_date','<=',now())->whereDate('end_date','>=',now())->first();
        if($fy) return $fy;
        $year=now()->year;
        $fy=FiscalYear::create(['institute_id'=>$inst->id,'branch_id'=>$branchId,'name'=>'FY '.$year,'start_date'=>$year.'-01-01','end_date'=>$year.'-12-31','status'=>'open','is_current'=>true]);
        try{ app(AccountingPeriodService::class)->createMonthlyPeriods($fy); }catch(\Throwable $e){}
        return $fy;
    }
    private function branch(Institute $i,string $n='Branch'): Branch { return Branch::create(['institute_id'=>$i->id,'name'=>$n.' '.uniqid(),'status'=>'active']); }
    private function user(Institute $i,string $role,?int $branchId=null): InstituteUser {
        return InstituteUser::create(['institute_id'=>$i->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'branch_id'=>$branchId,'first_name'=>ucfirst($role),'last_name'=>'User','email'=>$role.'-'.uniqid().'@example.test','phone'=>'01700'.rand(100000,999999),'password_hash'=>bcrypt('secret12345'),'status'=>'active']);
    }
    private function employee(Institute $i,?int $branchId=null,?int $actorId=null): HrEmployee {
        $svc=app(\App\Services\HrEmployeeService::class);
        return $svc->create(['first_name'=>'Emp','last_name'=>'Test '.uniqid(),'employment_status'=>'active'], $i->id, $branchId, $actorId ?? $this->user($i,'institute-owner')->id);
    }
    private function structure(Institute $i,array $over=[],?int $branchId=null,?int $actorId=null): HrSalaryStructure {
        $svc=app(\App\Services\HrSalaryStructureService::class);
        $data=array_merge(['name'=>'Struct '.uniqid(),'code'=>'STR-'.uniqid(),'basic_salary'=>40000,'housing_allowance'=>10000,'medical_allowance'=>5000,'transport_allowance'=>3000,'other_allowance'=>2000,'deduction_amount'=>2000,'tax_deduction'=>1000,'effective_from'=>now()->subMonth()->toDateString()],$over);
        return $svc->create($data,$i->id,$branchId,$actorId ?? $this->user($i,'institute-owner')->id);
    }

    public function test_payroll_journal_creation_with_allowances_and_deductions(): void
    {
        $inst=$this->institute(); $owner=$this->user($inst,'institute-owner'); $emp=$this->employee($inst,null,$owner->id);
        $struct=$this->structure($inst,['basic_salary'=>50000,'housing_allowance'=>10000,'medical_allowance'=>5000,'transport_allowance'=>3000,'other_allowance'=>2000,'deduction_amount'=>1500,'tax_deduction'=>2500],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period=app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.approve',$period))->assertRedirect();
        $payroll=HrPayroll::where('employee_id',$emp->id)->firstOrFail();
        $this->assertNotNull($payroll->journal_id);
        $journal=Journal::find($payroll->journal_id);
        $this->assertSame('posted',$journal->status);
        $this->assertSame('payroll',$journal->ref_type);
        // Check entries: salary expense 70000 debit, payable net 66000? net =70000-4000=66000
        $debit=$journal->entries->sum('debit'); $credit=$journal->entries->sum('credit');
        $this->assertEquals($debit,$credit);
        $this->assertTrue($journal->entries->where('debit','>',0)->count() >=1);
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_payroll_approved']);
        $this->assertDatabaseHas('accounting_audit_trails',['entity_type'=>'journal','action'=>'create']);
    }

    public function test_duplicate_post_prevention_idempotent(): void
    {
        $inst=$this->institute(); $owner=$this->user($inst,'institute-owner'); $emp=$this->employee($inst,null,$owner->id);
        $struct=$this->structure($inst,[],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period=app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.approve',$period))->assertRedirect();
        $payroll=HrPayroll::where('employee_id',$emp->id)->firstOrFail(); $jid=$payroll->journal_id;
        // Second approve should not create duplicate journal (no draft payrolls)
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.approve',$period))->assertSessionHasErrors();
        $payroll->refresh(); $this->assertEquals($jid,$payroll->journal_id);
        $this->assertEquals(1, Journal::where('ref_type','payroll')->where('ref_id',$payroll->id)->count());
    }

    public function test_payroll_payment_reduces_payable_and_records_cash(): void
    {
        $inst=$this->institute(); $owner=$this->user($inst,'institute-owner'); $emp=$this->employee($inst,null,$owner->id);
        $struct=$this->structure($inst,['basic_salary'=>30000,'housing_allowance'=>5000,'deduction_amount'=>1000,'tax_deduction'=>500],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period=app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.approve',$period))->assertRedirect();
        $payroll=HrPayroll::where('employee_id',$emp->id)->firstOrFail();
        $net=(float)$payroll->net_salary;
        // Before payment, payable balance = net (credit), paid 0
        $recBefore=app(\App\Services\HrPayrollFinanceService::class)->reconciliation($inst->id,null,$period->id);
        $this->assertEquals($net, $recBefore['salary_payable']);
        $this->assertEquals(0, $recBefore['paid_amount']);
        $this->assertEquals($net, $recBefore['outstanding_salary']);

        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.pay',$period))->assertRedirect();
        $payroll->refresh(); $this->assertSame('paid',$payroll->status); $this->assertNotNull($payroll->payment_journal_id);
        $payJournal=Journal::find($payroll->payment_journal_id);
        $this->assertSame('posted',$payJournal->status);
        $this->assertEquals($payJournal->entries->sum('debit'), $payJournal->entries->sum('credit'));
        // Payable entry debit = net (reduces liability)
        $recAfter=app(\App\Services\HrPayrollFinanceService::class)->reconciliation($inst->id,null,$period->id);
        $this->assertEquals($net, $recAfter['paid_amount']);
        $this->assertEquals(0, $recAfter['outstanding_salary']);
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_payroll_paid']);
    }

    public function test_closed_period_rejection(): void
    {
        $inst=$this->institute(); $owner=$this->user($inst,'institute-owner'); $emp=$this->employee($inst,null,$owner->id);
        $struct=$this->structure($inst,[],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period=app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        // Close accounting period covering end_date
        $fy=$this->ensureFiscalYear($inst);
        $ap=AccountingPeriod::where('institute_id',$inst->id)->where('fiscal_year_id',$fy->id)->whereDate('start_date','<=',$period->end_date)->whereDate('end_date','>=',$period->end_date)->first();
        if($ap){ $ap->update(['status'=>'closed','closed_at'=>now()]); }
        try {
            app(HrPayrollService::class)->approve($period,$owner->id);
            $this->fail('Should have thrown closed period');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $period->refresh(); $this->assertSame('processing',$period->status);
            $this->assertStringContainsString('closed', json_encode($e->errors()));
        }
        // Reopen for cleanup
        if(isset($ap) && $ap){ $ap->update(['status'=>'open','closed_at'=>null]); }
    }

    public function test_reversal_creates_traceable_history(): void
    {
        $inst=$this->institute(); $owner=$this->user($inst,'institute-owner'); $emp=$this->employee($inst,null,$owner->id);
        $struct=$this->structure($inst,[],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period=app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.approve',$period))->assertRedirect();
        $payroll=HrPayroll::where('employee_id',$emp->id)->firstOrFail(); $jid=$payroll->journal_id;
        $journal=Journal::find($jid); $this->assertSame('posted',$journal->status);

        // Cancel period (should reverse)
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.cancel',$period),['reason'=>'Test reversal'])->assertRedirect();
        $period->refresh(); $this->assertSame('cancelled',$period->status);
        $payroll->refresh(); $this->assertSame('cancelled',$payroll->status);
        $journal->refresh(); $this->assertSame('reversed',$journal->status);
        // Reversal journal exists
        $rev=Journal::where('reversal_of',$journal->id)->firstOrFail();
        $this->assertSame('posted',$rev->status);
        // Historical accounting preserved: original journal still exists, not deleted
        $this->assertDatabaseHas('journals',['id'=>$jid]);
        $this->assertDatabaseHas('journals',['reversal_of'=>$jid]);
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_payroll_period_cancelled']);
    }

    public function test_branch_and_institute_accounting(): void
    {
        $inst=$this->institute(); $b1=$this->branch($inst,'B1'); $b2=$this->branch($inst,'B2');
        $owner=$this->user($inst,'institute-owner'); $emp1=$this->employee($inst,$b1->id,$owner->id); $emp2=$this->employee($inst,$b2->id,$owner->id);
        $struct=$this->structure($inst,['basic_salary'=>20000],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp1->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp2->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $p1=app(HrPayrollService::class)->createPeriod(['name'=>'Jan B1','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,$b1->id,$owner->id);
        app(HrPayrollService::class)->generate($p1,$owner->id);
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.approve',$p1))->assertRedirect();
        $pay1=HrPayroll::where('employee_id',$emp1->id)->firstOrFail(); $this->assertEquals($b1->id,$pay1->branch_id);
        $this->assertNotNull($pay1->journal->branch_id); $this->assertEquals($b1->id,$pay1->journal->branch_id);
        // Institute-wide period
        $p2=app(HrPayrollService::class)->createPeriod(['name'=>'Feb All','start_date'=>'2026-02-01','end_date'=>'2026-02-28'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($p2,$owner->id);
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.approve',$p2))->assertRedirect();
        // Consolidated report
        $rep=app(HrPayrollService::class)->reports($inst->id,null);
        $this->assertArrayHasKey('by_branch',$rep);
        $this->assertArrayHasKey((string)$b1->id,$rep['by_branch']);
    }

    public function test_reconciliation_totals(): void
    {
        $inst=$this->institute(); $owner=$this->user($inst,'institute-owner'); $emp=$this->employee($inst,null,$owner->id);
        $struct=$this->structure($inst,['basic_salary'=>30000,'housing_allowance'=>5000,'deduction_amount'=>1000],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period=app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.approve',$period))->assertRedirect();
        $rec=app(\App\Services\HrPayrollFinanceService::class)->reconciliation($inst->id,null,$period->id);
        $this->assertEquals($rec['payroll_total'], $rec['salary_payable']);
        $this->assertEquals($rec['gross_total'], $rec['journal_total']);
        $this->assertEquals('matched',$rec['finance_reconciliation_status']);
        $this->assertGreaterThan(0,$rec['payroll_total']);
        // After pay, outstanding 0
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.pay',$period))->assertRedirect();
        $rec2=app(\App\Services\HrPayrollFinanceService::class)->reconciliation($inst->id,null,$period->id);
        $this->assertEquals(0,$rec2['outstanding_salary']);
        $this->assertEquals($rec2['payroll_total'],$rec2['paid_amount']);
    }

    public function test_tenant_isolation(): void
    {
        $a=$this->institute(); $b=$this->institute(); $ownerA=$this->user($a,'institute-owner'); $ownerB=$this->user($b,'institute-owner');
        $empA=$this->employee($a,null,$ownerA->id); $structA=$this->structure($a,[],null,$ownerA->id);
        TenantContext::set($a->id); app(HrPayrollService::class)->assignSalary(['employee_id'=>$empA->id,'salary_structure_id'=>$structA->id,'effective_date'=>'2025-12-01'],$a->id,$ownerA->id);
        $periodA=app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$a->id,null,$ownerA->id);
        app(HrPayrollService::class)->generate($periodA,$ownerA->id);
        $this->actingAs($ownerA,'institute_user')->post(route('hr.payroll.periods.approve',$periodA))->assertRedirect();
        $payA=HrPayroll::where('employee_id',$empA->id)->firstOrFail();

        TenantContext::set($b->id);
        $this->actingAs($ownerB,'institute_user')->get(route('hr.payroll.periods.show',$periodA))->assertNotFound();
        $this->actingAs($ownerB,'institute_user')->get(route('hr.payroll.payslip',$payA))->assertNotFound();
        $this->actingAs($ownerB,'institute_user')->post(route('hr.payroll.periods.approve',$periodA))->assertNotFound();
        // B's reconciliation should be 0
        $recB=app(\App\Services\HrPayrollFinanceService::class)->reconciliation($b->id);
        $this->assertEquals(0,$recB['payroll_total']);
    }

    public function test_permission_enforcement(): void
    {
        $inst=$this->institute(); $owner=$this->user($inst,'institute-owner'); $receptionist=$this->user($inst,'receptionist'); $accountant=$this->user($inst,'accountant');
        $emp=$this->employee($inst,null,$owner->id); $struct=$this->structure($inst,[],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period=app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        // Receptionist cannot approve (needs hr.payroll.approve + journals.post)
        TenantContext::set($inst->id);
        $this->actingAs($receptionist,'institute_user')->post(route('hr.payroll.periods.approve',$period))->assertForbidden();
        $this->actingAs($receptionist,'institute_user')->post(route('hr.payroll.periods.pay',$period))->assertForbidden();
        // Accountant has finance but not hr payroll manage? Check grants: accountant has journals.post but not hr.payroll.approve? In HR payroll grants, accountant not listed, so accountant cannot approve. Test that.
        $this->actingAs($accountant,'institute_user')->post(route('hr.payroll.periods.approve',$period))->assertForbidden();
        // Owner can
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.approve',$period))->assertRedirect();
        // Salary protection: teacher cannot view others payroll
        $teacher=$this->user($inst,'teacher'); $empTeacher=$this->employee($inst,null,$owner->id); $empTeacher->update(['institute_user_id'=>$teacher->id]);
        $struct2=$this->structure($inst,[],null,$owner->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$empTeacher->id,'salary_structure_id'=>$struct2->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period2=app(HrPayrollService::class)->createPeriod(['name'=>'Feb 2026','start_date'=>'2026-02-01','end_date'=>'2026-02-28'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period2,$owner->id);
        $pay2=HrPayroll::where('employee_id',$empTeacher->id)->where('payroll_period_id',$period2->id)->firstOrFail();
        $this->actingAs($teacher,'institute_user')->get(route('hr.payroll.payslip',$pay2))->assertOk(); // own
        $otherEmp=$this->employee($inst,null,$owner->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$otherEmp->id,'salary_structure_id'=>$struct2->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period3=app(HrPayrollService::class)->createPeriod(['name'=>'Mar 2026','start_date'=>'2026-03-01','end_date'=>'2026-03-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period3,$owner->id);
        $pay3=HrPayroll::where('employee_id',$otherEmp->id)->where('payroll_period_id',$period3->id)->firstOrFail();
        $this->actingAs($teacher,'institute_user')->get(route('hr.payroll.payslip',$pay3))->assertForbidden();
    }

    public function test_audit_trail_and_transaction_rollback(): void
    {
        $inst=$this->institute(); $owner=$this->user($inst,'institute-owner'); $emp=$this->employee($inst,null,$owner->id);
        $struct=$this->structure($inst,[],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period=app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.approve',$period))->assertRedirect();
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_payroll_period_approved']);
        $this->assertDatabaseHas('accounting_audit_trails',['action'=>'post']);
        // Transaction rollback on closed period: try to approve another period with closed fiscal year
        $fy=FiscalYear::where('institute_id',$inst->id)->where('is_current',true)->firstOrFail(); $fy->update(['status'=>'closed']);
        $period2=app(HrPayrollService::class)->createPeriod(['name'=>'Feb 2026','start_date'=>'2026-02-01','end_date'=>'2026-02-28'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period2,$owner->id);
        try{ app(HrPayrollService::class)->approve($period2,$owner->id); $this->fail('Should throw'); }catch(\Illuminate\Validation\ValidationException $e){ $period2->refresh(); $this->assertSame('processing',$period2->status); }
        $fy->update(['status'=>'open']);
    }

    public function test_historical_safety_salary_change_after_finalization(): void
    {
        $inst=$this->institute(); $owner=$this->user($inst,'institute-owner'); $emp=$this->employee($inst,null,$owner->id);
        $struct=$this->structure($inst,['basic_salary'=>30000],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period=app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        $pay=HrPayroll::where('employee_id',$emp->id)->firstOrFail(); $snap=$pay->calculation_snapshot;
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.approve',$period))->assertRedirect();
        // Change salary after finalization
        $struct->update(['basic_salary'=>99999]);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2026-02-01','basic_salary'=>99999],$inst->id,$owner->id);
        $pay->refresh(); $this->assertEquals(30000, $pay->calculation_snapshot['assignment']['basic_salary']);
        $this->assertEquals($snap['assignment']['basic_salary'], $pay->calculation_snapshot['assignment']['basic_salary']);
        // Journal still reflects old amount
        $journal=Journal::find($pay->journal_id); $this->assertNotNull($journal);
    }
}
