<?php

namespace App\Services\Medical;

use App\Models\Medical\Appointment;
use Carbon\Carbon;

/**
 * Manage OPD serial numbers and queue state.
 *
 * Phase 1 adaptation: every query is scoped to the institute (the spec
 * version was doctor+date only, which leaks serials across tenants).
 */
class QueueManager
{
    /**
     * Generate the next serial number for a doctor on a specific date.
     */
    public function getNextSerial(int $instituteId, int $doctorId, string $date): int
    {
        $lastSerial = Appointment::where('institute_id', $instituteId)
            ->where('doctor_id', $doctorId)
            ->whereDate('appointment_date', $date)
            ->max('serial_number');

        return $lastSerial ? ((int) $lastSerial) + 1 : 1;
    }

    /**
     * Get the current queue status for a doctor.
     */
    public function getQueueStatus(int $instituteId, int $doctorId, string $date): array
    {
        $appointments = Appointment::where('institute_id', $instituteId)
            ->where('doctor_id', $doctorId)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['scheduled', 'checked_in', 'in_progress'])
            // Manual drag-and-drop order first; never-reordered rows keep serial order.
            ->orderByRaw('queue_order IS NULL, queue_order ASC')
            ->orderBy('serial_number')
            ->get();

        return [
            'total' => $appointments->count(),
            'waiting' => $appointments->where('status', 'scheduled')->count(),
            'checked_in' => $appointments->where('status', 'checked_in')->count(),
            'in_progress' => $appointments->where('status', 'in_progress')->count(),
            'estimated_wait_minutes' => $appointments->whereIn('status', ['scheduled', 'checked_in'])->count() * 10,
            'queue' => $appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'serial' => $appointment->serial_number,
                    'patient_name' => $appointment->patient->full_name ?? 'N/A',
                    'status' => $appointment->status,
                    'estimated_time' => $appointment->status === 'in_progress' ? 'Now' : $this->getEstimatedTime($appointment),
                ];
            }),
        ];
    }

    /**
     * Get estimated time for an appointment.
     */
    private function getEstimatedTime(Appointment $appointment): string
    {
        $position = Appointment::where('institute_id', $appointment->institute_id)
            ->where('doctor_id', $appointment->doctor_id)
            ->whereDate('appointment_date', $appointment->appointment_date)
            ->where('serial_number', '<', $appointment->serial_number)
            ->whereIn('status', ['scheduled', 'checked_in'])
            ->count();

        $minutes = $position * 10;

        return Carbon::now()->addMinutes($minutes)->format('h:i A');
    }

    /**
     * Mark appointment as checked in.
     */
    public function checkIn(Appointment $appointment): void
    {
        if ($appointment->status === 'scheduled') {
            $appointment->update(['status' => 'checked_in']);
        }
    }

    /**
     * Mark appointment as in progress.
     */
    public function startConsultation(Appointment $appointment): void
    {
        if (in_array($appointment->status, ['checked_in', 'scheduled'], true)) {
            $appointment->update(['status' => 'in_progress']);
        }
    }

    /**
     * Mark appointment as completed.
     *
     * Accepts checked_in as well as in_progress: Phase 1 routes expose only
     * checkin/complete (no start-consultation step), so a checked-in visit
     * must be completable directly. startConsultation() remains for the
     * full three-step flow in Phase 2+.
     */
    public function complete(Appointment $appointment): void
    {
        if (in_array($appointment->status, ['checked_in', 'in_progress'], true)) {
            $appointment->update(['status' => 'completed']);
        }
    }
}
