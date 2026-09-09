<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\AppointmentRequest;
use App\Models\Country;
use App\Models\Institute;
use App\Models\Medical\Appointment;
use App\Models\Medical\Patient;
use App\Models\User;
use App\Services\Medical\AppointmentFeeService;
use App\Services\Medical\MrNumberGenerator;
use App\Services\Medical\QueueManager;
use App\Support\Workspace;
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
            new Middleware('permission:medical_appointments.edit', only: ['edit', 'update', 'checkin', 'complete', 'transfer']),
            new Middleware('permission:medical_appointments.delete', only: ['destroy']),
        ];
    }

    protected QueueManager $queueManager;
    protected MrNumberGenerator $mrGenerator;

    public function __construct(QueueManager $queueManager, MrNumberGenerator $mrGenerator)
    {
        $this->queueManager = $queueManager;
        $this->mrGenerator = $mrGenerator;
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

        // Data for the Book Appointment popup + its nested Add Patient popup.
        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();
        $countries = Country::where('status', true)->orderBy('name')->get(['id', 'name', 'phone_code']);
        $defaultCountryId = Institute::whereKey($instituteId)->value('country_id');
        $previewMr = $this->mrGenerator->generate($instituteId);

        $user = $request->user();
        $canCreatePatient = $user instanceof \App\Models\InstituteUser
            ? $user->hasPermission('medical_patients.create')
            : (Workspace::membershipFor($user)?->hasPermission('medical_patients.create') ?? false);

        return view('medical.appointments.index', compact(
            'appointments', 'doctors', 'patients', 'countries',
            'defaultCountryId', 'previewMr', 'canCreatePatient'
        ));
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
    public function store(AppointmentRequest $request, AppointmentFeeService $feeService)
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

        // First-visit vs follow-up fee (null when the doctor user has no
        // Doctor profile — booking then continues exactly as before).
        $patient = Patient::where('institute_id', $instituteId)->findOrFail($data['patient_id']);
        $quote = $feeService->calculateFor((int) $data['doctor_id'], $patient, $instituteId);
        if ($quote['fee'] !== null) {
            $data['fee_applied'] = $quote['fee'];
        }

        $appointment = Appointment::create($data);

        $message = 'Appointment booked successfully! Serial: '.$appointment->serial_number;
        if ($appointment->fee_applied !== null) {
            $message .= ' Fee: ৳'.number_format((float) $appointment->fee_applied, 2)
                .($quote['is_follow_up'] ? ' (Follow-up)' : ' (First visit)');
        }

        return redirect()->route('medical.appointments.show', $appointment)
            ->with('status', $message);
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
     * Transfer a scheduled appointment to another date.
     *
     * Keeps patient/doctor/time; moves appointment_date and issues a fresh
     * serial for the target date (serials are doctor+date scoped).
     */
    public function transfer(Request $request, Appointment $appointment)
    {
        $this->ensureSameInstitute($appointment, 'appointment');

        $validated = $request->validate([
            'appointment_date' => 'required|date|after_or_equal:today',
        ]);

        if ($appointment->status !== 'scheduled') {
            return redirect()->back()->withErrors(
                ['appointment_date' => 'Only scheduled appointments can be transferred to another date.']
            )->withInput();
        }

        $newDate = \Carbon\Carbon::parse($validated['appointment_date'])->format('Y-m-d');

        if ($newDate === $appointment->appointment_date->format('Y-m-d')) {
            return redirect()->back()->withErrors(
                ['appointment_date' => 'Appointment is already on this date. Pick a different date.']
            )->withInput();
        }

        $serial = $this->queueManager->getNextSerial(
            $appointment->institute_id,
            $appointment->doctor_id,
            $newDate
        );

        $appointment->update([
            'appointment_date' => $newDate,
            'serial_number' => $serial,
        ]);

        return redirect()->route('medical.appointments.index', ['date' => $newDate])
            ->with('status', 'Appointment transferred to '.$newDate.'. New serial: #'.$serial);
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
