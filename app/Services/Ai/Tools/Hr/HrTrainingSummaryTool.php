<?php

namespace App\Services\Ai\Tools\Hr;

use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;
use App\Services\HrReportService;

class HrTrainingSummaryTool extends AbstractAiTool
{
    public function __construct(private readonly HrReportService $reports) {}
    public function name(): string { return 'get_hr_training_summary'; }
    public function description(): string { return 'Get training summary: total trainings, enrollments, completion rate, cost, by status, skill gaps. Tenant and branch scoped.'; }
    public function parameters(): array {
        return ['type'=>'object','properties'=>[
            'from'=>['type'=>'string','description'=>'YYYY-MM-DD'],
            'to'=>['type'=>'string','description'=>'YYYY-MM-DD'],
            'limit'=>['type'=>'integer'],
        ]];
    }
    public function permission(): ?string { return 'hr.training.view'; }
    public function handle(array $args, AiContext $ctx): array
    {
        $this->guard($ctx);
        $data = $this->reports->trainingReport($ctx->instituteId(), $this->branchId($ctx), [
            'from'=>$this->dateArg($args,'from')?->toDateString(),
            'to'=>$this->dateArg($args,'to')?->toDateString(),
        ]);
        return $this->result(['total_trainings'=>$data['total_trainings'],'total_enrollments'=>$data['total_enrollments'],'completed'=>$data['completed'],'completion_rate'=>$data['completion_rate'],'total_cost'=>$data['total_cost'],'skill_gaps'=>$data['skill_gaps']]);
    }
}
