<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\PrescriptionItemRequest;
use App\Http\Requests\Medical\PrescriptionRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Country;
use App\Models\Institute;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Appointment;
use App\Models\Medical\CdsFinding;
use App\Models\Medical\Doctor;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Models\Medical\VitalSign;
use App\Models\Medical\PrescriptionItem;
use App\Services\Medical\DrugSafetyService;
use App\Services\Medical\CdsEngine;
use App\Services\Medical\CdsEvaluation;
use App\Services\Medical\CdsFindingService;
use App\Services\Medical\PrescriptionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PrescriptionController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_prescriptions.view', only: ['index', 'show', 'print', 'downloadPdf', 'reactIndex', 'reactData', 'patientInfo']),
            new Middleware('permission:medical_prescriptions.create', only: ['create', 'store', 'addItem']),
            new Middleware('permission:medical_prescriptions.edit', only: ['edit', 'update', 'finalize', 'removeItem', 'resolveFinding']),
            new Middleware('permission:medical_prescriptions.delete', only: ['destroy']),
        ];
    }

    protected PrescriptionService $prescriptionService;

    protected DrugSafetyService $drugSafetyService;

    protected CdsEngine $cdsEngine;

    protected CdsFindingService $cdsFindings;

    public function __construct(
        PrescriptionService $prescriptionService,
        DrugSafetyService $drugSafetyService,
        CdsEngine $cdsEngine,
        CdsFindingService $cdsFindings
    ) {
        $this->prescriptionService = $prescriptionService;
        $this->drugSafetyService = $drugSafetyService;
        $this->cdsEngine = $cdsEngine;
        $this->cdsFindings = $cdsFindings;
    }

    /**
     * Phase 13 — terminology-normalized CDS evaluation for draft writes.
     * Runs AFTER the existing DrugSafetyService block (which is untouched);
     * engine failures degrade to an honest warning, never to silent safety.
     */
    private function evaluateCds(Patient $patient, $items, int $instituteId): CdsEvaluation
    {
        try {
            return $this->cdsEngine->evaluate(
                $patient,
                collect($items)->map(fn ($i) => is_array($i) ? $i : $i->toArray())->all(),
                $instituteId
            );
        } catch (\Throwable $e) {
            report($e);
            $evaluation = new CdsEvaluation($patient->id, $instituteId);
            $evaluation->addError('engine', 'Evaluation unavailable: '.$e->getMessage());

            return $evaluation;
        }
    }

    /**
     * Phase 13 — refusal message + warning suffixes shared by draft writes.
     */
    private function cdsStatusSuffix(CdsEvaluation $cds): string
    {
        $suffix = '';
        foreach ($cds->warnings as $warning) {
            $suffix .= ' CDS Warning: '.$warning['message'];
        }
        if ($cds->hasErrors()) {
            $suffix .= ' CDS evaluation partially unavailable — DrugSafety checks applied.';
        }

        return $suffix;
    }

    /**
     * List prescriptions.
     */
    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = Prescription::where('institute_id', $instituteId)
            ->with(['patient', 'doctor']);
        // Phase 18: branch fence (context branch + legacy NULLs).
        $this->scopeBranch($query);

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->filled('status')) {
            $query->where('is_finalized', $request->status === 'finalized');
        }

        if ($request->filled('from_date')) {
            $query->whereDate('prescription_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('prescription_date', '<=', $request->to_date);
        }

        if (($fence = $this->doctorFenceId()) !== null) {
            $query->where('doctor_id', $fence);
        }

        $prescriptions = $query->orderBy('prescription_date', 'desc')
            ->paginate(20)
            ->withQueryString();
        $patients = $this->ownPatientOptions($instituteId);

        return view('medical.prescriptions.index', compact('prescriptions', 'patients'));
    }

    /**
     * Show prescription writing form.
     */
    public function create(Request $request)
    {
        $instituteId = $this->instituteId();
        $fence = $this->doctorFenceId();

        $patients = $this->ownPatientOptions($instituteId, $fence);
        $doctors = $this->doctors();
        if ($fence !== null) {
            $doctors = $doctors->where('id', $fence)->values();
        }
        // Phase 18: hide clinicians assigned exclusively to other branches.
        if ($this->branchContextId() !== null) {
            $allowed = $this->branchDoctorUserIds($this->branchContextId(), $instituteId);
            $doctors = $doctors->whereIn('id', $allowed)->values();
        }
        $medicines = $this->medicineCatalog($instituteId);

        $selectedPatient = null;
        if ($request->filled('patient_id')) {
            $selectedPatient = Patient::where('institute_id', $instituteId)
                ->find($request->patient_id);
            if ($selectedPatient && ! $this->mayActOnPatient($selectedPatient, $fence)) {
                abort(403, 'You do not have permission to book for this patient.');
            }
        }

        // Post-visit doctor flow: started from the queue, carries the
        // in-progress appointment so saving can chain into fee collection.
        $feeAppointment = null;
        $selectedDoctor = null;
        if ($request->filled('fee_appointment_id')) {
            $feeAppointment = Appointment::where('institute_id', $instituteId)
                ->where('status', 'in_progress')
                ->find($request->input('fee_appointment_id'));
            if ($feeAppointment) {
                $this->ensureDoctorOwns($feeAppointment, 'doctor_id', 'appointment');
                $this->ensureBranchAccess($feeAppointment, 'branch_id', 'appointment');
                $selectedPatient = Patient::where('institute_id', $instituteId)
                    ->find($feeAppointment->patient_id);
                $selectedDoctor = $feeAppointment->doctor_id;
            }
        }

        // Single-doctor lists (fenced doctors) pre-select themselves so the
        // letterhead and the required doctor field agree even with JS off.
        if ($selectedDoctor === null && ! $request->old('doctor_id') && $doctors->count() === 1) {
            $selectedDoctor = $doctors->first()->id;
        }

        // Read-only info cards above the form: patient details + latest vitals.
        $infoPatient = $selectedPatient ? $this->formatPatientForCard($selectedPatient) : null;
        $infoVitals = $selectedPatient ? $this->formatVitalsForCard($this->latestVitalsFor($selectedPatient)) : null;
        // Queue-visit payment status rides on the patient card; without a
        // visit chain, fall back to the live cycle state (Unpaid default).
        // The card also shows the visit serial when there is one.
        if ($infoPatient && $selectedPatient) {
            $infoPatient['payment'] = $feeAppointment
                ? $this->feePaymentStatus($feeAppointment, $selectedPatient)
                : $this->latestPaymentStatus($selectedPatient);
            $visit = $feeAppointment ?? $this->latestVisit($selectedPatient);
            $infoPatient['serial'] = $visit ? '#'.$visit->serial_number : null;
        }

        // Prescriber Information card: per-doctor details for the JS swap
        // plus the practice (institute) details shared by all doctors.
        $profiles = Doctor::where('institute_id', $instituteId)
            ->whereIn('user_id', $doctors->pluck('id')->all())
            ->with(['specialty:id,name', 'department:id,name'])
            ->get()
            ->keyBy('user_id');
        $doctorCards = [];
        foreach ($doctors as $doctorUser) {
            $prof = $profiles->get($doctorUser->id);
            $doctorCards[$doctorUser->id] = [
                'name' => $doctorUser->name,
                'qualification' => $prof?->qualification ?? null,
                'experience' => $prof?->experience_years ?? null,
                'registration' => $prof?->registration_number ?? null,
                'specialty' => $prof?->specialty?->name ?? null,
                'department' => $prof?->department?->name ?? null,
                'chamber' => $prof?->chamber_address ?? null,
                'room' => $prof?->room_no ?? null,
            ];
        }
        $practice = \App\Models\Institute::whereKey($instituteId)
            ->first(['name', 'address', 'phone', 'email']);
        $practiceInfo = [
            'clinic' => $practice->name ?? null,
            'address' => $practice->address ?? null,
            'phone' => $practice->phone ?? null,
            'email' => $practice->email ?? null,
        ];

        // Quick-add patient popup data (same inputs as the appointments page).
        $countries = Country::where('status', true)->orderBy('name')->get(['id', 'name', 'phone_code']);
        $defaultCountryId = Institute::whereKey($instituteId)->value('country_id');
        $previewMr = app(\App\Services\Medical\MrNumberGenerator::class)->peek($instituteId);

        return view('medical.prescriptions.create', compact('patients', 'doctors', 'medicines', 'selectedPatient', 'selectedDoctor', 'feeAppointment', 'infoPatient', 'infoVitals', 'doctorCards', 'practiceInfo', 'countries', 'defaultCountryId', 'previewMr'));
    }

    /**
     * JSON for the read-only info cards on the prescription form
     * (refreshed when the patient dropdown changes). Pass
     * ?fee_appointment_id= to recompute the payment line for that visit.
     */
    public function patientInfo(Request $request, Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');
        if (! $this->mayActOnPatient($patient)) {
            abort(403, 'You do not have permission to access this patient.');
        }

        $payment = null;
        $serial = null;
        if ($request->filled('fee_appointment_id')) {
            $feeAppointment = Appointment::where('institute_id', $this->instituteId())
                ->find($request->input('fee_appointment_id'));
            if ($feeAppointment) {
                $this->ensureDoctorOwns($feeAppointment, 'doctor_id', 'appointment');
                $payment = $this->feePaymentStatus($feeAppointment, $patient);
                $serial = '#'.$feeAppointment->serial_number;
            }
        }
        if ($payment === null) {
            $payment = $this->latestPaymentStatus($patient);
        }
        if ($serial === null && ($visit = $this->latestVisit($patient))) {
            $serial = '#'.$visit->serial_number;
        }

        return response()->json([
            'patient' => array_merge($this->formatPatientForCard($patient), ['payment' => $payment, 'serial' => $serial]),
            'vitals' => $this->formatVitalsForCard($this->latestVitalsFor($patient)),
        ]);
    }

    /**
     * Visit payment status line for the patient card: collected amount
     * (Paid …) or the amount due now (Due … with first/follow-up type),
     * computed exactly like fee collection does.
     */
    private function feePaymentStatus(Appointment $appointment, ?Patient $patient): ?string
    {
        if ($appointment->fee_collected_at) {
            $amount = number_format((float) $appointment->fee_collected_amount, 2);
            $by = $appointment->fee_collected_by_name ? ' by '.$appointment->fee_collected_by_name : '';
            $at = $appointment->fee_collected_at->format('d M Y, h:i A');

            return "Paid ৳{$amount}{$by} · {$at}";
        }

        $profile = Doctor::resolveForUser((int) $appointment->doctor_id, $this->instituteId());
        $amount = ($profile && $patient) ? (float) $profile->getApplicableFee($patient) : 0.0;
        $type = ($profile && $patient && $profile->hasFollowUpRateFor($patient)) ? 'Follow-up' : 'First visit';

        return 'Unpaid ৳'.number_format($amount, 2).' ('.$type.')';
    }

    /**
     * Latest live visit for card context (today or earlier, never
     * cancelled), fenced like everything else.
     */
    private function latestVisit(Patient $patient): ?Appointment
    {
        $query = Appointment::where('institute_id', $this->instituteId())
            ->where('patient_id', $patient->id)
            ->where('status', '!=', 'cancelled')
            ->whereDate('appointment_date', '<=', today());
        if (($fence = $this->doctorFenceId()) !== null) {
            $query->where('doctor_id', $fence);
        }

        return $query->orderByDesc('appointment_date')->orderByDesc('id')->first();
    }

    /**
     * Cycle status for the patient card when no queue visit is attached.
     * A new cycle starts Unpaid and stays Unpaid until its own fee lands:
     * only an OPEN visit (scheduled / checked-in / in-progress) can report
     * Paid or Due. Closed history never counts — the card always shows the
     * live cycle state, defaulting to Unpaid.
     */
    private function latestPaymentStatus(Patient $patient): ?string
    {
        $latest = $this->latestVisit($patient);

        if ($latest && in_array($latest->status, ['scheduled', 'checked_in', 'in_progress'], true)) {
            return $this->feePaymentStatus($latest, $patient);
        }

        return 'Unpaid';
    }

    /**
     * Latest vitals row for a patient (the patient is already
     * institute-scoped and fence-checked by the caller).
     */
    private function latestVitalsFor(Patient $patient): ?VitalSign
    {
        return VitalSign::where('patient_id', $patient->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Patient fields for the read-only details card (display-ready strings).
     */
    private function formatPatientForCard(Patient $patient): array
    {
        $age = $patient->age !== null ? $patient->age.' yrs' : '—';
        $gender = $patient->gender ? ucfirst((string) $patient->gender) : '—';

        return [
            'mr_number' => $patient->mr_number ?? '—',
            'name' => $patient->full_name ?? '—',
            'age_gender' => $age.' / '.$gender,
            'phone' => $patient->phone ?? '—',
            'blood_group' => $patient->blood_group ?? '—',
        ];
    }

    /**
     * Vitals fields for the read-only vitals card (display-ready strings,
     * null when the patient has no vitals yet).
     */
    private function formatVitalsForCard(?VitalSign $vitals): ?array
    {
        if (! $vitals) {
            return null;
        }

        // Null stays null (the card hides unmentioned values instead of
        // showing a dash).
        $payload = [
            'temperature' => $vitals->temperature !== null ? $vitals->temperature.' °C' : null,
            'bp' => $vitals->blood_pressure,
            'pulse' => $vitals->pulse !== null ? $vitals->pulse.' bpm' : null,
            'spo2' => $vitals->spo2 !== null ? $vitals->spo2.' %' : null,
            'respiratory_rate' => $vitals->respiratory_rate !== null ? $vitals->respiratory_rate.' /min' : null,
            'blood_sugar' => $vitals->blood_sugar !== null ? $vitals->blood_sugar.' mg/dL' : null,
            'weight' => $vitals->weight !== null ? $vitals->weight.' kg' : null,
            'height' => $vitals->height !== null ? $vitals->height.' cm' : null,
            'bmi' => $vitals->bmi,
            'recorded_at' => $vitals->recorded_at?->format('d M Y, h:i A') ?? '—',
            // Raw input values for prefilling the vitals popup.
            'raw' => [
                'temperature' => $vitals->temperature,
                'blood_pressure_systolic' => $vitals->blood_pressure_systolic,
                'blood_pressure_diastolic' => $vitals->blood_pressure_diastolic,
                'pulse' => $vitals->pulse,
                'heart_rate' => $vitals->heart_rate,
                'respiratory_rate' => $vitals->respiratory_rate,
                'spo2' => $vitals->spo2,
                'pain_score' => $vitals->pain_score,
                'blood_sugar' => $vitals->blood_sugar,
                'weight' => $vitals->weight,
                'height' => $vitals->height,
                'notes' => $vitals->notes,
            ],
        ];

        $payload['has_values'] = collect([
            $payload['temperature'], $payload['bp'], $payload['pulse'],
            $payload['spo2'], $payload['respiratory_rate'], $payload['blood_sugar'],
            $payload['weight'], $payload['height'], $payload['bmi'],
        ])->contains(fn ($v) => $v !== null && $v !== '');

        return $payload;
    }

    /**
     * Store a new prescription.
     *
     * Safety policy: BLOCK on allergies / contraindications / duplicate
     * therapy (high severity); medium-severity overlap warnings ride along
     * on the success message instead of refusing a legitimate script.
     */
    public function store(PrescriptionRequest $request)
    {
        $instituteId = $this->instituteId();
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);
        $data['institute_id'] = $instituteId;

        // Run safety checks (patient scoped to this institute by validation).
        $patient = Patient::where('institute_id', $instituteId)->findOrFail($data['patient_id']);
        if (! $this->mayActOnPatient($patient)) {
            abort(403, 'You do not have permission to book for this patient.');
        }

        // Fenced doctors prescribe only as themselves.
        if (($fence = $this->doctorFenceId()) !== null) {
            $data['doctor_id'] = $fence;
        }

        // Optional encounter link: same institute + same patient, visible to
        // the prescriber. History rows keep NULL (legacy behavior).
        $encounter = null;
        if (! empty($data['encounter_id'])) {
            $encounter = \App\Models\Medical\Encounter::where('institute_id', $instituteId)
                ->findOrFail($data['encounter_id']);
            if ((int) $encounter->patient_id !== (int) $patient->id) {
                return redirect()->back()
                    ->with('error', 'The selected encounter belongs to a different patient.')
                    ->withInput();
            }
            if (($fence ?? null) !== null && (int) $encounter->doctor_id !== (int) $fence) {
                abort(403, 'You do not have permission to use this encounter.');
            }
            $this->ensureBranchAccess($encounter, 'branch_id', 'encounter');
        }
        // Phase 18: branch ownership. A linked prescription inherits its
        // encounter's branch (deterministic); an explicit branch that
        // contradicts the encounter is rejected. Legacy (branch-less)
        // encounters accept any accessible branch.
        $requestedBranch = $request->input('branch_id');
        if ($encounter && $encounter->branch_id !== null) {
            if ($requestedBranch !== null && $requestedBranch !== ''
                && (int) $requestedBranch !== (int) $encounter->branch_id) {
                return redirect()->back()
                    ->with('error', 'The prescription branch must match its encounter branch.')
                    ->withInput();
            }
            $data['branch_id'] = $encounter->branch_id;
        } else {
            $data['branch_id'] = $this->resolveBranchId($requestedBranch);
        }
        if (! $this->doctorBranchOk((int) $data['doctor_id'], $data['branch_id'], $instituteId)) {
            return redirect()->back()
                ->with('error', 'The selected doctor is not assigned to this branch.')
                ->withInput();
        }
        $safety = $this->drugSafetyService->fullSafetyCheck($patient, collect($items));

        if ($safety['has_blocking_issues']) {
            return redirect()->back()
                ->with('error', 'Safety check failed â€” prescription NOT saved: '.implode(' | ', $safety['blocking']))
                ->withInput();
        }

        // Phase 13: terminology-normalized CDS augments (never replaces) the
        // heuristic block above. CRITICAL/HIGH findings with block policy
        // refuse the save; the rest persist as findings + warnings.
        $cds = $this->evaluateCds($patient, $items, $instituteId);
        if ($cds->hasBlockingIssues()) {
            return redirect()->back()
                ->with('error', 'Clinical check failed — prescription NOT saved: '.implode(' | ', array_column($cds->blocking, 'message')))
                ->withInput();
        }

        $prescription = $this->prescriptionService->createPrescription($data, $items);
        $this->cdsFindings->recordEvaluation($prescription, $cds);

        $status = 'Prescription '.$prescription->prescription_number.' created successfully!';
        if (! empty($safety['warnings'])) {
            $status .= ' Warning: '.implode(' | ', $safety['warnings']);
        }
        $status .= $this->cdsStatusSuffix($cds);
        if (($dgdaWarning = $this->dgdaWarning($prescription)) !== null) {
            $status .= ' '.$dgdaWarning;
        }

        // Post-visit doctor flow: prescription saved → chain straight into
        // the fee popup, confirming which completes the visit.
        if (($feeAppointment = $this->validFeeAppointment($request)) !== null) {
            return redirect()->route('medical.appointments.index', [
                'tab' => 'queue',
                'q_doctor' => $feeAppointment->doctor_id,
                'q_date' => $feeAppointment->appointment_date->format('Y-m-d'),
                'fee_collect' => $feeAppointment->id,
            ])->with('status', $status);
        }

        return redirect()->route('medical.prescriptions.show', $prescription)
            ->with('status', $status);
    }

    /**
     * React-powered prescription list page (Blade host for resources/js/medical/prescriptions.js).
     *
     * The Blade prescriptions index is untouched; React mounts only on this
     * page and polls reactData() every 10 seconds.
     */
    public function reactIndex()
    {
        $props = [
            'initialPrescriptions' => $this->reactPrescriptionPayload(),
            'refreshUrl' => route('medical.prescriptions.react.data'),
        ];

        return view('medical.prescriptions-react', compact('props'));
    }

    /**
     * JSON feed for the React prescription list (polled every 10 seconds).
     */
    public function reactData()
    {
        return response()->json($this->reactPrescriptionPayload());
    }

    /**
     * Prescription rows for the React list (institute-scoped, doctor-fenced).
     */
    private function reactPrescriptionPayload(): array
    {
        $query = Prescription::where('institute_id', $this->instituteId())
            ->with(['patient', 'doctor'])
            ->withCount('items');
        // Phase 18: same branch fence as index (React poll feed).
        $this->scopeBranch($query);

        if (($fence = $this->doctorFenceId()) !== null) {
            $query->where('doctor_id', $fence);
        }

        return $query->orderBy('prescription_date', 'desc')
            ->limit(100)
            ->get()
            ->map(fn (Prescription $prescription) => [
                'id' => $prescription->id,
                'prescription_number' => $prescription->prescription_number,
                'patient_name' => $prescription->patient->full_name ?? 'N/A',
                'doctor_name' => $prescription->doctor->name ?? null,
                'prescription_date' => $prescription->prescription_date?->format('Y-m-d'),
                'is_finalized' => (bool) $prescription->is_finalized,
                'items_count' => (int) ($prescription->items_count ?? 0),
                'show_url' => route('medical.prescriptions.show', $prescription),
            ])
            ->all();
    }

    /**
     * Show prescription details.
     */
    public function show(Prescription $prescription)
    {
        $this->ensureSameInstitute($prescription, 'prescription');
        $this->ensureDoctorOwns($prescription, 'doctor_id', 'prescription');
        $this->ensureBranchAccess($prescription, 'branch_id', 'prescription');
        $prescription->load(['patient', 'doctor', 'items.medicine', 'cdsFindings.ruleVersion.rule']);
        $qr = $prescription->is_finalized
            ? $this->prescriptionService->verificationQr($prescription)
            : '';
        $verifyCode = $prescription->is_finalized
            ? $this->prescriptionService->verificationPayload($prescription)
            : '';

        return view('medical.prescriptions.show', compact('prescription', 'qr', 'verifyCode'));
    }

    /**
     * Show prescription edit form (drafts only).
     */
    public function edit(Prescription $prescription)
    {
        $this->ensureSameInstitute($prescription, 'prescription');
        $this->ensureDoctorOwns($prescription, 'doctor_id', 'prescription');
        $this->ensureBranchAccess($prescription, 'branch_id', 'prescription');

        if ($prescription->is_finalized) {
            return redirect()->back()->with('error', 'Cannot edit a finalized prescription.');
        }

        $instituteId = $this->instituteId();
        $patients = $this->ownPatientOptions($instituteId);
        $doctors = $this->doctors();
        if (($fence = $this->doctorFenceId()) !== null) {
            $doctors = $doctors->where('id', $fence)->values();
        }
        if ($this->branchContextId() !== null) {
            $allowed = $this->branchDoctorUserIds($this->branchContextId(), $instituteId);
            $doctors = $doctors->whereIn('id', $allowed)->values();
        }
        $medicines = $this->medicineCatalog($instituteId);

        $prescription->load('items');

        return view('medical.prescriptions.edit', compact('prescription', 'patients', 'doctors', 'medicines'));
    }

    /**
     * Update prescription (drafts only, items replaced wholesale).
     */
    public function update(PrescriptionRequest $request, Prescription $prescription)
    {
        $this->ensureSameInstitute($prescription, 'prescription');
        $this->ensureDoctorOwns($prescription, 'doctor_id', 'prescription');
        $this->ensureBranchAccess($prescription, 'branch_id', 'prescription');

        if ($prescription->is_finalized) {
            return redirect()->back()->with('error', 'Cannot update a finalized prescription.');
        }

        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);
        // Phase 18: branch identity never moves between records.
        unset($data['branch_id']);

        // Fenced doctors keep ownership (no reassignment away).
        if (($fence = $this->doctorFenceId()) !== null) {
            $data['doctor_id'] = $fence;
        }

        // Run safety checks.
        $patient = Patient::where('institute_id', $prescription->institute_id)
            ->findOrFail($data['patient_id']);
        if (! $this->mayActOnPatient($patient, $fence ?? null)) {
            abort(403, 'You do not have permission to book for this patient.');
        }
        $safety = $this->drugSafetyService->fullSafetyCheck($patient, collect($items));

        if ($safety['has_blocking_issues']) {
            return redirect()->back()
                ->with('error', 'Safety check failed â€” changes NOT saved: '.implode(' | ', $safety['blocking']))
                ->withInput();
        }

        // Phase 13: CDS evaluation over the incoming item set (same contract
        // as store — block, then persist findings with the saved draft).
        $cds = $this->evaluateCds($patient, $items, (int) $prescription->institute_id);
        if ($cds->hasBlockingIssues()) {
            return redirect()->back()
                ->with('error', 'Clinical check failed — changes NOT saved: '.implode(' | ', array_column($cds->blocking, 'message')))
                ->withInput();
        }

        // Update prescription.
        // Phase 01: snapshot header + draft items; wholesale replacement
        // must stay attributable (finalized rows are still blocked above).
        $original = ClinicalAuditLog::snapshot($prescription);
        $originalItems = $this->itemSummaries($prescription->items()->orderBy('id')->get());
        $prescription->update($data);
        $this->prescriptionService->replaceItems($prescription, $items);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($prescription->refresh()));
        $newItems = $this->itemSummaries(collect($items));
        if ($originalItems !== $newItems) {
            $old['items'] = $originalItems;
            $new['items'] = $newItems;
        }
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($prescription, 'draft_updated', ['old' => $old, 'new' => $new]);
        }
        $this->cdsFindings->recordEvaluation($prescription->refresh(), $cds);

        $status = 'Prescription updated successfully!';
        if (! empty($safety['warnings'])) {
            $status .= ' Warning: '.implode(' | ', $safety['warnings']);
        }
        $status .= $this->cdsStatusSuffix($cds);

        return redirect()->route('medical.prescriptions.show', $prescription)
            ->with('status', $status);
    }

    /**
     * Add a single item to a draft prescription.
     */
    public function addItem(PrescriptionItemRequest $request, Prescription $prescription)
    {
        $this->ensureSameInstitute($prescription, 'prescription');
        $this->ensureDoctorOwns($prescription, 'doctor_id', 'prescription');
        $this->ensureBranchAccess($prescription, 'branch_id', 'prescription');

        if ($prescription->is_finalized) {
            return redirect()->back()->with('error', 'Cannot modify a finalized prescription.');
        }

        $item = $request->validated();
        $item['prescription_id'] = $prescription->id;

        $safety = $this->drugSafetyService->fullSafetyCheck(
            $prescription->patient,
            $prescription->items->concat([$item])
        );

        if ($safety['has_blocking_issues']) {
            return redirect()->back()
                ->with('error', 'Safety check failed â€” item NOT added: '.implode(' | ', $safety['blocking']))
                ->withInput();
        }

        // Phase 13: CDS over the full resulting set (existing + new item).
        $cds = $this->evaluateCds(
            $prescription->patient,
            $prescription->items->map(fn ($i) => $i->only([
                'medicine_id', 'medicine_name', 'dosage', 'frequency',
                'duration_days', 'quantity', 'special_instructions',
            ]))->all() + [$item],
            (int) $prescription->institute_id
        );
        if ($cds->hasBlockingIssues()) {
            return redirect()->back()
                ->with('error', 'Clinical check failed — item NOT added: '.implode(' | ', array_column($cds->blocking, 'message')))
                ->withInput();
        }

        PrescriptionItem::create($item);
        \App\Models\Medical\PrescriptionAuditLog::record(
            $prescription, 'item_added', $item['medicine_name'] ?? ('#'.$item['medicine_id'])
        );
        $this->cdsFindings->recordEvaluation($prescription->refresh(), $cds);

        $status = 'Medicine added to prescription.';
        $status .= $this->cdsStatusSuffix($cds);
        if (($dgdaWarning = $this->dgdaWarning($prescription->refresh())) !== null) {
            $status .= ' '.$dgdaWarning;
        }

        return redirect()->route('medical.prescriptions.show', $prescription)
            ->with('status', $status);
    }

    /**
     * Remove an item from a draft prescription (pending items only).
     */
    public function removeItem(Prescription $prescription, PrescriptionItem $item)
    {
        $this->ensureSameInstitute($prescription, 'prescription');
        $this->ensureDoctorOwns($prescription, 'doctor_id', 'prescription');
        $this->ensureBranchAccess($prescription, 'branch_id', 'prescription');

        if ($prescription->is_finalized) {
            return redirect()->back()->with('error', 'Cannot modify a finalized prescription.');
        }

        if ((int) $item->prescription_id !== (int) $prescription->id) {
            abort(404);
        }

        if ($item->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending items can be removed.');
        }

        $item->delete();
        \App\Models\Medical\PrescriptionAuditLog::record(
            $prescription, 'item_removed', $item->medicine_name ?? ('#'.$item->medicine_id)
        );

        return redirect()->route('medical.prescriptions.show', $prescription)
            ->with('status', 'Medicine removed from prescription.');
    }

    /**
     * Resolve a CDS finding: acknowledge | override (reason required) |
     * resolve. Same-institute + fence + ownership checks; the status change
     * itself is audited inside the service. No silent dismiss exists.
     */
    public function resolveFinding(Request $request, Prescription $prescription, CdsFinding $finding)
    {
        $this->ensureSameInstitute($prescription, 'prescription');
        $this->ensureDoctorOwns($prescription, 'doctor_id', 'prescription');
        $this->ensureBranchAccess($prescription, 'branch_id', 'prescription');

        if ((int) $finding->prescription_id !== (int) $prescription->id
            || (int) $finding->institute_id !== (int) $prescription->institute_id) {
            abort(404);
        }

        $data = $request->validate([
            'action' => 'required|in:acknowledge,override,resolve',
            'reason' => 'nullable|string|max:2000|required_if:action,override',
        ]);

        $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();
        $actorId = $staff?->getKey();
        $actorName = $staff->name
            ?? trim(($staff->first_name ?? '').' '.($staff->last_name ?? '')) ?: null;

        try {
            $this->cdsFindings->resolve(
                $finding,
                $data['action'],
                $data['reason'] ?? null,
                $actorId !== null ? (int) $actorId : null,
                $actorName
            );
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('medical.prescriptions.show', $prescription)
            ->with('status', 'Clinical finding '.ucfirst($data['action']).'d.');
    }

    /**
     * Finalize a prescription.
     */
    public function finalize(Prescription $prescription)    {
        $this->ensureSameInstitute($prescription, 'prescription');
        $this->ensureDoctorOwns($prescription, 'doctor_id', 'prescription');
        $this->ensureBranchAccess($prescription, 'branch_id', 'prescription');

        if ($prescription->is_finalized) {
            return redirect()->back()->with('error', 'Prescription is already finalized.');
        }

        $this->prescriptionService->finalize($prescription);

        return redirect()->route('medical.prescriptions.show', $prescription)
            ->with('status', 'Prescription finalized successfully! It can now be dispensed.');
    }

    /**
     * Print prescription (finalized only).
     */
    public function print(Prescription $prescription)
    {
        $this->ensureSameInstitute($prescription, 'prescription');
        $this->ensureDoctorOwns($prescription, 'doctor_id', 'prescription');
        $this->ensureBranchAccess($prescription, 'branch_id', 'prescription');

        if (! $prescription->is_finalized) {
            return redirect()->back()->with('error', 'Cannot print an unfinalized prescription.');
        }

        $data = $this->prescriptionService->getPrintData($prescription);

        return view('medical.prescriptions.print', $data);
    }

    /**
     * Download prescription as PDF (finalized only, standalone layout).
     */
    public function downloadPdf(Prescription $prescription)
    {
        $this->ensureSameInstitute($prescription, 'prescription');
        $this->ensureDoctorOwns($prescription, 'doctor_id', 'prescription');
        $this->ensureBranchAccess($prescription, 'branch_id', 'prescription');

        if (! $prescription->is_finalized) {
            return redirect()->back()->with('error', 'Cannot download PDF of an unfinalized prescription.');
        }

        $data = $this->prescriptionService->getPrintData($prescription);
        $data['verifyCode'] = $this->prescriptionService->verificationPayload($prescription);

        return Pdf::loadView('medical.prescriptions.print-pdf', $data)
            ->download('prescription-'.$prescription->prescription_number.'.pdf');
    }

    /**
     * Delete prescription (drafts only).
     */
    public function destroy(Prescription $prescription)
    {
        $this->ensureSameInstitute($prescription, 'prescription');
        $this->ensureDoctorOwns($prescription, 'doctor_id', 'prescription');
        $this->ensureBranchAccess($prescription, 'branch_id', 'prescription');

        if ($prescription->is_finalized) {
            return redirect()->back()->with('error', 'Cannot delete a finalized prescription.');
        }

        // Phase 01: draft deletion leaves an attributable trail.
        ClinicalAuditLog::record($prescription, 'deleted', [
            'old' => array_merge(ClinicalAuditLog::snapshot($prescription), [
                'items' => $this->itemSummaries($prescription->items()->orderBy('id')->get()),
            ]),
        ]);
        $prescription->items()->delete();
        $prescription->delete();

        return redirect()->route('medical.prescriptions.index')
            ->with('status', 'Prescription deleted successfully!');
    }

    /**
     * Doctors available for prescribing â€” tenant-scoped to members/profile
     * holders of this institute (Phase 02; validation enforces the same).
     */
    private function doctors()
    {
        return \App\Support\MedicalScope::instituteDoctors($this->instituteId());
    }

    /**
     * Resolve a valid fee-chain appointment: same institute, still
     * in progress, and visible to the current user. Anything else is
     * ignored (normal redirect applies) — never an error.
     */
    private function validFeeAppointment(Request $request): ?Appointment
    {
        if (! $request->filled('fee_appointment_id')) {
            return null;
        }

        $appointment = Appointment::where('institute_id', $this->instituteId())
            ->where('status', 'in_progress')
            ->find($request->input('fee_appointment_id'));

        if (! $appointment) {
            return null;
        }

        try {
            $this->ensureDoctorOwns($appointment, 'doctor_id', 'appointment');
        } catch (\Throwable) {
            return null;
        }

        return $appointment;
    }

    /**
     * Warn-first DGDA notice: lists items without a registry code without
     * blocking the save (strict mandatory enforcement would refuse every
     * prescription until the catalog is synced â€” enable it only after
     * medical:dgda-sync covers the catalog).
     */
    private function dgdaWarning(Prescription $prescription): ?string
    {
        if (! mawa_dgda_enabled()) {
            return null;
        }
        $prescription->loadMissing('items.medicine');
        $missing = $prescription->items
            ->filter(fn ($item) => empty($item->dgda_code))
            ->values();

        if ($missing->isEmpty()) {
            return null;
        }

        $names = $missing->map(fn ($item) => $item->medicine_name)->take(3)->implode(', ');
        $more = $missing->count() > 3 ? ' (+'.($missing->count() - 3).' more)' : '';

        return 'DGDA notice: '.$missing->count().' item(s) have no registry code yet ('.$names.$more.') â€” sync the catalog.';
    }

    private function medicineCatalog(int $instituteId)
    {
        return \App\Models\Medical\Medicine::where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('generic_name')
            ->get();
    }

    /**
     * Phase 01 â€” comparable item summaries for the draft-update audit.
     * Works for both Eloquent items and validated input arrays.
     */
    private function itemSummaries(iterable $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $get = fn ($key) => $item instanceof \Illuminate\Database\Eloquent\Model
                ? $item->getAttribute($key)
                : ($item[$key] ?? null);
            $out[] = [
                'medicine_name' => $get('medicine_name'),
                'dosage' => $get('dosage'),
                'frequency' => $get('frequency'),
                'duration_days' => $get('duration_days') !== null ? (int) $get('duration_days') : null,
                'quantity' => $get('quantity') !== null ? (int) $get('quantity') : null,
            ];
        }

        return $out;
    }
}
