<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\PrescriptionItemRequest;
use App\Http\Requests\Medical\PrescriptionRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Models\Medical\PrescriptionItem;
use App\Services\Medical\DrugSafetyService;
use App\Services\Medical\PrescriptionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PrescriptionController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_prescriptions.view', only: ['index', 'show', 'print', 'reactIndex', 'reactData']),
            new Middleware('permission:medical_prescriptions.create', only: ['create', 'store', 'addItem']),
            new Middleware('permission:medical_prescriptions.edit', only: ['edit', 'update', 'finalize', 'removeItem']),
            new Middleware('permission:medical_prescriptions.delete', only: ['destroy']),
        ];
    }

    protected PrescriptionService $prescriptionService;

    protected DrugSafetyService $drugSafetyService;

    public function __construct(
        PrescriptionService $prescriptionService,
        DrugSafetyService $drugSafetyService
    ) {
        $this->prescriptionService = $prescriptionService;
        $this->drugSafetyService = $drugSafetyService;
    }

    /**
     * List prescriptions.
     */
    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = Prescription::where('institute_id', $instituteId)
            ->with(['patient', 'doctor']);

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
        $medicines = $this->medicineCatalog($instituteId);

        $selectedPatient = null;
        if ($request->filled('patient_id')) {
            $selectedPatient = Patient::where('institute_id', $instituteId)
                ->find($request->patient_id);
            if ($selectedPatient && ! $this->mayActOnPatient($selectedPatient, $fence)) {
                abort(403, 'You do not have permission to book for this patient.');
            }
        }

        return view('medical.prescriptions.create', compact('patients', 'doctors', 'medicines', 'selectedPatient'));
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
        $safety = $this->drugSafetyService->fullSafetyCheck($patient, collect($items));

        if ($safety['has_blocking_issues']) {
            return redirect()->back()
                ->with('error', 'Safety check failed â€” prescription NOT saved: '.implode(' | ', $safety['blocking']))
                ->withInput();
        }

        $prescription = $this->prescriptionService->createPrescription($data, $items);

        $status = 'Prescription '.$prescription->prescription_number.' created successfully!';
        if (! empty($safety['warnings'])) {
            $status .= ' Warning: '.implode(' | ', $safety['warnings']);
        }
        if (($dgdaWarning = $this->dgdaWarning($prescription)) !== null) {
            $status .= ' '.$dgdaWarning;
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
        $prescription->load(['patient', 'doctor', 'items.medicine']);
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

        if ($prescription->is_finalized) {
            return redirect()->back()->with('error', 'Cannot edit a finalized prescription.');
        }

        $instituteId = $this->instituteId();
        $patients = $this->ownPatientOptions($instituteId);
        $doctors = $this->doctors();
        if (($fence = $this->doctorFenceId()) !== null) {
            $doctors = $doctors->where('id', $fence)->values();
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

        if ($prescription->is_finalized) {
            return redirect()->back()->with('error', 'Cannot update a finalized prescription.');
        }

        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

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

        $status = 'Prescription updated successfully!';
        if (! empty($safety['warnings'])) {
            $status .= ' Warning: '.implode(' | ', $safety['warnings']);
        }

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

        PrescriptionItem::create($item);
        \App\Models\Medical\PrescriptionAuditLog::record(
            $prescription, 'item_added', $item['medicine_name'] ?? ('#'.$item['medicine_id'])
        );

        $status = 'Medicine added to prescription.';
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
     * Finalize a prescription.
     */
    public function finalize(Prescription $prescription)
    {
        $this->ensureSameInstitute($prescription, 'prescription');
        $this->ensureDoctorOwns($prescription, 'doctor_id', 'prescription');

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
