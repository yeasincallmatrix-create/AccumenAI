<?php

namespace App\Services\Medical;

use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\User;
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
     * Return queue status for one or more doctors on a given date.
     *
     * Built for the OPD Queue Display board (1-2 doctors per screen):
     * exactly ONE appointment query for every requested doctor (whereIn —
     * never one query per doctor), plus two fixed metadata lookups
     * (users + doctor profiles) that do not grow with the doctor count.
     * No per-row or per-doctor queries anywhere.
     *
     * Ordering mirrors Appointment::scopeInQueue() — manual queue_order
     * first, then serial_number, then appointment_time.
     *
     * @param  int        $instituteId
     * @param  int|null   $branchId    null = institute-wide; else branch + legacy NULL rows
     * @param  array<int> $doctorIds   1 or 2 users.id values (the caller caps the count)
     * @param  string     $date        Y-m-d, default today
     * @return array<int, array>       keyed by doctor_id (input order preserved)
     */
    public function getQueueForDoctors(
        int $instituteId,
        ?int $branchId,
        array $doctorIds,
        string $date
    ): array {
        $doctorIds = array_values(array_unique(array_filter(array_map('intval', $doctorIds))));
        if ($doctorIds === []) {
            return [];
        }

        $date = $date !== '' ? $date : today()->format('Y-m-d');

        // ONE row query for all doctors (all statuses so total_today counts
        // every appointment booked today, finished or not).
        $appointments = Appointment::where('institute_id', $instituteId)
            ->whereIn('doctor_id', $doctorIds)
            ->whereDate('appointment_date', $date)
            ->when($branchId !== null, fn ($q) => $q->where(function ($qq) use ($branchId) {
                $qq->where('branch_id', $branchId)->orWhereNull('branch_id');
            }))
            ->orderByRaw('queue_order IS NULL, queue_order ASC')
            ->orderBy('serial_number')
            ->orderBy('appointment_time')
            ->with(['patient:id,first_name,last_name'])
            ->get()
            ->groupBy('doctor_id');

        // Fixed metadata lookups (2 queries regardless of doctor count).
        $users = User::whereIn('id', $doctorIds)->get(['id', 'name'])->keyBy('id');
        $profiles = Doctor::where('institute_id', $instituteId)
            ->whereIn('user_id', $doctorIds)
            ->with('department:id,name')
            ->get()
            ->keyBy('user_id');

        $result = [];
        foreach ($doctorIds as $doctorId) {
            $rows = $appointments->get($doctorId) ?? collect();

            $nowServingRow = $rows->firstWhere('status', 'in_progress');
            $waitingRows = $rows
                ->filter(fn ($a) => in_array($a->status, ['scheduled', 'checked_in'], true));

            $result[$doctorId] = [
                'doctor_id' => $doctorId,
                'doctor_name' => $users->get($doctorId)?->name ?? 'Doctor #'.$doctorId,
                'department_name' => $profiles->get($doctorId)?->department?->name,
                'now_serving' => $nowServingRow ? $this->mapDisplayRow($nowServingRow) : null,
                'up_next' => $waitingRows->take(5)
                    ->map(fn ($a) => $this->mapDisplayRow($a))
                    ->values()
                    ->all(),
                'waiting_count' => $waitingRows->count(),
                'total_today' => $rows->count(),
            ];
        }

        return $result;
    }

    /**
     * Display row for one appointment on the queue board.
     *
     * patient_name is the raw full name — the controller applies the
     * medical.queue_display.patient_name_format setting (and hides it
     * entirely for serial_only) before anything reaches the browser.
     * patient_id lets that controller format without a second fetch.
     *
     * @return array{patient_id: int, serial_number: ?int, patient_name: string, time: string}
     */
    private function mapDisplayRow(Appointment $appointment): array
    {
        return [
            'patient_id' => (int) $appointment->patient_id,
            'serial_number' => $appointment->serial_number !== null ? (int) $appointment->serial_number : null,
            'patient_name' => trim(($appointment->patient->first_name ?? '').' '.($appointment->patient->last_name ?? '')) ?: 'N/A',
            'time' => $appointment->appointment_time
                ? Carbon::parse($appointment->appointment_time)->format('h:i A')
                : '',
        ];
    }

    /**
     * Get the current queue status for a doctor. Phase 18.1 adds an
     * optional branch limitation (context branch + legacy NULLs); serial
     * generation and ordering are untouched.
     */
    public function getQueueStatus(int $instituteId, int $doctorId, string $date, ?int $branchId = null): array
    {
        $appointments = Appointment::where('institute_id', $instituteId)
            ->where('doctor_id', $doctorId)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['scheduled', 'checked_in', 'in_progress'])
            ->when($branchId !== null, fn ($q) => $q->where(function ($qq) use ($branchId) {
                $qq->where('branch_id', $branchId)->orWhereNull('branch_id');
            }))
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
