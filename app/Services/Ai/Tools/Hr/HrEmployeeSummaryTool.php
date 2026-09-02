<?php

namespace App\Services\Ai\Tools\Hr;

use App\Models\HrEmployee;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;

class HrEmployeeSummaryTool extends AbstractAiTool
{
    public function name(): string { return 'get_hr_employee_summary'; }
    public function description(): string { return 'Get HR employee summary: total, by status/branch/department, new hires, recent employees. Tenant and branch scoped.'; }
    public function parameters(): array {
        return [
            'type'=>'object',
            'properties'=>[
                'status'=>['type'=>'string','enum'=>['active','inactive','suspended','resigned','terminated']],
                'branch_id'=>['type'=>'integer'],
                'department_id'=>['type'=>'integer'],
                'from'=>['type'=>'string','description'=>'YYYY-MM-DD joining from'],
                'to'=>['type'=>'string','description'=>'YYYY-MM-DD joining to'],
                'group_by'=>['type'=>'string','enum'=>['none','status','department','branch','month']],
                'limit'=>['type'=>'integer','description'=>'Rows 1-50'],
            ],
        ];
    }
    public function permission(): ?string { return 'hr.employee.view'; }

    public function handle(array $args, AiContext $ctx): array
    {
        $this->guard($ctx);
        $q = HrEmployee::query()->where('hr_employees.institute_id',$ctx->instituteId());
        if (($bid=$this->branchId($ctx))!==null) $q->where('hr_employees.branch_id',$bid);
        if (!empty($args['branch_id'])) $q->where('hr_employees.branch_id',(int)$args['branch_id']);
        if (!empty($args['department_id'])) $q->where('hr_employees.department_id',(int)$args['department_id']);
        if (!empty($args['status'])) $q->where('hr_employees.employment_status',$args['status']);
        if ($f=$this->dateArg($args,'from')) $q->whereDate('hr_employees.joining_date','>=',$f);
        if ($t=$this->dateArg($args,'to')) $q->whereDate('hr_employees.joining_date','<=',$t);

        $summary=['total'=>(clone $q)->count()];
        $group=$this->groupBy($args,['status','department','branch','month'],'none');
        if ($group==='status') $summary['by_status']=(clone $q)->selectRaw('employment_status, COUNT(*) as c')->groupBy('employment_status')->pluck('c','employment_status')->all();
        if ($group==='department') $summary['by_department']=(clone $q)->selectRaw('department_id, COUNT(*) as c')->groupBy('department_id')->pluck('c','department_id')->all();
        if ($group==='branch') $summary['by_branch']=(clone $q)->whereNotNull('branch_id')->selectRaw('branch_id, COUNT(*) as c')->groupBy('branch_id')->pluck('c','branch_id')->all();
        if ($group==='month') $summary['by_month']=(clone $q)->selectRaw("DATE_FORMAT(joining_date,'%Y-%m') as m, COUNT(*) as c")->groupBy('m')->orderBy('m')->pluck('c','m')->all();

        $rows=(clone $q)->with(['branch','department','designation'])->orderByDesc('hr_employees.id')->limit($this->limit($args))->get()->map(fn($e)=>[
            'employee_code'=>$e->employee_code,'name'=>$e->display_name,'department'=>$e->department?->name,'designation'=>$e->designation?->name,'branch'=>$e->branch?->name,'status'=>$e->employment_status,'joining'=>$e->joining_date?->format('Y-m-d'),
        ])->all();

        return $this->result($summary,$rows);
    }
}
