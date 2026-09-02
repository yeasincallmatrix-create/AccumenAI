<?php

namespace App\Services;

class HrReportExportService
{
    public function __construct(private readonly HrReportService $reports) {}

    private function filename(string $prefix, array $filters): string
    {
        $from = $filters['from'] ?? date('Y-m-d');
        $to = $filters['to'] ?? date('Y-m-d');
        return $prefix.'-'.$from.'-to-'.$to.'.csv';
    }

    public function employee(int $instituteId, ?int $branchId, array $filters): array
    {
        $data = $this->reports->employeeReport($instituteId,$branchId,$filters);
        $headers = ['Code','Name','Department','Designation','Branch','Status','Type','Joining'];
        $rows = (function() use ($data) {
            foreach ($data['rows'] as $e) {
                yield [$e->employee_code, $e->display_name, $e->department?->name ?? '', $e->designation?->name ?? '', $e->branch?->name ?? '', $e->employment_status, $e->employment_type ?? '', $e->joining_date?->format('Y-m-d') ?? ''];
            }
        })();
        return ['valid'=>true,'filename'=>$this->filename('employee-master',$filters),'headers'=>$headers,'rows'=>$rows];
    }

    public function attendance(int $instituteId, ?int $branchId, array $filters): array
    {
        $data = $this->reports->attendanceReport($instituteId,$branchId,$filters);
        $headers = ['Date','Employee','Status','Check In','Check Out','Overtime'];
        $rows = (function() use ($data) {
            foreach ($data['rows'] as $a) {
                yield [$a->attendance_date->format('Y-m-d'), $a->employee->display_name ?? $a->employee_id, $a->status, $a->check_in ?? '', $a->check_out ?? '', (string)($a->overtime_minutes ?? 0)];
            }
        })();
        return ['valid'=>true,'filename'=>$this->filename('attendance-summary',$filters),'headers'=>$headers,'rows'=>$rows];
    }

    public function leave(int $instituteId, ?int $branchId, array $filters): array
    {
        $data = $this->reports->leaveReport($instituteId,$branchId,$filters);
        $headers = ['Employee','Leave Type','Start','End','Days','Status'];
        // For export, fetch applications matching filters
        $apps = \App\Models\HrLeaveApplication::where('institute_id',$instituteId)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))
            ->whereBetween('start_date',[$data['from'],$data['to']])->with(['employee','leaveType'])->limit(1000)->get();
        $rows = (function() use ($apps) {
            foreach ($apps as $a) {
                yield [$a->employee->display_name ?? $a->employee_id, $a->leaveType?->name ?? '', $a->start_date->format('Y-m-d'), $a->end_date->format('Y-m-d'), (string)$a->days_count, $a->status];
            }
        })();
        return ['valid'=>true,'filename'=>$this->filename('leave-utilization',$filters),'headers'=>$headers,'rows'=>$rows];
    }

    public function payroll(int $instituteId, ?int $branchId, array $filters): array
    {
        $data = $this->reports->payrollReport($instituteId,$branchId,$filters);
        $headers = ['Payslip','Employee','Period','Gross','Deductions','Net','Status'];
        $rows = (function() use ($data) {
            foreach ($data['rows'] as $p) {
                yield [$p->payslip_no, $p->employee->display_name ?? $p->employee_id, $p->period?->name ?? '', (string)$p->gross_earnings, (string)$p->total_deductions, (string)$p->net_salary, $p->status];
            }
        })();
        return ['valid'=>true,'filename'=>$this->filename('payroll-register',$filters),'headers'=>$headers,'rows'=>$rows];
    }

    public function recruitment(int $instituteId, ?int $branchId, array $filters): array
    {
        $data = $this->reports->recruitmentReport($instituteId,$branchId,$filters);
        $headers = ['Vacancy','Applicants','Hired','Hiring Rate'];
        $rows = (function() use ($data) {
            yield [$data['vacancies'], $data['applicants'], $data['by_stage']['hired'] ?? 0, $data['hiring_rate'].'%'];
        })();
        return ['valid'=>true,'filename'=>$this->filename('recruitment',$filters),'headers'=>$headers,'rows'=>$rows];
    }

    public function performance(int $instituteId, ?int $branchId, array $filters): array
    {
        $data = $this->reports->performanceReport($instituteId,$branchId,$filters);
        $headers = ['Employee','Period','Overall','Status','Review Date'];
        $rows = (function() use ($data) {
            foreach ($data['rows'] as $r) {
                yield [$r->employee->display_name ?? $r->employee_id, $r->period?->name ?? '', (string)($r->overall_score ?? ''), $r->status, $r->review_date?->format('Y-m-d') ?? ''];
            }
        })();
        return ['valid'=>true,'filename'=>$this->filename('performance',$filters),'headers'=>$headers,'rows'=>$rows];
    }

    public function training(int $instituteId, ?int $branchId, array $filters): array
    {
        $data = $this->reports->trainingReport($instituteId,$branchId,$filters);
        $headers = ['Training','Enrollments','Completed','Completion Rate','Cost'];
        $rows = (function() use ($data) {
            yield ['Summary', $data['total_enrollments'], $data['completed'], $data['completion_rate'].'%', $data['total_cost']];
        })();
        return ['valid'=>true,'filename'=>$this->filename('training',$filters),'headers'=>$headers,'rows'=>$rows];
    }

    public function workforce(int $instituteId, ?int $branchId, array $filters): array
    {
        $data = $this->reports->workforceReport($instituteId,$branchId,$filters);
        $headers = ['Headcount','Active','New Hires','Resignations','Terminations','Turnover'];
        $rows = (function() use ($data) {
            yield [$data['headcount'],$data['active'],$data['new_hires'],$data['resignations'],$data['terminations'],$data['turnover_rate'].'%'];
        })();
        return ['valid'=>true,'filename'=>$this->filename('workforce',$filters),'headers'=>$headers,'rows'=>$rows];
    }
}
