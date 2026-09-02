<?php

namespace App\Services;

use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\HrLeaveBalance;
use App\Models\HrPayroll;
use Illuminate\Validation\ValidationException;

class HrSelfService
{
    public const ALLOWED_PROFILE_FIELDS = [
        'phone','email','address','emergency_contact_name','emergency_contact_phone',
        'middle_name','first_name','last_name','gender','date_of_birth','profile_photo'
    ];

    public const SENSITIVE_FIELDS = [
        'employee_code','salary','employment_status','department_id','designation_id','branch_id',
        'institute_user_id','reporting_manager_id','joining_date','employment_type','national_id','passport_no'
    ];

    public function resolveEmployee(int $instituteId, ?int $userId): ?HrEmployee
    {
        if ($userId === null) return null;
        return HrEmployee::where('institute_id',$instituteId)->where('institute_user_id',$userId)->first();
    }

    public function resolveEmployeeOrFail(int $instituteId, ?int $userId): HrEmployee
    {
        if ($userId === null) throw ValidationException::withMessages(['employee'=>'No employee record linked to your user']);
        $emp = $this->resolveEmployee($instituteId,$userId);
        if (!$emp) throw ValidationException::withMessages(['employee'=>'No employee record linked to your user']);
        return $emp;
    }

    public function updateProfile(HrEmployee $employee, array $data, ?int $actorId): HrEmployee
    {
        $filtered = [];
        foreach (self::ALLOWED_PROFILE_FIELDS as $field) {
            if (array_key_exists($field, $data)) $filtered[$field] = $data[$field];
        }
        if (empty($filtered)) throw ValidationException::withMessages(['profile'=>'No permitted fields to update']);

        // Validate phone/email etc
        if (isset($filtered['phone']) && $filtered['phone'] !== null) {
            if (!preg_match('/^\+?[0-9\s\-]{7,20}$/', $filtered['phone'])) {
                throw ValidationException::withMessages(['phone'=>'Invalid phone']);
            }
            // uniqueness per institute
            $exists = HrEmployee::where('institute_id',$employee->institute_id)->where('id','!=',$employee->id)->where('phone',$filtered['phone'])->exists();
            if ($exists) throw ValidationException::withMessages(['phone'=>'Phone already used']);
        }
        if (isset($filtered['email']) && $filtered['email'] !== null) {
            if (!filter_var($filtered['email'], FILTER_VALIDATE_EMAIL)) throw ValidationException::withMessages(['email'=>'Invalid email']);
            $exists = HrEmployee::where('institute_id',$employee->institute_id)->where('id','!=',$employee->id)->where('email',$filtered['email'])->exists();
            if ($exists) throw ValidationException::withMessages(['email'=>'Email already used']);
        }

        $old = $employee->toArray();
        $employee->fill($filtered);
        // recompute display_name if name changed
        if (isset($filtered['first_name']) || isset($filtered['last_name']) || isset($filtered['middle_name'])) {
            $first = $filtered['first_name'] ?? $employee->first_name;
            $middle = $filtered['middle_name'] ?? $employee->middle_name;
            $last = $filtered['last_name'] ?? $employee->last_name;
            $employee->display_name = trim($first . ($middle ? " $middle" : '') . " $last");
        }
        $employee->save();

        app(HrAuditService::class)->record($employee->institute_id,$actorId,'hr_self_profile_updated',$employee->id,$old,$employee->fresh()->toArray());
        return $employee->fresh();
    }

    public function attendanceSummary(HrEmployee $employee, ?string $month = null): array
    {
        $date = $month ? \Carbon\Carbon::parse($month.'-01') : now();
        $start = $date->copy()->startOfMonth()->toDateString();
        $end = $date->copy()->endOfMonth()->toDateString();
        $attendances = HrAttendance::where('institute_id',$employee->institute_id)->where('employee_id',$employee->id)->whereBetween('attendance_date',[$start,$end])->get();
        return [
            'present' => $attendances->where('status','present')->count() + $attendances->where('status','late')->count() + $attendances->where('status','early_departure')->count(),
            'absent' => $attendances->where('status','absent')->count(),
            'late' => $attendances->where('status','late')->count(),
            'leave' => $attendances->where('status','leave')->count(),
            'half_day' => $attendances->where('status','half_day')->count(),
            'total' => $attendances->count(),
            'overtime_minutes' => $attendances->sum('overtime_minutes'),
            'records' => $attendances,
        ];
    }

    public function leaveBalances(HrEmployee $employee): \Illuminate\Support\Collection
    {
        return HrLeaveBalance::where('institute_id',$employee->institute_id)->where('employee_id',$employee->id)->with('leaveType')->get();
    }

    public function payslips(HrEmployee $employee)
    {
        return HrPayroll::where('institute_id',$employee->institute_id)->where('employee_id',$employee->id)->with(['period','currency'])->orderByDesc('id')->get();
    }

    public function documents(HrEmployee $employee)
    {
        return \App\Models\Document::where('institute_id',$employee->institute_id)->where('documentable_type',HrEmployee::class)->where('documentable_id',$employee->id)->with(['category'])->orderByDesc('id')->get();
    }

    public function teamEmployees(HrEmployee $manager): \Illuminate\Support\Collection
    {
        // Direct reports + branch team if manager is branch-scoped
        $query = HrEmployee::where('institute_id',$manager->institute_id)->where('id','!=',$manager->id);
        // If manager has branch, scope to branch
        if ($manager->branch_id) {
            $query->where('branch_id',$manager->branch_id);
        }
        // Also include direct reports
        $direct = HrEmployee::where('institute_id',$manager->institute_id)->where('reporting_manager_id',$manager->id)->pluck('id');
        // For manager dashboard, show both direct and branch
        return $query->where(function($q) use ($direct, $manager){
            $q->whereIn('id',$direct)->orWhere('branch_id',$manager->branch_id);
        })->get();
        // Fallback: if no branch, show direct reports only
    }

    public function teamPendingLeaves(HrEmployee $manager)
    {
        $teamIds = $this->teamEmployeeIds($manager);
        return \App\Models\HrLeaveApplication::where('institute_id',$manager->institute_id)->whereIn('employee_id',$teamIds)->where('status','pending')->with(['employee','leaveType'])->get();
    }

    public function teamAttendanceExceptions(HrEmployee $manager, ?string $date = null)
    {
        $teamIds = $this->teamEmployeeIds($manager);
        $date = $date ?? now()->toDateString();
        return HrAttendance::where('institute_id',$manager->institute_id)->whereIn('employee_id',$teamIds)->where('attendance_date',$date)->whereIn('status',['absent','late'])->get();
    }

    private function teamEmployeeIds(HrEmployee $manager): array
    {
        // If manager has direct reports, use them; otherwise branch team
        $direct = HrEmployee::where('institute_id',$manager->institute_id)->where('reporting_manager_id',$manager->id)->pluck('id')->all();
        if (!empty($direct)) return $direct;
        if ($manager->branch_id) {
            return HrEmployee::where('institute_id',$manager->institute_id)->where('branch_id',$manager->branch_id)->where('id','!=',$manager->id)->pluck('id')->all();
        }
        return [];
    }
}
