<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\AdmissionRequest;
use App\Models\Medical\Admission;
use App\Models\Medical\Bed;
use App\Models\Medical\NursingNote;
use App\Models\Medical\Patient;
use App\Models\Medical\VitalSign;
use App\Models\User;
use App\Services\Medical\BedAllocationService;
use App\Services\Medical\DischargeSummaryService;
use App\Support\MedicalScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class AdmissionController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_admissions.view', only: ['index', 'show', 'current', 'dischargeSummary']),
            new Middleware('permission:medical_admissions.create', only: ['create', 'store']),
            new Middleware('permission:medical_admissions.edit', only: ['edit', 'update', 'transferForm', 'transfer']),
            new Middleware('permission:medical_admissions.discharge', only: ['dischargeForm', 'discharge']),
            new Middleware('permission:medical_admissions.delete', only: ['destroy']),
        ];
    }

    protected BedAllocationService $bedAllocation;

    protected DischargeSummaryService $dischargeSummary;

    public function __construct(
        BedAllocationService $bedAllocation,
        DischargeSummaryService $dischargeSummary
    ) {
        $this->bedAllocation = $bedAllocation;
        $this->dischargeSummary = $dischargeSummary;
    }

    /**
     * List all admissions (defaults to active).
     */
    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = Admission::where('institute_id', $instituteId)
            ->with(['patient', 'bed.ward', 'admittingDoctor']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'active');
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('admission_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('admission_date', '<=', $request->to_date);
        }

        $admissions = $query->orderBy('admission_date', 'desc')
            ->orderBy('admission_time', 'desc')
            ->paginate(20)
            ->withQueryString();

        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();

        return view('medical.admissions.index', compact('admissions', 'patients'));
    }

    /**
     * Show current active admissions.
     */
    public function current()
    {
        $instituteId = $this->instituteId();

        $admissions = Admission::where('institute_id', $instituteId)
            ->where('status', 'active')
            ->with(['patient', 'bed.ward', 'admittingDoctor'])
            ->orderBy('admission_date')
            ->get();

        $summary = $this->bedAllocation->getOccupancySummary($instituteId);

        return view('medical.admissions.current', compact('admissions', 'summary'));
    }

    /**
     * Show admission form.
     */
    public function create(Request $request)
    {
        $instituteId = $this->instituteId();

        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();
        $doctors = $this->doctors();
        $beds = Bed::where('institute_id', $instituteId)
            ->where('status', 'available')
            ->with('ward')
            ->orderBy('bed_number')
            ->get();

        $selectedPatient = null;
        if ($request->filled('patient_id')) {
            $selectedPatient = Patient::where('institute_id', $instituteId)
                ->find($request->patient_id);
        }

        return view('medical.admissions.create', compact('patients', 'doctors', 'beds', 'selectedPatient'));
    }

    /**
     * Store a new admission.
     *
     * The admission row is created WITHOUT a bed first; the bed is then
     * allocated through the service (which links bed_id itself). Creating
     * the row with an unallocated bed_id would orphan the reference when
     * allocation fails.
     */
    public function store(AdmissionRequest $request)
    {
        $instituteId = $this->instituteId();
        $data = $request->validated();
        $data['institute_id'] = $instituteId;
        $bedId = $data['bed_id'] ?? null;
        unset($data['bed_id']);

        $admission = Admission::create($data);

        if ($bedId) {
            try {
                $this->bedAllocation->allocateBed($instituteId, (int) $bedId, $admission->id);
            } catch (\Throwable $e) {
                return redirect()->route('medical.admissions.show', $admission)
                    ->with('error', 'Admission created but bed allocation failed: '.$e->getMessage());
            }
        }

        return redirect()->route('medical.admissions.show', $admission)
            ->with('status', 'Patient admitted successfully!');
    }

    /**
     * Show admission details with vitals and notes.
     */
    public function show(Admission $admission)
    {
        $this->ensureSameInstitute($admission, 'admission');

        $admission->load(['patient', 'bed.ward', 'admittingDoctor', 'dischargedBy']);

        $vitals = VitalSign::where('admission_id', $admission->id)
            ->orderBy('recorded_at', 'desc')
            ->limit(10)
            ->get();

        $notes = NursingNote::where('admission_id', $admission->id)
            ->orderBy('recorded_at', 'desc')
            ->limit(10)
            ->get();

        return view('medical.admissions.show', compact('admission', 'vitals', 'notes'));
    }

    /**
     * Show admission edit form.
     */
    public function edit(Admission $admission)
    {
        $this->ensureSameInstitute($admission, 'admission');
        $instituteId = $this->instituteId();

        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();
        $doctors = $this->doctors();
        $beds = Bed::where('institute_id', $instituteId)
            ->where('status', 'available')
            ->with('ward')
            ->orderBy('bed_number')
            ->get();

        return view('medical.admissions.edit', compact('admission', 'patients', 'doctors', 'beds'));
    }

    /**
     * Update admission.
     */
    public function update(AdmissionRequest $request, Admission $admission)
    {
        $this->ensureSameInstitute($admission, 'admission');
        $instituteId = $this->instituteId();

        $data = $request->validated();
        $newBedId = $data['bed_id'] ?? null ? (int) $data['bed_id'] : null;
        unset($data['bed_id']);

        // Bed change: release the old bed, allocate the new one (the service
        // links bed_id on the admission itself).
        if ($newBedId !== ($admission->bed_id ? (int) $admission->bed_id : null)) {
            try {
                if ($admission->bed_id) {
                    $this->bedAllocation->releaseBed($instituteId, (int) $admission->bed_id);
                    $admission->bed_id = null;
                }
                if ($newBedId) {
                    $this->bedAllocation->allocateBed($instituteId, $newBedId, $admission->id);
                }
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', $e->getMessage())->withInput();
            }
        }

        $admission->update($data);

        return redirect()->route('medical.admissions.show', $admission)
            ->with('status', 'Admission updated successfully!');
    }

    /**
     * Show discharge form.
     */
    public function dischargeForm(Admission $admission)
    {
        $this->ensureSameInstitute($admission, 'admission');

        if ($admission->status !== 'active') {
            return redirect()->back()->with('error', 'This admission is already discharged.');
        }

        $formData = $this->dischargeSummary->formData($admission);

        return view('medical.admissions.discharge', $formData);
    }

    /**
     * Process discharge.
     */
    public function discharge(Request $request, Admission $admission)
    {
        $this->ensureSameInstitute($admission, 'admission');

        if ($admission->status !== 'active') {
            return redirect()->back()->with('error', 'This admission is already discharged.');
        }

        $request->validate([
            'discharge_summary' => 'nullable|string',
            'discharge_date' => 'required|date|after_or_equal:'.$admission->admission_date->format('Y-m-d'),
            'discharge_time' => 'required|date_format:H:i',
        ]);

        $admission->update([
            'status' => 'discharged',
            'discharge_date' => $request->discharge_date,
            'discharge_time' => $request->discharge_time,
            'discharge_summary' => $request->discharge_summary,
            'discharged_by' => MedicalScope::recorderId(),
        ]);

        // Release the bed.
        if ($admission->bed_id) {
            try {
                $this->bedAllocation->releaseBed($admission->institute_id, (int) $admission->bed_id);
            } catch (\Throwable $e) {
                return redirect()->route('medical.admissions.show', $admission)
                    ->with('error', 'Patient discharged but bed release failed: '.$e->getMessage());
            }
        }

        return redirect()->route('medical.admissions.show', $admission)
            ->with('status', 'Patient discharged successfully!');
    }

    /**
     * Show transfer form.
     */
    public function transferForm(Admission $admission)
    {
        $this->ensureSameInstitute($admission, 'admission');

        if ($admission->status !== 'active') {
            return redirect()->back()->with('error', 'Cannot transfer a discharged patient.');
        }

        if (! $admission->bed_id) {
            return redirect()->back()->with('error', 'Admission has no bed to transfer from.');
        }

        $admission->load(['patient', 'bed.ward']);

        $availableBeds = Bed::where('institute_id', $admission->institute_id)
            ->where('status', 'available')
            ->with('ward')
            ->orderBy('bed_number')
            ->get();

        return view('medical.admissions.transfer', compact('admission', 'availableBeds'));
    }

    /**
     * Process transfer.
     */
    public function transfer(Request $request, Admission $admission)
    {
        $this->ensureSameInstitute($admission, 'admission');

        if ($admission->status !== 'active' || ! $admission->bed_id) {
            return redirect()->back()->with('error', 'Only active admissions with a bed can be transferred.');
        }

        $request->validate([
            'bed_id' => [
                'required',
                'integer',
                Rule::exists('beds', 'id')->where(
                    fn ($q) => $q->where('institute_id', $admission->institute_id)->where('status', 'available')
                ),
                Rule::notIn([(int) $admission->bed_id]),
            ],
        ], [
            'bed_id.not_in' => 'Please choose a different bed.',
        ]);

        try {
            $this->bedAllocation->transferBed($admission->institute_id, (int) $admission->bed_id, (int) $request->bed_id);

            return redirect()->route('medical.admissions.show', $admission)
                ->with('status', 'Patient transferred successfully!');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Delete admission (discharged records only).
     */
    public function destroy(Admission $admission)
    {
        $this->ensureSameInstitute($admission, 'admission');

        if ($admission->status === 'active') {
            return redirect()->back()->with('error', 'Cannot delete an active admission. Please discharge first.');
        }

        // Release bed if still assigned and occupied (defensive; discharge
        // normally frees it already).
        if ($admission->bed_id) {
            $bed = Bed::find($admission->bed_id);
            if ($bed && $bed->status === 'occupied') {
                try {
                    $this->bedAllocation->releaseBed($admission->institute_id, (int) $admission->bed_id);
                } catch (\Throwable) {
                    // Fall through to delete; counters stay as-is rather
                    // than blocking record removal.
                }
            }
        }

        $admission->delete();

        return redirect()->route('medical.admissions.index')
            ->with('status', 'Admission deleted successfully!');
    }

    /**
     * Generate discharge summary PDF.
     */
    public function dischargeSummary(Admission $admission)
    {
        $this->ensureSameInstitute($admission, 'admission');

        if ($admission->status !== 'discharged') {
            return redirect()->back()->with('error', 'Discharge summary is only available for discharged patients.');
        }

        $pdf = $this->dischargeSummary->generatePdf($admission);

        return $pdf->download('discharge-summary-'.$admission->patient->mr_number.'.pdf');
    }

    /**
     * Doctors available for admission (same documented limitation as OPD:
     * no institute↔doctor mapping exists yet, so active system users).
     */
    private function doctors()
    {
        return User::where('status', 'active')->orderBy('name')->get();
    }
}
