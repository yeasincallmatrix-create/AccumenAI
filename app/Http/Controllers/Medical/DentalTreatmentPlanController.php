<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\DentalTreatmentPlan;
use App\Models\Medical\NumberSequence;
use App\Models\User;
use App\Services\Medical\DentalService;
use App\Services\Medical\NumberSequenceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DentalTreatmentPlanController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.dental.view', only: ['index', 'show']),
            new Middleware('permission:medical.dental.plan.manage', only: ['create', 'store', 'edit', 'update', 'destroy', 'completeStep', 'discontinue']),
        ];
    }

    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly DentalService $dentalService,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = DentalTreatmentPlan::where('institute_id', $instituteId);
        $this->scopeBranch($query);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('plan_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($pq) => $pq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $plans = $query->with(['patient', 'dentist'])->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('medical.dental.plans.index', compact('plans'));
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $planNumber = $this->sequences->peek(NumberSequence::TYPE_DENTAL_PLAN, $instituteId);
        $patients = \App\Models\Medical\Patient::where('institute_id', $instituteId)->active()->patients()->orderBy('first_name')->get();
        $dentists = User::where('status', 'active')->orderBy('name')->get();
        $statuses = DentalTreatmentPlan::STATUSES;

        return view('medical.dental.plans.create', compact('planNumber', 'patients', 'dentists', 'statuses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'dentist_id' => 'required|exists:users,id',
            'chief_complaint' => 'required|string|max:2000',
            'diagnosis' => 'nullable|string|max:2000',
            'treatment_summary' => 'nullable|string|max:2000',
            'planned_steps' => 'nullable|array',
            'planned_steps.*.procedure' => 'required_with:planned_steps|string|max:200',
            'planned_steps.*.tooth' => 'nullable|string|max:10',
            'planned_steps.*.estimated_fee' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'expected_end_date' => 'nullable|date|after_or_equal:start_date',
            'total_estimated_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $instituteId = $this->instituteId();
        $data = $request->all();
        $data['institute_id'] = $instituteId;
        $data['branch_id'] = $this->resolveBranchId($request->branch_id);
        $data['plan_number'] = $this->sequences->next(NumberSequence::TYPE_DENTAL_PLAN, $instituteId);
        $data['status'] = 'active';

        $steps = $data['planned_steps'] ?? [];
        foreach ($steps as &$step) {
            $step['status'] = 'pending';
        }
        unset($step);
        $data['planned_steps'] = $steps;
        $data['total_steps'] = count($steps);
        $data['completed_steps'] = 0;

        $plan = DentalTreatmentPlan::create($data);

        ClinicalAuditLog::record($plan, 'created');

        return redirect()
            ->route('medical.dental.plans.show', $plan)
            ->with('status', 'Treatment plan created: ' . $plan->plan_number);
    }

    public function show(DentalTreatmentPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'dental_treatment_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'dental_treatment_plan');
        $plan->load(['patient', 'dentist']);

        return view('medical.dental.plans.show', ['plan' => $plan]);
    }

    public function edit(DentalTreatmentPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'dental_treatment_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'dental_treatment_plan');

        $patients = \App\Models\Medical\Patient::where('institute_id', $plan->institute_id)->active()->patients()->orderBy('first_name')->get();
        $dentists = User::where('status', 'active')->orderBy('name')->get();
        $statuses = DentalTreatmentPlan::STATUSES;

        return view('medical.dental.plans.edit', [
            'plan' => $plan,
            'patients' => $patients,
            'dentists' => $dentists,
            'statuses' => $statuses,
        ]);
    }

    public function update(Request $request, DentalTreatmentPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'dental_treatment_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'dental_treatment_plan');

        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'dentist_id' => 'required|exists:users,id',
            'chief_complaint' => 'required|string|max:2000',
            'diagnosis' => 'nullable|string|max:2000',
            'treatment_summary' => 'nullable|string|max:2000',
            'planned_steps' => 'nullable|array',
            'planned_steps.*.procedure' => 'required_with:planned_steps|string|max:200',
            'planned_steps.*.tooth' => 'nullable|string|max:10',
            'planned_steps.*.estimated_fee' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'expected_end_date' => 'nullable|date|after_or_equal:start_date',
            'total_estimated_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'status' => 'nullable|string|in:' . implode(',', array_keys(DentalTreatmentPlan::STATUSES)),
        ]);

        $original = ClinicalAuditLog::snapshot($plan);

        $steps = $request->planned_steps ?? $plan->planned_steps ?? [];
        foreach ($steps as &$step) {
            if (! isset($step['status'])) {
                $step['status'] = 'pending';
            }
        }
        unset($step);
        $completedCount = collect($steps)->where('status', 'completed')->count();

        $plan->update(array_merge(
            $request->only([
                'patient_id', 'dentist_id', 'chief_complaint', 'diagnosis',
                'treatment_summary', 'start_date', 'expected_end_date',
                'total_estimated_fee', 'notes', 'status',
            ]),
            [
                'planned_steps' => $steps,
                'total_steps' => count($steps),
                'completed_steps' => $completedCount,
            ]
        ));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($plan->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($plan, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.dental.plans.show', $plan)
            ->with('status', 'Treatment plan updated.');
    }

    public function destroy(DentalTreatmentPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'dental_treatment_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'dental_treatment_plan');

        if (! in_array($plan->status, ['active', 'on_hold'])) {
            return redirect()->back()->with('error', 'Only active or on-hold plans can be deleted.');
        }

        ClinicalAuditLog::record($plan, 'deleted');
        $plan->delete();

        return redirect()
            ->route('medical.dental.plans.index')
            ->with('status', 'Treatment plan deleted.');
    }

    public function completeStep(DentalTreatmentPlan $plan, int $step)
    {
        $this->ensureSameInstitute($plan, 'dental_treatment_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'dental_treatment_plan');

        $this->dentalService->completeStep($plan, $step);

        return redirect()
            ->route('medical.dental.plans.show', $plan)
            ->with('status', 'Step marked as completed.');
    }

    public function discontinue(Request $request, DentalTreatmentPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'dental_treatment_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'dental_treatment_plan');

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->dentalService->discontinuePlan($plan, $request->reason);

        return redirect()
            ->route('medical.dental.plans.show', $plan)
            ->with('status', 'Treatment plan discontinued.');
    }
}
