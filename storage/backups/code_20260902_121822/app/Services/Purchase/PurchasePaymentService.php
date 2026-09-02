<?php

namespace App\Services\Purchase;

use App\Models\Party;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseSupplierPayment;
use App\Services\Accounting\PurchaseAccountingService;
use App\Services\Accounting\AccountingAuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasePaymentService
{
    public function __construct(
        private readonly PurchaseAccountingService $purchaseAccounting,
        private readonly AccountingAuditService $audit,
    ) {}

    public function pay(int $instituteId, ?int $branchId, int $purchaseInvoiceId, array $data, int $actorId): PurchaseSupplierPayment
    {
        $invoice = PurchaseInvoice::withoutGlobalScopes()->where('institute_id', $instituteId)->where('id', $purchaseInvoiceId)->first();
        if (! $invoice) throw ValidationException::withMessages(['purchase_invoice_id' => 'Invoice not found.']);
        if ($branchId !== null && $invoice->branch_id !== null && (int) $invoice->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages(['purchase_invoice_id' => 'Invoice not in your branch.']);
        }
        if ($invoice->status !== PurchaseInvoice::STATUS_POSTED) throw ValidationException::withMessages(['status' => 'Only posted invoices can be paid.']);

        $amount = round((float) ($data['amount'] ?? 0), 4);
        if ($amount <= 0) throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);

        $due = round((float) $invoice->due_amount, 4);
        if ($amount - $due > 0.00005) throw ValidationException::withMessages(['amount' => "Overpayment rejected: due is {$due}."]);

        $supplier = Party::withoutGlobalScopes()->where('id', $invoice->supplier_id)->first();

        return DB::transaction(function () use ($instituteId, $branchId, $invoice, $supplier, $amount, $data, $actorId) {
            $invoice->refresh();
            // Re-check overpayment inside transaction with lock
            $dueNow = round((float) $invoice->due_amount, 4);
            if ($amount - $dueNow > 0.00005) throw ValidationException::withMessages(['amount' => "Overpayment rejected: due is {$dueNow}."]);

            $journal = $this->purchaseAccounting->postSupplierPayment(
                $instituteId,
                $branchId,
                $supplier,
                $amount,
                $data['payment_method_id'] ?? null,
                $data['payment_method'] ?? 'cash',
                $actorId,
                $data['paid_at'] ?? now()->toDateString(),
                $data['notes'] ?? 'Supplier payment for ' . $invoice->invoice_number,
                $invoice->id,
                ['currency_id' => $invoice->currency_id]
            );

            $payment = PurchaseSupplierPayment::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'purchase_invoice_id' => $invoice->id,
                'supplier_id' => $invoice->supplier_id,
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'journal_id' => $journal->id,
                'paid_at' => $data['paid_at'] ?? now(),
                'created_by' => $actorId,
            ]);

            $newPaid = round((float) $invoice->paid_amount + $amount, 4);
            $newDue = round((float) $invoice->grand_total - $newPaid, 4);
            $invoice->update([
                'paid_amount' => $newPaid,
                'due_amount' => $newDue,
                'updated_by' => $actorId,
            ]);

            $this->audit->log($instituteId, [
                'branch_id' => $branchId,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'create',
                'entity_type' => 'supplier_payment',
                'entity_id' => $payment->id,
                'after_payload' => ['invoice' => $invoice->invoice_number, 'amount' => $amount, 'journal' => $journal->journal_no],
            ]);

            return $payment->load('journal');
        });
    }

    public function reverse(PurchaseSupplierPayment $payment, int $actorId, ?string $reason = null): void
    {
        $invoice = $payment->purchaseInvoice;
        if (! $invoice) throw ValidationException::withMessages(['payment' => 'Linked invoice not found.']);

        DB::transaction(function () use ($payment, $invoice, $actorId, $reason) {
            if ($payment->journal_id === null) throw ValidationException::withMessages(['payment' => 'Payment has no journal to reverse.']);

            $journal = $payment->journal;
            $this->purchaseAccounting->reversePurchase($journal, $invoice->institute_id, $actorId, $reason ?? 'Supplier payment reversed');

            $amount = (float) $payment->amount;
            $newPaid = max(0.0, (float) $invoice->paid_amount - $amount);
            $newDue = round((float) $invoice->grand_total - $newPaid, 4);
            $invoice->update([
                'paid_amount' => $newPaid,
                'due_amount' => $newDue,
                'updated_by' => $actorId,
            ]);

            $payment->delete();

            $this->audit->log($invoice->institute_id, [
                'branch_id' => $invoice->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'reverse',
                'entity_type' => 'supplier_payment',
                'entity_id' => $payment->id,
                'after_payload' => ['invoice' => $invoice->invoice_number, 'amount' => $amount, 'reason' => $reason],
            ]);
        });
    }
}
