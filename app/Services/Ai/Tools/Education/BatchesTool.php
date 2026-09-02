<?php

namespace App\Services\Ai\Tools\Education;

use App\Models\Batch;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;
use Illuminate\Support\Carbon;

class BatchesTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'get_batches';
    }

    public function description(): string
    {
        return 'List batches of this institute with their course, shift, start date, seats filled and status '
            .'(upcoming/ongoing/completed/cancelled). Returns a small summary plus recent rows.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['upcoming', 'ongoing', 'completed', 'cancelled']],
                'course_id' => ['type' => 'integer'],
                'limit' => ['type' => 'integer', 'description' => 'Rows to return, 1-50'],
            ],
        ];
    }

    public function permission(): string
    {
        return 'batches.view';
    }

    public function handle(array $args, AiContext $context): array
    {
        $this->guard($context);

        $query = Batch::query()
            ->with('course:id,name')
            ->where('institute_id', $context->instituteId());

        if (($branchId = $this->branchId($context)) !== null) {
            $query->where('batches.branch_id', $branchId);
        }

        if (($status = $args['status'] ?? null) !== null) {
            $query->where('status', $status);
        }
        if (($courseId = $args['course_id'] ?? null) !== null) {
            $query->where('course_id', (int) $courseId);
        }

        $summary = ['total_batches' => (clone $query)->count()];

        $rows = (clone $query)
            ->latest('id')
            ->limit($this->limit($args))
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->batch_code,
                'course' => $b->course?->name,
                'shift' => $b->shift,
                'start_date' => $b->start_date
                    ? Carbon::parse($b->start_date)->format('Y-m-d')
                    : null,
                'seats_filled' => (int) $b->seat_filled,
                'seat_capacity' => (int) $b->seat_capacity,
                'status' => $b->status,
            ])
            ->all();

        return $this->result($summary, $rows);
    }
}
