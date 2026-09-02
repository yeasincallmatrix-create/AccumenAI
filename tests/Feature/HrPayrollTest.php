<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\HrEmployeeSalaryAssignment;
use App\Models\HrLeaveApplication;
use App\Models\HrLeaveType;
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
 * HR-5 — Payroll Core
 */
class HrPayrollTest extends TestCase
{
    use DatabaseTransactions;

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

    private function currency(): Currency
    {
        return Currency::firstOrCreate(['code' => 'BDT'], ['name' => 'Taka', 'symbol' => '৳', 'is_base' => true, 'is_active' => true]);
    }

    private function institute(?Country $c = null): Institute
    {
        $c ??= $this->country();
        $this->currency();
        $inst = Institute::create(['name' => 'Payroll Inst '.uniqid(), 'slug' => 'payroll-'.uniqid(), 'country' => $c->name, 'country_id' => $c->id, 'status' => 'active']);
        $this->ensureFiscalYear($inst);
        return $inst;
    }

    private function ensureFiscalYear(Institute $institute, ?int $branchId = null): FiscalYear
    {
        $existing = FiscalYear::where('institute_id', $institute->id)->where('branch_id', $branchId)->where('is_current', true)->first();
        if ($existing) return $existing;
        // Try find any open covering now
        $now = now();
        $fy = FiscalYear::where('institute_id', $institute->id)->where('status','open')->whereDate('start_date','<=',$now)->whereDate('end_date','>=',$now)->first();
        if ($fy) return $fy;
        $year = $now->year;
        $fy = FiscalYear::create([
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'name' => 'FY '.$year,
            'start_date' => $year.'-01-01',
            'end_date' => $year.'-12-31',
            'status' => 'open',
            'is_current' => true,
        ]);
        // Create monthly periods so journal posting succeeds
        try {
            app(AccountingPeriodService::class)->createMonthlyPeriods($fy);
        } catch (\Throwable $e) {}
        return $fy;
    }

    private function branch(Institute $i, string $name = 'Branch'): Branch
    {
        return Branch::create(['institute_id' => $i->id, 'name' => $name.' '.uniqid(), 'status' => 'active']);
    }

    private function user(Institute $i, string $role, ?int $branchId = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $i->id,
            'role_id' => Role::where('slug',$role)->firstOrFail()->id,
            'branch_id' => $branchId,
            'first_name' => ucfirst($role),
            'last_name' => 'User',
            'email' => $role.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000,999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    private function employee(Institute $i, ?int $branchId = null, ?int $actorId = null): HrEmployee
    {
        $svc = app(\App\Services\HrEmployeeService::class);
        return $svc->create(['first_name'=>'Emp','last_name'=>'Test '.uniqid(),'employment_status'=>'active'], $i->id, $branchId, $actorId ?? $this->user($i,'institute-owner')->id);
    }

    private function structure(Institute $i, array $overrides = [], ?int $branchId = null, ?int $actorId = null): HrSalaryStructure
    {
        $svc = app(\App\Services\HrSalaryStructureService::class);
        $data = array_merge([
            'name' => 'Structure '.uniqid(),
            'code' => 'STR-'.uniqid(),
            'basic_salary' => 50000,
            'housing_allowance' => 10000,
            'medical_allowance' => 5000,
            'transport_allowance' => 3000,
            'other_allowance' => 2000,
            'overtime_rate' => 200,
            'bonus_amount' => 0,
            'commission_amount' => 0,
            'deduction_amount' => 1000,
            'tax_deduction' => 2000,
            'effective_from' => now()->subMonth()->toDateString(),
        ], $overrides);
        return $svc->create($data, $i->id, $branchId, $actorId ?? $this->user($i,'institute-owner')->id);
    }

    // ------------------------------------------------ Salary Structure

    public function test_salary_structure_crud_and_validation(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.salary-structures.store'), [
            'name' => 'Grade A',
            'code' => 'GRADE-A',
            'basic_salary' => 40000,
            'effective_from' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('hr_salary_structures',['institute_id'=>$inst->id,'code'=>'GRADE-A']);
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_salary_structure_created']);

        // Duplicate code rejected
        $this->actingAs($owner,'institute_user')->post(route('hr.salary-structures.store'), [
            'name' => 'Duplicate',
            'code' => 'GRADE-A',
            'basic_salary' => 30000,
            'effective_from' => now()->toDateString(),
        ])->assertSessionHasErrors();

        // Negative salary rejected
        $this->actingAs($owner,'institute_user')->post(route('hr.salary-structures.store'), [
            'name' => 'Neg',
            'code' => 'NEG',
            'basic_salary' => -100,
            'effective_from' => now()->toDateString(),
        ])->assertSessionHasErrors();
    }

    public function test_salary_assignment_and_history(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $emp = $this->employee($inst, null, $owner->id);
        $struct = $this->structure($inst, [], null, $owner->id);
        TenantContext::set($inst->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.employees.salary-assign',$emp), [
            'salary_structure_id' => $struct->id,
            'effective_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('hr_employee_salary_assignments',['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id]);

        // Second assignment closes previous
        $this->actingAs($owner,'institute_user')->post(route('hr.employees.salary-assign',$emp), [
            'salary_structure_id' => $struct->id,
            'effective_date' => now()->addMonth()->toDateString(),
            'basic_salary' => 60000,
        ])->assertRedirect();

        $assignments = HrEmployeeSalaryAssignment::where('employee_id',$emp->id)->orderBy('effective_date')->get();
        $this->assertCount(2,$assignments);
        $this->assertFalse((bool)$assignments->first()->is_active);
    }

