<?php

namespace App\Services\Accounting;

use App\Models\AccountingSetting;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Payment recording against invoices (AR receipts).
 *
 * Money is never accepted in excess of the invoice due amount (overpayment is
 * rejected with a 422). Recording a payment:
 *   - posts a RECEIPT journal (debit cash/bank account of the method / credit
 *     Accounts Receivable with the invoice party);
 *   - creates the payments row (legacy payment_method enum kept for
 *     education/legacy compatibility alongside payment_method_id);
 *   - atomically updates invoice paid/due amounts and status, and settles
 *     installments oldest-first.
 *
 * Reversing a payment reverses the receipt journal and restores the invoice
 * balances — no hard delete of posted records.
 */
class PaymentService
{
    public function __construct(
        private readonly JournalPostingService $posting,
        private readonly AccountingAuditService $audit,
        private readonly NotificationService $notifications,
        private readonly FxConversionService $fx,
        private readonly RealizedFxService $realizedFx,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): Payment
    {
        $data = $this->validate($instituteId, $branchId, $data);

        $payment = DB::transaction(function () use ($instituteId, $branchId, $data, $actorId) {
            $invoiceId = $data['invoice']->id;

            $invoice = Invoice::query()->where('id', $invoiceId)->lockForUpdate()->first();

            $amount = round((float) $data['amount'], 4);

            $fxMeta = $this->resolvePaymentFx($invoice, $branchId, $data, $amount);

            $this->assertNoOverpayment($invoice, (float) $fxMeta['applied_amount']);

            $payment = (new Payment())->forceFill([
                'institute_id' => $instituteId,
                'invoice_id' => $invoice->id,
                'party_id' => $invoice->party_id,
                'installment_id' => $data['installment_id'] ?? null,
                'student_id' => $invoice->student_id,
                'amount' => $amount,
                'currency_id' => $fxMeta['currency_id'],
                'exchange_rate' => $fxMeta['exchange_rate'],
                'base_amount' => $fxMeta['base_amount'],
                'applied_amount' => $fxMeta['applied_amount'],
                'payment_method' => $data['payment_method'],
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'received_by' => $actorId,
            ]);
            $payment->save();

            $journal = $this->posting->create(
                $this->receiptJournalPayload($invoice, $fxMeta, $branchId, $data['payment_method_id'] ?? null, $data['payment_method'], $data['paid_at'] ?? null),
                $actorId,
            );

            $payment->forceFill(['journal_id' => $journal->id])->save();

            $this->applyToInvoice($invoice, (float) $fxMeta['applied_amount'], $data['installment_id'] ?? null);

            $this->audit->log($instituteId, [
                'branch_id' => $branchId,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'create',
                'entity_type' => 'payment',
                'entity_id' => $payment->id,
                'after_payload' => [
                    'invoice' => $invoice->invoice_number,
                    'amount' => $amount,
                    'currency_id' => $fxMeta['currency_id'],
                    'exchange_rate' => $fxMeta['exchange_rate'],
                    'base_amount' => $fxMeta['base_amount'],
                    'journal' => $journal->journal_no,
                ],
            ]);

            return $payment->load('invoice');
        });

        $this->notifyPaymentReceived($payment, $actorId);
        $this->notifyAdmins($payment, $actorId);

        return $payment;
    }

    /**
     * Notify the student of a received payment. Safe pipeline — never fails the
     * recording of money.
     */
    private function notifyPaymentReceived(Payment $payment, ?int $actorId): void
    {
        if ($payment->student_id === null) {
            return;
        }

        $student = Student::query()->withoutGlobalScope('institute')->find($payment->student_id);
        if ($student === null) {
            return;
        }

        $invoice = $payment->invoice;

        $this->notifications->send('finance.payment_received', $student, [
            'student_name' => $student->full_name ?: $student->first_name,
            'reg_no' => $student->reg_no,
            'amount' => number_format((float) $payment->amount, 2),
            'invoice_number' => $invoice?->invoice_number,
            'balance' => number_format((float) ($invoice?->due_amount ?? 0), 2),
        ], [
            'actor_type' => 'institute_user',
            'actor_id' => $actorId,
            'link' => route('students.show', $student->id),
        ]);
    }

