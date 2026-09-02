<?php

namespace App\Services\Ai\Tools\Hr;

use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;
use App\Services\HrReportService;

class HrLeaveSummaryTool extends AbstractAiTool
{
    public function __construct(private readonly HrReportService $reports) {}
    public function name(): string { return 'get_hr_leave_summary'; }
    public function description(): string { return 'Get leave summary: utilization, balances, pending approvals, trends by type. Tenant and branch scoped.'; }
    public function parameters(): array {
        return ['type'=>'object','properties'=>[
            'from'=>['type'=>'string','description'=>'YYYY-MM-DD'],
            'to'=>['type'=>'string','description'=>'YYYY-MM-DD'],
            'limit'=>['type'=>'integer'],
        ]];
    }
    public function permission(): ?string { return 'hr.leave.view'; }
    public function handle(array $args, AiContext $ctx): array
    {
        $this->guard($ctx);
        $data = $this->reports->leaveReport($ctx->instituteId(), $this->branchId($ctx), [
            'from'=>$this->dateArg($args,'from')?->toDateString(),
            'to'=>$this->dateArg($args,'to')?->toDateString(),
        ]);
        return $this->result(['total'=>$data['total'],'pending'=>$data['pending'],'by_status'=>$data['by_status'],'utilization'=>$data['utilization']]);
    }
}
