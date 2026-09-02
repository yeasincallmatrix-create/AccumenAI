<?php

namespace App\Services\Ai\Tools\Education;

use App\Models\Attendance;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;
use Illuminate\Support\Carbon;

class AttendanceTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'get_attendance';
    }

    public function description(): string
    {
        return 'Summarise student attendance: present, late, absent and leave counts and the present rate over a '
            .'date range, optionally grouped by day, plus recent attendance rows. Filter by batch.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'batch_id' => ['type' => 'integer'],
                'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'group_by' => ['type' => 'string', 'enum' => ['none', 'day']],
                'limit' => ['type' => 'integer', 'description' => 'Rows to return, 1-50'],
            ],
        ];
    }

    public function permission(): string
    {
        return 'attendance.manage';
    }

    public function handle(array $args, AiContext $context): array
    {
        $this->guard($context);

        $query = Attendance::query()
            ->with(['batch:id,name', 'student:id,full_name'])
            ->where('institute_id', $context->instituteId());

        if (($branchId = $this->branchId($context)) !== null) {
            $query->whereHas('student', fn ($q) => $q->where('branch_id', $branchId));
        }

        if (($batchId = $args['batch_id'] ?? null) !== null) {
            $query->where('batch_id', (int) $batchId);
        }
        if (($from = $this->dateArg($args, 'from')) !== null) {
            $query->where('class_date', '>=', $from);
        }
        if (($to = $this->dateArg($args, 'to')) !== null) {
            $query->where('class_date', '<=', $to->endOfDay());
        }

        $present = (clone $query)->where('status', 'present')->count();
        $late = (clone $query)->where('status', 'late')->count();
        $absent = (clone $query)->where('status', 'absent')->count();
        $leave = (clone $query)->where('status', 'leave')->count();
        $total = $present + $late + $absent + $leave;

        $summary = [
            'total_records' => $total,
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'leave' => $leave,
            'present_rate' => $total > 0 ? round($present / $total * 100, 1) : 0,
        ];

        if ($this->groupBy($args, ['day'], 'none') === 'day') {
            $summary['by_day'] = (clone $query)
                ->selectRaw('class_date as day, count(*) as total')
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn ($row) => [
                    'day' => $row->day,
                    'total' => (int) $row->total,
                ])
                ->take(15)
                ->all();
        }

        $rows = (clone $query)
            ->latest('id')
            ->limit($this->limit($args))
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'student' => $a->student?->full_name,
                'batch' => $a->batch?->name,
                'date' => $a->class_date ? Carbon::parse($a->class_date)->format('Y-m-d') : null,
                'status' => $a->status,
            ])
            ->all();

        return $this->result($summary, $rows);
    }
}
