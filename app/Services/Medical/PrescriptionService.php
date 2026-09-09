<?php

namespace App\Services\Medical;

use App\Models\Medical\Prescription;
use App\Models\Medical\PrescriptionAuditLog;
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

            $loaded = $prescription->load('items');
            PrescriptionAuditLog::record($loaded, 'created', count($items).' item(s)');

            return $loaded;
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
     * Finalize (sign) a prescription: locks items, stamps signing metadata
     * and writes the audit trail. Signing hash binds number + parties +
     * item set + timestamp so the printed QR can be re-verified.
     */
    public function finalize(Prescription $prescription): void
    {
        $now = now();
        $itemKey = $prescription->items()
            ->orderBy('id')
            ->get(['medicine_id', 'medicine_name', 'dosage', 'frequency', 'duration_days', 'quantity'])
            ->map(fn ($i) => implode('|', [$i->medicine_id, $i->medicine_name, $i->dosage, $i->frequency, $i->duration_days, $i->quantity]))
            ->implode(';');

        $prescription->update([
            'is_finalized' => true,
            'signed_at' => $now,
            'signed_by' => $this->actorId(),
            'signature_hash' => hash('sha256', implode('|', [
                $prescription->prescription_number,
                $prescription->institute_id,
                $prescription->patient_id,
                $prescription->doctor_id,
                $itemKey,
                $now->format('Y-m-d H:i:s'),
            ])),
        ]);

        PrescriptionAuditLog::record($prescription, 'signed',
            'Signed by #'.$prescription->signed_by.' ('.$prescription->items()->count().' items)');
    }

    /**
     * Verification payload encoded in the printed QR code.
     */
    public function verificationPayload(Prescription $prescription): string
    {
        return implode('|', [
            'RX:'.$prescription->prescription_number,
            'INST:'.$prescription->institute_id,
            'SIG:'.substr((string) $prescription->signature_hash, 0, 12),
        ]);
    }

    /**
     * QR code (SVG data URI, no GD extension needed) for print views.
     */
    public function verificationQr(Prescription $prescription): string
    {
        try {
            return (string) (new \chillerlan\QRCode\QRCode(
                new \chillerlan\QRCode\QROptions([
                    'outputType' => \chillerlan\QRCode\Output\QRMarkupSVG::class,
                ])
            ))->render($this->verificationPayload($prescription));
        } catch (\Throwable) {
            return '';
        }
    }

    private function actorId(): ?int
    {
        try {
            $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();

            return $staff ? (int) $staff->getKey() : null;
        } catch (\Throwable) {
            return null;
        }
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
            'qr' => $prescription->is_finalized ? $this->verificationQr($prescription) : '',
            'verifyCode' => $prescription->is_finalized
                ? $this->verificationPayload($prescription)
                : '',
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
