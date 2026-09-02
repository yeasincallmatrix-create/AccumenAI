<?php

namespace App\Services\Ai\Tools\Education;

use App\Models\Student;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;

class StudentsTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'get_students';
    }

    public function description(): string
    {
        return 'List or count students of this institute. Can filter by status, branch, admission date '
            .'range and group the count by status or admission month. Returns a small summary plus recent rows.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['active', 'completed', 'dropped', 'suspended']],
                'branch_id' => ['type' => 'integer'],
                'admission_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'admission_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'group_by' => ['type' => 'string', 'enum' => ['none', 'status', 'month']],
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

        $query = Student::query()->where('institute_id', $context->instituteId());

        if (($branchId = $this->branchId($context)) !== null) {
            $query->where('students.branch_id', $branchId);
        }

        if (($status = $args['status'] ?? null) !== null) {
            $query->where('status', $status);
        }
        if (($branchId = $args['branch_id'] ?? null) !== null) {
            $query->where('branch_id', (int) $branchId);
        }
        if (($from = $this->dateArg($args, 'admission_from')) !== null) {
            $query->where('admission_date', '>=', $from);
        }
        if (($to = $this->dateArg($args, 'admission_to')) !== null) {
            $query->where('admission_date', '<=', $to->endOfDay());
        }

        $summary = ['total' => (clone $query)->count()];

        $group = $this->groupBy($args, ['status', 'month'], 'none');
        if ($group === 'status') {
            $summary['by_status'] = (clone $query)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();
        } elseif ($group === 'month') {
            $summary['by_month'] = (clone $query)
                ->selectRaw("DATE_FORMAT(admission_date, '%Y-%m') as month, count(*) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();
        }

        $rows = (clone $query)
            ->latest('id')
            ->limit($this->limit($args))
            ->get(['id', 'first_name', 'last_name', 'student_id_number', 'status', 'admission_date', 'phone'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->full_name,
                'student_id' => $s->student_id_number,
                'status' => $s->status,
                'admission_date' => $s->admission_date?->format('Y-m-d'),
                'phone' => $s->phone,
            ])
            ->all();

        return $this->result($summary, $rows);
    }
}
