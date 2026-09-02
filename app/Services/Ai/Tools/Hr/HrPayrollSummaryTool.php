<?php

namespace App\Services\Ai\Tools\Hr;

use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;
use App\Services\HrReportService;

class HrPayrollSummaryTool extends AbstractAiTool
{
    public function __construct(private readonly HrReportService $reports) {}
    public function name(): string { return 'get_hr_payroll_summary'; }
    public function description(): string { return 'Get payroll summary: total gross/net, deductions, outstanding, by branch/department. Salary amounts only if actor has payroll permission.'; }
    public function parameters(): array {
        return ['type'=>'object','properties'=>[
            'from'=>['type'=>'string','description'=>'YYYY-MM-DD'],
            'to'=>['type'=>'string','description'=>'YYYY-MM-DD'],
            'branch_id'=>['type'=>'integer'],
            'limit'=>['type'=>'integer'],
        ]];
    }
    public function permission(): ?string { return 'hr.payroll.view'; }
    public function handle(array $args, AiContext $ctx): array
    {
        $this->guard($ctx);
        // Salary privacy: if actor lacks payroll view, still allow but hide amounts? Here permission already gated, so if they call, they have view.
        // If they have view but not approve, still show totals (they have view).
        $data = $this->reports->payrollReport($ctx->instituteId(), $this->branchId($ctx), [
            'from'=>$this->dateArg($args,'from')?->toDateString(),
            'to'=>$this->dateArg($args,'to')?->toDateString(),
            'branch_id'=>$args['branch_id']??null,
        ]);
        // Bound rows
        $rows = array_slice($data['rows']->toArray(),0,$this->limit($args));
        $summary = ['total_gross'=>$data['total_gross'],'total_net'=>$data['total_net'],'total_deductions'=>$data['total_deductions'],'outstanding'=>$data['outstanding'],'by_branch'=>$data['by_branch']];
        // If actor lacks finance permission, mask salary? But payroll view already includes salary.
        if (!$ctx->hasPermission('hr.payroll.view') && !$ctx->hasPermission('hr.salary.view')) {
            $summary = ['total_gross'=>'[restricted]','total_net'=>'[restricted]'];
        }
        return $this->result($summary, $rows);
    }
}
