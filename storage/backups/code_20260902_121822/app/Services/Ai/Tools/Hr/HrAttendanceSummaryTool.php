<?php

namespace App\Services\Ai\Tools\Hr;

use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;
use App\Services\HrReportService;

class HrAttendanceSummaryTool extends AbstractAiTool
{
    public function __construct(private readonly HrReportService $reports) {}
    public function name(): string { return 'get_hr_attendance_summary'; }
    public function description(): string { return 'Get attendance summary: totals by status, lateness, overtime, trends by branch/department. Tenant and branch scoped.'; }
    public function parameters(): array {
        return ['type'=>'object','properties'=>[
            'from'=>['type'=>'string','description'=>'YYYY-MM-DD'],
            'to'=>['type'=>'string','description'=>'YYYY-MM-DD'],
            'branch_id'=>['type'=>'integer'],
            'department_id'=>['type'=>'integer'],
            'limit'=>['type'=>'integer','description'=>'Rows 1-50'],
        ]];
    }
    public function permission(): ?string { return 'hr.attendance.view'; }
    public function handle(array $args, AiContext $ctx): array
    {
        $this->guard($ctx);
        $data = $this->reports->attendanceReport($ctx->instituteId(), $this->branchId($ctx), [
            'from'=>$this->dateArg($args,'from')?->toDateString(),
            'to'=>$this->dateArg($args,'to')?->toDateString(),
            'branch_id'=>$args['branch_id']??null,
            'department_id'=>$args['department_id']??null,
        ]);
        $rows = array_slice($data['rows']->toArray(),0,$this->limit($args));
        return $this->result(['total'=>$data['total'],'by_status'=>$data['by_status'],'late'=>$data['late'],'absent'=>$data['absent'],'overtime_minutes'=>$data['overtime_minutes'],'by_branch'=>$data['by_branch']], $rows);
    }
}
