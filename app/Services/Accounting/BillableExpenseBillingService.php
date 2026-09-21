<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Expense;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class BillableExpenseBillingService
{
    public function __construct(
        protected InvoiceService $invoiceService,
    ) {}

    public function generateInvoice(int $customerId, array $expenseIds, array $invoiceData, int $actorId): Invoice
    {
        return DB::transaction(function () use ($customerId, $expenseIds, $invoiceData, $actorId) {
            $expenses = Expense::withoutGlobalScopes()
                ->whereIn('id', $expenseIds)
                ->where('customer_id', $customerId)
                ->where('is_billable', true)
                ->where('billing_status', 'unbilled')
                ->get();

            if ($expenses->isEmpty()) {
                throw new \RuntimeException("No unbilled expenses found for this customer.");
            }

            $items = [];
            foreach ($expenses as $expense) {
                $items[] = [
                    'description' => $this->lineDescription($expense),
                    'quantity' => 1,
                    'unit_price' => (float) ($expense->billable_amount ?? $expense->amount),
                    'amount' => (float) ($expense->billable_amount ?? $expense->amount),
                ];
            }

            $instituteId = $expenses->first()->institute_id;
            $branchId = $expenses->first()->branch_id;

            $invoice = $this->invoiceService->create(
                $instituteId,
                $branchId,
                [
                    'party_id' => $customerId,
                    'invoice_type' => 'other',
                    'invoice_date' => $invoiceData['invoice_date'] ?? now()->toDateString(),
                    'due_date' => $invoiceData['due_date'] ?? now()->addDays(30)->toDateString(),
                    'items' => $items,
                    'notes' => $invoiceData['notes'] ?? "Expense reimbursement invoice",
                ],
                $actorId
            );

            foreach ($expenses as $expense) {
                $expense->update([
                    'billing_status' => 'billed',
                    'billed_invoice_id' => $invoice->id,
                    'billed_at' => now(),
                ]);
            }

            return $invoice;
        });
    }

    protected function lineDescription(Expense $expense): string
    {
        $parts = array_filter([
            $expense->expense_category ? '[' . $expense->categoryLabel() . ']' : null,
            $expense->description,
            $expense->vendor_name ? '— ' . $expense->vendor_name : null,
            $expense->expense_date ? '(' . $expense->expense_date->format('d M Y') . ')' : null,
        ]);
        return implode(' ', $parts) ?: 'Expense';
    }

    public function previewTotals(array $expenseIds): array
    {
        $expenses = Expense::withoutGlobalScopes()
            ->whereIn('id', $expenseIds)
            ->where('is_billable', true)
            ->where('billing_status', 'unbilled')
            ->get();

        $totalCost = 0;
        $totalBillable = 0;
        foreach ($expenses as $e) {
            $totalCost += (float) $e->amount;
            $totalBillable += (float) ($e->billable_amount ?? $e->amount);
        }

        return [
            'count' => $expenses->count(),
            'total_cost' => round($totalCost, 2),
            'total_billable' => round($totalBillable, 2),
            'total_markup' => round($totalBillable - $totalCost, 2),
        ];
    }
}
