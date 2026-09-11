<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\VitalSignRequest;
use App\Models\Medical\Admission;
use App\Models\Medical\Appointment;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\NursingNote;
use App\Models\Medical\Patient;
use App\Models\Medical\VitalSign;
use App\Support\MedicalScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class VitalSignController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_vitals.view', only: ['index', 'show', 'opdIndex']),
            new Middleware('permission:medical_vitals.create', only: ['create', 'store', 'storeNote']),
            new Middleware('permission:medical_vitals.update', only: ['edit', 'update']),
            new Middleware('permission:medical_vitals.delete', only: ['destroy', 'destroyNote']),
        ];
    }

    /**
     * Show vitals entry form for an admission.
     */
    public function create(Request $request)
    {
        $admissionId = $request->query('admission_id');

        if (! $admissionId) {
            return redirect()->route('medical.admissions.current')
                ->with('error', 'Please select an admission first.');
        }

        $admission = Admission::where('institute_id', $this->instituteId())
            ->with(['patient', 'bed.ward'])
            ->findOrFail($admissionId);
        $this->ensureDoctorOwns($admission, 'admitting_doctor_id', 'admission');

        if ($admission->status !== 'active') {
            return redirect()->back()->with('error', 'Cannot record vitals for a discharged patient.');
        }

        return view('medical.admissions.vitals', compact('admission'));
    }

    /**
     * Store vitals (IPD admission or OPD appointment context).
     */
    public function store(VitalSignRequest $request)
    {
        $data = $request->validated();
        $data['recorded_by'] = MedicalScope::recorderId();
        $data['recorded_at'] = now();

        // OPD branch: link patient/doctor from the appointment itself —
        // never trust client-supplied linkage.
        if (! empty($data['appointment_id'])) {
            return $this->storeForAppointment($data);
        }

        $admission = Admission::where('institute_id', $this->instituteId())
            ->findOrFail($data['admission_id']);
        $this->ensureDoctorOwns($admission, 'admitting_doctor_id', 'admission');

        if ($admission->status !== 'active') {
            return redirect()->back()
                ->with('error', 'Cannot record vitals for a discharged patient.')
                ->withInput();
        }

        $vital = VitalSign::create($data);
        ClinicalAuditLog::record($vital, 'created', ['new' => ClinicalAuditLog::snapshot($vital)]);

        return redirect()->route('medical.admissions.show', $data['admission_id'])
            ->with('status', 'Vitals recorded successfully!');
    }

    /**
     * Quick vitals entry from the appointments list popup. Deliberately
     * open to any authenticated medical user (no medical_vitals.create
     * gate) so receptionists can record at check-in; the doctor fence,
     * validation and audit trail still apply.
     */
    public function quickStore(VitalSignRequest $request)
    {
        $data = $request->validated();

        if (empty($data['appointment_id'])) {
            // Patient-direct save (prescription page pencil): no appointment
            // context, vitals link straight to the patient.
            if (! empty($data['patient_id'])) {
                return $this->storeForPatient($data, $request);
            }

            return redirect()->back()
                ->with('error', 'Please choose an appointment first.')
                ->withInput();
        }

        return $this->storeForAppointment($data);
    }

    /**
     * Store vitals linked directly to a patient (prescription page flow).
     * JSON for AJAX saves (card refreshes in place); redirect otherwise.
     */
    private function storeForPatient(array $data, Request $request)
    {
        $patient = Patient::where('institute_id', $this->instituteId())
            ->findOrFail($data['patient_id']);
        if (! $this->mayActOnPatient($patient)) {
            abort(403, 'You do not have permission to record vitals for this patient.');
        }

        $data['recorded_by'] = MedicalScope::recorderId();
        $data['recorded_at'] = now();
        $data['admission_id'] = null;
        $data['appointment_id'] = null;
        $data['patient_id'] = $patient->id;
        $data['doctor_id'] = null;

        $vital = VitalSign::create($data);
        ClinicalAuditLog::record($vital, 'created', ['new' => ClinicalAuditLog::snapshot($vital)]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()
            ->with('status', 'Vitals recorded successfully!');
    }

    private function storeForAppointment(array $data)
    {
        $data['recorded_by'] = MedicalScope::recorderId();
        $data['recorded_at'] = now();

        $appointment = Appointment::where('institute_id', $this->instituteId())
            ->findOrFail($data['appointment_id']);
        $this->ensureDoctorOwns($appointment, 'doctor_id', 'appointment');

        $data['admission_id'] = null;
        $data['patient_id'] = $appointment->patient_id;
        $data['doctor_id'] = $appointment->doctor_id;

        $vital = VitalSign::create($data);
        ClinicalAuditLog::record($vital, 'created', ['new' => ClinicalAuditLog::snapshot($vital)]);

        // Appointment-context saves (quick modal on the appointments list)
        // land back on the Live Queue tab for the same doctor + date.
        return redirect()->route('medical.appointments.index', [
            'tab' => 'queue',
            'q_doctor' => $appointment->doctor_id,
            'q_date' => $appointment->appointment_date->format('Y-m-d'),
        ])->with('status', 'Vitals recorded successfully!');
    }

    /**
     * Show vitals history for an admission.
     */
    public function index(Request $request)
    {
        $admissionId = $request->query('admission_id');

        if (! $admissionId) {
            return redirect()->route('medical.admissions.current')
                ->with('error', 'Please select an admission.');
        }

        $admission = Admission::where('institute_id', $this->instituteId())
            ->with('patient')
            ->findOrFail($admissionId);
        $this->ensureDoctorOwns($admission, 'admitting_doctor_id', 'admission');

        $vitals = VitalSign::where('admission_id', $admission->id)
            ->orderBy('recorded_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('medical.admissions.vitals_history', compact('admission', 'vitals'));
    }

    /**
     * Show vitals details (resolves the IPD or OPD context).
     */
    public function show(VitalSign $vital)
    {
        $context = $this->resolveContext($vital);
        $vital->load('recordedBy');

        $admission = $context['admission'] ?? null;
        $appointment = $context['appointment'] ?? null;

        return view('medical.admissions.vitals_show', compact('vital', 'admission', 'appointment'));
    }

    /**
     * Show the vitals edit form (IPD or OPD).
     */
    public function edit(VitalSign $vital)
    {
        $context = $this->resolveContext($vital);

        return view('medical.vitals.edit', [
            'vital' => $vital,
            'contextLabel' => $context['label'],
            'backUrl' => $context['backUrl'],
        ]);
    }

    /**
     * Update a vitals entry (IPD or OPD) with an audit trail.
     */
    public function update(VitalSignRequest $request, VitalSign $vital)
    {
        $context = $this->resolveContext($vital);

        $data = $request->validated();
        // Context linkage is immutable — only clinical values change.
        unset($data['admission_id'], $data['appointment_id']);

        $old = ClinicalAuditLog::snapshot($vital);
        $vital->update($data);
        ClinicalAuditLog::record($vital, 'updated', [
            'old' => $old,
            'new' => ClinicalAuditLog::snapshot($vital->refresh()),
        ]);

        return redirect()->to($context['backUrl'])
            ->with('status', 'Vitals updated successfully!');
    }

    /**
     * OPD vitals list (appointment-linked), filterable by date/patient/doctor.
     */
    public function opdIndex(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = VitalSign::query()
            ->opd()
            ->join('appointments', 'appointments.id', '=', 'vital_signs.appointment_id')
            ->where('appointments.institute_id', $instituteId)
            ->select('vital_signs.*')
            ->with(['patient', 'appointment.patient', 'recordedBy'])
            ->orderByDesc('vital_signs.recorded_at')
            ->orderByDesc('vital_signs.id');

        // Fenced doctors see only their own visits (request filter cannot
        // widen this).
        $fence = $this->doctorFenceId();
        if ($fence !== null) {
            $query->where('appointments.doctor_id', $fence);
        } elseif ($request->filled('doctor_id')) {
            $query->where('appointments.doctor_id', $request->doctor_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('vital_signs.recorded_at', $request->date);
        }
        if ($request->filled('patient_id')) {
            $query->where('vital_signs.patient_id', $request->patient_id);
        }

        $vitals = $query->paginate(20)->withQueryString();
        $patients = $this->ownPatientOptions($instituteId, $fence);
        $doctors = MedicalScope::instituteDoctors($instituteId);
        if ($fence !== null) {
            $doctors = $doctors->where('id', $fence)->values();
        }

        return view('medical.vitals.opd_index', compact('vitals', 'patients', 'doctors'));
    }

    /**
     * Delete vitals entry (IPD or OPD).
     */
    public function destroy(VitalSign $vital)
    {
        $context = $this->resolveContext($vital);

        // Phase 01: vitals removal is archival (soft delete) + audited.
        ClinicalAuditLog::record($vital, 'deleted', ['old' => ClinicalAuditLog::snapshot($vital)]);
        $vital->delete();

        return redirect()->to($context['backUrl'])
            ->with('status', 'Vitals entry deleted.');
    }

    /**
     * Resolve the owning context of a vitals row (IPD admission or OPD
     * appointment) inside this institute, enforcing the doctor fence.
     *
     * @return array{type: string, admission: ?Admission, appointment: ?Appointment, label: string, backUrl: string}
     */
    private function resolveContext(VitalSign $vital): array
    {
        if ($vital->admission_id) {
            $admission = Admission::where('institute_id', $this->instituteId())
                ->with('patient')
                ->findOrFail($vital->admission_id);
            $this->ensureDoctorOwns($admission, 'admitting_doctor_id', 'admission');

            return [
                'type' => 'ipd',
                'admission' => $admission,
                'appointment' => null,
                'label' => 'Admission #'.$admission->id.' ('.($admission->patient->full_name ?? 'N/A').')',
                'backUrl' => route('medical.admissions.show', $admission),
            ];
        }

        $appointment = Appointment::where('institute_id', $this->instituteId())
            ->with('patient')
            ->findOrFail($vital->appointment_id);
        $this->ensureDoctorOwns($appointment, 'doctor_id', 'appointment');

        return [
            'type' => 'opd',
            'admission' => null,
            'appointment' => $appointment,
            'label' => 'Visit #'.$appointment->serial_number.' ('.($appointment->patient->full_name ?? 'N/A').')',
            'backUrl' => route('medical.appointments.show', $appointment),
        ];
    }

    /**
     * Store a nursing note for an admission.
     */
    public function storeNote(Request $request, Admission $admission)
    {
        $this->ensureSameInstitute($admission, 'admission');
        $this->ensureDoctorOwns($admission, 'admitting_doctor_id', 'admission');

        if ($admission->status !== 'active') {
            return redirect()->back()->with('error', 'Cannot add notes to a discharged admission.');
        }

        $request->validate(['note' => 'required|string|max:5000']);

        NursingNote::create([
            'admission_id' => $admission->id,
            'note' => $request->note,
            'recorded_by' => MedicalScope::recorderId(),
            'recorded_at' => now(),
        ]);

        return redirect()->route('medical.admissions.show', $admission)
            ->with('status', 'Nursing note added successfully!');
    }

    /**
     * Delete a nursing note.
     */
    public function destroyNote(NursingNote $note)
    {
        $admission = Admission::where('institute_id', $this->instituteId())->findOrFail($note->admission_id);
        $this->ensureDoctorOwns($admission, 'admitting_doctor_id', 'admission');

        $admissionId = $note->admission_id;
        // Phase 01: note removal is archival (soft delete) + audited.
        ClinicalAuditLog::record($note, 'deleted', ['old' => ClinicalAuditLog::snapshot($note)]);
        $note->delete();

        return redirect()->route('medical.admissions.show', $admissionId)
            ->with('status', 'Nursing note deleted.');
    }
}
