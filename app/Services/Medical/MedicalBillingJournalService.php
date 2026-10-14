<?php

namespace App\Services\Medical;

use App\Models\AccountingSetting;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Journal;
use App\Models\Medical\Invoice;
use App\Models\Medical\Payment;
use App\Models\Setting;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Medical billing journal posting (accrual + cash modes).
 *
 * Two revenue recognition modes controlled by per-institute setting:
 *   medical.billing.revenue_mode.{institute_id} = 'accrual' | 'cash'
 *   medical.billing.auto_post.{institute_id}    = '1' | '0'
 *
 * Accrual mode:
 *   - Invoice creation → sale journal: Dr AR (total) / Dr Discount Allowed
 *     (discount, 4000.5, only when > 0) / Cr Revenue (subtotal, per
 *     incomeAccountFor()) / Cr VAT Output Payable (2100.1, only when > 0)
 *   - Payment → receipt journal: Dr Cash / Cr AR
 *
 * Cash mode:
 *   - Invoice creation → NO journal (revenue recognized on payment only)
 *   - Payment → single journal: Dr Cash (amount) / Cr Revenue (amount minus
 *     proportional VAT) / Cr VAT Output Payable (proportional VAT share).
 *     Cash-mode partial-payment formula (option (a) for VAT, discount netted):
 *       taxOnPayment     = round(invoice.tax * (payment.amount / invoice.total), 2)
 *       revenueOnPayment = round(payment.amount - taxOnPayment, 2)
 *     Discount is NOT a separate leg: BillingService::computeTotals() nets it
 *     into total (total = subtotal + tax - discount), so each payment
 *     implicitly recognizes revenue net of discount. Example: subtotal=1000,
 *     discount=100, tax=50, total=950, payment=500 → Dr Cash 500 /
 *     Cr Revenue 473.68 / Cr VAT 26.32 (26.32 = round(50 * 500/950, 2)).
 *
 * Both modes share:
 *   - Each medical_payment row gets its own receipt journal (medical_payments.journal_id)
 *   - medical_invoices.journal_id = the sale journal (accrual) or null (cash)
 *   - Fail-soft on missing CoA: log warning, return null, don't block billing flow
 *   - Idempotency via existing FK columns (invoice.journal_id, payment.journal_id)
 */
class MedicalBillingJournalService
{
    public const BALANCE_EPSILON = 0.005;

    public function __construct(
        private readonly JournalPostingService $posting,
        private readonly ChartOfAccountService $coa,
    ) {}

