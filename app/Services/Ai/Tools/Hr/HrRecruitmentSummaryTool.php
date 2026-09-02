<?php

namespace App\Services\Ai\Tools\Hr;

use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;
use App\Services\HrReportService;

class HrRecruitmentSummaryTool extends AbstractAiTool
{
    public function __construct(private readonly HrReportService $reports) {}
    public function name(): string { return 'get_hr_recruitment_summary'; }
    public function description(): string { return 'Get recruitment summary: vacancies, applicants, pipeline by stage, hiring rate, by source/department. Tenant and branch scoped.'; }
    public function parameters(): array {
        return ['type'=>'object','properties'=>[
            'from'=>['type'=>'string','description'=>'YYYY-MM-DD'],
            'to'=>['type'=>'string','description'=>'YYYY-MM-DD'],
            'limit'=>['type'=>'integer'],
        ]];
    }
    public function permission(): ?string { return 'hr.recruitment.view'; }
    public function handle(array $args, AiContext $ctx): array
    {
        $this->guard($ctx);
        $data = $this->reports->recruitmentReport($ctx->instituteId(), $this->branchId($ctx), [
            'from'=>$this->dateArg($args,'from')?->toDateString(),
            'to'=>$this->dateArg($args,'to')?->toDateString(),
        ]);
        return $this->result(['vacancies'=>$data['vacancies'],'applicants'=>$data['applicants'],'hiring_rate'=>$data['hiring_rate'],'by_stage'=>$data['by_stage'],'by_source'=>$data['by_source']]);
    }
}
