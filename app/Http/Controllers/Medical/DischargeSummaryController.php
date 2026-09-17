<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\Admission;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\DischargeSummary;
use App\Models\Medical\NumberSequence;
use App\Services\Medical\NumberSequenceService;
use App\Services\Medical\PatientTimelineEventService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DischargeSummaryController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.records.view', only: ['index', 'show', 'pdf']),
            new Middleware('permission:medical.records.discharge.create', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly PatientTimelineEventService $timeline,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = DischargeSummary::forInstitute($instituteId);
        $this->scopeBranch($query);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('summary_number', 'like', "%{$search}%")
                    ->orWhere('final_diagnosis', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($pq) => $pq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $summaries = $query->with(['patient', 'admission', 'preparedBy'])
            ->orderByDesc('discharge_date')->paginate(25)->withQueryString();

        return view('medical.records.discharge-summaries.index', compact('summaries'));
    }

    public function create(Request $request)
    {
        $instituteId = $this->instituteId();
        $summaryNumber = $this->sequences->peek(NumberSequence::TYPE_DISCHARGE_SUMMARY, $instituteId);
        $admissions = Admission::where('institute_id', $instituteId)->orderByDesc('id')->limit(200)->get();
        $conditions = DischargeSummary::CONDITION_OPTIONS;
        $preselectedAdmission = $request->input('admission_id');

        return view('medical.records.discharge-summaries.create', compact(
            'summaryNumber', 'admissions', 'conditions', 'preselectedAdmission'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'admission_id' => 'required|exists:admissions,id',
            'admission_diagnosis' => 'required|string',
            'final_diagnosis' => 'nullable|string',
            'hospital_course' => 'required|string',
            'procedures_done' => 'nullable|string',
            'investigations_summary' => 'nullable|string',
            'treatment_given' => 'nullable|string',
            'discharge_medications' => 'required|string',
            'discharge_instructions' => 'required|string',
            'diet_instructions' => 'nullable|string',
            'activity_restrictions' => 'nullable|string',
            'condition_on_discharge' => 'required|string|in:' . implode(',', array_keys(DischargeSummary::CONDITION_OPTIONS)),
            'discharge_date' => 'required|date',
            'follow_up_date' => 'nullable|date|after_or_equal:discharge_date',
            'follow_up_instructions' => 'nullable|string',
            'follow_up_department' => 'nullable|string|max:100',
        ]);

        $instituteId = $this->instituteId();
        $admission = Admission::findOrFail($request->admission_id);
        $this->ensureSameInstitute($admission, 'admission');

        if (DischargeSummary::where('institute_id', $instituteId)->where('admission_id', $admission->id)->exists()) {
            return back()->withErrors(['admission_id' => 'A discharge summary already exists for this admission.'])->withInput();
        }

        $los = DischargeSummary::computeLos(
            $admission->admission_date->toDateString(),
            $request->discharge_date
        );

        $summary = DischargeSummary::create([
            'institute_id' => $instituteId,
            'branch_id' => $this->resolveBranchId($request->branch_id ?? $admission->branch_id),
            'summary_number' => $this->sequences->next(NumberSequence::TYPE_DISCHARGE_SUMMARY, $instituteId),
            'admission_id' => $admission->id,
            'patient_id' => $admission->patient_id,
            'prepared_by' => auth()->id(),
            'admission_date' => $admission->admission_date,
            'discharge_date' => $request->discharge_date,
            'length_of_stay_days' => $los,
            'admission_diagnosis' => $request->admission_diagnosis,
            'final_diagnosis' => $request->final_diagnosis,
            'hospital_course' => $request->hospital_course,
            'procedures_done' => $request->procedures_done,
            'investigations_summary' => $request->investigations_summary,
            'treatment_given' => $request->treatment_given,
            'discharge_medications' => $request->discharge_medications,
            'discharge_instructions' => $request->discharge_instructions,
            'diet_instructions' => $request->diet_instructions,
            'activity_restrictions' => $request->activity_restrictions,
            'condition_on_discharge' => $request->condition_on_discharge,
            'follow_up_date' => $request->follow_up_date,
            'follow_up_instructions' => $request->follow_up_instructions,
            'follow_up_department' => $request->follow_up_department,
        ]);

        ClinicalAuditLog::record($summary, 'created');

        $this->timeline->recordEvent([
            'institute_id' => $instituteId,
            'branch_id' => $summary->branch_id,
            'patient_id' => $summary->patient_id,
            'event_type' => 'discharge',
            'title' => "Discharge Summary {$summary->summary_number}",
            'description' => $summary->final_diagnosis,
            'event_at' => now(),
            'event_date' => $summary->discharge_date,
            'source_type' => DischargeSummary::class,
            'source_id' => $summary->id,
            'icon' => 'bi-box-arrow-right',
        ]);

        return redirect()
            ->route('medical.records.discharge-summaries.show', $summary)
            ->with('status', 'Discharge summary created: ' . $summary->summary_number);
    }

    public function show(DischargeSummary $dischargeSummary)
    {
        $this->ensureSameInstitute($dischargeSummary, 'discharge_summary');
        $this->ensureBranchAccess($dischargeSummary, 'branch_id', 'discharge_summary');
        $dischargeSummary->load(['patient', 'admission', 'preparedBy', 'document']);

        return view('medical.records.discharge-summaries.show', ['summary' => $dischargeSummary]);
    }

    public function edit(DischargeSummary $dischargeSummary)
    {
        $this->ensureSameInstitute($dischargeSummary, 'discharge_summary');
        $this->ensureBranchAccess($dischargeSummary, 'branch_id', 'discharge_summary');

        return view('medical.records.discharge-summaries.edit', [
            'summary' => $dischargeSummary,
            'conditions' => DischargeSummary::CONDITION_OPTIONS,
        ]);
    }

    public function update(Request $request, DischargeSummary $dischargeSummary)
    {
        $this->ensureSameInstitute($dischargeSummary, 'discharge_summary');
        $this->ensureBranchAccess($dischargeSummary, 'branch_id', 'discharge_summary');

        $request->validate([
            'final_diagnosis' => 'nullable|string',
            'hospital_course' => 'required|string',
            'discharge_medications' => 'required|string',
            'discharge_instructions' => 'required|string',
            'condition_on_discharge' => 'required|string|in:' . implode(',', array_keys(DischargeSummary::CONDITION_OPTIONS)),
            'follow_up_date' => 'nullable|date',
        ]);

        $original = ClinicalAuditLog::snapshot($dischargeSummary);
        $dischargeSummary->update($request->only([
            'final_diagnosis', 'hospital_course', 'procedures_done',
            'investigations_summary', 'treatment_given', 'discharge_medications',
            'discharge_instructions', 'diet_instructions', 'activity_restrictions',
            'condition_on_discharge', 'follow_up_date', 'follow_up_instructions',
            'follow_up_department',
        ]));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($dischargeSummary->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($dischargeSummary, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.records.discharge-summaries.show', $dischargeSummary)
            ->with('status', 'Discharge summary updated.');
    }

    public function destroy(DischargeSummary $dischargeSummary)
    {
        $this->ensureSameInstitute($dischargeSummary, 'discharge_summary');
        $this->ensureBranchAccess($dischargeSummary, 'branch_id', 'discharge_summary');

        ClinicalAuditLog::record($dischargeSummary, 'deleted');
        $dischargeSummary->delete();

        return redirect()
            ->route('medical.records.discharge-summaries.index')
            ->with('status', 'Discharge summary deleted.');
    }

    public function pdf(DischargeSummary $dischargeSummary)
    {
        $this->ensureSameInstitute($dischargeSummary, 'discharge_summary');
        $dischargeSummary->load(['patient', 'admission', 'preparedBy']);

        return view('medical.records.discharge-summaries.pdf', ['summary' => $dischargeSummary]);
    }
}