    /**
     * Post the sale journal for a medical invoice (accrual mode only).
     *
     * Returns null when:
     *   - revenue_mode = 'cash'
     *   - invoice already has journal_id (idempotent)
     *   - invoice total <= 0
     *   - required CoA missing (fail-soft: logs, returns null)
     */
    public function postSaleJournal(Invoice $invoice, ?int $actorId = null): ?Journal
    {
        // Idempotency: invoice already carries its sale journal link
        if ($invoice->journal_id !== null) {
            return null;
        }

        // Revenue mode check: cash mode defers all recognition to payment time
        $revenueMode = $this->getRevenueMode($invoice->institute_id);
        if ($revenueMode === 'cash') {
            return null;
        }

        $total = round((float) $invoice->total, 2);
        if ($total <= 0) {
            return null;
        }

        $instituteId = (int) $invoice->institute_id;
        $branchId = $invoice->branch_id !== null ? (int) $invoice->branch_id : null;

        // Resolve accounts (fail-soft: missing CoA → log + return null)
        $ar = $this->requireAccount($instituteId, '1200.1', $branchId);      // Trade Receivable
        if ($ar === null) {
            return null;
        }

        $revenue = $this->incomeAccountFor($invoice, $instituteId, $branchId);
        if ($revenue === null) {
            return null; // already logged in incomeAccountFor
        }
        $taxAccount = null;

        $tax = round((float) $invoice->tax, 2);
        if ($tax > 0) {
            $taxAccount = $this->requireAccount($instituteId, '2100.1', $branchId); // VAT Output Payable
            if ($taxAccount === null) {
                return null; // already logged in requireAccount
            }
        }

        // Discount is a contra-revenue leg: total = subtotal + tax - discount,
        // so Dr AR (total) + Dr Discount Allowed (discount) = Cr Revenue
        // (subtotal) + Cr VAT (tax). Without the discount leg a discounted
        // invoice cannot balance.
        $discount = round((float) $invoice->discount, 2);
        $discountAccount = null;
        if ($discount > 0) {
            $discountAccount = $this->requireAccount($instituteId, '4000.5', $branchId); // Discount Allowed
            if ($discountAccount === null) {
                return null; // already logged in requireAccount
            }
        }

        // Build entries: Dr AR (total) [+ Dr Discount], Cr Revenue (subtotal), Cr VAT (if any)
        $entries = [
            ['coa_id' => $ar->id, 'debit' => $total, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => round((float) $invoice->subtotal, 2)],
        ];

        if ($discount > 0 && $discountAccount !== null) {
            $entries[] = ['coa_id' => $discountAccount->id, 'debit' => $discount, 'credit' => 0];
        }

        if ($tax > 0 && $taxAccount !== null) {
            $entries[] = ['coa_id' => $taxAccount->id, 'debit' => 0, 'credit' => $tax];
        }

        if (! $this->validateBalance($entries)) {
            return null;
        }

        // Auto-post check
        $postNow = $this->getAutoPost($instituteId);

        return DB::transaction(function () use ($invoice, $entries, $actorId, $instituteId, $branchId, $postNow): ?Journal {
            $journal = $this->posting->create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'journal_date' => $invoice->invoice_date ?? now()->toDateString(),
                'type' => 'sale',
                'ref_type' => 'medical_invoice',
                'ref_id' => $invoice->id,
                'currency_id' => $this->resolveCurrencyId($instituteId, $branchId),
                'description' => 'Medical sale — invoice '.$invoice->invoice_number,
                'entries' => $entries,
            ], $actorId, postNow: $postNow);

            $invoice->forceFill(['journal_id' => $journal->id])->save();

            return $journal;
        });
    }

    /**
     * Post the receipt journal for a medical payment (both modes).
     *
     * In accrual mode: Dr Cash / Cr AR.
     * In cash mode:    Dr Cash (amount) / Cr Revenue (amount minus
     * proportional VAT) / Cr VAT Output Payable (proportional VAT share).
     * Cash-mode partial-payment formula (VAT proportional, discount netted —
     * see class docblock for the worked example):
     *   taxOnPayment     = total > 0 ? round(tax * (amount / total), 2) : 0
     *   revenueOnPayment = round(amount - taxOnPayment, 2)
     *
     * Returns null when:
     *   - payment already has journal_id (idempotent)
     *   - amount <= 0
     *   - required CoA missing (fail-soft: logs, returns null)
     */
    public function postReceiptJournal(Payment $payment, ?int $actorId = null): ?Journal
    {
        // Idempotency: payment already carries its receipt journal link
        if ($payment->journal_id !== null) {
            return null;
        }

        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $invoice = $payment->invoice;
        if ($invoice === null) {
            Log::warning('MedicalBillingJournalService: payment missing invoice relation', [
                'payment_id' => $payment->id,
            ]);
            return null;
        }

        $instituteId = (int) $invoice->institute_id;
        $branchId = $invoice->branch_id !== null ? (int) $invoice->branch_id : null;
        $revenueMode = $this->getRevenueMode($instituteId);

        // Resolve accounts (fail-soft)
        $cash = $this->requireAccount($instituteId, '1000.1', $branchId);
        if ($cash === null) {
            return null;
        }

        $entries = [];

        if ($revenueMode === 'accrual') {
            // Accrual: Dr Cash (net), Cr AR (full payment amount)
            $ar = $this->requireAccount($instituteId, '1200.1', $branchId);
            if ($ar === null) {
                return null;
            }

            $entries[] = ['coa_id' => $cash->id, 'debit' => $amount, 'credit' => 0];
            $entries[] = ['coa_id' => $ar->id, 'debit' => 0, 'credit' => $amount];
        } else {
            // Cash mode: Dr Cash, Cr Revenue (+ VAT if invoice has tax)
            $revenue = $this->incomeAccountFor($invoice, $instituteId, $branchId);
            if ($revenue === null) {
                return null; // already logged in incomeAccountFor
            }

            // Prorate tax from invoice proportionally to payment amount
            $tax = round((float) $invoice->tax, 2);
            $total = round((float) $invoice->total, 2);

            $taxOnPayment = $total > 0 ? round($tax * ($amount / $total), 2) : 0;
            $revenueOnPayment = round($amount - $taxOnPayment, 2);

            $entries[] = ['coa_id' => $cash->id, 'debit' => $amount, 'credit' => 0];
            $entries[] = ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => $revenueOnPayment];

            if ($taxOnPayment > 0) {
                $taxAccount = $this->requireAccount($instituteId, '2100.1', $branchId); // VAT Output Payable
                if ($taxAccount === null) {
                    return null; // already logged in requireAccount
                }
                $entries[] = ['coa_id' => $taxAccount->id, 'debit' => 0, 'credit' => $taxOnPayment];
            }
        }

        if (! $this->validateBalance($entries)) {
            return null;
        }

        // Auto-post check
        $postNow = $this->getAutoPost($instituteId);

        return DB::transaction(function () use ($payment, $entries, $actorId, $instituteId, $branchId, $postNow): ?Journal {
            $journal = $this->posting->create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'journal_date' => $payment->paid_at?->toDateString() ?? now()->toDateString(),
                'type' => 'receipt',
                'currency_id' => $this->resolveCurrencyId($instituteId, $branchId),
                'description' => 'Medical receipt — payment for invoice '.$payment->invoice->invoice_number,
                'entries' => $entries,
            ], $actorId, postNow: $postNow);

            $payment->forceFill(['journal_id' => $journal->id])->save();

            return $journal;
        });
    }

    /**
     * Reverse all journals linked to an invoice (sale + all payment receipts).
     * Idempotent: journals already reversed / null are skipped.
     */
    public function reverseInvoiceJournals(Invoice $invoice, ?int $actorId = null, string $reason = 'Invoice cancelled'): void
    {
        $instituteId = (int) $invoice->institute_id;

        DB::transaction(function () use ($invoice, $actorId, $reason, $instituteId): void {
            $reversedIds = [];

            // 1. Reverse sale journal (if any)
            if ($invoice->journal_id !== null) {
                $journal = Journal::where('institute_id', $instituteId)->find($invoice->journal_id);
                if ($journal !== null && $journal->status === 'posted') {
                    $this->posting->reverse($journal, $instituteId, $actorId, $reason);
                    $reversedIds[] = (int) $journal->id;
                }
                $invoice->forceFill(['journal_id' => null])->save();
            }

            // 2. Reverse all payment receipt journals — one row per
            // medical_payments record. Fresh query (not the possibly stale
            // loaded relation) so a second run sees the nulled FKs.
            foreach ($invoice->payments()->get() as $payment) {
                if ($payment->journal_id === null) {
                    continue;
                }

                $journalId = (int) $payment->journal_id;
                if (in_array($journalId, $reversedIds, true)) {
                    $payment->forceFill(['journal_id' => null])->save();
                    continue;
                }

                $journal = Journal::where('institute_id', $instituteId)->find($journalId);
                if ($journal !== null && $journal->status === 'posted') {
                    $this->posting->reverse($journal, $instituteId, $actorId, $reason);
                }
                $reversedIds[] = $journalId;

                $payment->forceFill(['journal_id' => null])->save();
            }
        });
    }

    /**
     * Income account for a medical invoice by type.
     *
     *   opd      → 4300.1 Consultation Fees
     *   lab      → 4300.2 Diagnostic Fees
     *   pharmacy → 4300.3 Pharmacy Sales
     *   ipd      → 4300.4 Surgery & IPD (not yet in CoaTemplate registry)
     *   surgery  → 4300.4 Surgery & IPD (not yet in CoaTemplate registry)
     *
     * NOTE: 4300.4 does not exist in CoaTemplate (verified 2026-10-14:
     * registry holds only 4300.1/4300.2/4300.3). ipd/surgery therefore fall
     * through to the fallback below until a 4300.4 migration + registry row
     * lands. Fallback is the first active income account for the institute
     * (branch row preferred, then unscoped), never a silent null — null is
     * returned only when the tenant has no active income account at all
     * (fail-soft, logged).
     */
    public function incomeAccountFor(Invoice|string $invoiceOrType, ?int $instituteId = null, ?int $branchId = null): ?ChartOfAccount
    {
        $type = $invoiceOrType instanceof Invoice ? (string) $invoiceOrType->type : (string) $invoiceOrType;
        $instituteId ??= $invoiceOrType instanceof Invoice ? (int) $invoiceOrType->institute_id : null;
        $branchId ??= $invoiceOrType instanceof Invoice && $invoiceOrType->branch_id !== null
            ? (int) $invoiceOrType->branch_id
            : null;

        if ($instituteId === null || $instituteId <= 0) {
            Log::warning('MedicalBillingJournalService: income mapping without institute', ['type' => $type]);
            return null;
        }

        $code = match (strtolower($type)) {
            'opd' => '4300.1',
            'lab' => '4300.2',
            'pharmacy' => '4300.3',
            'ipd', 'surgery' => '4300.4',
            default => null,
        };

        if ($code !== null) {
            $mapped = $this->coa->accountByCode($instituteId, $code, $branchId)
                ?? ($branchId !== null ? $this->coa->accountByCode($instituteId, $code, null) : null);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        // Fallback: first active income account (branch row preferred), revenue
        // (43%) before other income so contra-revenue 4000.5 Discount Allowed
        // never wins the fallback.
        foreach ([$branchId, null] as $scope) {
            foreach ([true, false] as $revenueFirst) {
                $query = ChartOfAccount::query()
                    ->where('institute_id', $instituteId)
                    ->where('type', 'income')
                    ->where('is_active', true);
                $scope !== null ? $query->where('branch_id', $scope) : $query->whereNull('branch_id');
                $revenueFirst ? $query->where('code', 'like', '43%') : $query->where('code', 'not like', '43%');
                $fallback = $query->orderBy('code')->first();
                if ($fallback !== null) {
                    return $fallback;
                }
            }
        }

        Log::warning('MedicalBillingJournalService: tenant has no active income account', [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'type' => $type,
        ]);

        return null;
    }

    /**
     * Get per-institute revenue mode, falling back to config.
     */
    protected function getRevenueMode(int $instituteId): string
    {
        return Setting::get(
            "medical.billing.revenue_mode.{$instituteId}",
            config('medical_billing.revenue_mode', 'accrual')
        );
    }

    /**
     * Get per-institute auto-post setting, falling back to config.
     */
    protected function getAutoPost(int $instituteId): bool
    {
        $value = Setting::get(
            "medical.billing.auto_post.{$instituteId}",
            config('medical_billing.auto_post', true) ? '1' : '0'
        );

        return (bool) $value;
    }

    /**
     * Resolve a tenant-owned account by code with branch fallback.
     * Fail-soft: logs warning and returns null (caller must check).
     */
    private function requireAccount(int $instituteId, string $code, ?int $branchId): ?ChartOfAccount
    {
        $account = $this->coa->accountByCode($instituteId, $code, $branchId);

        // H1 fallback: Phase A medical COA install writes branch_id NULL rows.
        // A branch-scoped lookup therefore misses on any record that
        // carries a branch_id even though the tenant owns the account —
        // fall back to the unscoped tenant row instead of aborting.
        if ($account === null && $branchId !== null) {
            $account = $this->coa->accountByCode($instituteId, $code, null);
        }

        if ($account === null) {
            Log::warning('MedicalBillingJournalService: required account missing', [
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'code' => $code,
            ]);
            return null;
        }

        return $account;
    }

    /**
     * Validate journal entries balance within epsilon.
     */
    private function validateBalance(array $entries): bool
    {
        $sumDebit = round(array_sum(array_column($entries, 'debit')), 2);
        $sumCredit = round(array_sum(array_column($entries, 'credit')), 2);

        if (abs($sumDebit - $sumCredit) > self::BALANCE_EPSILON) {
            Log::warning('MedicalBillingJournalService: journal does not balance', [
                'debit' => $sumDebit,
                'credit' => $sumCredit,
                'entries' => $entries,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Functional currency for the journal — same resolution order as
     * PaymentService::resolveCurrencyId: the institute's base_currency
     * accounting setting, else the first currency by code. Never hardcoded.
     */
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
}