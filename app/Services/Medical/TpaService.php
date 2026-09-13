<?php

namespace App\Services\Medical;

use App\Models\Medical\Invoice;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\TpaClaim;
use App\Support\MedicalScope;
use Illuminate\Support\Facades\DB;

/**
 * TPA (insurance) claim pipeline: numbering, creation, approval (which
 * credits the linked invoice), rejection and settlement.
 */
class TpaService
{
    /**
     * Generate a unique claim number (stored TPA-YYYY-NNNNN, displayed
     * TPA-YY-NNNNN) via the database-backed sequence (Phase 04).
     */
    public function generateClaimNumber(int $instituteId): string
    {
        return app(NumberSequenceService::class)->next(NumberSequence::TYPE_TPA_CLAIM, $instituteId);
    }

    /**
     * Create a new TPA claim.
     */
    public function createClaim(array $data): TpaClaim
    {
        $instituteId = MedicalScope::instituteIdOrFail();

        // Invoice must belong to this institute and to the same patient.
        $invoice = Invoice::where('institute_id', $instituteId)
            ->findOrFail($data['invoice_id']);

        if ((int) $invoice->patient_id !== (int) $data['patient_id']) {
            throw new \RuntimeException('Invoice does not belong to the selected patient.');
        }

        // Phase 08: a claim reimburses the invoice — it can never exceed what
        // is still outstanding, otherwise approval would overpay the invoice
        // (paid > total breaks the paid/due invariant).
        $outstanding = round((float) $invoice->total - (float) $invoice->paid_amount, 2);
        if (round((float) $data['claim_amount'], 2) > $outstanding) {
            throw new \RuntimeException(
                'Claim amount cannot exceed the invoice outstanding due (৳'.number_format($outstanding, 2).').'
            );
        }

        $data['institute_id'] = $instituteId;
        $data['claim_number'] = $this->generateClaimNumber($instituteId);
        $data['claim_date'] = $data['claim_date'] ?? now()->format('Y-m-d');
        $data['status'] = 'pending';

        return TpaClaim::create($data);
    }

    /**
     * Approve a claim (credits the linked invoice).
     */
    public function approveClaim(TpaClaim $claim, float $approvedAmount): bool
    {
        return DB::transaction(function () use ($claim, $approvedAmount) {
            // Phase 08: re-read under lock — a concurrent approval or payment
            // must not double-credit the invoice or push paid past total.
            $claim = TpaClaim::whereKey($claim->getKey())->lockForUpdate()->firstOrFail();

            if ($claim->status !== 'pending') {
                throw new \RuntimeException('Only pending claims can be approved.');
            }

            if ($approvedAmount <= 0 || $approvedAmount > (float) $claim->claim_amount) {
                throw new \RuntimeException('Approved amount must be between 0.01 and the claimed amount.');
            }

            $claim->update([
                'status' => 'approved',
                'approved_amount' => $approvedAmount,
                'approval_date' => now()->format('Y-m-d'),
            ]);

            // Mark the associated invoice as paid (if fully approved).
            // Locked re-read + due cap: a concurrent cash payment after the
            // claim was filed must not let approval push paid past total.
            if ($claim->invoice_id) {
                $invoice = Invoice::whereKey($claim->invoice_id)->lockForUpdate()->firstOrFail();
                $due = round((float) $invoice->total - (float) $invoice->paid_amount, 2);
                if (round($approvedAmount, 2) > $due) {
                    throw new \RuntimeException(
                        'Approved amount exceeds the invoice outstanding due (৳'.number_format($due, 2).').'
                    );
                }
                $newPaid = round((float) $invoice->paid_amount + $approvedAmount, 2);
                $newDue = round((float) $invoice->total - $newPaid, 2);

                $invoice->update([
                    'paid_amount' => $newPaid,
                    'due_amount' => max(0, $newDue),
                    'status' => $newDue <= 0 ? 'paid' : 'partial',
                    'payment_method' => 'tpa',
                ]);
            }

            return true;
        });
    }

    /**
     * Reject a claim.
     */
    public function rejectClaim(TpaClaim $claim, string $reason): bool
    {
        $claim->update([
            'status' => 'rejected',
            'remarks' => $reason,
        ]);

        return true;
    }

    /**
     * Settle a claim.
     */
    public function settleClaim(TpaClaim $claim): bool
    {
        if ($claim->status !== 'approved') {
            throw new \RuntimeException('Only approved claims can be settled.');
        }

        $claim->update([
            'status' => 'settled',
            'settlement_date' => now()->format('Y-m-d'),
        ]);

        return true;
    }

    /**
     * Get claim statistics for an institute.
     */
    public function getClaimStats(int $instituteId, ?int $doctorUserId = null): array
    {
        $claims = TpaClaim::where('institute_id', $instituteId)
            ->whereHas('invoice', fn ($q) => $q->visibleToDoctor($instituteId, $doctorUserId))
            ->get();

        return [
            'total_claims' => $claims->count(),
            'pending' => $claims->where('status', 'pending')->count(),
            'approved' => $claims->where('status', 'approved')->count(),
            'rejected' => $claims->where('status', 'rejected')->count(),
            'settled' => $claims->where('status', 'settled')->count(),
            'total_claimed_amount' => $claims->sum('claim_amount'),
            'total_approved_amount' => $claims->sum('approved_amount'),
        ];
    }
}
