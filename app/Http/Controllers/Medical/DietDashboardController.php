<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\DietPlan;
use App\Models\Medical\MealSchedule;
use App\Services\Medical\DietService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DietDashboardController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.diet.view', only: ['index', 'kitchenToday']),
        ];
    }

    public function __construct(
        private readonly DietService $dietService,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $planQuery = DietPlan::forInstitute($instituteId);
        $this->scopeBranch($planQuery);

        $stats = [
            'active_plans' => (clone $planQuery)->where('diet_plans.status', 'active')->count(),
            'on_hold' => (clone $planQuery)->where('diet_plans.status', 'on_hold')->count(),
            'meals_today' => MealSchedule::where('meal_schedules.institute_id', $instituteId)->today()->count(),
            'served_today' => MealSchedule::where('meal_schedules.institute_id', $instituteId)->today()->served()->count(),
        ];

        $todayMeals = MealSchedule::where('meal_schedules.institute_id', $instituteId)
            ->today()
            ->with(['dietPlan.patient'])
            ->orderBy('meal_schedules.scheduled_time')
            ->limit(15)
            ->get();

        $activePlans = (clone $planQuery)->with(['patient', 'prescribedBy'])
            ->where('diet_plans.status', 'active')
            ->orderByDesc('diet_plans.start_date')
            ->limit(10)
            ->get();

        return view('medical.diet.dashboard', compact('stats', 'todayMeals', 'activePlans'));
    }

    public function kitchenToday(Request $request)
    {
        $instituteId = $this->instituteId();
        $branchId = $this->branchContextId();

        $orders = $this->dietService->getTodayKitchenOrders($instituteId, $branchId);

        return view('medical.diet.kitchen-today', compact('orders'));
    }
}
