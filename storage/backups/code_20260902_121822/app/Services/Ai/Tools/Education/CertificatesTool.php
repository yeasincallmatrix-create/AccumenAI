<?php

namespace App\Services\Ai\Tools\Education;

use App\Models\Certificate;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;

class CertificatesTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'get_certificates';
    }

    public function description(): string
    {
        return 'Summarise certificates: total and how many per status (pending/active/rejected/revoked), plus recent '
            .'certificates with the student and course. Filter by status or issue date range.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['pending', 'active', 'rejected', 'revoked']],
                'issued_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'issued_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'limit' => ['type' => 'integer', 'description' => 'Rows to return, 1-50'],
            ],
        ];
    }

    public function permission(): string
    {
        return 'certificates.manage';
    }

    public function handle(array $args, AiContext $context): array
    {
        $this->guard($context);

        $query = Certificate::query()
            ->with(['student:id,full_name', 'course:id,name'])
            ->where('institute_id', $context->instituteId());

        if (($branchId = $this->branchId($context)) !== null) {
            $query->whereHas('student', fn ($q) => $q->where('branch_id', $branchId));
        }

        if (($status = $args['status'] ?? null) !== null) {
            $query->where('status', $status);
        }
        if (($from = $this->dateArg($args, 'issued_from')) !== null) {
            $query->where('issue_date', '>=', $from);
        }
        if (($to = $this->dateArg($args, 'issued_to')) !== null) {
            $query->where('issue_date', '<=', $to->endOfDay());
        }

        $summary = [
            'total_certificates' => (clone $query)->count(),
            'by_status' => (clone $query)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray(),
        ];

        $rows = (clone $query)
            ->latest('id')
            ->limit($this->limit($args))
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'certificate_no' => $c->certificate_number,
                'student' => $c->student?->full_name,
                'course' => $c->course?->name,
                'issue_date' => $c->issue_date?->format('Y-m-d'),
                'status' => $c->status,
            ])
            ->all();

        return $this->result($summary, $rows);
    }
}
