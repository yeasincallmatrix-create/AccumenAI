<?php

namespace App\Services\Ai\Tools\Education;

use App\Models\Invoice;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;
use Illuminate\Support\Carbon;

class FeesTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'get_fees';
    }

    public function description(): string
    {
        return 'Summarise student fees from invoices: total invoiced, total paid, total due, and overdue invoices '
            .'(unpaid/partial past their due date). Filter by payment status or creation date range. Returns a small '
            .'summary plus recent invoices.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'invoice_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'invoice_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'payment_status' => ['type' => 'string', 'enum' => ['paid', 'partial', 'unpaid', 'overdue']],
                'limit' => ['type' => 'integer', 'description' => 'Rows to return, 1-50'],
            ],
        ];
    }

    public function permission(): string
    {
        return 'finance.view';
    }

    public function handle(array $args, AiContext $context): array
    {
        $this->guard($context);

        $query = Invoice::query()
            ->with('student:id,full_name')
            ->where('institute_id', $context->instituteId());

        if (($branchId = $this->branchId($context)) !== null) {
            $query->whereHas('student', fn ($q) => $q->where('branch_id', $branchId));
        }

        if (($from = $this->dateArg($args, 'invoice_from')) !== null) {
            $query->where('created_at', '>=', $from);
        }
        if (($to = $this->dateArg($args, 'invoice_to')) !== null) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        $status = $args['payment_status'] ?? null;
        if ($status === 'overdue') {
            $query->whereIn('status', ['unpaid', 'partial'])
                ->where('due_date', '<', Carbon::today());
        } elseif ($status !== null) {
            $query->where('status', $status);
        }

        $summary = [
            'total_invoices' => (clone $query)->count(),
            'total_amount' => round((float) (clone $query)->sum('total_amount'), 2),
            'total_paid' => round((float) (clone $query)->sum('paid_amount'), 2),
            'total_due' => round((float) (clone $query)->sum('due_amount'), 2),
        ];

        $summary['overdue_invoices'] = Invoice::query()
            ->where('institute_id', $context->instituteId())
            ->whereIn('status', ['unpaid', 'partial'])
            ->where('due_date', '<', Carbon::today())
            ->count();

        $rows = (clone $query)
            ->latest('id')
            ->limit($this->limit($args))
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'invoice_no' => $i->invoice_number,
                'student' => $i->student?->full_name,
                'date' => $i->created_at?->format('Y-m-d'),
                'total' => (float) $i->total_amount,
                'paid' => (float) $i->paid_amount,
                'due' => (float) $i->due_amount,
                'payment_status' => $i->status,
            ])
            ->all();

        return $this->result($summary, $rows);
    }
}
