<?php

namespace App\Services;

use App\Models\HrAttendance;
use App\Models\HrEmployeeSalaryAssignment;
use App\Models\HrLeaveApplication;
use App\Models\HrPayrollAdjustment;
use App\Models\HrPayrollPeriod;
use Carbon\Carbon;

class HrPayrollCalculationService
{
    /**
     * Compute payroll breakdown for an employee in a period using
     * HR-4 attendance/leave data where applicable. Does NOT assume
     * missing attendance = absence.
     *
     * @return array{working_days:int, present_days:float, leave_days:float, unpaid_leave_days:float, overtime_minutes:int, overtime_amount:float, gross_earnings:float, total_deductions:float, net_salary:float, earnings_snapshot:array, deductions_snapshot:array, calculation_snapshot:array}
     */
    public function calculate(
        HrEmployeeSalaryAssignment $assignment,
        HrPayrollPeriod $period,
        ?int $overtimeMinutesOverride = null,
        array $extraAdjustments = []
    ): array {
        $start = Carbon::parse($period->start_date);
        $end = Carbon::parse($period->end_date);
        $workingDays = $start->diffInDays($end) + 1; // inclusive

        // Attendance aggregates (only rows that exist)
        $attendances = HrAttendance::where('institute_id', $assignment->institute_id)
            ->where('employee_id', $assignment->employee_id)
            ->whereBetween('attendance_date', [$period->start_date, $period->end_date])
            ->get();

        $presentDays = 0;
        $leaveDays = 0;
        $unpaidLeaveDays = 0;
        $overtimeMinutes = 0;

        foreach ($attendances as $att) {
            if (in_array($att->status, ['present', 'late', 'early_departure'], true)) {
                $presentDays += 1;
            } elseif ($att->status === 'half_day') {
                $presentDays += 0.5;
            } elseif ($att->status === 'leave') {
                $leaveDays += 1;
            } elseif ($att->status === 'absent') {
                $unpaidLeaveDays += 1;
            }
            $overtimeMinutes += (int) ($att->overtime_minutes ?? 0);
        }

        // Approved leave applications overlapping period (extra leave not in attendance)
        $leaveApps = HrLeaveApplication::where('institute_id', $assignment->institute_id)
            ->where('employee_id', $assignment->employee_id)
            ->where('status', 'approved')
            ->where('end_date', '>=', $period->start_date)
            ->where('start_date', '<=', $period->end_date)
            ->get();

        $approvedLeaveDays = 0;
        foreach ($leaveApps as $app) {
            $overlapStart = max($start->toDateString(), $app->start_date->format('Y-m-d'));
            $overlapEnd = min($end->toDateString(), $app->end_date->format('Y-m-d'));
            $days = Carbon::parse($overlapStart)->diffInDays(Carbon::parse($overlapEnd)) + 1;
            // Cap by days_count proportion if partial overlap
            $approvedLeaveDays += min($days, (float) $app->days_count);
        }
        // If attendance doesn't have leave entry, use leave app days as paid leave (not unpaid)
        // Unpaid is only absent/half.

        if ($overtimeMinutesOverride !== null) {
            $overtimeMinutes = $overtimeMinutesOverride;
        }

        $overtimeRate = (float) $assignment->overtime_rate;
        $overtimeAmount = $overtimeMinutes > 0 && $overtimeRate > 0 ? round(($overtimeMinutes / 60) * $overtimeRate, 2) : 0;

        // Base earnings from assignment
        $basic = (float) $assignment->basic_salary;
        $housing = (float) $assignment->housing_allowance;
        $medical = (float) $assignment->medical_allowance;
        $transport = (float) $assignment->transport_allowance;
        $other = (float) $assignment->other_allowance;
        $bonus = (float) $assignment->bonus_amount;
        $commission = (float) $assignment->commission_amount;

        // Configurable components (if assignment has structure with components)
        $componentEarnings = 0;
        $componentDeductions = 0;
        $componentTax = 0;
        $earningsDetails = [];
        $deductionsDetails = [];

        if ($assignment->salary_structure_id) {
            $structure = $assignment->structure()->with('components')->first();
            if ($structure) {
                foreach ($structure->components()->where('is_active', true)->get() as $comp) {
                    $amt = $this->componentAmount($comp, $basic);
                    if (in_array($comp->component_type, ['earning'], true)) {
                        $componentEarnings += $amt;
                        $earningsDetails[] = ['name' => $comp->name, 'code' => $comp->code, 'amount' => $amt, 'type' => $comp->component_type];
                    } elseif ($comp->component_type === 'deduction') {
                        $componentDeductions += $amt;
                        $deductionsDetails[] = ['name' => $comp->name, 'code' => $comp->code, 'amount' => $amt, 'type' => $comp->component_type];
                    } elseif (in_array($comp->component_type, ['tax', 'statutory'], true)) {
                        $componentTax += $amt;
                        $deductionsDetails[] = ['name' => $comp->name, 'code' => $comp->code, 'amount' => $amt, 'type' => $comp->component_type];
                    }
                }
            }
        }

        // Adjustments for this employee/period (approved)
        $adjustments = HrPayrollAdjustment::where('institute_id', $assignment->institute_id)
            ->where('employee_id', $assignment->employee_id)
            ->where(function ($q) use ($period) {
                $q->where('payroll_period_id', $period->id)->orWhereNull('payroll_period_id');
            })
            ->where('status', 'approved')
            ->get()
            ->merge(collect($extraAdjustments));

        $adjustEarnings = 0;
        $adjustDeductions = 0;
        foreach ($adjustments as $adj) {
            $amt = (float) $adj['amount'] ?? (float) ($adj->amount ?? 0);
            $type = $adj['adjustment_type'] ?? $adj->type ?? 'bonus';
            if (in_array($type, ['bonus', 'allowance', 'overtime', 'commission', 'correction'], true) && $amt > 0) {
                // correction could be positive or negative; treat positive as earning, negative as deduction
                if ($amt >= 0) {
                    $adjustEarnings += abs($amt);
                    $earningsDetails[] = ['name' => ucfirst($type) . ' Adj', 'code' => $type, 'amount' => abs($amt), 'type' => 'earning'];
                } else {
                    $adjustDeductions += abs($amt);
                    $deductionsDetails[] = ['name' => ucfirst($type) . ' Adj', 'code' => $type, 'amount' => abs($amt), 'type' => 'deduction'];
                }
            } elseif (in_array($type, ['deduction', 'tax'], true)) {
                $adjustDeductions += abs($amt);
                $deductionsDetails[] = ['name' => ucfirst($type) . ' Adj', 'code' => $type, 'amount' => abs($amt), 'type' => 'deduction'];
            } else {
                // fallback: positive earning, negative deduction
                if ($amt >= 0) {
                    $adjustEarnings += abs($amt);
                } else {
                    $adjustDeductions += abs($amt);
                }
            }
        }

        // Pro-rata unpaid leave deduction
        $dailyRate = $workingDays > 0 ? ($basic + $housing + $medical + $transport + $other) / $workingDays : 0;
        $unpaidDeduction = round($unpaidLeaveDays * $dailyRate, 2);

        $gross = $basic + $housing + $medical + $transport + $other + $bonus + $commission + $overtimeAmount + $componentEarnings + $adjustEarnings;
        $deductions = (float) $assignment->deduction_amount + (float) $assignment->tax_deduction + $componentDeductions + $componentTax + $adjustDeductions + $unpaidDeduction;

        $gross = round($gross, 2);
        $deductions = round($deductions, 2);
        $net = round($gross - $deductions, 2);
        if ($net < 0) $net = 0;

        $earningsSnapshot = [
            ['name' => 'Basic Salary', 'code' => 'basic', 'amount' => $basic],
            ['name' => 'Housing Allowance', 'code' => 'housing', 'amount' => $housing],
            ['name' => 'Medical Allowance', 'code' => 'medical', 'amount' => $medical],
            ['name' => 'Transport Allowance', 'code' => 'transport', 'amount' => $transport],
            ['name' => 'Other Allowance', 'code' => 'other', 'amount' => $other],
            ['name' => 'Overtime', 'code' => 'overtime', 'amount' => $overtimeAmount],
            ['name' => 'Bonus', 'code' => 'bonus', 'amount' => $bonus],
            ['name' => 'Commission', 'code' => 'commission', 'amount' => $commission],
        ];
        // Filter zero earnings for snapshot brevity? Keep all but also append component/adjust earnings
        foreach ($earningsDetails as $e) $earningsSnapshot[] = $e;
        // Remove zero amounts for cleanliness but keep basic
        $earningsSnapshot = array_values(array_filter($earningsSnapshot, fn ($e) => $e['amount'] != 0 || $e['code'] === 'basic'));

        $deductionsSnapshot = [];
        if ((float) $assignment->deduction_amount > 0) $deductionsSnapshot[] = ['name' => 'Deduction', 'code' => 'deduction', 'amount' => (float) $assignment->deduction_amount];
        if ((float) $assignment->tax_deduction > 0) $deductionsSnapshot[] = ['name' => 'Tax', 'code' => 'tax', 'amount' => (float) $assignment->tax_deduction];
        if ($unpaidDeduction > 0) $deductionsSnapshot[] = ['name' => 'Unpaid Leave (' . $unpaidLeaveDays . ' days)', 'code' => 'unpaid_leave', 'amount' => $unpaidDeduction];
        foreach ($deductionsDetails as $d) $deductionsSnapshot[] = $d;
        // Also include tax component already via details

        $calculationSnapshot = [
            'assignment' => $assignment->toArray(),
            'period' => $period->toArray(),
            'working_days' => $workingDays,
            'present_days' => $presentDays,
            'leave_days' => $leaveDays,
            'approved_leave_days' => $approvedLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_rate' => $overtimeRate,
            'daily_rate' => round($dailyRate, 2),
            'attendances_count' => $attendances->count(),
            'leave_applications_count' => $leaveApps->count(),
            'adjustments' => $adjustments->map(fn ($a) => is_array($a) ? $a : $a->toArray())->values()->all(),
            'generated_at' => now()->toIso8601String(),
        ];

        return [
            'working_days' => $workingDays,
            'present_days' => $presentDays,
            'leave_days' => $leaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_amount' => $overtimeAmount,
            'gross_earnings' => $gross,
            'total_deductions' => $deductions,
            'net_salary' => $net,
            'earnings_snapshot' => $earningsSnapshot,
            'deductions_snapshot' => $deductionsSnapshot,
            'calculation_snapshot' => $calculationSnapshot,
        ];
    }

    private function componentAmount($comp, float $basic): float
    {
        if ($comp->amount_type === 'percent') {
            $pct = (float) ($comp->percent_base ?? $comp->amount);
            return round($basic * $pct / 100, 2);
        }
        return (float) $comp->amount;
    }
}
