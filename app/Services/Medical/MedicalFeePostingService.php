<?php

namespace App\Services\Medical;

use App\Models\AccountingSetting;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Journal;
use App\Models\Medical\Appointment;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Support\Facades\DB;

/**
 * OPD consultation-fee ledger posting (Phase B, Option A — direct receipt
 * journal, no medical_invoice).
 *
 * When an OPD fee is collected the appointment already records the money on
 * the fee_collected_* columns; this service mirrors that into the double
 * entry ledger so the cash movement is visible in trial balance / cash flow
 * without going through the invoicing subsystem. The entry shape is a
 * classic service receipt:
 *
 *   Dr 1000.1 Cash            (net collected)
 *   Dr 4000.5 Discount Allowed (contra-revenue, only when a discount > 0)
 *   Cr 4300.1 Consultation Fees (gross fee)
 *
 * VAT does NOT apply here: the OPD fee is a net service fee and is not
 * invoiced under Option A (the medical.vat_rate setting only feeds
 * BillingService's invoice math).
 *
 * Idempotency contract: appointments.journal_id is the ledger link. When it
 * is already set the appointment's fee has been posted, so
 * postOpdFeeJournal() returns null and never double-posts — the controller
 * sets the column in the same DB transaction that creates the journal
 * (Phase A added the column; Phase B wires it). Historical fee_collected
 * rows that predate this wiring are intentionally NOT backfilled.
 *
 * Account resolution is by tenant-owned code via
 * ChartOfAccountService::accountByCode (branch-scoped; the Phase A medical
 * COA install writes branch_id NULL rows, so appointments without a branch
 * resolve cleanly). A missing required account throws — the caller's
 * transaction rolls back, leaving the appointment uncollected rather than
 * half-updated with no ledger trace.
 *
 * Phase C1 (two-stage posting) supersedes postOpdFeeJournal() with
 * postAdvanceReceipt() + postIncomeRecognition() (staged collection) or
 * postDirectCollection() (same-visit cash-in). The legacy method below stays
 * callable for existing wiring, but new callers must not use it.
 */
class MedicalFeePostingService
{
    public function __construct(
        private readonly JournalPostingService $posting,
        private readonly ChartOfAccountService $coa,
    ) {}

