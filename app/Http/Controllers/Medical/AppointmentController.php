<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\AppointmentRequest;
use App\Models\Country;
use App\Models\Institute;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\Patient;
use App\Models\Medical\QueueAuditLog;
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
            new Middleware('permission:medical_appointments.view', only: ['index', 'show', 'queue', 'reactIndex', 'reactQueueData', 'reactAppointmentsData']),
            new Middleware('permission:medical_appointments.create', only: ['create', 'store']),
            new Middleware('permission:medical_appointments.edit', only: ['edit', 'update', 'checkin', 'complete', 'transfer', 'collectFee']),
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

        // Fenced doctors see only their own appointments (request filter
        // cannot widen this).
        $fence = $this->doctorFenceId();
        if ($fence !== null) {
            $query->where('doctor_id', $fence);
        }

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
        if ($fence !== null) {
            $doctors = $doctors->where('id', $fence)->values();
        }

        // Data for the Book Appointment popup + its nested Add Patient popup.
        // Fenced doctors choose from their own patients plus brand-new ones.
        $patients = $this->ownPatientOptions($instituteId, $fence);
        $countries = Country::where('status', true)->orderBy('name')->get(['id', 'name', 'phone_code']);
        $defaultCountryId = Institute::whereKey($instituteId)->value('country_id');
        $previewMr = $this->mrGenerator->generate($instituteId);

        $user = $request->user();
        $canCreatePatient = $user instanceof \App\Models\InstituteUser
            ? $user->hasPermission('medical_patients.create')
            : (Workspace::membershipFor($user)?->hasPermission('medical_patients.create') ?? false);

        $activeTab = in_array($request->input('tab'), ['queue', 'audit'], true) ? $request->input('tab') : 'appointments';
        $queue = $this->queueViewData($instituteId, $request, $doctors);

        // Props for the React appointments list (filters + table). The
        // server still renders the page shell, tabs, modals and Livewire
        // queue — React owns only the Appointments-tab list.
        $reactProps = [
            'initialAppointments' => $this->mapAppointmentsForReact($appointments),
            'doctors' => $doctors->map(fn ($doctor) => [
                'id' => $doctor->id,
                'name' => $doctor->name,
            ])->values()->all(),
            'filters' => [
                'date' => (string) $request->input('date', today()->format('Y-m-d')),
                'doctor_id' => (string) $request->input('doctor_id', ''),
                'status' => (string) $request->input('status', ''),
            ],
            'dataUrl' => route('medical.appointments.react.data'),
            'indexUrl' => route('medical.appointments.index'),
            // Finalized (completed / paid) rows hide their Cancel button for
            // non-admins; the server still enforces this on every request.
            'canDeleteFinalized' => $this->isMedicalAdmin($instituteId),
        ];

        return view('medical.appointments.index', compact(
            'appointments', 'doctors', 'patients', 'countries',
            'defaultCountryId', 'previewMr', 'canCreatePatient',
            'activeTab', 'queue', 'instituteId', 'reactProps'
        ));
    }

    /**
     * Show appointment booking form.
     */
    public function create(Request $request)
    {
        $instituteId = $this->instituteId();
        $fence = $this->doctorFenceId();

        $patients = $this->ownPatientOptions($instituteId, $fence);

        $doctors = $this->doctors($instituteId);
        if ($fence !== null) {
            $doctors = $doctors->where('id', $fence)->values();
        }

        $selectedPatient = null;
        if ($request->filled('patient_id')) {
            $selectedPatient = Patient::where('institute_id', $instituteId)
                ->find($request->patient_id);
            if ($selectedPatient && ! $this->mayActOnPatient($selectedPatient, $fence)) {
                abort(403, 'You do not have permission to book for this patient.');
            }
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

        // Fenced doctors book only under themselves.
        if (($fence = $this->doctorFenceId()) !== null) {
            $data['doctor_id'] = $fence;
        }

        // Generate serial number (institute + doctor + date scoped).
        $data['serial_number'] = $this->queueManager->getNextSerial(
            $instituteId,
            $data['doctor_id'],
            $data['appointment_date']
        );

        // First-visit vs follow-up fee (null when the doctor user has no
        // Doctor profile â€” booking then continues exactly as before).
        $patient = Patient::where('institute_id', $instituteId)->findOrFail($data['patient_id']);
        if (! $this->mayActOnPatient($patient)) {
            abort(403, 'You do not have permission to book for this patient.');
        }
        $quote = $feeService->calculateFor((int) $data['doctor_id'], $patient, $instituteId);
        if ($quote['fee'] !== null) {
            $data['fee_applied'] = $quote['fee'];
        }

        $appointment = Appointment::create($data);

        $message = 'Appointment booked successfully! Serial: '.$appointment->serial_number;
        if ($appointment->fee_applied !== null) {
            $message .= ' Fee: à§³'.number_format((float) $appointment->fee_applied, 2)
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
        $this->ensureDoctorOwns($appointment, 'doctor_id', 'appointment');
        $appointment->load(['patient', 'doctor']);
        $canDeleteFinalized = $this->isMedicalAdmin($appointment->institute_id);
        $isOwnDoctor = $this->isOwnDoctor($appointment->doctor_id, $appointment->institute_id);

        return view('medical.appointments.show', compact('appointment', 'canDeleteFinalized', 'isOwnDoctor'));
    }

    /**
     * Show appointment edit form.
     */
    public function edit(Appointment $appointment)
    {
        $this->ensureSameInstitute($appointment, 'appointment');
        $this->ensureDoctorOwns($appointment, 'doctor_id', 'appointment');
        $instituteId = $this->instituteId();
        $fence = $this->doctorFenceId();

        $patients = $this->ownPatientOptions($instituteId, $fence);

        $doctors = $this->doctors($instituteId);
        if ($fence !== null) {
            $doctors = $doctors->where('id', $fence)->values();
        }

        return view('medical.appointments.edit', compact('appointment', 'patients', 'doctors'));
    }

    /**
     * Update appointment.
     */
    public function update(AppointmentRequest $request, Appointment $appointment)
    {
        $this->ensureSameInstitute($appointment, 'appointment');
        $this->ensureDoctorOwns($appointment, 'doctor_id', 'appointment');

        $data = $request->validated();

        // Fenced doctors keep their own doctor (no reassignment away).
        if (($fence = $this->doctorFenceId()) !== null) {
            $data['doctor_id'] = $fence;
            if ((int) ($data['patient_id'] ?? 0) !== (int) $appointment->patient_id) {
                $next = Patient::where('institute_id', $appointment->institute_id)->find($data['patient_id']);
                if (! $next || ! $this->mayActOnPatient($next, $fence)) {
                    abort(403, 'You do not have permission to book for this patient.');
                }
            }
        }

        // If doctor or date changed, regenerate serial.
        if ((int) ($data['doctor_id'] ?? $request->doctor_id) !== (int) $appointment->doctor_id
            || $request->appointment_date !== $appointment->appointment_date->format('Y-m-d')) {
            $data['serial_number'] = $this->queueManager->getNextSerial(
                $appointment->institute_id,
                (int) ($data['doctor_id'] ?? $request->doctor_id),
                $request->appointment_date
            );
        }

        $appointment->update($data);

        return redirect()->route('medical.appointments.show', $appointment)
            ->with('status', 'Appointment updated successfully!');
    }

    /**
     * Cancel appointment (status change, record kept).
     *
     * Completed or paid appointments are finalized records — only the
     * patient's own doctor or an administrator (institute owner /
     * hospital-admin) may delete them, and every such delete is written
     * to the Audit Log. Deleting a paid appointment reverses the payment
     * (collection stamp cleared + reversal entry).
     */
    public function destroy(Appointment $appointment)
    {
        $this->ensureSameInstitute($appointment, 'appointment');
        $this->ensureDoctorOwns($appointment, 'doctor_id', 'appointment');

        $isPaid = $appointment->fee_collected_at !== null;
        $isFinalized = $appointment->status === 'completed' || $isPaid;
        $allowed = $this->isMedicalAdmin($appointment->institute_id)
            || $this->isOwnDoctor($appointment->doctor_id, $appointment->institute_id);

        if ($isFinalized && ! $allowed) {
            return redirect()->back()->withErrors(
                ['appointment' => 'Only the treating doctor or an administrator may delete a completed or paid appointment.']
            );
        }

        $message = 'Appointment cancelled successfully!';
        $actor = $this->feeReceiverSnapshot($appointment->institute_id);

        if ($isPaid) {
            // Reversing the payment: clear the collection stamp and leave a
            // reversal entry in the Audit Log so the money trail stays intact.
            $reversedAmount = (float) ($appointment->fee_collected_amount ?? 0);

            $appointment->update([
                'status' => 'cancelled',
                'fee_collected_amount' => null,
                'fee_collected_by_id' => null,
                'fee_collected_by_name' => null,
                'fee_collected_at' => null,
            ]);

            QueueAuditLog::create([
                'institute_id' => $appointment->institute_id,
                'appointment_id' => $appointment->id,
                'user_id' => $actor['id'],
                'user_type' => $actor['type'],
                'actor_name' => $actor['name'],
                'action' => 'fee_reversed',
                'new_order' => $appointment->queue_order ?? $appointment->serial_number ?? 0,
                'amount' => $reversedAmount,
            ]);

            $message = 'Appointment deleted and payment of ৳'.number_format($reversedAmount, 2).' reversed.';
        } else {
            $appointment->update(['status' => 'cancelled']);

            // Every delete is audit-logged, whatever the prior status was.
            QueueAuditLog::create([
                'institute_id' => $appointment->institute_id,
                'appointment_id' => $appointment->id,
                'user_id' => $actor['id'],
                'user_type' => $actor['type'],
                'actor_name' => $actor['name'],
                'action' => 'deleted',
                'old_order' => $appointment->queue_order ?? $appointment->serial_number ?? 0,
                'new_order' => 0,
            ]);
        }

        return redirect()->route('medical.appointments.index')
            ->with('status', $message);
    }

    /**
     * Display real-time queue for a doctor.
     *
     * Merged into the appointments index as the "Live Queue" tab; this
     * action keeps old/deep URLs working by redirecting to that tab.
     */
    public function queue(Request $request)
    {
        return redirect()->route('medical.appointments.index', array_filter([
            'tab' => 'queue',
            'q_doctor' => $request->input('doctor_id', $request->route('doctor')),
            'q_date' => $request->input('date'),
        ]));
    }

    /**
     * React-powered live queue page (Blade host for resources/js/medical/queue.js).
     *
     * The Blade + Livewire appointments index is untouched; React mounts only
     * on this page and polls reactQueueData() every 10 seconds.
     */
    public function reactIndex(Request $request)
    {
        $instituteId = $this->instituteId();
        $doctors = $this->doctors($instituteId);
        $fence = $this->doctorFenceId();
        if ($fence !== null) {
            $doctors = $doctors->where('id', $fence)->values();
        }

        // Fenced doctors are pinned to their own queue (request cannot widen).
        $doctorId = $fence;
        if ($doctorId === null) {
            $doctorId = (int) $request->input('doctor_id', 0) ?: $doctors->first()?->id;
        }
        if ($doctorId && ! \App\Support\MedicalScope::isDoctorInInstitute((int) $doctorId, $instituteId)) {
            abort(403, 'You do not have permission to view this queue.');
        }

        $date = $request->input('date', today()->format('Y-m-d'));

        $queue = [];
        $doctorName = null;
        if ($doctorId) {
            $status = $this->queueManager->getQueueStatus($instituteId, (int) $doctorId, $date);
            $queue = $status['queue'] instanceof \Illuminate\Support\Collection
                ? $status['queue']->values()->all()
                : array_values((array) ($status['queue'] ?? []));
            $doctorName = $doctors->firstWhere('id', (int) $doctorId)?->name;
        }

        $props = [
            'doctorId' => $doctorId,
            'date' => $date,
            'initialQueue' => $queue,
            'refreshUrl' => $doctorId
                ? route('medical.queue.react.data', ['doctor_id' => $doctorId, 'date' => $date])
                : null,
        ];

        return view('medical.queue-react', compact('props', 'doctorName', 'date'));
    }

    /**
     * JSON feed for the React live queue (polled every 10 seconds).
     */
    public function reactQueueData(Request $request)
    {
        $instituteId = $this->instituteId();

        // Fenced doctors are pinned to their own queue (request cannot widen).
        $fence = $this->doctorFenceId();
        $doctorId = $fence ?? (int) $request->input('doctor_id', 0);
        if (! $doctorId || ! \App\Support\MedicalScope::isDoctorInInstitute($doctorId, $instituteId)) {
            abort(403, 'You do not have permission to view this queue.');
        }

        $date = $request->input('date', today()->format('Y-m-d'));

        $status = $this->queueManager->getQueueStatus($instituteId, $doctorId, $date);
        $queue = $status['queue'] instanceof \Illuminate\Support\Collection
            ? $status['queue']->values()->all()
            : array_values((array) ($status['queue'] ?? []));

        return response()->json($queue);
    }

    /**
     * JSON feed for the React appointments list (filters + 10s poll).
     *
     * Mirrors index() scoping: institute, doctor fence, date (default
     * today), status and doctor filters.
     */
    public function reactAppointmentsData(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = Appointment::where('institute_id', $instituteId)
            ->with(['patient', 'doctor']);

        // Fenced doctors see only their own appointments (request filter
        // cannot widen this).
        $fence = $this->doctorFenceId();
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
            $query->where('doctor_id', $request->doctor_id);
        }

        $appointments = $query->orderBy('appointment_time')->get();

        return response()->json($this->mapAppointmentsForReact($appointments));
    }

    /**
     * Appointment rows for the React list, pre-formatted exactly like the
     * Blade table (tenant-aware date, 12h time, action URLs). Row actions
     * POST back through the normal web routes so flash messages and
     * validation behave identically to the Blade list.
     */
    private function mapAppointmentsForReact($appointments): array
    {
        $ownDoctorId = $this->doctorFenceId();

        return $appointments->map(function (Appointment $appointment) use ($ownDoctorId) {
            $isToday = (bool) $appointment->appointment_date?->isToday();
            $isFinalized = $appointment->status === 'completed' || $appointment->fee_collected_at !== null;

            return [
                'id' => $appointment->id,
                'patient_name' => $appointment->patient->full_name ?? 'N/A',
                'patient_phone' => $appointment->patient->phone ?? 'N/A',
                'has_patient' => $appointment->patient !== null,
                'doctor_name' => $appointment->doctor->name ?? 'N/A',
                'date_display' => mawa_format_date($appointment->appointment_date, null, 'd M Y'),
                'time_display' => $appointment->appointment_time
                    ? \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A')
                    : 'N/A',
                'serial_number' => $appointment->serial_number,
                'status' => $appointment->status,
                'is_finalized' => $isFinalized,
                'is_own_doctor' => $ownDoctorId !== null && (int) $ownDoctorId === (int) $appointment->doctor_id,
                'can_checkin' => $appointment->status === 'scheduled' && $isToday,
                'can_transfer' => $appointment->status === 'scheduled',
                'show_url' => route('medical.appointments.show', $appointment),
                'edit_url' => route('medical.appointments.edit', $appointment),
                'checkin_url' => route('medical.appointments.checkin', $appointment),
                'transfer_url' => route('medical.appointments.transfer', $appointment),
                'cancel_url' => route('medical.appointments.destroy', $appointment),
                'transfer_info' => [
                    'date' => $appointment->appointment_date?->format('Y-m-d'),
                    'patient' => $appointment->patient->full_name ?? 'N/A',
                    'doctor' => $appointment->doctor->name ?? 'N/A',
                    'serial' => (string) $appointment->serial_number,
                ],
            ];
        })->values()->all();
    }

    /**
     * Queue tab data for the appointments index (separate q_* params so the
     * queue filters never clash with the appointments list filters).
     */
    private function queueViewData(int $instituteId, Request $request, $doctors): array
    {
        // Fenced doctors are pinned to their own queue (request cannot widen).
        $fence = $this->doctorFenceId();
        $doctorId = $fence ?? $request->input('q_doctor') ?? $doctors->first()?->id;
        $date = $request->input('q_date', today()->format('Y-m-d'));

        $queueStatus = $doctorId
            ? $this->queueManager->getQueueStatus($instituteId, (int) $doctorId, $date)
            : ['total' => 0, 'waiting' => 0, 'checked_in' => 0, 'in_progress' => 0, 'estimated_wait_minutes' => 0, 'queue' => collect()];

        // Full audit history for the page-level Audit Log tab (same scope):
        // queue reorders + fee collections (who received the payment).
        $auditLogs = ($doctorId && $date)
            ? QueueAuditLog::with(['appointment.patient', 'appointment.doctor'])
                ->where('institute_id', $instituteId)
                ->whereHas('appointment', fn ($q) => $q
                    ->where('doctor_id', (int) $doctorId)
                    ->whereDate('appointment_date', $date))
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(fn (QueueAuditLog $l) => [
                    'patient' => $l->appointment->patient->full_name ?? 'N/A',
                    'doctor' => $l->appointment->doctor->name ?? 'N/A',
                    'actor' => $l->actor_name ?? ucfirst((string) $l->user_type),
                    'user_type' => $l->user_type,
                    'action' => $l->action ?? 'reorder',
                    'old' => $l->old_order,
                    'new' => $l->new_order,
                    'amount' => $l->amount !== null ? (float) $l->amount : null,
                    // Receiver of the payment = staff member who collected it.
                    'received_by' => ($l->action ?? '') === 'fee_collected'
                        ? ($l->actor_name ?? ucfirst((string) $l->user_type))
                        : null,
                    'at' => $l->created_at?->format('d M Y, h:i A'),
                ])
                ->all()
            : [];

        return [
            'doctorId' => $doctorId,
            'date' => $date,
            'status' => $queueStatus,
            'auditLogs' => $auditLogs,
            'orgWord' => mawa_org_word($instituteId),
        ];
    }

    /**
     * Check in a patient.
     */
    public function checkin(Appointment $appointment)
    {
        $this->ensureSameInstitute($appointment, 'appointment');
        $this->ensureDoctorOwns($appointment, 'doctor_id', 'appointment');
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
        $this->ensureDoctorOwns($appointment, 'doctor_id', 'appointment');

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
     * Collect the visit fee and advance the appointment (fee popup target).
     *
     * Pre-visit doctors collect on start (checked_in â†’ in_progress),
     * post-visit doctors on complete. The amount is always recomputed
     * server-side â€” the popup figure is informational only. The receiver
     * (current staff member) is stamped on the appointment and written to
     * the Audit Log so it is clear who received the payment.
     */
    public function collectFee(Request $request, Appointment $appointment)
    {
        $this->ensureSameInstitute($appointment, 'appointment');
        $this->ensureDoctorOwns($appointment, 'doctor_id', 'appointment');

        $validated = $request->validate([
            'action' => 'required|in:start,complete',
        ]);

        $profile = Doctor::resolveForUser($appointment->doctor_id, $appointment->institute_id);
        $amount = ($profile && $appointment->patient)
            ? $profile->getApplicableFee($appointment->patient)
            : 0.0;

        $receiver = $this->feeReceiverSnapshot($appointment->institute_id);

        if ($validated['action'] === 'start') {
            if ($appointment->status !== 'checked_in') {
                return redirect()->back()->withErrors(
                    ['fee' => 'Only checked-in appointments can be started.']
                );
            }
            $appointment->update([
                'status' => 'in_progress',
                'fee_collected_amount' => $amount,
                'fee_collected_by_id' => $receiver['id'],
                'fee_collected_by_name' => $receiver['name'],
                'fee_collected_at' => now(),
            ]);
            QueueAuditLog::create([
                'institute_id' => $appointment->institute_id,
                'appointment_id' => $appointment->id,
                'user_id' => $receiver['id'],
                'user_type' => $receiver['type'],
                'actor_name' => $receiver['name'],
                'action' => 'fee_collected',
                'new_order' => $appointment->queue_order ?? $appointment->serial_number ?? 0,
                'amount' => $amount,
            ]);
            $message = $amount > 0
                ? 'Fee collected (à§³'.number_format($amount, 2).'). Consultation started!'
                : 'Consultation started!';
        } else {
            if (! in_array($appointment->status, ['checked_in', 'in_progress'], true)) {
                return redirect()->back()->withErrors(
                    ['fee' => 'Only active queue appointments can be completed.']
                );
            }
            $appointment->update([
                'status' => 'completed',
                'fee_collected_amount' => $amount,
                'fee_collected_by_id' => $receiver['id'],
                'fee_collected_by_name' => $receiver['name'],
                'fee_collected_at' => now(),
            ]);
            QueueAuditLog::create([
                'institute_id' => $appointment->institute_id,
                'appointment_id' => $appointment->id,
                'user_id' => $receiver['id'],
                'user_type' => $receiver['type'],
                'actor_name' => $receiver['name'],
                'action' => 'fee_collected',
                'new_order' => $appointment->queue_order ?? $appointment->serial_number ?? 0,
                'amount' => $amount,
            ]);
            $message = $amount > 0
                ? 'Fee collected (à§³'.number_format($amount, 2).'). Appointment completed!'
                : 'Appointment completed!';
        }

        // Land back on the Live Queue tab (same doctor + date) so the
        // updated queue is visible immediately â€” no manual refresh needed.
        return redirect()->route('medical.appointments.index', [
            'tab' => 'queue',
            'q_doctor' => $appointment->doctor_id,
            'q_date' => $appointment->appointment_date->format('Y-m-d'),
        ])->with('status', $message);
    }

    /**
     * Complete appointment.
     */
    public function complete(Appointment $appointment)
    {
        $this->ensureSameInstitute($appointment, 'appointment');
        $this->ensureDoctorOwns($appointment, 'doctor_id', 'appointment');
        $this->queueManager->complete($appointment);

        return redirect()->back()->with('status', 'Appointment completed!');
    }

    /**
     * Snapshot of the staff member receiving the fee (for the appointment
     * stamp + audit log). Mirrors the queue reorder actor snapshot:
     * institute owner â†’ owner, linked doctor profile â†’ doctor, otherwise
     * the staff member's actual role slug.
     *
     * @return array{id: ?int, type: string, name: ?string}
     */
    private function feeReceiverSnapshot(int $instituteId): array
    {
        $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();

        $type = 'staff';
        $id = null;
        $name = null;

        if ($staff) {
            $id = $staff->getKey();
            $name = $staff->name ?? trim(($staff->first_name ?? '').' '.($staff->last_name ?? '')) ?: null;

            if ($staff instanceof \App\Models\InstituteUser && $staff->isOwner()) {
                $type = 'owner';
            } elseif (\App\Support\MedicalScope::ownDoctorUserId($instituteId) !== null) {
                $type = 'doctor';
            } else {
                try {
                    if ($staff instanceof \App\Models\InstituteUser) {
                        $slug = $staff->role?->slug;
                    } else {
                        $slug = \App\Models\Membership::where('user_id', $staff->getKey())
                            ->where('institution_id', $instituteId)
                            ->where('status', 'active')
                            ->with('role')
                            ->first()
                            ?->role?->slug;
                    }
                    if ($slug === 'institute-owner') {
                        $type = 'owner';
                    } elseif (is_string($slug) && $slug !== '') {
                        $type = $slug;
                    }
                } catch (\Throwable) {
                    // keep default 'staff'
                }
            }
        }

        return ['id' => $id, 'type' => $type, 'name' => $name];
    }

    /**
     * Doctors available for booking.
     *
     * Phase 02: appointments.doctor_id still references the global `users`
     * table, but the selector is now tenant-scoped â€” active users holding a
     * membership or Doctor profile in this institute
     * (MedicalScope::instituteDoctors). Validation enforces the same set.
     */
    private function doctors(int $instituteId)
    {
        return \App\Support\MedicalScope::instituteDoctors($instituteId);
    }
}
