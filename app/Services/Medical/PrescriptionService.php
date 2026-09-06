<?php

namespace App\Services\Medical;

use App\Models\Medical\Prescription;
use App\Models\Medical\PrescriptionItem;
use App\Support\MedicalScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Prescription numbering, creation and dispensing readiness.
 */
class PrescriptionService
{
    /**
     * Generate a unique prescription number (RX-YYYY-III-XXXXX).
     */
    public function generateNumber(int $instituteId): string
    {
        $year = date('Y');
        $prefix = 'RX-'.$year.'-'.str_pad((string) $instituteId, 3, '0', STR_PAD_LEFT).'-';

        return DB::transaction(function () use ($instituteId, $year, $prefix) {
            $last = Prescription::where('institute_id', $instituteId)
                ->whereYear('created_at', $year)
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $nextNumber = 1;
            if ($last && preg_match('/(\d{5})$/', (string) $last->prescription_number, $m)) {
                $nextNumber = ((int) $m[1]) + 1;
            } elseif ($last) {
                $nextNumber = Prescription::where('institute_id', $instituteId)
                    ->whereYear('created_at', $year)
                    ->count() + 1;
            }

            $candidate = $prefix.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);

            while (Prescription::where('prescription_number', $candidate)->exists()) {
                $nextNumber++;
                $candidate = $prefix.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
            }

            return $candidate;
        });
    }

    /**
     * Create a new prescription with items.
     */
    public function createPrescription(array $data, array $items): Prescription
    {
        return DB::transaction(function () use ($data, $items) {
            $instituteId = MedicalScope::instituteIdOrFail();

            // Create prescription.
            $data['prescription_number'] = $this->generateNumber($instituteId);
            $data['institute_id'] = $instituteId;

            $prescription = Prescription::create($data);

            // Create items.
            foreach ($items as $item) {
                $item['prescription_id'] = $prescription->id;
                PrescriptionItem::create($item);
            }

            return $prescription->load('items');
        });
    }

    /**
     * Replace all items of a draft prescription.
     */
    public function replaceItems(Prescription $prescription, array $items): void
    {
        DB::transaction(function () use ($prescription, $items) {
            $prescription->items()->delete();
            foreach ($items as $item) {
                $item['prescription_id'] = $prescription->id;
                PrescriptionItem::create($item);
            }
        });
    }

    /**
     * Finalize a prescription.
     */
    public function finalize(Prescription $prescription): void
    {
        $prescription->update(['is_finalized' => true]);
    }

    /**
     * Get prescription for printing.
     */
    public function getPrintData(Prescription $prescription): array
    {
        $prescription->load(['patient', 'doctor', 'items.medicine']);

        return [
            'prescription' => $prescription,
            'patient' => $prescription->patient,
            'doctor' => $prescription->doctor,
            'items' => $prescription->items,
        ];
    }

    /**
     * Check if a prescription can be dispensed.
     */
    public function canDispense(Prescription $prescription): bool
    {
        if (! $prescription->is_finalized) {
            return false;
        }

        // Check if any items are still pending.
        return $prescription->items()->where('status', 'pending')->exists();
    }

    /**
     * Get pending (finalized, undispensed) prescriptions for pharmacy.
     */
    public function getPendingPrescriptions(int $instituteId): Collection
    {
        return Prescription::where('institute_id', $instituteId)
            ->where('is_finalized', true)
            ->whereHas('items', function ($q) {
                $q->where('status', 'pending');
            })
            ->with(['patient', 'doctor', 'items'])
            ->orderBy('prescription_date', 'desc')
            ->get();
    }
}
