<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\AppointmentRequest;
use App\Models\Medical\Appointment;
use App\Models\Medical\Patient;
use App\Models\User;
use App\Services\Medical\QueueManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AppointmentController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_appointments.view', only: ['index', 'show', 'queue']),
            new Middleware('permission:medical_appointments.create', only: ['create', 'store']),
            new Middleware('permission:medical_appointments.edit', only: ['edit', 'update', 'checkin', 'complete']),
            new Middleware('permission:medical_appointments.delete', only: ['destroy']),
        ];
    }

    protected QueueManager $queueManager;

    public function __construct(QueueManager $queueManager)
    {
        $this->queueManager = $queueManager;
    }

    /**
     * List appointments (defaults to today).
     */
    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = Appointment::where('institute_id', $instituteId)
            ->with(['patient', 'doctor']);

        // Filter by date (default: today).
        if ($request->filled('date')) {
            $query->whereDate('appointment_date', $request->date);
        } else {
            $query->whereDate('appointment_date', today());
        }

        // Filter by status.
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by doctor.
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }

        $appointments = $query->orderBy('appointment_time')->get();
        $doctors = $this->doctors($instituteId);

        return view('medical.appointments.index', compact('appointments', 'doctors'));
    }

    /**
     * Show appointment booking form.
     */
    public function create(Request $request)
    {
        $instituteId = $this->instituteId();

        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();

        $doctors = $this->doctors($instituteId);

        $selectedPatient = null;
        if ($request->filled('patient_id')) {
            $selectedPatient = Patient::where('institute_id', $instituteId)
                ->find($request->patient_id);
        }

        return view('medical.appointments.create', compact('patients', 'doctors', 'selectedPatient'));
    }

    /**
     * Store a new appointment.
     */
    public function store(AppointmentRequest $request)
    {
        $instituteId = $this->instituteId();
        $data = $request->validated();
        $data['institute_id'] = $instituteId;

        // Generate serial number (institute + doctor + date scoped).
        $data['serial_number'] = $this->queueManager->getNextSerial(
            $instituteId,
            $data['doctor_id'],
            $data['appointment_date']
        );

        $appointment = Appointment::create($data);

        return redirect()->route('medical.appointments.show', $appointment)
            ->with('status', 'Appointment booked successfully! Serial: '.$appointment->serial_number);
    }

    /**
     * Show appointment details.
     */
    public function show(Appointment $appointment)
    {
        $this->ensureSameInstitute($appointment, 'appointment');
        $appointment->load(['patient', 'doctor']);

        return view('medical.appointments.show', compact('appointment'));
    }

    /**
     * Show appointment edit form.
     */
    public function edit(Appointment $appointment)
    {
        $this->ensureSameInstitute($appointment, 'appointment');
        $instituteId = $this->instituteId();

        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();

        $doctors = $this->doctors($instituteId);

        return view('medical.appointments.edit', compact('appointment', 'patients', 'doctors'));
    }

    /**
     * Update appointment.
     */
    public function update(AppointmentRequest $request, Appointment $appointment)
    {
        $this->ensureSameInstitute($appointment, 'appointment');

        $data = $request->validated();

        // If doctor or date changed, regenerate serial.
        if ((int) $request->doctor_id !== (int) $appointment->doctor_id
            || $request->appointment_date !== $appointment->appointment_date->format('Y-m-d')) {
            $data['serial_number'] = $this->queueManager->getNextSerial(
                $appointment->institute_id,
                (int) $request->doctor_id,
                $request->appointment_date
            );
        }

        $appointment->update($data);

        return redirect()->route('medical.appointments.show', $appointment)
            ->with('status', 'Appointment updated successfully!');
    }

    /**
     * Cancel appointment (status change, record kept).
     */
    public function destroy(Appointment $appointment)
    {
        $this->ensureSameInstitute($appointment, 'appointment');
        $appointment->update(['status' => 'cancelled']);

        return redirect()->route('medical.appointments.index')
            ->with('status', 'Appointment cancelled successfully!');
    }

    /**
     * Display real-time queue for a doctor.
     */
    public function queue(Request $request)
    {
        $instituteId = $this->instituteId();
        $doctors = $this->doctors($instituteId);

        // Doctor resolution: route param {doctor?} → ?doctor_id= → first doctor.
        $doctorId = $request->route('doctor')
            ?? $request->input('doctor_id')
            ?? $doctors->first()?->id;

        $date = $request->input('date', today()->format('Y-m-d'));

        $queueStatus = $doctorId
            ? $this->queueManager->getQueueStatus($instituteId, (int) $doctorId, $date)
            : ['total' => 0, 'waiting' => 0, 'checked_in' => 0, 'in_progress' => 0, 'estimated_wait_minutes' => 0, 'queue' => collect()];

        return view('medical.appointments.queue', compact('doctors', 'queueStatus', 'doctorId', 'date'));
    }

    /**
     * Check in a patient.
     */
    public function checkin(Appointment $appointment)
    {
        $this->ensureSameInstitute($appointment, 'appointment');
        $this->queueManager->checkIn($appointment);

        return redirect()->back()->with('status', 'Patient checked in successfully!');
    }

    /**
     * Complete appointment.
     */
    public function complete(Appointment $appointment)
    {
        $this->ensureSameInstitute($appointment, 'appointment');
        $this->queueManager->complete($appointment);

        return redirect()->back()->with('status', 'Appointment completed!');
    }

    /**
     * Doctors available for booking.
     *
     * Phase 1 note: appointments.doctor_id references the global `users`
     * table and no institute↔doctor mapping exists yet (that arrives with
     * HR/staff integration), so the selector lists active system users.
     * Validation additionally requires the account to be active.
     */
    private function doctors(int $instituteId)
    {
        return User::where('status', 'active')
            ->orderBy('name')
            ->get();
    }
}
