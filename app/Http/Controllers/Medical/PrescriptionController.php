<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\PrescriptionItemRequest;
use App\Http\Requests\Medical\PrescriptionRequest;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Models\Medical\PrescriptionItem;
use App\Models\User;
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
            new Middleware('permission:medical_prescriptions.view', only: ['index', 'show', 'print']),
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

        $prescriptions = $query->orderBy('prescription_date', 'desc')
            ->paginate(20)
            ->withQueryString();
        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();

        return view('medical.prescriptions.index', compact('prescriptions', 'patients'));
    }

    /**
     * Show prescription writing form.
     */
    public function create(Request $request)
    {
        $instituteId = $this->instituteId();

        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();
        $doctors = $this->doctors();
        $medicines = $this->medicineCatalog($instituteId);

        $selectedPatient = null;
        if ($request->filled('patient_id')) {
            $selectedPatient = Patient::where('institute_id', $instituteId)
                ->find($request->patient_id);
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
        $safety = $this->drugSafetyService->fullSafetyCheck($patient, collect($items));

        if ($safety['has_blocking_issues']) {
            return redirect()->back()
                ->with('error', 'Safety check failed — prescription NOT saved: '.implode(' | ', $safety['blocking']))
                ->withInput();
        }

        $prescription = $this->prescriptionService->createPrescription($data, $items);

        $status = 'Prescription '.$prescription->prescription_number.' created successfully!';
        if (! empty($safety['warnings'])) {
            $status .= ' Warning: '.implode(' | ', $safety['warnings']);
        }

        return redirect()->route('medical.prescriptions.show', $prescription)
            ->with('status', $status);
    }

    /**
     * Show prescription details.
     */
    public function show(Prescription $prescription)
    {
        $this->ensureSameInstitute($prescription, 'prescription');
        $prescription->load(['patient', 'doctor', 'items.medicine']);

        return view('medical.prescriptions.show', compact('prescription'));
    }

    /**
     * Show prescription edit form (drafts only).
     */
    public function edit(Prescription $prescription)
    {
        $this->ensureSameInstitute($prescription, 'prescription');

        if ($prescription->is_finalized) {
            return redirect()->back()->with('error', 'Cannot edit a finalized prescription.');
        }

        $instituteId = $this->instituteId();
        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();
        $doctors = $this->doctors();
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

        if ($prescription->is_finalized) {
            return redirect()->back()->with('error', 'Cannot update a finalized prescription.');
        }

        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        // Run safety checks.
        $patient = Patient::where('institute_id', $prescription->institute_id)
            ->findOrFail($data['patient_id']);
        $safety = $this->drugSafetyService->fullSafetyCheck($patient, collect($items));

        if ($safety['has_blocking_issues']) {
            return redirect()->back()
                ->with('error', 'Safety check failed — changes NOT saved: '.implode(' | ', $safety['blocking']))
                ->withInput();
        }

        // Update prescription.
        $prescription->update($data);
        $this->prescriptionService->replaceItems($prescription, $items);

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
                ->with('error', 'Safety check failed — item NOT added: '.implode(' | ', $safety['blocking']))
                ->withInput();
        }

        PrescriptionItem::create($item);

        return redirect()->route('medical.prescriptions.show', $prescription)
            ->with('status', 'Medicine added to prescription.');
    }

    /**
     * Remove an item from a draft prescription (pending items only).
     */
    public function removeItem(Prescription $prescription, PrescriptionItem $item)
    {
        $this->ensureSameInstitute($prescription, 'prescription');

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

        return redirect()->route('medical.prescriptions.show', $prescription)
            ->with('status', 'Medicine removed from prescription.');
    }

    /**
     * Finalize a prescription.
     */
    public function finalize(Prescription $prescription)
    {
        $this->ensureSameInstitute($prescription, 'prescription');

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

        if (! $prescription->is_finalized) {
            return redirect()->back()->with('error', 'Cannot print an unfinalized prescription.');
        }

        $data = $this->prescriptionService->getPrintData($prescription);

        return view('medical.prescriptions.print', $data);
    }

    /**
     * Delete prescription (drafts only).
     */
    public function destroy(Prescription $prescription)
    {
        $this->ensureSameInstitute($prescription, 'prescription');

        if ($prescription->is_finalized) {
            return redirect()->back()->with('error', 'Cannot delete a finalized prescription.');
        }

        $prescription->items()->delete();
        $prescription->delete();

        return redirect()->route('medical.prescriptions.index')
            ->with('status', 'Prescription deleted successfully!');
    }

    /**
     * Doctors available for prescribing (same documented limitation as
     * OPD/IPD: no institute↔doctor mapping exists yet).
     */
    private function doctors()
    {
        return User::where('status', 'active')->orderBy('name')->get();
    }

    private function medicineCatalog(int $instituteId)
    {
        return \App\Models\Medical\Medicine::where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('generic_name')
            ->get();
    }
}
