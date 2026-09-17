<?php

namespace App\Services\Medical;

use App\Models\Medical\DietPlan;
use App\Models\Medical\MealSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DietService
{
    /**
     * Generate meal schedules for a diet plan.
     */
    public function generateMealSchedules(DietPlan $plan): int
    {
        $meals = MealSchedule::MEAL_TIMES;

        $count = 0;
        $start = $plan->start_date->copy();
        $end = $plan->end_date?->copy()
            ?? $plan->start_date->copy()->addDays(max(0, ($plan->days_planned ?? 7) - 1));

        DB::transaction(function () use ($plan, $meals, $start, $end, &$count) {
            $current = $start->copy();
            while ($current <= $end) {
                foreach ($meals as $type => $time) {
                    $created = MealSchedule::firstOrCreate(
                        [
                            'diet_plan_id' => $plan->id,
                            'meal_date' => $current->toDateString(),
                            'meal_type' => $type,
                        ],
                        [
                            'institute_id' => $plan->institute_id,
                            'branch_id' => $plan->branch_id,
                            'scheduled_time' => $time,
                            'menu_items' => 'Pending selection',
                            'status' => 'scheduled',
                        ]
                    );
                    if ($created->wasRecentlyCreated) {
                        $count++;
                    }
                }
                $current->addDay();
            }
        });

        return $count;
    }

    /**
     * Get today's kitchen orders for an institute.
     */
    public function getTodayKitchenOrders(int $instituteId, ?int $branchId = null): Collection
    {
        return MealSchedule::forInstitute($instituteId)
            ->when($branchId, fn ($q) => $q->where('meal_schedules.branch_id', $branchId))
            ->whereDate('meal_schedules.meal_date', today())
            ->whereIn('meal_schedules.status', ['scheduled', 'prepared'])
            ->with(['dietPlan.patient'])
            ->orderBy('meal_schedules.scheduled_time')
            ->get();
    }

    /**
     * Calculate adherence percentage.
     */
    public function adherencePercent(DietPlan $plan): int
    {
        $total = $plan->mealSchedules()->count();
        if ($total === 0) {
            return 0;
        }
        $served = $plan->mealSchedules()->where('meal_schedules.status', 'served')->count();

        return (int) round(($served / $total) * 100);
    }

    /**
     * Daily calorie progress for a plan on a given date.
     *
     * @return array{target: ?int, actual: int, percent: int}
     */
    public function dailyCalorieProgress(DietPlan $plan, $date = null): array
    {
        $date ??= today();
        $actual = (int) $plan->mealSchedules()
            ->whereDate('meal_schedules.meal_date', $date)
            ->whereIn('meal_schedules.status', ['served', 'prepared'])
            ->sum('meal_schedules.calories');

        $target = $plan->daily_calories;

        return [
            'target' => $target,
            'actual' => $actual,
            'percent' => $target ? (int) round(($actual / $target) * 100) : 0,
        ];
    }
}
