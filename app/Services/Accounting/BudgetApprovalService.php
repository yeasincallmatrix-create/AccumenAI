<?php

namespace App\Services\Accounting;

use App\Models\Budget;
use App\Models\BudgetVersion;
use Illuminate\Support\Facades\DB;

class BudgetApprovalService
{
    public function __construct(
        private readonly BudgetService $budgets,
        private readonly AccountingAuditService $audit,
        private readonly AccountingSetupService $settings,
    ) {}

    public function getThresholds(int $instituteId): array
    {
        return $this->settings->getSetting($instituteId, 'budget_thresholds', [
            'warning' => 80,
            'critical' => 100,
            'severe' => 120,
        ]);
    }

    public function setThresholds(int $instituteId, array $thresholds, ?int $actorId = null): void
    {
        $this->settings->setSetting($instituteId, 'budget_thresholds', $thresholds, null, $actorId);
    }

    public function checkAlerts(int $instituteId, ?int $branchId, int $fiscalYearId): array
    {
        $thresholds = $this->getThresholds($instituteId);
        $budget = Budget::where('institute_id', $instituteId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereIn('status', ['approved', 'locked'])
            ->first();

        if (!$budget) {
            return [];
        }

        $comparison = app(BudgetCalculationService::class)
            ->budgetVsActualForBudget($instituteId, $branchId, $budget->id);

        $alerts = [];
        foreach ($comparison['lines'] as $line) {
            if ($line['budget_amount'] <= 0) {
                continue;
            }

            $consumedPct = round((($line['actual_amount'] / $line['budget_amount']) * 100), 2);

            if ($consumedPct >= $thresholds['severe']) {
                $alerts[] = [
                    'level' => 'severe',
                    'account' => $line['name'],
                    'code' => $line['code'],
                    'budget' => $line['budget_amount'],
                    'actual' => $line['actual_amount'],
                    'consumed_pct' => $consumedPct,
                    'message' => "{$line['name']}: Budget exceeded ({$consumedPct}% consumed)",
                ];
            } elseif ($consumedPct >= $thresholds['critical']) {
                $alerts[] = [
                    'level' => 'critical',
                    'account' => $line['name'],
                    'code' => $line['code'],
                    'budget' => $line['budget_amount'],
                    'actual' => $line['actual_amount'],
                    'consumed_pct' => $consumedPct,
                    'message' => "{$line['name']}: 100% budget consumed",
                ];
            } elseif ($consumedPct >= $thresholds['warning']) {
                $alerts[] = [
                    'level' => 'warning',
                    'account' => $line['name'],
                    'code' => $line['code'],
                    'budget' => $line['budget_amount'],
                    'actual' => $line['actual_amount'],
                    'consumed_pct' => $consumedPct,
                    'message' => "{$line['name']}: {$consumedPct}% budget consumed",
                ];
            }
        }

        return collect($alerts)->sortByDesc('consumed_pct')->values()->all();
    }
}
