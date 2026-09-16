<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\EmergencyVisitRequest;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\EmergencyVisit;
use App\Services\Medical\EmergencyVisitService;
use App\Support\MedicalScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EmergencyController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_emergency.view', only: ['index', 'show', 'dashboard']),
            new Middleware('permission:medical_emergency.create', only: ['create', 'store', 'registerWalkIn']),
            new Middleware('permission:medical_emergency.edit', only: ['edit', 'update', 'triageForm', 'triage', 'attend']),
            new Middleware('permission:medical_emergency.discharge', only: ['dischargeForm', 'discharge']),
            new Middleware('permission:medical_emergency.delete', only: ['destroy']),
        ];
    }

    public function __construct(
        private readonly EmergencyVisitService $emergencyService,
    ) {}

    /**
     * Emergency dashboard — triage board showing patients by level.
     */
    public function dashboard()
    {
        $instituteId = $this->instituteId();
        $query = EmergencyVisit::where('institute_id', $instituteId);
        $this->scopeBranch($query);

        $active = $query->active()->with(['patient', 'attendingDoctor'])
            ->orderBy('arrived_at', 'desc')
            ->get();

        $byTriage = [
            EmergencyVisit::TRIAGE_RED    => $active->where('triage_level', EmergencyVisit::TRIAGE_RED),
            EmergencyVisit::TRIAGE_ORANGE => $active->where('triage_level', EmergencyVisit::TRIAGE_ORANGE),
            EmergencyVisit::TRIAGE_YELLOW => $active->where('triage_level', EmergencyVisit::TRIAGE_YELLOW),
            EmergencyVisit::TRIAGE_GREEN  => $active->where('triage_level', EmergencyVisit::TRIAGE_GREEN),
            EmergencyVisit::TRIAGE_WHITE  => $active->where('triage_level', EmergencyVisit::TRIAGE_WHITE),
        ];

        $todayStats = [
            'total'     => EmergencyVisit::where('institute_id', $instituteId)->forToday()->count(),
            'active'    => $active->count(),
            'red'       => $byTriage[EmergencyVisit::TRIAGE_RED]->count(),
            'orange'    => $byTriage[EmergencyVisit::TRIAGE_ORANGE]->count(),
            'yellow'    => $byTriage[EmergencyVisit::TRIAGE_YELLOW]->count(),
            'discharged' => EmergencyVisit::where('institute_id', $instituteId)->forToday()
                ->where('status', EmergencyVisit::STATUS_DISCHARGED)->count(),
        ];

        return view('medical.emergency.dashboard', compact('byTriage', 'todayStats'));
    }

    /**
     * List all emergency visits.
     */
    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = EmergencyVisit::where('institute_id', $instituteId)
            ->with(['patient', 'attendingDoctor', 'triagedBy']);
        $this->scopeBranch($query);

        if ($request->filled('triage_level')) {
            $query->where('triage_level', $request->triage_level);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('arrived_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('arrived_at', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('visit_number', 'like', "%{$search}%")
                    ->orWhere('patient_name_temp', 'like', "%{$search}%")
                    ->orWhere('chief_complaint', 'like', "%{$search}%");
            });
        }

        $visits = $query->orderBy('arrived_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('medical.emergency.index', compact('visits'));
    }

    /**
     * Walk-in registration form (fast track).
     */
    public function create()
    {
        $instituteId = $this->instituteId();
        $visitNumber = $this->emergencyService->generateVisitNumber($instituteId);
        $doctors = MedicalScope::instituteDoctors($instituteId);
        $triageLevels = EmergencyVisit::TRIAGE_LEVELS;
        $arrivalModes = EmergencyVisit::ARRIVAL_MODES;

        return view('medical.emergency.create', compact('visitNumber', 'doctors', 'triageLevels', 'arrivalModes'));
    }

    /**
     * Store a new emergency visit.
     */
    public function store(EmergencyVisitRequest $request)
    {
        $instituteId = $this->instituteId();

        $data = $request->validated();
        $data['institute_id'] = $instituteId;
        $data['branch_id']    = $request->branch_id ?? $this->branchContextId();
        $data['visit_number'] = $this->emergencyService->generateVisitNumber($instituteId);
        $data['arrived_at']   = $data['arrived_at'] ?? now();
        $data['status']       = ($data['triage_level'] ?? null) ? EmergencyVisit::STATUS_TRIAGED : EmergencyVisit::STATUS_REGISTERED;

        if (($data['triage_level'] ?? null) && !isset($data['triaged_at'])) {
            $data['triaged_at'] = now();
            $data['triaged_by'] = MedicalScope::recorderId();
        }

        $visit = EmergencyVisit::create($data);

        ClinicalAuditLog::record($visit, 'created');

        return redirect()
            ->route('medical.emergency.show', $visit)
            ->with('status', 'Emergency visit created: ' . $visit->visit_number);
    }

    /**
     * Show emergency visit details.
     */
    public function show(EmergencyVisit $emergencyVisit)
    {
        $this->ensureSameInstitute($emergencyVisit, 'emergency_visit');
        $emergencyVisit->load(['patient', 'attendingDoctor', 'triagedBy', 'dispositionBy', 'admission']);

        return view('medical.emergency.show', ['visit' => $emergencyVisit]);
    }

    /**
     * Edit form.
     */
    public function edit(EmergencyVisit $emergencyVisit)
    {
        $this->ensureSameInstitute($emergencyVisit, 'emergency_visit');
        $emergencyVisit->load(['patient', 'attendingDoctor']);

        $instituteId = $this->instituteId();
        $doctors = MedicalScope::instituteDoctors($instituteId);
        $triageLevels = EmergencyVisit::TRIAGE_LEVELS;
        $arrivalModes = EmergencyVisit::ARRIVAL_MODES;
        $statuses = EmergencyVisit::STATUSES;
        $dispositions = [
            'discharge'       => 'Discharge',
            'admit'           => 'Admit to IPD',
            'transfer'        => 'Transfer',
            'observation'     => 'Observation',
            'left_without'    => 'Left Without Treatment',
            'expired'         => 'Expired',
        ];

        return view('medical.emergency.edit', [
            'visit'         => $emergencyVisit,
            'doctors'       => $doctors,
            'triageLevels'  => $triageLevels,
            'arrivalModes'  => $arrivalModes,
            'statuses'      => $statuses,
            'dispositions'  => $dispositions,
        ]);
    }

    /**
     * Update an emergency visit.
     */
    public function update(EmergencyVisitRequest $request, EmergencyVisit $emergencyVisit)
    {
        $this->ensureSameInstitute($emergencyVisit, 'emergency_visit');
        $original = ClinicalAuditLog::snapshot($emergencyVisit);

        $data = $request->validated();

        // Handle triage assignment
        if ($data['triage_level'] ?? null) {
            if (!$emergencyVisit->triaged_at) {
                $data['triaged_at'] = now();
                $data['triaged_by'] = MedicalScope::recorderId();
            }
            if ($emergencyVisit->status === EmergencyVisit::STATUS_WAITING || $emergencyVisit->status === EmergencyVisit::STATUS_REGISTERED) {
                $data['status'] = EmergencyVisit::STATUS_TRIAGED;
            }
        }

        $emergencyVisit->update($data);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($emergencyVisit));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($emergencyVisit, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.emergency.show', $emergencyVisit)
            ->with('status', 'Emergency visit updated.');
    }

    /**
     * Triage form (assign level + vitals).
     */
    public function triageForm(EmergencyVisit $emergencyVisit)
    {
        $this->ensureSameInstitute($emergencyVisit, 'emergency_visit');

        $triageLevels = EmergencyVisit::TRIAGE_LEVELS;
        $suggested = $this->emergencyService->suggestTriageLevel(
            $emergencyVisit->vitals_snapshot ?? [],
            $emergencyVisit->chief_complaint
        );

        return view('medical.emergency.triage', [
            'visit'         => $emergencyVisit,
            'triageLevels'  => $triageLevels,
            'suggested'     => $suggested,
        ]);
    }

    /**
     * Apply triage level.
     */
    public function triage(Request $request, EmergencyVisit $emergencyVisit)
    {
        $this->ensureSameInstitute($emergencyVisit, 'emergency_visit');

        $request->validate([
            'triage_level' => 'required|string|in:' . implode(',', array_keys(EmergencyVisit::TRIAGE_LEVELS)),
        ]);

        $original = ClinicalAuditLog::snapshot($emergencyVisit);

        $emergencyVisit->update([
            'triage_level'  => $request->triage_level,
            'triaged_at'    => $emergencyVisit->triaged_at ?? now(),
            'triaged_by'    => $emergencyVisit->triaged_by ?? MedicalScope::recorderId(),
            'vitals_snapshot' => array_merge($emergencyVisit->vitals_snapshot ?? [], $request->only([
                'systolic_bp', 'diastolic_bp', 'heart_rate', 'respiratory_rate',
                'temperature', 'spo2', 'weight',
            ])),
            'status'        => EmergencyVisit::STATUS_TRIAGED,
        ]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($emergencyVisit->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($emergencyVisit, 'triaged', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.emergency.show', $emergencyVisit)
            ->with('status', 'Triage level assigned: ' . $emergencyVisit->triageLabel());
    }

    /**
     * Assign attending doctor.
     */
    public function attend(Request $request, EmergencyVisit $emergencyVisit)
    {
        $this->ensureSameInstitute($emergencyVisit, 'emergency_visit');

        $request->validate([
            'attending_doctor_id' => 'required|exists:users,id',
        ]);

        $original = ClinicalAuditLog::snapshot($emergencyVisit);

        $emergencyVisit->update([
            'attending_doctor_id' => $request->attending_doctor_id,
            'attended_at'         => now(),
            'status'              => EmergencyVisit::STATUS_ATTENDED,
        ]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($emergencyVisit->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($emergencyVisit, 'attended', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.emergency.show', $emergencyVisit)
            ->with('status', 'Attending doctor assigned.');
    }

    /**
     * Discharge form.
     */
    public function dischargeForm(EmergencyVisit $emergencyVisit)
    {
        $this->ensureSameInstitute($emergencyVisit, 'emergency_visit');
        $this->ensureDoctorOwns($emergencyVisit, 'attending_doctor_id', 'emergency_visit');

        return view('medical.emergency.discharge', ['visit' => $emergencyVisit]);
    }

    /**
     * Discharge a patient.
     */
    public function discharge(Request $request, EmergencyVisit $emergencyVisit)
    {
        $this->ensureSameInstitute($emergencyVisit, 'emergency_visit');

        $request->validate([
            'disposition'        => 'required|string',
            'treatment_given'    => 'nullable|string',
            'disposition_notes'  => 'nullable|string',
        ]);

        $original = ClinicalAuditLog::snapshot($emergencyVisit);

        $emergencyVisit->update([
            'status'            => EmergencyVisit::STATUS_DISCHARGED,
            'disposition'       => $request->disposition,
            'treatment_given'   => $request->treatment_given,
            'disposition_notes' => $request->disposition_notes,
            'disposition_at'    => now(),
            'disposition_by'    => MedicalScope::recorderId(),
        ]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($emergencyVisit->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($emergencyVisit, 'discharged', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.emergency.show', $emergencyVisit)
            ->with('status', 'Patient discharged.');
    }

    /**
     * Delete (soft) an emergency visit.
     */
    public function destroy(EmergencyVisit $emergencyVisit)
    {
        $this->ensureSameInstitute($emergencyVisit, 'emergency_visit');

        ClinicalAuditLog::record($emergencyVisit, 'deleted');

        $emergencyVisit->delete();

        return redirect()
            ->route('medical.emergency.index')
            ->with('status', 'Emergency visit deleted.');
    }
}