    // ------------------------------------------------ Payroll Period & Calculation

    public function test_payroll_period_creation_and_duplicate_prevention(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.store'), [
            'name' => 'Jan 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ])->assertRedirect();

        // Duplicate overlapping period rejected
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.store'), [
            'name' => 'Jan Duplicate',
            'start_date' => '2026-01-15',
            'end_date' => '2026-02-15',
        ])->assertSessionHasErrors();

        $this->assertDatabaseHas('hr_payroll_periods',['institute_id'=>$inst->id,'name'=>'Jan 2026']);
    }

    public function test_payroll_generation_and_calculation(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $emp = $this->employee($inst, null, $owner->id);
        $struct = $this->structure($inst, ['basic_salary'=>50000,'housing_allowance'=>10000,'medical_allowance'=>5000,'transport_allowance'=>3000,'other_allowance'=>2000,'deduction_amount'=>2000,'tax_deduction'=>3000], null, $owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=> '2025-12-01'], $inst->id, $owner->id);

        $period = app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'], $inst->id, null, $owner->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.generate',$period))->assertRedirect();

        $payroll = HrPayroll::where('employee_id',$emp->id)->where('payroll_period_id',$period->id)->firstOrFail();
        // Gross = 50000+10000+5000+3000+2000=70000, deductions 5000, net 65000
        $this->assertEquals(70000, (float)$payroll->gross_earnings);
        $this->assertEquals(5000, (float)$payroll->total_deductions);
        $this->assertEquals(65000, (float)$payroll->net_salary);
        $this->assertSame('draft',$payroll->status);
        $this->assertNotNull($payroll->calculation_snapshot);

        // Duplicate generate without recalc should not create duplicate
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.generate',$period))->assertRedirect();
        $this->assertEquals(1, HrPayroll::where('payroll_period_id',$period->id)->count());
    }

    public function test_attendance_leave_integration(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $emp = $this->employee($inst, null, $owner->id);
        $struct = $this->structure($inst, ['basic_salary'=>30000,'housing_allowance'=>5000,'other_allowance'=>0,'deduction_amount'=>0,'tax_deduction'=>0,'overtime_rate'=>120], null, $owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);

        // Create attendance: 2 present, 1 absent, 1 half_day with overtime
        HrAttendance::create(['institute_id'=>$inst->id,'employee_id'=>$emp->id,'attendance_date'=>'2026-01-02','status'=>'present','overtime_minutes'=>60]);
        HrAttendance::create(['institute_id'=>$inst->id,'employee_id'=>$emp->id,'attendance_date'=>'2026-01-03','status'=>'present']);
        HrAttendance::create(['institute_id'=>$inst->id,'employee_id'=>$emp->id,'attendance_date'=>'2026-01-04','status'=>'absent']);
        HrAttendance::create(['institute_id'=>$inst->id,'employee_id'=>$emp->id,'attendance_date'=>'2026-01-05','status'=>'half_day','overtime_minutes'=>30]);

        // Approved leave
        $lt = HrLeaveType::create(['institute_id'=>$inst->id,'name'=>'Annual','code'=>'ANNUAL','yearly_allowance'=>20]);
        HrLeaveApplication::create(['institute_id'=>$inst->id,'employee_id'=>$emp->id,'leave_type_id'=>$lt->id,'start_date'=>'2026-01-10','end_date'=>'2026-01-11','days_count'=>2,'status'=>'approved','applied_by'=>$owner->id]);

        $period = app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        $payroll = HrPayroll::where('employee_id',$emp->id)->firstOrFail();

        // present_days should be 2 + 0.5 =2.5
        $this->assertEquals(2.5, (float)$payroll->present_days);
        // unpaid 1 (absent)
        $this->assertEquals(1, (float)$payroll->unpaid_leave_days);
        // overtime 90 mins => 1.5 *120=180
        $this->assertEquals(90, $payroll->overtime_minutes);
        $this->assertEquals(180, (float)$payroll->overtime_amount);
        // working days 31
        $this->assertEquals(31, $payroll->working_days);

        // Missing attendance should NOT be counted as absent
        // We have only 4 attendance rows, but unpaid is only 1, not 27
        $this->assertLessThan(5, (float)$payroll->unpaid_leave_days);
    }

    public function test_adjustments(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $emp = $this->employee($inst,null,$owner->id);
        $struct = $this->structure($inst,[],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period = app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        $payroll = HrPayroll::where('employee_id',$emp->id)->firstOrFail();
        $netBefore = (float)$payroll->net_salary;

        // Add bonus adjustment before approval
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.adjustments.store'), [
            'employee_id' => $emp->id,
            'payroll_id' => $payroll->id,
            'adjustment_type' => 'bonus',
            'amount' => 5000,
            'reason' => 'Performance bonus for exceeding target',
        ])->assertRedirect();

        $payroll->refresh();
        $this->assertEquals($netBefore + 5000, (float)$payroll->net_salary);
        $this->assertDatabaseHas('hr_payroll_adjustments',['payroll_id'=>$payroll->id,'amount'=>5000]);
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_payroll_adjustment_created']);

        // Missing reason rejected
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.adjustments.store'), [
            'employee_id' => $emp->id,
            'payroll_id' => $payroll->id,
            'adjustment_type' => 'deduction',
            'amount' => 1000,
            'reason' => '',
        ])->assertSessionHasErrors();
    }

    public function test_duplicate_prevention_payroll(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $emp = $this->employee($inst,null,$owner->id);
        $struct = $this->structure($inst,[],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period = app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        $svc = app(HrPayrollService::class);
        $svc->generate($period,$owner->id);
        // Second generate should not duplicate
        $svc->generate($period,$owner->id);
        $this->assertEquals(1, HrPayroll::where('payroll_period_id',$period->id)->where('employee_id',$emp->id)->count());

        // Direct DB duplicate attempt should fail unique constraint
        $this->expectException(\Illuminate\Database\QueryException::class);
        HrPayroll::create([
            'institute_id'=>$inst->id,
            'payroll_period_id'=>$period->id,
            'employee_id'=>$emp->id,
            'payslip_no'=>'PSL-'.uniqid(),
            'status'=>'draft',
            'gross_earnings'=>0,'total_deductions'=>0,'net_salary'=>0,
            'working_days'=>31,
        ]);
    }

    public function test_approval_finalization_and_journals(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $emp = $this->employee($inst,null,$owner->id);
        $struct = $this->structure($inst,['basic_salary'=>40000,'housing_allowance'=>10000,'deduction_amount'=>2000,'tax_deduction'=>1000],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period = app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.approve',$period))->assertRedirect();
        $period->refresh();
        $this->assertSame('approved',$period->status);
        $payroll = HrPayroll::where('payroll_period_id',$period->id)->firstOrFail();
        $this->assertSame('approved',$payroll->status);
        $this->assertNotNull($payroll->journal_id);
        $journal = Journal::find($payroll->journal_id);
        $this->assertNotNull($journal);
        $this->assertSame('posted',$journal->status);
        // Journal balanced
        $debit = $journal->entries->sum('debit');
        $credit = $journal->entries->sum('credit');
        $this->assertEquals($debit,$credit);
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_payroll_approved']);
        $this->assertDatabaseHas('accounting_audit_trails',['entity_type'=>'journal','action'=>'create']);

        // No duplicate journal on second approve
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.approve',$period))->assertSessionHasErrors();

        // Pay
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.periods.pay',$period))->assertRedirect();
        $payroll->refresh();
        $this->assertSame('paid',$payroll->status);
        $this->assertNotNull($payroll->payment_journal_id);
        $payJournal = Journal::find($payroll->payment_journal_id);
        $this->assertSame('posted',$payJournal->status);
    }

    public function test_payslip_and_snapshot(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $emp = $this->employee($inst,null,$owner->id);
        $struct = $this->structure($inst,['basic_salary'=>30000],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period = app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        $payroll = HrPayroll::firstOrFail();
        $snapshot = $payroll->calculation_snapshot;
        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('assignment',$snapshot);

        // Change structure after generation should NOT affect payslip
        $struct->update(['basic_salary'=>99999]);
        $payroll->refresh();
        $this->assertEquals(30000, $payroll->calculation_snapshot['assignment']['basic_salary']);
        $this->assertEquals($snapshot['assignment']['basic_salary'], $payroll->calculation_snapshot['assignment']['basic_salary']);

        // Payslip view contains data
        $this->actingAs($owner,'institute_user')->get(route('hr.payroll.payslip',$payroll))->assertOk()->assertSee($payroll->payslip_no)->assertSee($emp->display_name)->assertSee($inst->name);

        // After approval, payslip snapshot preserved
        app(HrPayrollService::class)->approve($period,$owner->id);
        $payroll->refresh();
        $this->assertEquals($snapshot['assignment']['basic_salary'], $payroll->calculation_snapshot['assignment']['basic_salary']);
        // Attempt to adjust finalized payroll should fail
        $this->actingAs($owner,'institute_user')->post(route('hr.payroll.adjustments.store'), [
            'employee_id'=>$emp->id,'payroll_id'=>$payroll->id,'adjustment_type'=>'bonus','amount'=>1000,'reason'=>'Late bonus',
        ])->assertSessionHasErrors();
    }

    public function test_tenant_and_branch_isolation(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $ownerA = $this->user($a,'institute-owner');
        $ownerB = $this->user($b,'institute-owner');
        $branchA = $this->branch($a,'Branch A');
        $branchB = $this->branch($a,'Branch B');
        $mgrA = $this->user($a,'branch-manager',$branchA->id);
        $mgrB = $this->user($a,'branch-manager',$branchB->id);
        $empA = $this->employee($a,$branchA->id,$ownerA->id);
        $empB = $this->employee($a,$branchB->id,$ownerA->id);
        $struct = $this->structure($a,[],null,$ownerA->id);
        TenantContext::set($a->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$empA->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$a->id,$ownerA->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$empB->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$a->id,$ownerA->id);
        $period = app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$a->id,$branchA->id,$ownerA->id);
        app(HrPayrollService::class)->generate($period,$ownerA->id);
        $payroll = HrPayroll::withoutGlobalScopes()->where('employee_id',$empA->id)->where('payroll_period_id',$period->id)->firstOrFail();

        // Cross tenant cannot see
        TenantContext::set($b->id);
        $this->actingAs($ownerB,'institute_user')->get(route('hr.payroll.periods.show',$period))->assertNotFound();
        $this->actingAs($ownerB,'institute_user')->get(route('hr.payroll.payslip',$payroll))->assertNotFound();

        // Branch isolation: mgrB cannot see branch A period
        TenantContext::set($a->id);
        BranchContext::set($branchB->id);
        $this->actingAs($mgrB,'institute_user')->get(route('hr.payroll.periods.show',$period))->assertNotFound();
        BranchContext::set($branchA->id);
        $this->actingAs($mgrA,'institute_user')->get(route('hr.payroll.periods.show',$period))->assertOk();
        BranchContext::clear();
        // Owner sees all
        $this->actingAs($ownerA,'institute_user')->get(route('hr.payroll.periods.show',$period))->assertOk();
    }

    public function test_permission_matrix(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $receptionist = $this->user($inst,'receptionist');
        $teacher = $this->user($inst,'teacher');
        TenantContext::set($inst->id);

        $this->actingAs($receptionist,'institute_user')->get(route('hr.salary-structures.index'))->assertForbidden();
        $this->actingAs($receptionist,'institute_user')->post(route('hr.salary-structures.store'), ['name'=>'X','code'=>'X','basic_salary'=>1000,'effective_from'=>now()->toDateString()])->assertForbidden();
        $this->actingAs($receptionist,'institute_user')->get(route('hr.payroll.periods.index'))->assertForbidden();
        $this->actingAs($teacher,'institute_user')->get(route('hr.salary-structures.index'))->assertForbidden();
        // Teacher can view own payslip if linked
        $emp = $this->employee($inst,null,$owner->id);
        $emp->update(['institute_user_id'=>$teacher->id]);
        $struct = $this->structure($inst,[],null,$owner->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period = app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        $payroll = HrPayroll::where('employee_id',$emp->id)->firstOrFail();
        $this->actingAs($teacher,'institute_user')->get(route('hr.payroll.payslip',$payroll))->assertOk();
        // Teacher cannot see other employee payslip
        $emp2 = $this->employee($inst,null,$owner->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp2->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);
        $period2 = app(HrPayrollService::class)->createPeriod(['name'=>'Feb 2026','start_date'=>'2026-02-01','end_date'=>'2026-02-28'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period2,$owner->id);
        $payroll2 = HrPayroll::where('employee_id',$emp2->id)->where('payroll_period_id',$period2->id)->firstOrFail();
        $this->actingAs($teacher,'institute_user')->get(route('hr.payroll.payslip',$payroll2))->assertForbidden();
    }

    public function test_audit_and_transaction_rollback(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $emp = $this->employee($inst,null,$owner->id);
        $struct = $this->structure($inst,[],null,$owner->id);
        TenantContext::set($inst->id);
        app(HrPayrollService::class)->assignSalary(['employee_id'=>$emp->id,'salary_structure_id'=>$struct->id,'effective_date'=>'2025-12-01'],$inst->id,$owner->id);

        // Create period and generate
        $period = app(HrPayrollService::class)->createPeriod(['name'=>'Jan 2026','start_date'=>'2026-01-01','end_date'=>'2026-01-31'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period,$owner->id);
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_payroll_generated']);

        // Approve should create journal transactionally; if fiscal year closed, should rollback and not mark approved
        // Close fiscal year to cause failure
        $fy = FiscalYear::where('institute_id',$inst->id)->where('is_current',true)->firstOrFail();
        $fy->update(['status'=>'closed']);
        $period2 = app(HrPayrollService::class)->createPeriod(['name'=>'Feb 2026','start_date'=>'2026-02-01','end_date'=>'2026-02-28'],$inst->id,null,$owner->id);
        app(HrPayrollService::class)->generate($period2,$owner->id);
        try {
            app(HrPayrollService::class)->approve($period2,$owner->id);
            $this->fail('Should have thrown due to closed fiscal year');
        } catch (\Exception $e) {
            $period2->refresh();
            $this->assertSame('processing',$period2->status); // not approved, rolled back
            $payroll2 = HrPayroll::where('payroll_period_id',$period2->id)->firstOrFail();
            $this->assertSame('draft',$payroll2->status);
            $this->assertNull($payroll2->journal_id);
        }
        // Reopen for cleanup
        $fy->update(['status'=>'open']);

        // Reports
        $this->actingAs($owner,'institute_user')->get(route('hr.payroll.reports'))->assertOk()->assertSee('Payroll Reports');
        $this->actingAs($owner,'institute_user')->get(route('hr.payroll.register'))->assertOk();
    }
}
