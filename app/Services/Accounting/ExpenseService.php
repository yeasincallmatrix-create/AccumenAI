<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        protected JournalPostingService $journalService,
        protected \App\Services\Accounting\AccountingAuditService $audit,
    ) {}

    public function create(array $data, int $actorId): Expense
    {
        return DB::transaction(function () use ($data, $actorId) {
            $data['expense_number'] = $data['expense_number']
                ?? $this->generateExpenseNumber($data['institute_id']);
            $data['is_billable'] = (bool) ($data['is_billable'] ?? false);
            $data['billing_status'] = $data['is_billable'] ? 'unbilled' : 'unbillable';

            if ($data['is_billable']) {
                $data['billable_amount'] = round(
                    (float) $data['amount'] * (1 + ((float) ($data['markup_percentage'] ?? 0)) / 100),
                    2
                );
            }

            $expense = Expense::create($data);

            $je = $this->journalService->postExpenseEntry($expense, $actorId);
            $expense->update(['journal_entry_id' => $je?->id]);

            $this->audit->log($expense->institute_id, [
                'branch_id' => $expense->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'create',
                'entity_type' => 'expense',
                'entity_id' => $expense->id,
                'after_payload' => [
                    'expense_number' => $expense->expense_number,
                    'amount' => $expense->amount,
                    'is_billable' => $expense->is_billable,
                ],
            ]);

            return $expense->fresh();
        });
    }

    public function update(Expense $expense, array $data, int $actorId): Expense
    {
        if ($expense->isBilled()) {
            throw new \RuntimeException("Cannot edit a billed expense.");
        }

        return DB::transaction(function () use ($expense, $data, $actorId) {
            if (isset($data['is_billable'])) {
                $data['is_billable'] = (bool) $data['is_billable'];
                $data['billing_status'] = $data['is_billable'] ? 'unbilled' : 'unbillable';
            }
            $expense->update($data);

            if ($expense->is_billable) {
                $expense->update(['billable_amount' => $expense->computeBillableAmount()]);
            }

            $this->audit->log($expense->institute_id, [
                'branch_id' => $expense->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'update',
                'entity_type' => 'expense',
                'entity_id' => $expense->id,
                'after_payload' => ['expense_number' => $expense->expense_number],
            ]);

            return $expense->fresh();
        });
    }

    public function markBillable(Expense $expense, int $customerId, float $markupPercentage = 0): Expense
    {
        if ($expense->isBilled()) {
            throw new \RuntimeException("Already billed.");
        }

        $expense->update([
            'is_billable' => true,
            'customer_id' => $customerId,
            'markup_percentage' => $markupPercentage,
            'billable_amount' => round((float) $expense->amount * (1 + $markupPercentage / 100), 2),
            'billing_status' => 'unbilled',
        ]);

        $this->audit->log($expense->institute_id, [
            'branch_id' => $expense->branch_id,
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'update',
            'entity_type' => 'expense',
            'entity_id' => $expense->id,
            'after_payload' => [
                'is_billable' => true,
                'customer_id' => $customerId,
                'markup_percentage' => $markupPercentage,
            ],
        ]);

        return $expense->fresh();
    }

    public function revertBilling(Expense $expense): Expense
    {
        if (!$expense->isBilled()) {
            return $expense;
        }

        $expense->update([
            'billing_status' => 'unbilled',
            'billed_invoice_id' => null,
            'billed_at' => null,
        ]);

        return $expense->fresh();
    }

    public function delete(Expense $expense): void
    {
        if ($expense->isBilled()) {
            throw new \RuntimeException("Cannot delete a billed expense.");
        }

        DB::transaction(function () use ($expense) {
            if ($expense->journal_entry_id) {
                $this->journalService->reverseExpenseEntry($expense, auth()->id());
            }
            $expense->delete();
        });
    }

    public function bulkMarkBillable(array $expenseIds, int $customerId, float $markupPercentage = 0): int
    {
        $count = 0;
        foreach (Expense::whereIn('id', $expenseIds)->get() as $expense) {
            if (!$expense->isBilled()) {
                $this->markBillable($expense, $customerId, $markupPercentage);
                $count++;
            }
        }
        return $count;
    }

    protected function generateExpenseNumber(int $instituteId): string
    {
        $year = date('Y');
        $prefix = 'EXP-' . $year . '-';

        $last = Expense::where('institute_id', $instituteId)
            ->where('expense_number', 'like', $prefix . '%')
            ->orderByDesc('expense_number')
            ->value('expense_number');

        if ($last) {
            $seq = (int) substr($last, -5) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
