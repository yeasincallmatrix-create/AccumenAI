<?php

namespace App\Services\Ai\Tools\Education;

use App\Models\Training\Enrollment;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;
use Illuminate\Support\Carbon;

class EnrollmentTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'get_course_enrollment';
    }

    public function description(): string
    {
        return 'Summarise student enrollment: total enrollments, how many are grouped by course or by enrollment '
            .'month, plus the most recent enrollments. Filter by course or batch.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'course_id' => ['type' => 'integer'],
                'batch_id' => ['type' => 'integer'],
                'group_by' => ['type' => 'string', 'enum' => ['none', 'course', 'month']],
                'limit' => ['type' => 'integer', 'description' => 'Rows to return, 1-50'],
            ],
        ];
    }

    public function permission(): string
    {
        return 'students.view';
    }

    public function handle(array $args, AiContext $context): array
    {
        $this->guard($context);

        $base = Enrollment::query()->where('enrollments.institute_id', $context->instituteId());

        if (($branchId = $this->branchId($context)) !== null) {
            $base->whereHas('batch', fn ($q) => $q->where('branch_id', $branchId));
        }

        if (($courseId = $args['course_id'] ?? null) !== null) {
            $base->whereHas('batch', fn ($q) => $q->where('course_id', (int) $courseId));
        }
        if (($batchId = $args['batch_id'] ?? null) !== null) {
            $base->where('batch_id', (int) $batchId);
        }

        $summary = ['total_enrollments' => (clone $base)->count()];

        $group = $this->groupBy($args, ['course', 'month'], 'none');
        if ($group === 'course') {
            $summary['by_course'] = (clone $base)
                ->join('batches', 'batches.id', '=', 'enrollments.batch_id')
                ->join('courses', 'courses.id', '=', 'batches.course_id')
                ->selectRaw('courses.name as course, count(*) as total')
                ->groupBy('courses.name')
                ->orderByDesc('total')
                ->limit(15)
                ->pluck('total', 'course')
                ->toArray();
        } elseif ($group === 'month') {
            $summary['by_month'] = (clone $base)
                ->selectRaw("DATE_FORMAT(enrollment_date, '%Y-%m') as month, count(*) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();
        }

        $rows = (clone $base)
            ->with(['batch:id,name'])
            ->latest('id')
            ->limit($this->limit($args))
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'student_id' => $e->student_id,
                'course' => $e->batch?->course?->name,
                'batch' => $e->batch?->name,
                'enrollment_date' => $e->enrollment_date
                    ? Carbon::parse($e->enrollment_date)->format('Y-m-d')
                    : null,
                'status' => $e->status,
            ])
            ->all();

        return $this->result($summary, $rows);
    }
}
