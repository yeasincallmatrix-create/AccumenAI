<?php

namespace App\Services\Ai\Tools\Hr;

use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;
use App\Services\HrReportService;

class HrPerformanceSummaryTool extends AbstractAiTool
{
    public function __construct(private readonly HrReportService $reports) {}
    public function name(): string { return 'get_hr_performance_summary'; }
    public function description(): string { return 'Get performance summary: review completion, avg score, by status/period. Tenant and branch scoped.'; }
    public function parameters(): array {
        return ['type'=>'object','properties'=>[
            'from'=>['type'=>'string','description'=>'YYYY-MM-DD'],
            'to'=>['type'=>'string','description'=>'YYYY-MM-DD'],
            'limit'=>['type'=>'integer'],
        ]];
    }
    public function permission(): ?string { return 'hr.performance.view'; }
    public function handle(array $args, AiContext $ctx): array
    {
        $this->guard($ctx);
        $data = $this->reports->performanceReport($ctx->instituteId(), $this->branchId($ctx), [
            'from'=>$this->dateArg($args,'from')?->toDateString(),
            'to'=>$this->dateArg($args,'to')?->toDateString(),
        ]);
        $rows = array_slice($data['rows']->toArray(),0,$this->limit($args));
        return $this->result(['total'=>$data['total'],'avg_score'=>$data['avg_score'],'by_status'=>$data['by_status']], $rows);
    }
}
