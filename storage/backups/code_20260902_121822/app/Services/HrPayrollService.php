<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\HrEmployee;
use App\Models\HrEmployeeSalaryAssignment;
use App\Models\HrPayroll;
use App\Models\HrPayrollAdjustment;
use App\Models\HrPayrollItem;
use App\Models\HrPayrollPeriod;
use App\Models\HrSalaryStructure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrPayrollService
{
    public function __construct(
        private readonly HrPayrollCalculationService $calculator,
        private readonly HrPayrollFinanceService $finance,
        private readonly HrAuditService $audit,
    ) {}

    // ---------------- Salary Assignment

    public function assignSalary(array $data, int $instituteId, ?int $actorId): HrEmployeeSalaryAssignment
    {
        $employee = HrEmployee::where('institute_id', $instituteId)->where('id', $data['employee_id'])->firstOrFail();
        $this->assertBranchOwnership($data['branch_id'] ?? $employee->branch_id, $instituteId);

        if (! empty($data['salary_structure_id'])) {
            $structure = HrSalaryStructure::where('institute_id', $instituteId)->where('id', $data['salary_structure_id'])->firstOrFail();
            $data['currency_id'] = $data['currency_id'] ?? $structure->currency_id;
            $data['pay_frequency'] = $data['pay_frequency'] ?? $structure->pay_frequency;
            // copy defaults from structure if not overridden
            foreach (['basic_salary', 'housing_allowance', 'medical_allowance', 'transport_allowance', 'other_allowance', 'overtime_rate', 'bonus_amount', 'commission_amount', 'deduction_amount', 'tax_deduction'] as $field) {
                if (! array_key_exists($field, $data) || $data[$field] === null) {
                    $data[$field] = $structure->$field;
                }
            }
        }

        $currencyId = $data['currency_id'] ?? $this->defaultCurrencyId();
        $effectiveDate = $data['effective_date'];

        // Close previous active assignment
        DB::transaction(function () use ($employee, $instituteId, $effectiveDate) {
            HrEmployeeSalaryAssignment::where('institute_id', $instituteId)
                ->where('employee_id', $employee->id)
                ->where('is_active', true)
                ->where('effective_date', '<', $effectiveDate)
                ->update(['is_active' => false, 'effective_to' => $effectiveDate]);
        });

        $assignment = HrEmployeeSalaryAssignment::create([
            'institute_id' => $instituteId,
            'branch_id' => $data['branch_id'] ?? $employee->branch_id,
            'employee_id' => $employee->id,
            'salary_structure_id' => $data['salary_structure_id'] ?? null,
            'currency_id' => $currencyId,
            'pay_frequency' => $data['pay_frequency'] ?? 'monthly',
            'effective_date' => $effectiveDate,
            'basic_salary' => $data['basic_salary'] ?? 0,
            'housing_allowance' => $data['housing_allowance'] ?? 0,
            'medical_allowance' => $data['medical_allowance'] ?? 0,
            'transport_allowance' => $data['transport_allowance'] ?? 0,
            'other_allowance' => $data['other_allowance'] ?? 0,
            'overtime_rate' => $data['overtime_rate'] ?? 0,
            'bonus_amount' => $data['bonus_amount'] ?? 0,
            'commission_amount' => $data['commission_amount'] ?? 0,
            'deduction_amount' => $data['deduction_amount'] ?? 0,
            'tax_deduction' => $data['tax_deduction'] ?? 0,
            'is_active' => true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $this->audit->record($instituteId, $actorId, 'hr_salary_assigned', $assignment->id, null, ['employee_id' => $employee->id, 'effective_date' => $effectiveDate]);

        return $assignment;
    }

    public function currentAssignment(int $employeeId, int $instituteId): ?HrEmployeeSalaryAssignment
    {
        return HrEmployeeSalaryAssignment::where('institute_id', $instituteId)
            ->where('employee_id', $employeeId)
            ->where('is_active', true)
            ->orderByDesc('effective_date')
            ->first();
    }

    // ---------------- Payroll Period

    public function createPeriod(array $data, int $instituteId, ?int $branchId, ?int $actorId): HrPayrollPeriod
    {
        $this->assertBranchOwnership($branchId, $instituteId);
        $currencyId = $data['currency_id'] ?? $this->defaultCurrencyId();
        if (strtotime($data['start_date']) > strtotime($data['end_date'])) {
            throw ValidationException::withMessages(['end_date' => 'End date must be after start date.']);
        }
        // Prevent overlapping period for same branch+frequency
        $overlap = HrPayrollPeriod::where('institute_id', $instituteId)
            ->when($branchId === null, fn ($q) => $q->whereNull('branch_id'), fn ($q) => $q->where('branch_id', $branchId))
            ->where('pay_frequency', $data['pay_frequency'] ?? 'monthly')
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                    ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                    ->orWhere(function ($qq) use ($data) {
                        $qq->where('start_date', '<=', $data['start_date'])->where('end_date', '>=', $data['end_date']);
                    });
            })
            ->whereNotIn('status', ['cancelled', 'void'])
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['period' => 'Payroll period overlaps with existing period.']);
        }

        $period = HrPayrollPeriod::create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'name' => $data['name'],
            'pay_frequency' => $data['pay_frequency'] ?? 'monthly',
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => 'draft',
            'currency_id' => $currencyId,
            'generated_by' => $actorId,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $this->audit->record($instituteId, $actorId, 'hr_payroll_period_created', $period->id, null, ['name' => $period->name, 'start' => $period->start_date, 'end' => $period->end_date]);

        return $period;
    }

    public function updatePeriod(HrPayrollPeriod $period, array $data, ?int $actorId): HrPayrollPeriod
    {
        if ($period->isFinalized()) {
            throw ValidationException::withMessages(['period' => 'Cannot modify finalized period.']);
        }
        $old = $period->toArray();
        $period->update([
            'name' => $data['name'] ?? $period->name,
            'start_date' => $data['start_date'] ?? $period->start_date,
            'end_date' => $data['end_date'] ?? $period->end_date,
            'updated_by' => $actorId,
        ]);
        $this->audit->record($period->institute_id, $actorId, 'hr_payroll_period_updated', $period->id, $old, $period->fresh()->toArray());

        return $period;
    }

    // ---------------- Generate / Preview

    /**
     * Preview calculation for one employee (no persistence).
     */
    public function preview(int $employeeId, int $periodId, int $instituteId): array
    {
        $period = HrPayrollPeriod::where('institute_id', $instituteId)->where('id', $periodId)->firstOrFail();
        $assignment = $this->currentAssignment($employeeId, $instituteId);
        if (! $assignment) {
            throw ValidationException::withMessages(['employee' => 'Employee has no active salary assignment.']);
        }

        return $this->calculator->calculate($assignment, $period);
    }

    /**
     * Generate payrolls for all active employees with salary assignments in period's branch scope.
     * Transaction-safe, prevents duplicates via unique constraint + check.
     */
    public function generate(HrPayrollPeriod $period, ?int $actorId = null, bool $recalculate = false): HrPayrollPeriod
    {
        if ($period->isFinalized()) {
            throw ValidationException::withMessages(['period' => 'Cannot generate for finalized period.']);
        }

        return DB::transaction(function () use ($period, $actorId, $recalculate) {
            $period->update(['status' => 'processing']);

            $employees = HrEmployee::where('institute_id', $period->institute_id)
                ->where('employment_status', 'active')
                ->when($period->branch_id !== null, fn ($q) => $q->where('branch_id', $period->branch_id))
                ->get();

            $totalGross = 0;
            $totalDed = 0;
            $totalNet = 0;
            $count = 0;

            foreach ($employees as $emp) {
                $assignment = $this->currentAssignment($emp->id, $period->institute_id);
                if (! $assignment) {
                    continue;
                }
                // Check duplicate
                $existing = HrPayroll::where('institute_id', $period->institute_id)
                    ->where('payroll_period_id', $period->id)
                    ->where('employee_id', $emp->id)
                    ->first();
                if ($existing && ! $recalculate) {
                    // Skip duplicate, but count it
                    $totalGross += (float) $existing->gross_earnings;
                    $totalDed += (float) $existing->total_deductions;
                    $totalNet += (float) $existing->net_salary;
                    $count++;

                    continue;
                }
                if ($existing && $recalculate) {
                    if ($existing->isFinalized()) {
                        // Do not recalculate finalized
                        $totalGross += (float) $existing->gross_earnings;
                        $totalDed += (float) $existing->total_deductions;
                        $totalNet += (float) $existing->net_salary;
                        $count++;

                        continue;
                    }
                    $existing->delete(); // remove items cascade? Need to delete items
                    HrPayrollItem::where('payroll_id', $existing->id)->delete();
                }

                $calc = $this->calculator->calculate($assignment, $period);

                $payslipNo = $this->nextPayslipNo($period->institute_id);

                $payroll = HrPayroll::create([
                    'institute_id' => $period->institute_id,
                    'branch_id' => $emp->branch_id ?? $period->branch_id,
                    'payroll_period_id' => $period->id,
                    'employee_id' => $emp->id,
                    'salary_assignment_id' => $assignment->id,
                    'payslip_no' => $payslipNo,
                    'status' => 'draft',
                    'currency_id' => $assignment->currency_id ?? $period->currency_id,
                    'working_days' => $calc['working_days'],
                    'present_days' => $calc['present_days'],
                    'leave_days' => $calc['leave_days'],
                    'unpaid_leave_days' => $calc['unpaid_leave_days'],
                    'overtime_minutes' => $calc['overtime_minutes'],
                    'overtime_amount' => $calc['overtime_amount'],
                    'gross_earnings' => $calc['gross_earnings'],
                    'total_deductions' => $calc['total_deductions'],
                    'net_salary' => $calc['net_salary'],
                    'earnings_snapshot' => $calc['earnings_snapshot'],
                    'deductions_snapshot' => $calc['deductions_snapshot'],
                    'calculation_snapshot' => $calc['calculation_snapshot'],
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                foreach ($calc['earnings_snapshot'] as $e) {
                    HrPayrollItem::create([
                        'institute_id' => $period->institute_id,
                        'payroll_id' => $payroll->id,
                        'item_type' => 'earning',
                        'name' => $e['name'],
                        'code' => $e['code'] ?? null,
                        'amount' => $e['amount'],
                    ]);
                }
                foreach ($calc['deductions_snapshot'] as $d) {
                    HrPayrollItem::create([
                        'institute_id' => $period->institute_id,
                        'payroll_id' => $payroll->id,
                        'item_type' => 'deduction',
                        'name' => $d['name'],
                        'code' => $d['code'] ?? null,
                        'amount' => $d['amount'],
                    ]);
                }

                $totalGross += $calc['gross_earnings'];
                $totalDed += $calc['total_deductions'];
                $totalNet += $calc['net_salary'];
                $count++;
            }

            $period->update([
                'total_employees' => $count,
                'total_gross' => $totalGross,
                'total_deductions' => $totalDed,
                'total_net' => $totalNet,
                'status' => $count > 0 ? 'processing' : 'draft',
            ]);

            $this->audit->record($period->institute_id, $actorId, 'hr_payroll_generated', $period->id, null, ['count' => $count, 'total_net' => $totalNet]);

            return $period->fresh();
        });
    }

    // ---------------- Approve / Finalize / Pay

    public function approve(HrPayrollPeriod $period, ?int $actorId): HrPayrollPeriod
    {
        if ($period->status !== 'processing' && $period->status !== 'draft') {
            throw ValidationException::withMessages(['period' => 'Only processing/draft periods can be approved.']);
        }
        $this->finance->assertPeriodOpenForPayroll($period);

        return DB::transaction(function () use ($period, $actorId) {
            $payrolls = HrPayroll::where('payroll_period_id', $period->id)->where('institute_id', $period->institute_id)->get();
            if ($payrolls->isEmpty()) {
                throw ValidationException::withMessages(['period' => 'No payrolls to approve.']);
            }
            foreach ($payrolls as $payroll) {
                if ($payroll->status !== 'draft') {
                    continue;
                }
                $this->finance->postAccrual($payroll->load('period', 'employee'), $actorId);
                $payroll->update(['status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now()]);
                $this->audit->record($period->institute_id, $actorId, 'hr_payroll_approved', $payroll->id, ['status' => 'draft'], ['status' => 'approved']);
            }
            $period->update(['status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now()]);
            $this->audit->record($period->institute_id, $actorId, 'hr_payroll_period_approved', $period->id, ['status' => 'processing'], ['status' => 'approved']);

            return $period->fresh();
        });
    }

    public function markPaid(HrPayrollPeriod $period, ?int $actorId, ?string $paymentMethod = null): HrPayrollPeriod
    {
        if ($period->status !== 'approved') {
            throw ValidationException::withMessages(['period' => 'Period must be approved before payment.']);
        }

        return DB::transaction(function () use ($period, $actorId, $paymentMethod) {
            $payrolls = HrPayroll::where('payroll_period_id', $period->id)->where('status', 'approved')->get();
            foreach ($payrolls as $payroll) {
                $this->finance->postPayment($payroll->load('period', 'employee'), $actorId, $paymentMethod);
                $this->audit->record($period->institute_id, $actorId, 'hr_payroll_paid', $payroll->id, ['status' => 'approved'], ['status' => 'paid']);
            }
            $period->update(['status' => 'paid', 'paid_by' => $actorId, 'paid_at' => now()]);
            $this->audit->record($period->institute_id, $actorId, 'hr_payroll_period_paid', $period->id, ['status' => 'approved'], ['status' => 'paid']);

            return $period->fresh();
        });
    }

    public function cancelPeriod(HrPayrollPeriod $period, ?int $actorId, ?string $reason = null): HrPayrollPeriod
    {
        if ($period->isPaid()) {
            throw ValidationException::withMessages(['period' => 'Cannot cancel paid period.']);
        }

        return DB::transaction(function () use ($period, $actorId, $reason) {
            $payrolls = HrPayroll::where('payroll_period_id', $period->id)->get();
            foreach ($payrolls as $payroll) {
                if ($payroll->status === 'paid') {
                    continue;
                }
                if ($payroll->journal_id) {
                    $this->finance->reverseAccrual($payroll, $actorId, $reason);
                }
                if ($payroll->payment_journal_id) {
                    $this->finance->reversePayment($payroll, $actorId, $reason);
                }
                $payroll->update(['status' => 'cancelled', 'cancelled_by' => $actorId, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            }
            $period->update(['status' => 'cancelled', 'cancelled_by' => $actorId, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            $this->audit->record($period->institute_id, $actorId, 'hr_payroll_period_cancelled', $period->id, ['status' => $period->getOriginal('status')], ['status' => 'cancelled']);

            return $period->fresh();
        });
    }

    // ---------------- Adjustments

    public function addAdjustment(array $data, int $instituteId, ?int $actorId): HrPayrollAdjustment
    {
        $employee = HrEmployee::where('institute_id', $instituteId)->where('id', $data['employee_id'])->firstOrFail();
        $this->assertBranchOwnership($employee->branch_id, $instituteId);

        $payroll = null;
        if (! empty($data['payroll_id'])) {
            $payroll = HrPayroll::where('institute_id', $instituteId)->where('id', $data['payroll_id'])->firstOrFail();
            if ($payroll->isPaid()) {
                throw ValidationException::withMessages(['payroll' => 'Cannot adjust paid payroll.']);
            }
            if ($payroll->status === 'approved' && $payroll->journal_id) {
                throw ValidationException::withMessages(['payroll' => 'Cannot adjust finalized payroll.']);
            }
        }

        $adjustment = HrPayrollAdjustment::create([
            'institute_id' => $instituteId,
            'branch_id' => $employee->branch_id,
            'payroll_id' => $payroll?->id,
            'payroll_period_id' => $data['payroll_period_id'] ?? $payroll?->payroll_period_id,
            'employee_id' => $employee->id,
            'adjustment_type' => $data['adjustment_type'],
            'amount' => $data['amount'],
            'reason' => trim($data['reason']),
            'status' => 'approved',
            'created_by' => $actorId,
            'approved_by' => $actorId,
            'approved_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->record($instituteId, $actorId, 'hr_payroll_adjustment_created', $adjustment->id, null, ['type' => $adjustment->adjustment_type, 'amount' => $adjustment->amount]);

        // If payroll exists and draft, recalculate net
        if ($payroll && $payroll->status === 'draft') {
            $this->recalculatePayroll($payroll);
        }

        return $adjustment;
    }

    private function recalculatePayroll(HrPayroll $payroll): void
    {
        $assignment = HrEmployeeSalaryAssignment::where('id', $payroll->salary_assignment_id)->first();
        $period = HrPayrollPeriod::where('id', $payroll->payroll_period_id)->first();
        if (! $assignment || ! $period) {
            return;
        }
        $calc = $this->calculator->calculate($assignment, $period);
        $payroll->update([
            'gross_earnings' => $calc['gross_earnings'],
            'total_deductions' => $calc['total_deductions'],
            'net_salary' => $calc['net_salary'],
            'earnings_snapshot' => $calc['earnings_snapshot'],
            'deductions_snapshot' => $calc['deductions_snapshot'],
            'calculation_snapshot' => $calc['calculation_snapshot'],
            'overtime_amount' => $calc['overtime_amount'],
            'unpaid_leave_days' => $calc['unpaid_leave_days'],
        ]);
        // Recreate items
        HrPayrollItem::where('payroll_id', $payroll->id)->delete();
        foreach ($calc['earnings_snapshot'] as $e) {
            HrPayrollItem::create(['institute_id' => $payroll->institute_id, 'payroll_id' => $payroll->id, 'item_type' => 'earning', 'name' => $e['name'], 'code' => $e['code'] ?? null, 'amount' => $e['amount']]);
        }
        foreach ($calc['deductions_snapshot'] as $d) {
            HrPayrollItem::create(['institute_id' => $payroll->institute_id, 'payroll_id' => $payroll->id, 'item_type' => 'deduction', 'name' => $d['name'], 'code' => $d['code'] ?? null, 'amount' => $d['amount']]);
        }
    }

    // ---------------- Helpers

    private function nextPayslipNo(int $instituteId): string
    {
        return DB::transaction(function () use ($instituteId) {
            $seq = DB::table('hr_payroll_no_sequences')->where('institute_id', $instituteId)->lockForUpdate()->first();
            if (! $seq) {
                DB::table('hr_payroll_no_sequences')->insert(['institute_id' => $instituteId, 'last_sequence' => 1, 'created_at' => now(), 'updated_at' => now()]);
                $next = 1;
            } else {
                $next = $seq->last_sequence + 1;
                DB::table('hr_payroll_no_sequences')->where('institute_id', $instituteId)->update(['last_sequence' => $next, 'updated_at' => now()]);
            }

            return 'PSL-'.str_pad((string) $instituteId, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        });
    }

    private function defaultCurrencyId(): ?int
    {
        $currency = Currency::where('is_base', true)->first() ?? Currency::orderBy('code')->first();

        return $currency?->id;
    }

    private function assertBranchOwnership(?int $branchId, int $instituteId): void
    {
        if ($branchId === null) {
            return;
        }
        if (! Branch::where('institute_id', $instituteId)->where('id', $branchId)->exists()) {
            throw ValidationException::withMessages(['branch_id' => 'Branch does not belong to institute.']);
        }
    }

    // Reports
    public function reports(int $instituteId, ?int $branchId = null): array
    {
        $base = HrPayroll::where('institute_id', $instituteId)->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        return [
            'total_payrolls' => (clone $base)->count(),
            'total_net' => (clone $base)->sum('net_salary'),
            'total_gross' => (clone $base)->sum('gross_earnings'),
            'total_deductions' => (clone $base)->sum('total_deductions'),
            'unpaid' => (clone $base)->where('status', '!=', 'paid')->sum('net_salary'),
            'by_department' => HrPayroll::where('hr_payrolls.institute_id', $instituteId)
                ->join('hr_employees', 'hr_employees.id', '=', 'hr_payrolls.employee_id')
                ->when($branchId, fn ($q) => $q->where('hr_payrolls.branch_id', $branchId))
                ->selectRaw('hr_employees.department_id, SUM(hr_payrolls.net_salary) as total')
                ->groupBy('hr_employees.department_id')->pluck('total', 'department_id')->all(),
            'by_branch' => HrPayroll::where('institute_id', $instituteId)->selectRaw('branch_id, SUM(net_salary) as total')->groupBy('branch_id')->pluck('total', 'branch_id')->all(),
        ];
    }

    public function reconciliation(int $instituteId, ?int $branchId = null, ?int $periodId = null): array
    {
        return $this->finance->reconciliation($instituteId, $branchId, $periodId);
    }
}
