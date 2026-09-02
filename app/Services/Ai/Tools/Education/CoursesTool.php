<?php

namespace App\Services\Ai\Tools\Education;

use App\Models\InstituteCourse;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;

class CoursesTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'get_courses';
    }

    public function description(): string
    {
        return 'List the courses assigned to this institute with fee, category, mode (offline/online/hybrid) '
            .'and status. Returns a small summary plus recent rows.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'draft']],
                'category_id' => ['type' => 'integer'],
                'limit' => ['type' => 'integer', 'description' => 'Rows to return, 1-50'],
            ],
        ];
    }

    public function permission(): string
    {
        return 'courses.view';
    }

    public function handle(array $args, AiContext $context): array
    {
        $this->guard($context);

        $query = InstituteCourse::query()
            ->with('course.category:id,name')
            ->where('institute_id', $context->instituteId());

        if (($status = $args['status'] ?? null) !== null) {
            $query->whereHas('course', fn ($q) => $q->where('status', $status));
        }
        if (($categoryId = $args['category_id'] ?? null) !== null) {
            $query->whereHas('course', fn ($q) => $q->where('category_id', (int) $categoryId));
        }

        $summary = ['total_courses' => (clone $query)->count()];

        $rows = (clone $query)
            ->latest('id')
            ->limit($this->limit($args))
            ->get()
            ->map(fn ($ic) => [
                'id' => $ic->course->id,
                'name' => $ic->course->name,
                'code' => $ic->course->course_code,
                'category' => $ic->course->category?->name,
                'fee' => (float) $ic->course->fee,
                'discount' => (float) $ic->course->discount,
                'mode' => $ic->course->mode,
                'status' => $ic->course->status,
            ])
            ->all();

        return $this->result($summary, $rows);
    }
}