    /**
     * Admin notification for every payment — institute-scoped so it is visible
     * to both institute owners (institute bell) and platform admins (admin bell
     * after NotificationCenter fix). Safe — never fails the payment.
     */
    private function notifyAdmins(Payment $payment, ?int $actorId): void
    {
        try {
            $instituteId = (int) ($payment->institute_id ?? $payment->invoice?->institute_id ?? 0);
            if ($instituteId <= 0) {
                return;
            }

            $invoiceNumber = $payment->invoice?->invoice_number ?? ('#'.$payment->invoice_id);
            $amountFormatted = number_format((float) $payment->amount, 2);

            Notification::create([
                'scope' => 'institute',
                'institute_id' => $instituteId,
                'category' => 'finance_payment',
                'title' => 'Payment received',
                'message' => "Payment of {$amountFormatted} received for invoice {$invoiceNumber}.",
                'link_url' => route('finance.invoices.show', $payment->invoice_id),
                'created_by_type' => 'institute_user',
                'created_by_id' => $actorId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('notification.admin_payment_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reverse a recorded payment: reverse its receipt journal and restore the
     * invoice balances.
     */
    public function reverse(Payment $payment, int $instituteId, ?int $actorId = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($payment, $instituteId, $actorId, $reason) {
            if ($payment->journal_id === null) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment has no receipt journal to reverse.',
                ]);
            }

            $this->posting->reverse($payment->journal, $instituteId, $actorId, $reason ?? 'Payment reversed');

            $invoice = $payment->invoice;
            $amount = $payment->applied_amount !== null
                ? (float) $payment->applied_amount
                : (float) $payment->amount;

            $newPaid = max(0.0, (float) $invoice->paid_amount - $amount);
            $newDue = round((float) $invoice->payable_amount - $newPaid, 4);

            $invoice->forceFill([
                'paid_amount' => $newPaid,
                'due_amount' => $newDue,
                'status' => $newDue <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid'),
            ])->save();

            if ($payment->installment_id !== null) {
                $installment = $payment->installment;
                $installment->forceFill([
                    'paid_amount' => max(0.0, (float) $installment->paid_amount - $amount),
                ])->save();
                $this->refreshInstallmentStatus($installment);
            }

            $this->audit->log($instituteId, [
                'branch_id' => $invoice->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'reverse',
                'entity_type' => 'payment',
                'entity_id' => $payment->id,
                'after_payload' => ['invoice' => $invoice->invoice_number, 'reason' => $reason],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(int $instituteId, ?int $branchId, array $data): array
    {
        $validator = validator($data, [
            'invoice_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:cash,bkash,nagad,rocket,bank,card,online,other'],
            'payment_method_id' => ['nullable', 'integer'],
            'installment_id' => ['nullable', 'integer'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $invoice = Invoice::query()->where('institute_id', $instituteId)->find((int) $data['invoice_id']);

        if ($invoice === null) {
            throw ValidationException::withMessages([
                'invoice_id' => 'The invoice does not exist in this institute.',
            ]);
        }

        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'invoice_id' => 'Payments cannot be recorded against a cancelled invoice.',
            ]);
        }

        if ($invoice->status === 'paid') {
            throw ValidationException::withMessages([
                'invoice_id' => 'This invoice is already fully paid.',
            ]);
        }

        if (filled($data['payment_method_id'] ?? null)) {
            $method = PaymentMethod::query()
                ->where('institute_id', $instituteId)
                ->where(fn ($query) => $query
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id'))
                ->find((int) $data['payment_method_id']);

            if ($method === null || ! $method->is_active) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'The selected payment method is not available.',
                ]);
            }

            $data['payment_method_id'] = (int) $method->id;
        } else {
            $data['payment_method_id'] = null;
        }

        $data['invoice'] = $invoice;

        return $data;
    }

    /**
     * STEP 19 FX: resolve the payment's settlement currency + rate and the
     * base amounts. Same-currency payments behave exactly as before (rate 1,
     * applied = amount). Foreign payments convert at the settlement rate
     * (explicit rate first, then the effective tenant rate for the payment
     * date); a missing rate fails safely.
     *
     * applied_amount is denominated in the INVOICE currency (the invoice's
     * due_amount is stored in the invoice currency), so it equals the payment
     * amount. base_amount is the cash-side base value at the settlement rate.
     *
     * @param  array<string, mixed>  $data
     *
     * @return array{currency_id: ?int, exchange_rate: string, base_amount: string, applied_amount: string, is_foreign: bool}
     */
    private function resolvePaymentFx(Invoice $invoice, ?int $branchId, array $data, float $amount): array
    {
        $instituteId = (int) $invoice->institute_id;
        $baseCurrencyId = $this->fx->baseCurrencyId($instituteId, $branchId);
        $invoiceCurrencyId = $invoice->currency_id !== null ? (int) $invoice->currency_id : null;
        $amountString = number_format($amount, 4, '.', '');

        // Same-currency settlement (invoice currency is base or unset).
        if ($invoiceCurrencyId === null || $invoiceCurrencyId === $baseCurrencyId) {
            return [
                'currency_id' => $invoiceCurrencyId,
                'exchange_rate' => '1.00000000',
                'base_amount' => $amountString,
                'applied_amount' => $amountString,
                'is_foreign' => false,
            ];
        }

        $paidDate = isset($data['paid_at'])
            ? \Illuminate\Support\Carbon::parse($data['paid_at'])->toDateString()
            : now()->toDateString();

        $resolved = $this->fx->resolveRate(
            $instituteId,
            $branchId,
            $invoiceCurrencyId,
            $baseCurrencyId,
            $paidDate,
            filled($data['exchange_rate'] ?? null) ? (string) $data['exchange_rate'] : null,
        );

        return [
            'currency_id' => $invoiceCurrencyId,
            'exchange_rate' => $resolved['rate'],
            'base_amount' => $this->fx->convert($amountString, $resolved['rate']),
            'applied_amount' => $amountString,
            'is_foreign' => true,
        ];
    }

    private function assertNoOverpayment(Invoice $invoice, float $appliedAmount): void
    {
        $due = round((float) $invoice->due_amount, 4);

        if ($appliedAmount > $due + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => "Overpayment rejected: the invoice's due amount is {$due}.",
            ]);
        }
    }

    /**
     * @param  array{currency_id: ?int, exchange_rate: string, base_amount: string, applied_amount: string, is_foreign: bool}  $fxMeta
     *
     * @return array<string, mixed>
     */
    private function receiptJournalPayload(Invoice $invoice, array $fxMeta, ?int $branchId, ?int $paymentMethodId = null, ?string $paymentMethod = null, ?string $paidAt = null): array
    {
        $coaService = app(ChartOfAccountService::class);

        $receivable = $coaService->accountByCode($invoice->institute_id, '1100', $branchId)
            ?? ChartOfAccount::query()
                ->where('institute_id', $invoice->institute_id)
                ->where('branch_id', $branchId)
                ->where('is_receivable', true)
                ->first();

        if ($receivable === null) {
            throw new \RuntimeException('No receivable account is configured for this institute.');
        }

        $rate = $fxMeta['exchange_rate'];
        $lineCurrency = $fxMeta['is_foreign'] ? $fxMeta['currency_id'] : null;
        $appliedForeign = $fxMeta['applied_amount'];

        if (! $fxMeta['is_foreign']) {
            // Same-currency: base amounts equal the payment amount (unchanged
            // legacy behavior).
            $baseAmount = (float) $fxMeta['base_amount'];

            $entries = [
                [
                    'coa_id' => $this->debitAccountId($invoice->institute_id, $branchId, $paymentMethodId, $paymentMethod),
                    'party_id' => null,
                    'debit' => $baseAmount,
                    'credit' => 0,
                    'memo' => 'Received against invoice '.$invoice->invoice_number,
                ],
                [
                    'coa_id' => $receivable->id,
                    'party_id' => $invoice->party_id,
                    'debit' => 0,
                    'credit' => $baseAmount,
                    'memo' => 'Receipt applied to invoice '.$invoice->invoice_number,
                ],
            ];
        } else {
            // Foreign settlement: AR is relieved at the invoice carrying rate
            // (the rate AR was recorded at), cash is valued at the settlement
            // rate, and the difference is realized FX gain/loss.
            $carryingRate = $this->realizedFx->carryingRate($invoice);

            $arBase = (float) $this->fx->convert($appliedForeign, $carryingRate);
            $cashBase = (float) $this->fx->convert($appliedForeign, $rate);
            $difference = round($cashBase - $arBase, 4);

            $entries = [
                [
                    'coa_id' => $this->debitAccountId($invoice->institute_id, $branchId, $paymentMethodId, $paymentMethod),
                    'party_id' => null,
                    'currency_id' => $lineCurrency,
                    'foreign_debit' => (float) $appliedForeign,
                    'foreign_credit' => 0,
                    'exchange_rate' => $rate,
                    'debit' => $cashBase,
                    'credit' => 0,
                    'memo' => 'Received against invoice '.$invoice->invoice_number,
                ],
                [
                    'coa_id' => $receivable->id,
                    'party_id' => $invoice->party_id,
                    'currency_id' => $lineCurrency,
                    'foreign_debit' => 0,
                    'foreign_credit' => (float) $appliedForeign,
                    'exchange_rate' => $carryingRate,
                    'debit' => 0,
                    'credit' => $arBase,
                    'memo' => 'Receipt applied to invoice '.$invoice->invoice_number,
                ],
            ];

            if (abs($difference) > 0.00005) {
                $computed = [
                    'difference' => number_format($difference, 4, '.', ''),
                    'is_gain' => $difference > 0,
                    'is_loss' => $difference < 0,
                ];

                $fxLine = $this->realizedFx->journalLine(
                    (int) $invoice->institute_id,
                    $branchId,
                    $computed,
                    'Realized FX '.($computed['is_gain'] ? 'gain' : 'loss').' on invoice '.$invoice->invoice_number,
                );

                if ($fxLine !== null) {
                    $entries[] = $fxLine;
                }
            }
        }

        return [
            'institute_id' => $invoice->institute_id,
            'branch_id' => $branchId,
            'journal_date' => $paidAt !== null ? \Illuminate\Support\Carbon::parse($paidAt)->toDateString() : now()->toDateString(),
            'currency_id' => $invoice->currency_id ?? $this->resolveCurrencyId($invoice->institute_id, $branchId),
            'exchange_rate' => $rate,
            'type' => 'receipt',
            'ref_type' => 'invoice',
            'ref_id' => $invoice->id,
            'description' => 'Payment against invoice '.$invoice->invoice_number,
            'entries' => $entries,
        ];
    }

    private function resolveCurrencyId(int $instituteId, ?int $branchId): int
    {
        $code = AccountingSetting::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('settings_key', 'base_currency')
            ->value('settings_value');

        if ($code !== null) {
            $currency = Currency::query()->where('code', $code)->first();

            if ($currency !== null) {
                return (int) $currency->id;
            }
        }

        return (int) (Currency::query()->orderBy('code')->value('id'));
    }

    /**
     * The cash/bank account a receipt debits, resolved through the shared
     * STEP 14 payment-method → account mapping (PaymentAccountResolverService).
     */
    private function debitAccountId(int $instituteId, ?int $branchId, ?int $paymentMethodId, ?string $paymentMethod): int
    {
        return app(PaymentAccountResolverService::class)->resolve($instituteId, $branchId, $paymentMethodId, $paymentMethod);
    }

    private function applyToInvoice(Invoice $invoice, float $amount, ?int $installmentId): void
    {
        $newPaid = round((float) $invoice->paid_amount + $amount, 4);
        $newDue = round((float) $invoice->payable_amount - $newPaid, 4);

        $invoice->forceFill([
            'paid_amount' => $newPaid,
            'due_amount' => $newDue,
            'status' => $newDue <= 0 ? 'paid' : 'partial',
        ])->save();

        if ($installmentId !== null) {
            $installment = $invoice->installments()->find($installmentId);

            if ($installment !== null) {
                $installment->forceFill([
                    'paid_amount' => round((float) $installment->paid_amount + $amount, 4),
                ])->save();
                $this->refreshInstallmentStatus($installment);
            }
        }
    }

    private function refreshInstallmentStatus($installment): void
    {
        $installment->forceFill([
            'status' => (float) $installment->paid_amount >= (float) $installment->amount - 0.01 ? 'paid' : 'pending',
        ])->save();
    }
}
