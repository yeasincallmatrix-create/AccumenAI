<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\PhysiotherapyPlan;
use App\Models\Medical\PhysiotherapySession;
use App\Models\User;
use App\Services\Medical\NumberSequenceService;
use App\Services\Medical\PhysiotherapyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PhysiotherapyPlanController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.physiotherapy.view', only: ['index', 'show']),
            new Middleware('permission:medical.physiotherapy.plan.create', only: ['create', 'store']),
            new Middleware('permission:medical.physiotherapy.plan.edit', only: ['edit', 'update', 'complete', 'discontinue']),
            new Middleware('permission:medical.physiotherapy.plan.create', only: ['destroy']),
        ];
    }

    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly PhysiotherapyService $physioService,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = PhysiotherapyPlan::where('institute_id', $instituteId);
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

        $plans = $query->with(['patient', 'therapist'])->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('medical.physiotherapy.plans.index', compact('plans'));
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $planNumber = $this->sequences->peek(NumberSequence::TYPE_PHYSIO_PLAN, $instituteId);
        $patients = \App\Models\Medical\Patient::where('institute_id', $instituteId)->active()->patients()->orderBy('first_name')->get();
        $therapists = User::where('status', 'active')->orderBy('name')->get();
        $frequencies = PhysiotherapyPlan::FREQUENCIES;
        $modalities = PhysiotherapyPlan::MODALITIES;

        return view('medical.physiotherapy.plans.create', compact('planNumber', 'patients', 'therapists', 'frequencies', 'modalities'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'therapist_id' => 'required|exists:users,id',
            'referring_doctor_id' => 'nullable|exists:users,id',
            'chief_complaint' => 'nullable|string|max:1000',
            'assessment' => 'nullable|string',
            'diagnosis' => 'nullable|string|max:1000',
            'treatment_goals' => 'nullable|string|max:1000',
            'pain_score_initial' => 'nullable|integer|min:0|max:10',
            'modality' => 'required|string|in:' . implode(',', array_keys(PhysiotherapyPlan::MODALITIES)),
            'sessions_planned' => 'required|integer|min:1|max:200',
            'frequency' => 'required|string|in:' . implode(',', array_keys(PhysiotherapyPlan::FREQUENCIES)),
            'start_date' => 'required|date',
            'expected_end_date' => 'nullable|date|after_or_equal:start_date',
            'fee_per_session' => 'nullable|numeric|min:0',
        ]);

        $instituteId = $this->instituteId();
        $data = $request->all();
        $data['institute_id'] = $instituteId;
        $data['branch_id'] = $request->branch_id ?? $this->branchContextId();
        $data['plan_number'] = $this->sequences->next(NumberSequence::TYPE_PHYSIO_PLAN, $instituteId);
        $data['status'] = 'active';
        $data['sessions_completed'] = 0;
        $data['fee_per_session'] = $data['fee_per_session'] ?? 0;
        $data['total_fee'] = ($data['fee_per_session'] ?? 0) * $data['sessions_planned'];
        $data['payment_status'] = 'pending';
        $data['referring_doctor_id'] = $data['referring_doctor_id'] ?? null;
        $data['expected_end_date'] = $data['expected_end_date'] ?? null;

        $plan = PhysiotherapyPlan::create($data);

        ClinicalAuditLog::record($plan, 'created');

        return redirect()
            ->route('medical.physiotherapy.plans.show', $plan)
            ->with('status', 'Physiotherapy plan created: ' . $plan->plan_number);
    }

    public function show(PhysiotherapyPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'physiotherapy_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'physiotherapy_plan');
        $plan->load(['patient', 'therapist', 'referringDoctor', 'sessions' => function ($q) {
            $q->orderBy('session_order');
        }]);

        return view('medical.physiotherapy.plans.show', ['plan' => $plan]);
    }

    public function edit(PhysiotherapyPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'physiotherapy_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'physiotherapy_plan');

        $patients = \App\Models\Medical\Patient::where('institute_id', $plan->institute_id)->active()->patients()->orderBy('first_name')->get();
        $therapists = User::where('status', 'active')->orderBy('name')->get();
        $frequencies = PhysiotherapyPlan::FREQUENCIES;
        $modalities = PhysiotherapyPlan::MODALITIES;
        $statuses = PhysiotherapyPlan::STATUSES;

        return view('medical.physiotherapy.plans.edit', [
            'plan' => $plan,
            'patients' => $patients,
            'therapists' => $therapists,
            'frequencies' => $frequencies,
            'modalities' => $modalities,
            'statuses' => $statuses,
        ]);
    }

    public function update(Request $request, PhysiotherapyPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'physiotherapy_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'physiotherapy_plan');

        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'therapist_id' => 'required|exists:users,id',
            'referring_doctor_id' => 'nullable|exists:users,id',
            'chief_complaint' => 'nullable|string|max:1000',
            'assessment' => 'nullable|string',
            'diagnosis' => 'nullable|string|max:1000',
            'treatment_goals' => 'nullable|string|max:1000',
            'pain_score_initial' => 'nullable|integer|min:0|max:10',
            'modality' => 'required|string|in:' . implode(',', array_keys(PhysiotherapyPlan::MODALITIES)),
            'sessions_planned' => 'required|integer|min:1|max:200',
            'frequency' => 'required|string|in:' . implode(',', array_keys(PhysiotherapyPlan::FREQUENCIES)),
            'start_date' => 'required|date',
            'expected_end_date' => 'nullable|date|after_or_equal:start_date',
            'fee_per_session' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:' . implode(',', array_keys(PhysiotherapyPlan::STATUSES)),
        ]);

        $original = ClinicalAuditLog::snapshot($plan);

        $plan->update($request->only([
            'patient_id', 'therapist_id', 'referring_doctor_id',
            'chief_complaint', 'assessment', 'diagnosis', 'treatment_goals',
            'pain_score_initial', 'modality', 'sessions_planned', 'frequency',
            'start_date', 'expected_end_date', 'fee_per_session', 'status',
        ]));

        if ($request->filled('fee_per_session')) {
            $plan->update(['total_fee' => $plan->fee_per_session * $plan->sessions_planned]);
        }

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($plan->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($plan, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.physiotherapy.plans.show', $plan)
            ->with('status', 'Physiotherapy plan updated.');
    }

    public function destroy(PhysiotherapyPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'physiotherapy_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'physiotherapy_plan');

        if (! in_array($plan->status, ['active', 'on_hold'])) {
            return redirect()->back()->with('error', 'Only active or on-hold plans can be deleted.');
        }

        ClinicalAuditLog::record($plan, 'deleted');

        $plan->delete();

        return redirect()
            ->route('medical.physiotherapy.plans.index')
            ->with('status', 'Physiotherapy plan deleted.');
    }

    public function complete(PhysiotherapyPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'physiotherapy_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'physiotherapy_plan');

        $this->physioService->completePlan($plan);

        return redirect()
            ->route('medical.physiotherapy.plans.show', $plan)
            ->with('status', 'Physiotherapy plan marked as completed.');
    }

    public function discontinue(Request $request, PhysiotherapyPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'physiotherapy_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'physiotherapy_plan');

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->physioService->discontinuePlan($plan, $request->reason);

        return redirect()
            ->route('medical.physiotherapy.plans.show', $plan)
            ->with('status', 'Physiotherapy plan discontinued.');
    }
}
