<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\DietPlan;
use App\Models\Medical\DietTemplate;
use App\Models\Medical\NumberSequence;
use App\Services\Medical\DietService;
use App\Services\Medical\NumberSequenceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DietPlanController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.diet.view', only: ['index', 'show']),
            new Middleware('permission:medical.diet.plan.create', only: ['create', 'store']),
            new Middleware('permission:medical.diet.plan.edit', only: ['edit', 'update', 'destroy', 'discontinue', 'generateMeals']),
        ];
    }

    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly DietService $dietService,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = DietPlan::forInstitute($instituteId);
        $this->scopeBranch($query);

        if ($request->filled('status')) {
            $query->where('diet_plans.status', $request->status);
        }
        if ($request->filled('diet_type')) {
            $query->where('diet_plans.diet_type', $request->diet_type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('diet_plans.plan_number', 'like', "%{$search}%")
                    ->orWhere('diet_plans.plan_name', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($pq) => $pq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $plans = $query->with(['patient', 'prescribedBy'])
            ->orderByDesc('diet_plans.start_date')->paginate(25)->withQueryString();
        $types = DietPlan::DIET_TYPES;
        $statuses = DietPlan::STATUSES;

        return view('medical.diet.plans.index', compact('plans', 'types', 'statuses'));
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $planNumber = $this->sequences->peek(NumberSequence::TYPE_DIET_PLAN, $instituteId);
        $patients = \App\Models\Medical\Patient::where('institute_id', $instituteId)->active()->patients()->orderBy('first_name')->get();
        $types = DietPlan::DIET_TYPES;
        $templates = DietTemplate::forInstitute($instituteId)->active()->orderBy('name')->get();

        return view('medical.diet.plans.create', compact('planNumber', 'patients', 'types', 'templates'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'plan_name' => 'required|string|max:200',
            'diet_type' => 'required|string|in:' . implode(',', array_keys(DietPlan::DIET_TYPES)),
            'restrictions' => 'nullable|string',
            'medical_notes' => 'nullable|string',
            'daily_calories' => 'nullable|integer|min:0|max:10000',
            'protein_grams' => 'nullable|numeric|min:0',
            'carbs_grams' => 'nullable|numeric|min:0',
            'fat_grams' => 'nullable|numeric|min:0',
            'sodium_mg' => 'nullable|numeric|min:0',
            'potassium_mg' => 'nullable|numeric|min:0',
            'fluid_ml' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'days_planned' => 'nullable|integer|min:1|max:365',
            'template_id' => 'nullable|exists:diet_templates,id',
        ]);

        $instituteId = $this->instituteId();
        $patient = \App\Models\Medical\Patient::findOrFail($request->patient_id);
        $this->ensureSameInstitute($patient, 'patient');

        $plan = DietPlan::create([
            'institute_id' => $instituteId,
            'branch_id' => $this->resolveBranchId($request->branch_id),
            'plan_number' => $this->sequences->next(NumberSequence::TYPE_DIET_PLAN, $instituteId),
            'patient_id' => $patient->id,
            'prescribed_by' => auth()->id(),
            'admission_id' => $request->admission_id,
            'plan_name' => $request->plan_name,
            'diet_type' => $request->diet_type,
            'restrictions' => $request->restrictions,
            'medical_notes' => $request->medical_notes,
            'daily_calories' => $request->daily_calories,
            'protein_grams' => $request->protein_grams,
            'carbs_grams' => $request->carbs_grams,
            'fat_grams' => $request->fat_grams,
            'sodium_mg' => $request->sodium_mg,
            'potassium_mg' => $request->potassium_mg,
            'fluid_ml' => $request->fluid_ml,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'days_planned' => $request->days_planned,
            'status' => 'active',
        ]);

        ClinicalAuditLog::record($plan, 'created');

        $generated = $this->dietService->generateMealSchedules($plan);

        return redirect()
            ->route('medical.diet.plans.show', $plan)
            ->with('status', "Diet plan created: {$plan->plan_number} ({$generated} meals scheduled).");
    }

    public function show(DietPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'diet_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'diet_plan');
        $plan->load(['patient', 'prescribedBy', 'admission']);

        $meals = $plan->mealSchedules()->orderBy('meal_date')->orderBy('scheduled_time')->get();
        $adherence = $this->dietService->adherencePercent($plan);
        $calories = $this->dietService->dailyCalorieProgress($plan);

        return view('medical.diet.plans.show', compact('plan', 'meals', 'adherence', 'calories'));
    }

    public function edit(DietPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'diet_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'diet_plan');

        return view('medical.diet.plans.edit', [
            'plan' => $plan,
            'types' => DietPlan::DIET_TYPES,
            'statuses' => DietPlan::STATUSES,
        ]);
    }

    public function update(Request $request, DietPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'diet_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'diet_plan');

        $request->validate([
            'plan_name' => 'required|string|max:200',
            'diet_type' => 'required|string|in:' . implode(',', array_keys(DietPlan::DIET_TYPES)),
            'restrictions' => 'nullable|string',
            'medical_notes' => 'nullable|string',
            'daily_calories' => 'nullable|integer|min:0|max:10000',
            'protein_grams' => 'nullable|numeric|min:0',
            'carbs_grams' => 'nullable|numeric|min:0',
            'fat_grams' => 'nullable|numeric|min:0',
            'sodium_mg' => 'nullable|numeric|min:0',
            'potassium_mg' => 'nullable|numeric|min:0',
            'fluid_ml' => 'nullable|numeric|min:0',
            'end_date' => 'nullable|date',
            'status' => 'nullable|string|in:' . implode(',', array_keys(DietPlan::STATUSES)),
        ]);

        $original = ClinicalAuditLog::snapshot($plan);
        $plan->update($request->only([
            'plan_name', 'diet_type', 'restrictions', 'medical_notes',
            'daily_calories', 'protein_grams', 'carbs_grams', 'fat_grams',
            'sodium_mg', 'potassium_mg', 'fluid_ml', 'end_date', 'status',
        ]));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($plan->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($plan, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.diet.plans.show', $plan)
            ->with('status', 'Diet plan updated.');
    }

    public function destroy(DietPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'diet_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'diet_plan');

        ClinicalAuditLog::record($plan, 'deleted');
        $plan->delete();

        return redirect()
            ->route('medical.diet.plans.index')
            ->with('status', 'Diet plan deleted.');
    }

    public function discontinue(Request $request, DietPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'diet_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'diet_plan');

        $request->validate(['discontinue_reason' => 'required|string|max:2000']);

        $original = ClinicalAuditLog::snapshot($plan);
        $plan->update([
            'status' => 'discontinued',
            'discontinue_reason' => $request->discontinue_reason,
        ]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($plan->refresh()));
        ClinicalAuditLog::record($plan, 'discontinued', ['old' => $old, 'new' => $new, 'reason' => $request->discontinue_reason]);

        return redirect()
            ->route('medical.diet.plans.show', $plan)
            ->with('status', 'Diet plan discontinued.');
    }

    public function generateMeals(DietPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'diet_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'diet_plan');

        $count = $this->dietService->generateMealSchedules($plan);

        return redirect()
            ->route('medical.diet.plans.show', $plan)
            ->with('status', "Meal generation complete: {$count} new meals scheduled.");
    }
}
