<?php

namespace App\Services\Medical;

use App\Models\Medical\Appointment;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Encounter;
use App\Models\Medical\NumberSequence;
use Illuminate\Database\QueryException;

/**
 * Reconciliation helper: 1 visit = 1 Encounter.
 *
 * Appointments stay queue+fee shells; the Encounter is the clinical record.
 * Every method is best-effort and never throws into the queue/Rx flow —
 * a failure returns null and the caller continues exactly as before
 * (legacy NULL encounter links stay valid).
 */
class EncounterAutoService
{
    public function __construct(
        protected NumberSequenceService $sequences
    ) {}

    /**
     * Ensure an OPD encounter exists for a checked-in/in-progress visit.
     * Returns the existing row when the appointment already has one
     * (DB unique on appointment_id is the final backstop against doubles).
     */
    public function ensureForAppointment(Appointment $appointment, ?int $actorId = null): ?Encounter
    {
        try {
            if (! in_array($appointment->status, ['checked_in', 'in_progress', 'completed'], true)) {
                return null;
            }

            $existing = Encounter::where('appointment_id', $appointment->id)->first();
            if ($existing) {
                $this->syncProgress($existing, $appointment);
                $this->backfillComplaint($existing, $appointment);

                return $existing->refresh();
            }

            $number = $this->sequences->next(NumberSequence::TYPE_ENCOUNTER, (int) $appointment->institute_id);

            $status = $appointment->status === 'in_progress'
                ? Encounter::STATUS_IN_PROGRESS
                : Encounter::STATUS_OPEN;

            try {
                $encounter = Encounter::create([
                    'institute_id' => $appointment->institute_id,
                    'branch_id' => $appointment->branch_id,
                    'patient_id' => $appointment->patient_id,
                    'appointment_id' => $appointment->id,
                    'doctor_id' => $appointment->doctor_id,
                    'encounter_number' => $number,
                    'encounter_type' => Encounter::TYPE_OPD,
                    'status' => $status,
                    'started_at' => now(),
                    'chief_complaint' => $appointment->complaints ? trim((string) $appointment->complaints) : null,
                    'created_by' => $actorId,
                ]);
            } catch (QueryException $e) {
                // Concurrent check-in/start race: the loser re-reads the winner.
                if ($e->getCode() === '23000') {
                    return Encounter::where('appointment_id', $appointment->id)->first();
                }
                throw $e;
            }

            try {
                ClinicalAuditLog::record($encounter, 'auto_created', [
                    'new' => [
                        'encounter_number' => $encounter->encounter_number,
                        'appointment_id' => $appointment->id,
                        'status' => $encounter->status,
                    ],
                ]);
            } catch (\Throwable) {
                // Audit must never break the visit flow.
            }

            return $encounter;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Resolve the encounter for a prescription save: explicit encounter_id
     * wins, otherwise fall back to the chained queue visit's encounter
     * (creating it when the visit is still actionable).
     */
    public function resolveForPrescription(?int $encounterId, ?Appointment $feeAppointment, ?int $actorId = null): ?Encounter
    {
        try {
            if ($encounterId) {
                return Encounter::find($encounterId);
            }
            if ($feeAppointment) {
                return $this->ensureForAppointment($feeAppointment, $actorId);
            }

            return null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function syncProgress(Encounter $encounter, Appointment $appointment): void
    {
        try {
            if ($appointment->status === 'in_progress' && $encounter->status === Encounter::STATUS_OPEN) {
                $encounter->transitionTo(Encounter::STATUS_IN_PROGRESS);
            }
        } catch (\Throwable) {
            // Illegal transitions stay silent — the encounter record wins.
        }
    }

    private function backfillComplaint(Encounter $encounter, Appointment $appointment): void
    {
        try {
            $booking = $appointment->complaints ? trim((string) $appointment->complaints) : '';
            if ($booking !== '' && trim((string) ($encounter->chief_complaint ?? '')) === '') {
                $encounter->update(['chief_complaint' => $booking]);
            }
        } catch (\Throwable) {
            // Prefill must never break the flow.
        }
    }
}
