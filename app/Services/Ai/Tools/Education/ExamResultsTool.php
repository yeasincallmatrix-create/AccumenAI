<?php

namespace App\Services\Ai\Tools\Education;

use App\Models\Result;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;

class ExamResultsTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'get_exam_results';
    }

    public function description(): string
    {
        return 'Summarise exam results: total results, pass rate and average percentage, optionally grouped by '
            .'course or by month, plus recent results. Filter by course, batch or publish date range.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'course_id' => ['type' => 'integer'],
                'batch_id' => ['type' => 'integer'],
                'published_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'published_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'group_by' => ['type' => 'string', 'enum' => ['none', 'course', 'month']],
                'limit' => ['type' => 'integer', 'description' => 'Rows to return, 1-50'],
            ],
        ];
    }

    public function permission(): string
    {
        return 'exams.manage';
    }

    public function handle(array $args, AiContext $context): array
    {
        $this->guard($context);

        $base = Result::query()->where('results.institute_id', $context->instituteId());

        if (($branchId = $this->branchId($context)) !== null) {
            $base->whereHas('batch', fn ($q) => $q->where('branch_id', $branchId));
        }

        if (($courseId = $args['course_id'] ?? null) !== null) {
            $base->where('course_id', (int) $courseId);
        }
        if (($batchId = $args['batch_id'] ?? null) !== null) {
            $base->where('batch_id', (int) $batchId);
        }
        if (($from = $this->dateArg($args, 'published_from')) !== null) {
            $base->where('published_at', '>=', $from);
        }
        if (($to = $this->dateArg($args, 'published_to')) !== null) {
            $base->where('published_at', '<=', $to->endOfDay());
        }

        $summary = [
            'total_results' => (clone $base)->count(),
            'pass_count' => (clone $base)->where('result_status', 'pass')->count(),
            'average_percentage' => round((float) (clone $base)->avg('percentage'), 1),
        ];

        $group = $this->groupBy($args, ['course', 'month'], 'none');
        if ($group === 'course') {
            $summary['by_course'] = (clone $base)
                ->join('courses', 'courses.id', '=', 'results.course_id')
                ->selectRaw('courses.name as course, count(*) as total')
                ->groupBy('courses.name')
                ->orderByDesc('total')
                ->limit(15)
                ->pluck('total', 'course')
                ->toArray();
        } elseif ($group === 'month') {
            $summary['by_month'] = (clone $base)
                ->selectRaw("DATE_FORMAT(published_at, '%Y-%m') as month, count(*) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();
        }

        $rows = (clone $base)
            ->with(['course:id,name', 'batch:id,name', 'student:id,full_name'])
            ->latest('id')
            ->limit($this->limit($args))
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'student' => $r->student?->full_name,
                'course' => $r->course?->name,
                'batch' => $r->batch?->name,
                'percentage' => (float) $r->percentage,
                'grade' => $r->grade,
                'result_status' => $r->result_status,
                'published_at' => $r->published_at?->format('Y-m-d'),
            ])
            ->all();

        return $this->result($summary, $rows);
    }
}