    /**
     * Post the receipt journal for a collected OPD fee, or return null when
     * there is nothing to post (already posted / zero gross).
     *
     * @deprecated Phase C1 replaces this with the two-stage pair
     *             postAdvanceReceipt() + postIncomeRecognition() (or
     *             postDirectCollection() for same-visit cash-in). Kept for
     *             existing Phase B wiring; do not call from new code.
     */
    public function postOpdFeeJournal(Appointment $appointment, float $grossAmount, float $discountAmount, ?int $actorId = null): ?Journal
    {
        // Idempotency: the appointment already carries its ledger link.
        if ($appointment->journal_id !== null) {
            return null;
        }

        $gross = round($grossAmount, 2);
        if ($gross <= 0) {
            return null;
        }

        $instituteId = (int) $appointment->institute_id;
        $branchId = $appointment->branch_id !== null ? (int) $appointment->branch_id : null;

        $cash = $this->requireAccount($instituteId, '1000.1', $branchId);
        $income = $this->requireAccount($instituteId, '4300.1', $branchId);

        // A discount can never exceed the gross fee; clamp defensively so the
        // ledger cannot go negative.
        $discount = min(round($discountAmount, 2), $gross);
        if ($discount < 0) {
            $discount = 0.0;
        }
        $net = round($gross - $discount, 2);

        $entries = [];
        if ($net > 0) {
            $entries[] = ['coa_id' => $cash->id, 'debit' => $net, 'credit' => 0];
        }
        if ($discount > 0) {
            $discountAccount = $this->requireAccount($instituteId, '4000.5', $branchId);
            $entries[] = ['coa_id' => $discountAccount->id, 'debit' => $discount, 'credit' => 0];
        }
        $entries[] = ['coa_id' => $income->id, 'debit' => 0, 'credit' => $gross];

        $sumDebit = round(array_sum(array_column($entries, 'debit')), 2);
        $sumCredit = round(array_sum(array_column($entries, 'credit')), 2);
        if (abs($sumDebit - $sumCredit) > JournalPostingService::BALANCE_EPSILON) {
            throw new \RuntimeException(
                "OPD fee journal does not balance (debit {$sumDebit} vs credit {$sumCredit})."
            );
        }

        return $this->posting->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $appointment->fee_collected_at ?? now(),
            'type' => 'receipt',
            'currency_id' => $this->resolveCurrencyId($instituteId, $branchId),
            'description' => 'OPD consultation fee — appointment #'.$appointment->id,
            'entries' => $entries,
        ], $actorId, postNow: true);
    }

    /**
     * Phase C1, stage 1 — the money-in receipt for a staged OPD fee. Cash
     * lands now; the gross sits as an unearned liability until the service is
     * recognised (postIncomeRecognition()).
     *
     *   Dr 1000.1 Cash             (net received)
     *   Dr 4000.5 Discount Allowed (contra-revenue, only when discounted)
     *   Cr 2400.3 OPD Fees Advance (gross, unearned)
     *
     * Idempotent on appointments.fee_receipt_journal_id; null on zero gross.
     */
    public function postAdvanceReceipt(Appointment $appointment, float $grossAmount, float $discountAmount, ?int $actorId = null): ?Journal
    {
        if ($appointment->fee_receipt_journal_id !== null) {
            return null;
        }

        $gross = round($grossAmount, 2);
        if ($gross <= 0) {
            return null;
        }

        $entries = $this->cashSideEntries($appointment, $gross, $discountAmount);
        $entries[] = $this->entry($appointment, '2400.3', 0, $gross);

        return $this->postAndLink(
            $appointment,
            $entries,
            'OPD advance fee receipt — appointment #'.$appointment->id,
            'fee_receipt_journal_id',
            $actorId,
        );
    }

    /**
     * Phase C1, stage 2 — recognise the gross that was parked as a liability
     * by postAdvanceReceipt() once the service has been delivered.
     *
     *   Dr 2400.3 OPD Fees Advance (gross)
     *   Cr 4300.1 Consultation Fees (gross)
     *
     * The discount, if any, was already expensed at receipt time, so the
     * full gross is released. Idempotent on
     * appointments.fee_recognition_journal_id; null on zero gross.
     *
     * @throws \RuntimeException when no advance receipt journal exists yet —
     *                           recognising revenue the tenant never received
     *                           would corrupt the liability account.
     */
    public function postIncomeRecognition(Appointment $appointment, float $grossAmount, ?int $actorId = null): ?Journal
    {
        if ($appointment->fee_recognition_journal_id !== null) {
            return null;
        }

        if ($appointment->fee_receipt_journal_id === null) {
            throw new \RuntimeException('Recognition requires a prior advance receipt journal.');
        }

        $gross = round($grossAmount, 2);
        if ($gross <= 0) {
            return null;
        }

        $entries = [
            $this->entry($appointment, '2400.3', $gross, 0),
            $this->entry($appointment, '4300.1', 0, $gross),
        ];

        return $this->postAndLink(
            $appointment,
            $entries,
            'OPD fee revenue recognition — appointment #'.$appointment->id,
            'fee_recognition_journal_id',
            $actorId,
        );
    }

    /**
     * Phase C1 — same-visit collection: money in and revenue recognised in a
     * single journal, no liability stage.
     *
     *   Dr 1000.1 Cash             (net received)
     *   Dr 4000.5 Discount Allowed (contra-revenue, only when discounted)
     *   Cr 4300.1 Consultation Fees (gross)
     *
     * Only fee_receipt_journal_id is linked (fee_recognition_journal_id stays
     * null — there is no second stage). Idempotent on
     * appointments.fee_receipt_journal_id; null on zero gross.
     */
    public function postDirectCollection(Appointment $appointment, float $grossAmount, float $discountAmount, ?int $actorId = null): ?Journal
    {
        if ($appointment->fee_receipt_journal_id !== null) {
            return null;
        }

        $gross = round($grossAmount, 2);
        if ($gross <= 0) {
            return null;
        }

        $entries = $this->cashSideEntries($appointment, $gross, $discountAmount);
        $entries[] = $this->entry($appointment, '4300.1', 0, $gross);

        return $this->postAndLink(
            $appointment,
            $entries,
            'OPD direct fee collection — appointment #'.$appointment->id,
            'fee_receipt_journal_id',
            $actorId,
        );
    }

    /**
     * One journal-entry line for a tenant account resolved by code.
     *
     * @return array{coa_id: int, debit: float, credit: float}
     */
    private function entry(Appointment $appointment, string $code, float $debit, float $credit): array
    {
        $instituteId = (int) $appointment->institute_id;
        $branchId = $appointment->branch_id !== null ? (int) $appointment->branch_id : null;

        return [
            'coa_id' => $this->requireAccount($instituteId, $code, $branchId)->id,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    /**
     * Shared cash-in side of both receipt shapes: Dr Cash (net) plus Dr
     * Discount Allowed when a discount applies. The discount can never
     * exceed the gross fee; clamped defensively so the ledger cannot go
     * negative.
     *
     * @return array<int, array{coa_id: int, debit: float, credit: float}>
     */
    private function cashSideEntries(Appointment $appointment, float $gross, float $discountAmount): array
    {
        $discount = min(round($discountAmount, 2), $gross);
        if ($discount < 0) {
            $discount = 0.0;
        }
        $net = round($gross - $discount, 2);

        $entries = [];
        if ($net > 0) {
            $entries[] = $this->entry($appointment, '1000.1', $net, 0);
        }
        if ($discount > 0) {
            $entries[] = $this->entry($appointment, '4000.5', $discount, 0);
        }

        return $entries;
    }

    /**
     * Create a posted receipt journal for the appointment and write the
     * ledger link back onto it in the same DB transaction, so the journal
     * and its idempotency column can never diverge.
     *
     * @param  array<int, array{coa_id: int, debit: float, credit: float}>  $entries
     */
    private function postAndLink(Appointment $appointment, array $entries, string $description, string $linkColumn, ?int $actorId): Journal
    {
        $instituteId = (int) $appointment->institute_id;
        $branchId = $appointment->branch_id !== null ? (int) $appointment->branch_id : null;

        $sumDebit = round(array_sum(array_column($entries, 'debit')), 2);
        $sumCredit = round(array_sum(array_column($entries, 'credit')), 2);
        if (abs($sumDebit - $sumCredit) > JournalPostingService::BALANCE_EPSILON) {
            throw new \RuntimeException(
                "OPD fee journal does not balance (debit {$sumDebit} vs credit {$sumCredit})."
            );
        }

        return DB::transaction(function () use ($appointment, $entries, $description, $linkColumn, $actorId, $instituteId, $branchId): Journal {
            $journal = $this->posting->create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'journal_date' => $appointment->fee_collected_at ?? now(),
                'type' => 'receipt',
                'currency_id' => $this->resolveCurrencyId($instituteId, $branchId),
                'description' => $description,
                'entries' => $entries,
            ], $actorId, postNow: true);

            $appointment->forceFill([$linkColumn => $journal->id])->save();

            return $journal;
        });
    }

    /**
     * Resolve a tenant-owned account by code, or fail loudly: the caller's
     * transaction must roll back rather than record fee money with no
     * ledger trace.
     */
    private function requireAccount(int $instituteId, string $code, ?int $branchId): ChartOfAccount
    {
        $account = $this->coa->accountByCode($instituteId, $code, $branchId);

        // H1: the Phase A medical COA install writes branch_id NULL rows. A
        // branch-scoped lookup therefore misses on any appointment that
        // carries a branch_id even though the tenant owns the account — fall
        // back to the unscoped tenant row instead of aborting the posting.
        if ($account === null && $branchId !== null) {
            $account = $this->coa->accountByCode($instituteId, $code, null);
        }

        if ($account === null) {
            throw new \RuntimeException(
                "OPD fee posting requires account {$code} on institute {$instituteId}."
            );
        }

        return $account;
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
