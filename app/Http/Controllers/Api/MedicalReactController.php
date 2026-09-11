<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Services\Medical\QueueManager;
use App\Support\MedicalScope;
use Illuminate\Http\Request;

/**
 * Sanctum JSON feeds backing the React medical components.
 *
 * All endpoints are institute-scoped (via ensure.institute.context +
 * MedicalScope) and honour doctor fencing, exactly like the sibling web
 * feeds on the medical.*.react.data routes. The in-Blade React components
 * poll the session-auth web feeds; these token-auth endpoints serve
 * external/mobile clients.
 */
class MedicalReactController extends Controller
{
    /**
     * Live queue for one doctor: GET /api/medical/queue/{doctorId}?date=Y-m-d
     */
    public function queue(Request $request, int $doctorId, QueueManager $queues)
    {
        $instituteId = MedicalScope::instituteIdOrFail();

        // Fenced doctors may only read their own queue — no oracle widening.
        if (($fence = MedicalScope::ownDoctorUserId($instituteId)) !== null && $doctorId !== $fence) {
            abort(403, 'You do not have permission to view this queue.');
        }
        if (! MedicalScope::isDoctorInInstitute($doctorId, $instituteId)) {
            abort(403, 'You do not have permission to view this queue.');
        }

        $date = (string) $request->query('date', today()->format('Y-m-d'));

        // Phase 18.1: token branch scope applies when present (null-safe).
        $status = $queues->getQueueStatus($instituteId, $doctorId, $date, \App\Support\BranchContext::id());
        $queue = $status['queue'] instanceof \Illuminate\Support\Collection
            ? $status['queue']->values()->all()
            : array_values((array) ($status['queue'] ?? []));

        return response()->json($queue);
    }

    /**
     * Appointment list: GET /api/medical/appointments?date=&doctor_id=&status=
     */
    public function appointments(Request $request)
    {
        $instituteId = MedicalScope::instituteIdOrFail();
        $fence = MedicalScope::ownDoctorUserId($instituteId);

        $query = \App\Models\Medical\Appointment::where('institute_id', $instituteId)
            ->with(['patient', 'doctor']);
        // Phase 18.1: token branch scope applies when present (null-safe).
        if (($apiBranch = \App\Support\BranchContext::id()) !== null) {
            $query->where(function ($q) use ($apiBranch) {
                $q->where('branch_id', $apiBranch)->orWhereNull('branch_id');
            });
        }
        if ($fence !== null) {
            $query->where('doctor_id', $fence);
        }
        if ($request->filled('date')) {
            $query->whereDate('appointment_date', $request->date);
        } else {
            $query->whereDate('appointment_date', today());
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', (int) $request->doctor_id);
        }
        if (is_string($request->input('search')) && trim((string) $request->input('search')) !== '') {
            $search = trim((string) $request->input('search'));
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        return response()->json($query->orderBy('appointment_time')->get()
            ->map(fn (\App\Models\Medical\Appointment $appointment) => [
                'id' => $appointment->id,
                'patient_name' => $appointment->patient->full_name ?? 'N/A',
                'patient_phone' => $appointment->patient->phone ?? 'N/A',
                'doctor_name' => $appointment->doctor->name ?? 'N/A',
                'date_display' => mawa_format_date($appointment->appointment_date, null, 'd M Y'),
                'time_display' => $appointment->appointment_time
                    ? \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A')
                    : 'N/A',
                'serial_number' => $appointment->serial_number,
                'status' => $appointment->status,
            ])
            ->values()
            ->all());
    }

    /**
     * Patient list: GET /api/medical/patients
     */
    public function patients()
    {
        $instituteId = MedicalScope::instituteIdOrFail();
        $fence = MedicalScope::ownDoctorUserId($instituteId);

        $query = Patient::where('institute_id', $instituteId)->orderBy('first_name');
        if ($fence !== null) {
            $query->whereHas('appointments', fn ($q) => $q
                ->where('institute_id', $instituteId)
                ->where('doctor_id', $fence));
        }

        return response()->json($query->limit(200)->get()->map(fn (Patient $patient) => [
            'id' => $patient->id,
            'mr_number' => $patient->mr_number,
            'name' => $patient->full_name,
            'age' => $patient->age,
            'gender' => $patient->gender,
            'phone' => $patient->phone,
            'blood_group' => $patient->blood_group,
            'is_active' => (bool) $patient->is_active,
        ])->all());
    }

    /**
     * Prescription list: GET /api/medical/prescriptions
     */
    public function prescriptions()
    {
        $instituteId = MedicalScope::instituteIdOrFail();
        $fence = MedicalScope::ownDoctorUserId($instituteId);

        $query = Prescription::where('institute_id', $instituteId)
            ->with(['patient', 'doctor'])
            ->withCount('items');
        // Phase 18.1: token branch scope applies when present (null-safe).
        if (($apiBranch = \App\Support\BranchContext::id()) !== null) {
            $query->where(function ($q) use ($apiBranch) {
                $q->where('branch_id', $apiBranch)->orWhereNull('branch_id');
            });
        }
        if ($fence !== null) {
            $query->where('doctor_id', $fence);
        }

        return response()->json($query->orderBy('prescription_date', 'desc')->limit(100)->get()
            ->map(fn (Prescription $prescription) => [
                'id' => $prescription->id,
                'prescription_number' => $prescription->prescription_number,
                'patient_name' => $prescription->patient->full_name ?? 'N/A',
                'doctor_name' => $prescription->doctor->name ?? null,
                'prescription_date' => $prescription->prescription_date?->format('Y-m-d'),
                'is_finalized' => (bool) $prescription->is_finalized,
                'items_count' => (int) ($prescription->items_count ?? 0),
            ])
            ->all());
    }
}
