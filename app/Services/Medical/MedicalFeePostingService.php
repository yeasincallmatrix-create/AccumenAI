<?php

namespace App\Services\Medical;

use App\Models\AccountingSetting;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Journal;
use App\Models\Medical\Appointment;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;

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
     * Resolve a tenant-owned account by code, or fail loudly: the caller's
     * transaction must roll back rather than record fee money with no
     * ledger trace.
     */
    private function requireAccount(int $instituteId, string $code, ?int $branchId): ChartOfAccount
    {
        $account = $this->coa->accountByCode($instituteId, $code, $branchId);

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
