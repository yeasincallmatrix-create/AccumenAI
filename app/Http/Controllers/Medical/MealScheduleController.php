<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\DietPlan;
use App\Models\Medical\MealSchedule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class MealScheduleController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.diet.view', only: ['index', 'show']),
            new Middleware('permission:medical.diet.meal.serve', only: ['create', 'store', 'edit', 'update', 'destroy', 'markPrepared', 'markServed', 'markRefused']),
        ];
    }

    public function index(Request $request, DietPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'diet_plan');

        $query = $plan->mealSchedules();
        if ($request->filled('meal_date')) {
            $query->whereDate('meal_schedules.meal_date', $request->meal_date);
        }
        if ($request->filled('status')) {
            $query->where('meal_schedules.status', $request->status);
        }

        $meals = $query->orderBy('meal_schedules.meal_date')
            ->orderBy('meal_schedules.scheduled_time')
            ->paginate(25)->withQueryString();

        return view('medical.diet.meals.index', compact('plan', 'meals'));
    }

    public function create(DietPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'diet_plan');
        $types = MealSchedule::MEAL_TYPES;

        return view('medical.diet.meals.create', compact('plan', 'types'));
    }

    public function store(Request $request, DietPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'diet_plan');

        $request->validate([
            'meal_date' => 'required|date',
            'meal_type' => 'required|string|in:' . implode(',', array_keys(MealSchedule::MEAL_TYPES)),
            'scheduled_time' => 'required',
            'menu_items' => 'required|string',
            'calories' => 'nullable|integer|min:0',
        ]);

        $meal = MealSchedule::create([
            'diet_plan_id' => $plan->id,
            'institute_id' => $plan->institute_id,
            'branch_id' => $plan->branch_id,
            'meal_date' => $request->meal_date,
            'meal_type' => $request->meal_type,
            'scheduled_time' => $request->scheduled_time,
            'menu_items' => $request->menu_items,
            'calories' => $request->calories,
            'status' => 'scheduled',
        ]);

        ClinicalAuditLog::record($meal, 'created');

        return redirect()
            ->route('medical.diet.plans.meals.show', [$plan, $meal])
            ->with('status', 'Meal scheduled.');
    }

    public function show(DietPlan $plan, MealSchedule $meal)
    {
        $this->ensureSameInstitute($plan, 'diet_plan');
        $this->ensureMealBelongs($plan, $meal);
        $meal->load(['dietPlan.patient', 'preparedBy', 'servedBy']);

        return view('medical.diet.meals.show', compact('plan', 'meal'));
    }

    public function edit(DietPlan $plan, MealSchedule $meal)
    {
        $this->ensureSameInstitute($plan, 'diet_plan');
        $this->ensureMealBelongs($plan, $meal);

        return view('medical.diet.meals.edit', [
            'plan' => $plan,
            'meal' => $meal,
            'types' => MealSchedule::MEAL_TYPES,
            'statuses' => MealSchedule::STATUSES,
        ]);
    }

    public function update(Request $request, DietPlan $plan, MealSchedule $meal)
    {
        $this->ensureSameInstitute($plan, 'diet_plan');
        $this->ensureMealBelongs($plan, $meal);

        $request->validate([
            'meal_date' => 'required|date',
            'meal_type' => 'required|string|in:' . implode(',', array_keys(MealSchedule::MEAL_TYPES)),
            'scheduled_time' => 'required',
            'menu_items' => 'required|string',
            'calories' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $original = ClinicalAuditLog::snapshot($meal);
        $meal->update($request->only(['meal_date', 'meal_type', 'scheduled_time', 'menu_items', 'calories', 'notes']));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($meal->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($meal, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.diet.plans.meals.show', [$plan, $meal])
            ->with('status', 'Meal updated.');
    }

    public function destroy(DietPlan $plan, MealSchedule $meal)
    {
        $this->ensureSameInstitute($plan, 'diet_plan');
        $this->ensureMealBelongs($plan, $meal);

        ClinicalAuditLog::record($meal, 'deleted');
        $meal->delete();

        return redirect()
            ->route('medical.diet.plans.show', $plan)
            ->with('status', 'Meal removed.');
    }

    public function markPrepared(MealSchedule $meal)
    {
        $this->ensureSameInstitute($meal, 'meal');
        $original = ClinicalAuditLog::snapshot($meal);
        $meal->markPrepared(auth()->id());

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($meal->refresh()));
        ClinicalAuditLog::record($meal, 'prepared', ['old' => $old, 'new' => $new]);

        return back()->with('status', 'Meal marked as prepared.');
    }

    public function markServed(MealSchedule $meal)
    {
        $this->ensureSameInstitute($meal, 'meal');
        $original = ClinicalAuditLog::snapshot($meal);
        $meal->markServed(auth()->id());

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($meal->refresh()));
        ClinicalAuditLog::record($meal, 'served', ['old' => $old, 'new' => $new]);

        return back()->with('status', 'Meal marked as served.');
    }

    public function markRefused(Request $request, MealSchedule $meal)
    {
        $this->ensureSameInstitute($meal, 'meal');
        $request->validate(['notes' => 'nullable|string|max:2000']);

        $original = ClinicalAuditLog::snapshot($meal);
        $meal->markRefused($request->notes);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($meal->refresh()));
        ClinicalAuditLog::record($meal, 'refused', ['old' => $old, 'new' => $new]);

        return back()->with('status', 'Meal marked as refused.');
    }

    private function ensureMealBelongs(DietPlan $plan, MealSchedule $meal): void
    {
        if ((int) $meal->diet_plan_id !== (int) $plan->id) {
            abort(404);
        }
        $this->ensureSameInstitute($meal, 'meal');
    }
}
