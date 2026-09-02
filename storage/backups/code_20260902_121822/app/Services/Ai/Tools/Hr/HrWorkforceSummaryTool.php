<?php

namespace App\Services\Ai\Tools\Hr;

use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;
use App\Services\HrReportService;

class HrWorkforceSummaryTool extends AbstractAiTool
{
    public function __construct(private readonly HrReportService $reports) {}
    public function name(): string { return 'get_hr_workforce_summary'; }
    public function description(): string { return 'Get workforce summary: headcount, active/inactive, new hires, resignations, terminations, turnover rate. Tenant and branch scoped.'; }
    public function parameters(): array {
        return ['type'=>'object','properties'=>[
            'from'=>['type'=>'string','description'=>'YYYY-MM-DD'],
            'to'=>['type'=>'string','description'=>'YYYY-MM-DD'],
        ]];
    }
    public function permission(): ?string { return 'hr.employee.view'; }
    public function handle(array $args, AiContext $ctx): array
    {
        $this->guard($ctx);
        $data = $this->reports->workforceReport($ctx->instituteId(), $this->branchId($ctx), [
            'from'=>$this->dateArg($args,'from')?->toDateString(),
            'to'=>$this->dateArg($args,'to')?->toDateString(),
        ]);
        return $this->result(['headcount'=>$data['headcount'],'active'=>$data['active'],'new_hires'=>$data['new_hires'],'resignations'=>$data['resignations'],'terminations'=>$data['terminations'],'turnover_rate'=>$data['turnover_rate'],'trend'=>$data['trend']]);
    }
}
