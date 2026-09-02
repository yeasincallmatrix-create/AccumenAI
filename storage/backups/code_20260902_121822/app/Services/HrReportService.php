<?php

namespace App\Services;

use App\Models\HrApplication;
use App\Models\HrAttendance;
use App\Models\HrDepartment;
use App\Models\HrEmployee;
use App\Models\HrEmploymentHistory;
use App\Models\HrLeaveApplication;
use App\Models\HrLeaveBalance;
use App\Models\HrPayroll;
use App\Models\HrPayrollPeriod;
use App\Models\HrPerformanceReview;
use App\Models\HrRequisition;
use App\Models\HrTraining;
use App\Models\HrTrainingEnrollment;
use App\Models\HrVacancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HrReportService
{
    // ---------------- Employee
    public function employeeReport(int $instituteId, ?int $branchId, array $filters = []): array
    {
        $q = HrEmployee::query()->where('hr_employees.institute_id', $instituteId);
        if ($branchId !== null) $q->where('hr_employees.branch_id', $branchId);
        if (!empty($filters['branch_id'])) $q->where('hr_employees.branch_id', (int)$filters['branch_id']);
        if (!empty($filters['department_id'])) $q->where('hr_employees.department_id', (int)$filters['department_id']);
        if (!empty($filters['designation_id'])) $q->where('hr_employees.designation_id', (int)$filters['designation_id']);
        if (!empty($filters['employment_status'])) $q->where('hr_employees.employment_status', $filters['employment_status']);
        if (!empty($filters['employment_type'])) $q->where('hr_employees.employment_type', $filters['employment_type']);
        if (!empty($filters['employee_id'])) $q->where('hr_employees.id', (int)$filters['employee_id']);
        if (!empty($filters['from'])) $q->whereDate('hr_employees.joining_date', '>=', $filters['from']);
        if (!empty($filters['to'])) $q->whereDate('hr_employees.joining_date', '<=', $filters['to']);

        $total = (clone $q)->count();
        $byStatus = (clone $q)->selectRaw('employment_status, COUNT(*) as c')->groupBy('employment_status')->pluck('c','employment_status')->all();
        $byDept = (clone $q)->selectRaw('department_id, COUNT(*) as c')->groupBy('department_id')->pluck('c','department_id')->all();
        $byBranch = (clone $q)->whereNotNull('branch_id')->selectRaw('branch_id, COUNT(*) as c')->groupBy('branch_id')->pluck('c','branch_id')->all();

        $rows = (clone $q)->with(['branch','department','designation'])->orderBy('hr_employees.id')->limit(200)->get();

        return ['valid'=>true,'total'=>$total,'by_status'=>$byStatus,'by_department'=>$byDept,'by_branch'=>$byBranch,'rows'=>$rows];
    }

    // ---------------- Workforce
    public function workforceReport(int $instituteId, ?int $branchId, array $filters = []): array
    {
        $from = $filters['from'] ?? Carbon::now()->subYear()->toDateString();
        $to = $filters['to'] ?? Carbon::now()->toDateString();

        $base = HrEmployee::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->where('branch_id',$branchId));
        $headcount = (clone $base)->count();
        $active = (clone $base)->where('employment_status','active')->count();
        $inactive = (clone $base)->where('employment_status','inactive')->count();

        $newHires = HrEmployee::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->whereBetween('joining_date',[$from,$to])->count();

        // Histories for resignations/terminations etc.
        $histQ = HrEmploymentHistory::where('institute_id',$instituteId)->whereBetween('effective_date',[$from,$to]);
        if ($branchId !== null) $histQ->where(function($q) use ($branchId){ $q->where('new_branch_id',$branchId)->orWhere('previous_branch_id',$branchId); });
        $resignations = (clone $histQ)->where('event_type','resignation')->count();
        $terminations = (clone $histQ)->where('event_type','termination')->count();

        $avgHeadcount = max(1, ($headcount + max(0, $headcount - $newHires + $resignations + $terminations))/2);
        $turnover = $avgHeadcount > 0 ? round((($resignations+$terminations)/$avgHeadcount)*100,2) : 0;

        // Monthly trend
        $trend = HrEmployee::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))
            ->whereBetween('joining_date',[$from,$to])
            ->selectRaw("DATE_FORMAT(joining_date,'%Y-%m') as m, COUNT(*) as c")
            ->groupBy('m')->orderBy('m')->pluck('c','m')->all();

        return ['valid'=>true,'headcount'=>$headcount,'active'=>$active,'inactive'=>$inactive,'new_hires'=>$newHires,'resignations'=>$resignations,'terminations'=>$terminations,'turnover_rate'=>$turnover,'trend'=>$trend];
    }

    // ---------------- Attendance
    public function attendanceReport(int $instituteId, ?int $branchId, array $filters = []): array
    {
        $from = $filters['from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? Carbon::now()->endOfMonth()->toDateString();

        $q = HrAttendance::where('institute_id',$instituteId)->whereBetween('attendance_date',[$from,$to]);
        if ($branchId !== null) $q->where('branch_id',$branchId);
        if (!empty($filters['branch_id'])) $q->where('branch_id',(int)$filters['branch_id']);
        if (!empty($filters['department_id'])) {
            $empIds = HrEmployee::where('institute_id',$instituteId)->where('department_id',(int)$filters['department_id'])->pluck('id');
            $q->whereIn('employee_id',$empIds);
        }
        if (!empty($filters['employee_id'])) $q->where('employee_id',(int)$filters['employee_id']);

        $total = (clone $q)->count();
        $byStatus = (clone $q)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c','status')->all();
        $overtime = (clone $q)->sum('overtime_minutes');
        $late = $byStatus['late'] ?? 0;
        $absent = $byStatus['absent'] ?? 0;

        // Branch/department trends
        $byBranch = HrAttendance::where('institute_id',$instituteId)->whereBetween('attendance_date',[$from,$to])->whereNotNull('branch_id')->selectRaw('branch_id, COUNT(*) as c')->groupBy('branch_id')->pluck('c','branch_id')->all();
        $byDeptTrend = DB::table('hr_attendances')
            ->join('hr_employees','hr_employees.id','=','hr_attendances.employee_id')
            ->where('hr_attendances.institute_id',$instituteId)
            ->whereBetween('hr_attendances.attendance_date',[$from,$to])
            ->when($branchId, fn($q)=>$q->where('hr_attendances.branch_id',$branchId))
            ->groupBy('hr_employees.department_id')
            ->selectRaw('hr_employees.department_id, COUNT(*) as c')
            ->pluck('c','department_id')->all();

        $rows = (clone $q)->with(['employee'])->orderBy('attendance_date')->limit(200)->get();

        return ['valid'=>true,'total'=>$total,'by_status'=>$byStatus,'late'=>$late,'absent'=>$absent,'overtime_minutes'=>$overtime,'by_branch'=>$byBranch,'by_department'=>$byDeptTrend,'rows'=>$rows,'from'=>$from,'to'=>$to];
    }

    // ---------------- Leave
    public function leaveReport(int $instituteId, ?int $branchId, array $filters = []): array
    {
        $from = $filters['from'] ?? Carbon::now()->startOfYear()->toDateString();
        $to = $filters['to'] ?? Carbon::now()->endOfYear()->toDateString();

        $apps = HrLeaveApplication::where('institute_id',$instituteId)->whereBetween('start_date',[$from,$to]);
        if ($branchId !== null) $apps->where('branch_id',$branchId);
        if (!empty($filters['branch_id'])) $apps->where('branch_id',(int)$filters['branch_id']);
        if (!empty($filters['department_id'])) {
            $empIds = HrEmployee::where('institute_id',$instituteId)->where('department_id',(int)$filters['department_id'])->pluck('id');
            $apps->whereIn('employee_id',$empIds);
        }

        $total = (clone $apps)->count();
        $byStatus = (clone $apps)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c','status')->all();
        $pending = $byStatus['pending'] ?? 0;
        $byType = (clone $apps)->selectRaw('leave_type_id, COUNT(*) as c')->groupBy('leave_type_id')->pluck('c','leave_type_id')->all();

        $balances = HrLeaveBalance::where('institute_id',$instituteId)->when($branchId, function($q) use ($branchId){
            $empIds = HrEmployee::where('branch_id',$branchId)->pluck('id');
            $q->whereIn('employee_id',$empIds);
        });
        if (!empty($filters['employee_id'])) $balances->where('employee_id',(int)$filters['employee_id']);
        $balanceRows = $balances->with(['leaveType','employee'])->limit(200)->get();
        $utilization = $balanceRows->sum(fn($b)=> (float)$b->used) . '/' . $balanceRows->sum(fn($b)=> (float)$b->allocated + (float)$b->carry_forward);

        return ['valid'=>true,'total'=>$total,'by_status'=>$byStatus,'pending'=>$pending,'by_type'=>$byType,'balances'=>$balanceRows,'utilization'=>$utilization,'from'=>$from,'to'=>$to];
    }

    // ---------------- Payroll
    public function payrollReport(int $instituteId, ?int $branchId, array $filters = []): array
    {
        $from = $filters['from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? Carbon::now()->endOfMonth()->toDateString();

        $periods = HrPayrollPeriod::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))
            ->where('start_date','>=',$from)->where('end_date','<=',$to)->get();
        $periodIds = $periods->pluck('id');

        $payrolls = HrPayroll::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))
            ->when($periodIds->isNotEmpty(), fn($q)=>$q->whereIn('payroll_period_id',$periodIds))
            ->when(!empty($filters['employee_id']), fn($q)=>$q->where('employee_id',(int)$filters['employee_id']))
            ->when(!empty($filters['department_id']), function($q) use ($instituteId, $filters){
                $empIds = HrEmployee::where('institute_id',$instituteId)->where('department_id',(int)$filters['department_id'])->pluck('id');
                $q->whereIn('employee_id',$empIds);
            });

        $totalGross = (clone $payrolls)->sum('gross_earnings');
        $totalNet = (clone $payrolls)->sum('net_salary');
        $totalDed = (clone $payrolls)->sum('total_deductions');
        $outstanding = (clone $payrolls)->where('status','!=','paid')->sum('net_salary');
        $byBranch = (clone $payrolls)->whereNotNull('branch_id')->selectRaw('branch_id, SUM(net_salary) as c')->groupBy('branch_id')->pluck('c','branch_id')->all();
        $byDept = DB::table('hr_payrolls')->join('hr_employees','hr_employees.id','=','hr_payrolls.employee_id')
            ->where('hr_payrolls.institute_id',$instituteId)
            ->when($branchId, fn($q)=>$q->where('hr_payrolls.branch_id',$branchId))
            ->when($periodIds->isNotEmpty(), fn($q)=>$q->whereIn('hr_payrolls.payroll_period_id',$periodIds))
            ->groupBy('hr_employees.department_id')->selectRaw('hr_employees.department_id, SUM(hr_payrolls.net_salary) as c')->pluck('c','department_id')->all();

        // Allowances/deductions breakdown
        $allowances = (clone $payrolls)->sum(DB::raw('gross_earnings - net_salary - total_deductions')) ?? 0; // rough
        $rows = (clone $payrolls)->with(['employee','period'])->orderByDesc('id')->limit(200)->get();

        return ['valid'=>true,'total_gross'=>$totalGross,'total_net'=>$totalNet,'total_deductions'=>$totalDed,'outstanding'=>$outstanding,'by_branch'=>$byBranch,'by_department'=>$byDept,'rows'=>$rows,'from'=>$from,'to'=>$to];
    }

    // ---------------- Recruitment
    public function recruitmentReport(int $instituteId, ?int $branchId, array $filters = []): array
    {
        $from = $filters['from'] ?? Carbon::now()->subMonths(3)->toDateString();
        $to = $filters['to'] ?? Carbon::now()->toDateString();

        $vacancies = HrVacancy::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))
            ->when(!empty($filters['department_id']), fn($q)=>$q->where('department_id',(int)$filters['department_id']))
            ->whereBetween('created_at',[$from,$to])->count();
        $applications = HrApplication::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))
            ->whereBetween('created_at',[$from,$to])->count();
        $byStage = HrApplication::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))
            ->whereBetween('created_at',[$from,$to])->selectRaw('current_stage, COUNT(*) as c')->groupBy('current_stage')->pluck('c','current_stage')->all();
        $hired = $byStage['hired'] ?? 0;
        $hiringRate = $applications > 0 ? round(($hired/$applications)*100,2) : 0;
        $bySource = HrApplication::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))
            ->whereBetween('created_at',[$from,$to])->whereNotNull('source_id')->selectRaw('source_id, COUNT(*) as c')->groupBy('source_id')->pluck('c','source_id')->all();
        $byDept = HrVacancy::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))
            ->whereBetween('created_at',[$from,$to])->selectRaw('department_id, COUNT(*) as c')->groupBy('department_id')->pluck('c','department_id')->all();

        return ['valid'=>true,'vacancies'=>$vacancies,'applicants'=>$applications,'by_stage'=>$byStage,'hiring_rate'=>$hiringRate,'by_source'=>$bySource,'by_department'=>$byDept,'from'=>$from,'to'=>$to];
    }

    // ---------------- Performance
    public function performanceReport(int $instituteId, ?int $branchId, array $filters = []): array
    {
        $q = HrPerformanceReview::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->whereHas('employee', fn($qq)=>$qq->where('branch_id',$branchId)));
        if (!empty($filters['department_id'])) $q->whereHas('employee', fn($qq)=>$qq->where('department_id',(int)$filters['department_id']));
        if (!empty($filters['employee_id'])) $q->where('employee_id',(int)$filters['employee_id']);
        if (!empty($filters['from'])) $q->whereDate('review_date','>=',$filters['from']);
        if (!empty($filters['to'])) $q->whereDate('review_date','<=',$filters['to']);

        $total = (clone $q)->count();
        $byStatus = (clone $q)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c','status')->all();
        $avgScore = (clone $q)->avg('overall_score');
        $byPeriod = (clone $q)->selectRaw('period_id, AVG(overall_score) as avg')->groupBy('period_id')->pluck('avg','period_id')->all();

        $rows = (clone $q)->with(['employee','period'])->orderByDesc('id')->limit(200)->get();

        return ['valid'=>true,'total'=>$total,'by_status'=>$byStatus,'avg_score'=>round($avgScore ?? 0,2),'by_period'=>$byPeriod,'rows'=>$rows];
    }

    // ---------------- Training
    public function trainingReport(int $instituteId, ?int $branchId, array $filters = []): array
    {
        $trainings = HrTraining::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->where('branch_id',$branchId));
        if (!empty($filters['from'])) $trainings->whereDate('start_date','>=',$filters['from']);
        if (!empty($filters['to'])) $trainings->whereDate('end_date','<=',$filters['to']);
        $total = (clone $trainings)->count();
        $byStatus = (clone $trainings)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c','status')->all();

        $enrollments = HrTrainingEnrollment::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->whereHas('training', fn($qq)=>$qq->where('branch_id',$branchId)));
        if (!empty($filters['employee_id'])) $enrollments->where('employee_id',(int)$filters['employee_id']);
        $totalEnroll = (clone $enrollments)->count();
        $completed = (clone $enrollments)->where('status','completed')->count();
        $completionRate = $totalEnroll > 0 ? round(($completed/$totalEnroll)*100,2) : 0;
        $totalCost = (clone $trainings)->sum('cost');
        $byTraining = (clone $enrollments)->selectRaw('training_id, COUNT(*) as c')->groupBy('training_id')->pluck('c','training_id')->all();

        // Skill gaps: skills with low proficiency
        $skills = \App\Models\HrEmployeeSkill::where('institute_id',$instituteId)->when($branchId, function($q) use ($branchId){
            $empIds = HrEmployee::where('branch_id',$branchId)->pluck('id');
            $q->whereIn('employee_id',$empIds);
        })->selectRaw('proficiency_level, COUNT(*) as c')->groupBy('proficiency_level')->pluck('c','proficiency_level')->all();

        return ['valid'=>true,'total_trainings'=>$total,'by_status'=>$byStatus,'total_enrollments'=>$totalEnroll,'completed'=>$completed,'completion_rate'=>$completionRate,'total_cost'=>$totalCost,'by_training'=>$byTraining,'skill_gaps'=>$skills];
    }
}
