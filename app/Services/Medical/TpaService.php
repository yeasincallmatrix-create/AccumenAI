<?php

namespace App\Services\Medical;

use App\Models\Medical\Invoice;
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
     * Generate a unique claim number (TPA-YYYY-III-XXXXX).
     */
    public function generateClaimNumber(int $instituteId): string
    {
        $year = date('Y');
        $prefix = 'TPA-'.$year.'-'.str_pad((string) $instituteId, 3, '0', STR_PAD_LEFT).'-';

        return DB::transaction(function () use ($instituteId, $year, $prefix) {
            $last = TpaClaim::where('institute_id', $instituteId)
                ->whereYear('created_at', $year)
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $nextNumber = 1;
            if ($last && preg_match('/(\d{5})$/', (string) $last->claim_number, $m)) {
                $nextNumber = ((int) $m[1]) + 1;
            } elseif ($last) {
                $nextNumber = TpaClaim::where('institute_id', $instituteId)
                    ->whereYear('created_at', $year)
                    ->count() + 1;
            }

            $candidate = $prefix.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);

            while (TpaClaim::where('claim_number', $candidate)->exists()) {
                $nextNumber++;
                $candidate = $prefix.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
            }

            return $candidate;
        });
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
            if ($approvedAmount <= 0 || $approvedAmount > (float) $claim->claim_amount) {
                throw new \RuntimeException('Approved amount must be between 0.01 and the claimed amount.');
            }

            $claim->update([
                'status' => 'approved',
                'approved_amount' => $approvedAmount,
                'approval_date' => now()->format('Y-m-d'),
            ]);

            // Mark the associated invoice as paid (if fully approved).
            if ($claim->invoice) {
                $invoice = $claim->invoice;
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
