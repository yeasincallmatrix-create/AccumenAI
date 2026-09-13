<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\EncounterRequest;
use App\Models\Medical\Admission;
use App\Models\Medical\Appointment;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Department;
use App\Models\Medical\Encounter;
use App\Models\Medical\Patient;
use App\Models\Medical\Specialty;
use App\Services\Medical\NumberSequenceService;
use App\Models\Medical\NumberSequence;
use App\Support\MedicalScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EncounterController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_encounters.view', only: ['index', 'show']),
            new Middleware('permission:medical_encounters.create', only: ['create', 'store']),
            new Middleware('permission:medical_encounters.edit', only: ['edit', 'update', 'start', 'cancel']),
            new Middleware('permission:medical_encounters.complete', only: ['complete']),
            new Middleware('permission:medical_encounters.amend', only: ['amend']),
        ];
    }

    /**
     * Encounter list; doubles as the patient timeline when ?patient_id= is
     * given (bounded, paginated, tenant-scoped — never a full-history dump).
     */
    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $fence = $this->doctorFenceId();

        $query = Encounter::where('institute_id', $instituteId)
            ->with(['patient', 'doctor'])
            ->orderByDesc('started_at')
            ->orderByDesc('id');
        // Phase 18: branch fence (context branch + legacy NULLs).
        $this->scopeBranch($query);

        if ($request->filled('patient_id')) {
            $patient = Patient::where('institute_id', $instituteId)->find($request->patient_id);
            if (! $patient || ! $this->mayActOnPatient($patient, $fence)) {
                abort(403, 'You do not have permission to access this patient.');
            }
            $query->where('patient_id', $patient->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($fence !== null) {
            $query->where('doctor_id', $fence);
        }

        $encounters = $query->paginate(20)->withQueryString();
        $patients = $this->ownPatientOptions($instituteId, $fence);

        return view('medical.encounters.index', compact('encounters', 'patients'));
    }

    public function create(Request $request)
    {
        $instituteId = $this->instituteId();
        $fence = $this->doctorFenceId();

        $patients = $this->ownPatientOptions($instituteId, $fence);
        $doctors = MedicalScope::instituteDoctors($instituteId);
        if ($fence !== null) {
            $doctors = $doctors->where('id', $fence)->values();
        }
        // Phase 18: doctor picker hides clinicians assigned exclusively to
        // other branches (legacy unassigned doctors stay selectable).
        if ($this->branchContextId() !== null) {
            $allowed = $this->branchDoctorUserIds($this->branchContextId(), $instituteId);
            $doctors = $doctors->whereIn('id', $allowed)->values();
        }
        $departments = Department::where('institute_id', $instituteId)->active()->orderBy('name')->get();
        $specialties = Specialty::where('institute_id', $instituteId)->active()->orderBy('name')->get();

        $selectedAppointment = null;
        if ($request->filled('appointment_id')) {
            $selectedAppointment = Appointment::where('institute_id', $instituteId)
                ->find($request->appointment_id);
            // Phase 18: never pre-select a foreign-branch appointment.
            if ($selectedAppointment
                && ! \App\Support\BranchContext::allows($selectedAppointment->branch_id ?? null)) {
                $selectedAppointment = null;
            }
        }

        return view('medical.encounters.create', compact(
            'patients', 'doctors', 'departments', 'specialties', 'selectedAppointment'
        ));
    }

    public function store(EncounterRequest $request, NumberSequenceService $sequences)
    {
        $instituteId = $this->instituteId();
        $data = $request->validated();

        $patient = Patient::where('institute_id', $instituteId)->findOrFail($data['patient_id']);
        if (! $this->mayActOnPatient($patient)) {
            abort(403, 'You do not have permission to book for this patient.');
        }

        if (($fence = $this->doctorFenceId()) !== null) {
            $data['doctor_id'] = $fence;
        }

        // Appointment linkage: same institute + same patient + checked-in
        // (or in progress), and no open encounter already attached — the DB
        // unique on appointment_id is the final backstop against doubles.
        $appointment = null;
        if (! empty($data['appointment_id'])) {
            $appointment = Appointment::where('institute_id', $instituteId)->findOrFail($data['appointment_id']);
            if ((int) $appointment->patient_id !== (int) $patient->id) {
                return redirect()->back()
                    ->with('error', 'The selected appointment belongs to a different patient.')
                    ->withInput();
            }
            if (! in_array($appointment->status, ['checked_in', 'in_progress'], true)) {
                return redirect()->back()
                    ->with('error', 'Encounters can only start from a checked-in appointment (walk-ins need none).')
                    ->withInput();
            }
            if (Encounter::where('appointment_id', $appointment->id)->exists()) {
                return redirect()->back()
                    ->with('error', 'This appointment already has an encounter.')
                    ->withInput();
            }
            $this->ensureDoctorOwns($appointment, 'doctor_id', 'appointment');
        }

        if (! empty($data['admission_id'])) {
            $admission = Admission::where('institute_id', $instituteId)->findOrFail($data['admission_id']);
            if ((int) $admission->patient_id !== (int) $patient->id) {
                return redirect()->back()
                    ->with('error', 'The selected admission belongs to a different patient.')
                    ->withInput();
            }
            $this->ensureDoctorOwns($admission, 'admitting_doctor_id', 'admission');
        }

        $data['institute_id'] = $instituteId;
        // Phase 18: branch ownership (validated context/default; legacy
        // NULL for institute-wide actors) + clinician branch assignment.
        $data['branch_id'] = $this->resolveBranchId($request->input('branch_id'));
        if (! $this->doctorBranchOk((int) $data['doctor_id'], $data['branch_id'], $instituteId)) {
            return redirect()->back()
                ->with('error', 'The selected doctor is not assigned to this branch.')
                ->withInput();
        }
        $data['encounter_number'] = $sequences->next(NumberSequence::TYPE_ENCOUNTER, $instituteId);
        $data['created_by'] = $this->actorId();

        $encounter = Encounter::create($data);
        ClinicalAuditLog::record($encounter, 'created', [
            'new' => ['encounter_number' => $encounter->encounter_number, 'status' => $encounter->status],
        ]);

        return redirect()->route('medical.encounters.show', $encounter)
            ->with('status', 'Encounter '.clinical_no($encounter->encounter_number).' opened successfully!');
    }

    public function show(Encounter $encounter)
    {
        $this->ensureSameInstitute($encounter, 'encounter');
        $this->ensureBranchAccess($encounter, 'branch_id', 'encounter');
        $this->ensureDoctorOwns($encounter, 'doctor_id', 'encounter');
        $encounter->load([
            'patient', 'doctor', 'appointment', 'admission', 'department', 'specialty',
            'prescriptions.items', 'labOrders.results',
            'diagnoses' => fn ($q) => $q->orderBy('label'),
            'problems', 'followUps',
        ]);

        // Phase 17 — active longitudinal problems for context (bounded;
        // problems are patient-gated, and this encounter is already fenced).
        $patientProblems = \App\Models\Medical\PatientProblem::where('institute_id', $encounter->institute_id)
            ->where('patient_id', $encounter->patient_id)
            ->where('status', \App\Models\Medical\PatientProblem::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $vitals = collect();
        if ($encounter->admission_id) {
            $vitals = \App\Models\Medical\VitalSign::where('admission_id', $encounter->admission_id)
                ->orderByDesc('recorded_at')
                ->limit(20)
                ->get();
        }

        // Sibling encounters for the longitudinal strip (bounded).
        $timeline = Encounter::where('institute_id', $encounter->institute_id)
            ->where('patient_id', $encounter->patient_id)
            ->where('id', '!=', $encounter->id)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'encounter_number', 'encounter_type', 'status', 'started_at']);

        // Phase 15 — encounter event timeline (bounded, tenant-scoped):
        // lifecycle + diagnosis + linked lab/rx audit events, newest first.
        $eventTimeline = ClinicalAuditLog::where('institute_id', $encounter->institute_id)
            ->where(function ($q) use ($encounter) {
                $q->where(function ($qq) use ($encounter) {
                    $qq->where('auditable_type', Encounter::class)
                        ->where('auditable_id', $encounter->id);
                })->orWhere(function ($qq) use ($encounter) {
                    $qq->where('auditable_type', \App\Models\Medical\EncounterDiagnosis::class)
                        ->whereIn('auditable_id', $encounter->diagnoses->pluck('id'));
                })->orWhere(function ($qq) use ($encounter) {
                    $qq->where('auditable_type', \App\Models\Medical\LabOrder::class)
                        ->whereIn('auditable_id', $encounter->labOrders->pluck('id'));
                })->orWhere(function ($qq) use ($encounter) {
                    $qq->where('auditable_type', \App\Models\Medical\Prescription::class)
                        ->whereIn('auditable_id', $encounter->prescriptions->pluck('id'));
                });
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('medical.encounters.show', compact('encounter', 'vitals', 'timeline', 'eventTimeline', 'patientProblems'));
    }

    public function edit(Encounter $encounter)
    {
        $this->ensureSameInstitute($encounter, 'encounter');
        $this->ensureBranchAccess($encounter, 'branch_id', 'encounter');
        $this->ensureDoctorOwns($encounter, 'doctor_id', 'encounter');

        if (! $encounter->isEditable()) {
            return redirect()->back()->with('error', 'Only open encounters can be edited. Completed encounters need an amendment.');
        }

        return view('medical.encounters.edit', compact('encounter'));
    }

    public function update(EncounterRequest $request, Encounter $encounter)
    {
        $this->ensureSameInstitute($encounter, 'encounter');
        $this->ensureBranchAccess($encounter, 'branch_id', 'encounter');
        $this->ensureDoctorOwns($encounter, 'doctor_id', 'encounter');

        if (! $encounter->isEditable()) {
            return redirect()->back()->with('error', 'Only open encounters can be edited. Completed encounters need an amendment.');
        }

        // Identity links are set at creation and never moved between records.
        // Branch identity likewise never moves (no cross-branch transfer).
        $data = $request->validated();
        unset(
            $data['patient_id'], $data['appointment_id'], $data['admission_id'],
            $data['doctor_id'], $data['department_id'], $data['specialty_id'],
            $data['encounter_type'], $data['branch_id']
        );

        $original = ClinicalAuditLog::snapshot($encounter);
        $encounter->update($data);
        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($encounter->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($encounter, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()->route('medical.encounters.show', $encounter)
            ->with('status', 'Encounter updated successfully!');
    }

    public function start(Encounter $encounter)
    {
        $this->ensureSameInstitute($encounter, 'encounter');
        $this->ensureBranchAccess($encounter, 'branch_id', 'encounter');
        $this->ensureDoctorOwns($encounter, 'doctor_id', 'encounter');

        try {
            $encounter->transitionTo(Encounter::STATUS_IN_PROGRESS);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        ClinicalAuditLog::record($encounter, 'started', [
            'new' => ['status' => $encounter->status],
        ]);

        return redirect()->route('medical.encounters.show', $encounter)
            ->with('status', 'Consultation started.');
    }

    public function complete(Encounter $encounter)
    {
        $this->ensureSameInstitute($encounter, 'encounter');
        $this->ensureBranchAccess($encounter, 'branch_id', 'encounter');
        $this->ensureDoctorOwns($encounter, 'doctor_id', 'encounter');

        try {
            $encounter->transitionTo(Encounter::STATUS_COMPLETED, $this->actorId());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        ClinicalAuditLog::record($encounter->refresh(), 'completed', [
            'new' => ['status' => $encounter->status],
        ]);

        return redirect()->route('medical.encounters.show', $encounter)
            ->with('status', 'Encounter completed successfully!');
    }

    public function cancel(Encounter $encounter)
    {
        $this->ensureSameInstitute($encounter, 'encounter');
        $this->ensureBranchAccess($encounter, 'branch_id', 'encounter');
        $this->ensureDoctorOwns($encounter, 'doctor_id', 'encounter');

        try {
            $encounter->transitionTo(Encounter::STATUS_CANCELLED);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        ClinicalAuditLog::record($encounter->refresh(), 'cancelled', [
            'old' => ['status' => 'open'],
            'new' => ['status' => $encounter->status],
        ]);

        return redirect()->route('medical.encounters.index')
            ->with('status', 'Encounter cancelled.');
    }

    /**
     * Controlled correction of a completed encounter. The row is updated in
     * place (identifiers never change) but only with a reason, and the diff
     * is audit-logged — never a silent mutation.
     */
    public function amend(Request $request, Encounter $encounter)
    {
        $this->ensureSameInstitute($encounter, 'encounter');
        $this->ensureBranchAccess($encounter, 'branch_id', 'encounter');
        $this->ensureDoctorOwns($encounter, 'doctor_id', 'encounter');

        if ($encounter->status !== Encounter::STATUS_COMPLETED) {
            return redirect()->back()->with('error', 'Only completed encounters use the amendment path.');
        }

        $data = $request->validate([
            'reason' => 'required|string|max:2000',
            'chief_complaint' => 'nullable|string|max:1000',
            'history_of_present_illness' => 'nullable|string|max:5000',
            'examination_notes' => 'nullable|string|max:5000',
            'assessment_notes' => 'nullable|string|max:5000',
            'plan_notes' => 'nullable|string|max:5000',
            'follow_up_notes' => 'nullable|string|max:2000',
            'diagnosis_text' => 'nullable|string|max:1000',
            'diagnosis_code' => 'nullable|string|max:60',
        ]);

        $reason = $data['reason'];
        unset($data['reason']);

        $original = ClinicalAuditLog::snapshot($encounter);
        $encounter->update($data);
        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($encounter->refresh()));
        if ($old === [] && $new === []) {
            return redirect()->back()->with('status', 'No changes to amend.');
        }
        ClinicalAuditLog::record($encounter, 'amended', [
            'reason' => $reason,
            'old' => $old,
            'new' => $new,
        ]);

        return redirect()->route('medical.encounters.show', $encounter)
            ->with('status', 'Amendment recorded with audit trail.');
    }

    /**
     * Actor id across portal guards (institute_user, web, others) for the
     * created_by/completed_by snapshot columns. Attribution truth lives in
     * the audit log; these columns are informational only.
     */
    private function actorId(): ?int
    {
        $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();

        return $staff ? (int) $staff->getKey() : null;
    }
}
