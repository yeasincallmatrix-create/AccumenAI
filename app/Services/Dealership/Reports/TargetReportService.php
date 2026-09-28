<?php

namespace App\Services\Dealership\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Target vs achievement — joins sr_targets with sr_orders aggregates.
 */
class TargetReportService extends BaseReportService
{
    public function calculate(array $filters, int $instituteId): array
    {
        return $this->cached('target_vs_achievement', $filters, $instituteId, function () use ($filters, $instituteId) {
            $targets = DB::table('dealership_sr_targets')->where('institute_id', $instituteId);

            if (! empty($filters['sr'])) {
                $targets->where('sales_force_id', $filters['sr']);
            }
            if (! empty($filters['period'])) {
                $targets->where('period_type', $filters['period']);
            }

            $rows = $targets->orderBy('sales_force_id')->orderBy('period_start')->get();

            $out = [];
            $totalTarget = 0;
            $totalAchieved = 0;
            foreach ($rows as $t) {
                $achieved = (float) DB::table('dealership_sr_orders')
                    ->where('institute_id', $instituteId)
                    ->where('sales_force_id', $t->sales_force_id)
                    ->whereIn('status', ['approved', 'delivered'])
                    ->whereDate('created_at', '>=', $t->period_start)
                    ->whereDate('created_at', '<=', $t->period_end)
                    ->sum('total');

                $target = (float) $t->target_amount;
                $variance = round($achieved - $target, 2);
                $pct = $target > 0 ? round($achieved / $target * 100, 2) : 0;

                $out[] = [
                    'sales_force_id' => (int) $t->sales_force_id,
                    'period_type' => $t->period_type,
                    'period_start' => $t->period_start,
                    'period_end' => $t->period_end,
                    'target' => $target,
                    'achieved' => $achieved,
                    'variance' => $variance,
                    'achievement_pct' => $pct,
                ];
                $totalTarget += $target;
                $totalAchieved += $achieved;
            }

            return [
                'rows' => $out,
                'totals' => [
                    'target' => round($totalTarget, 2),
                    'achieved' => round($totalAchieved, 2),
                    'variance' => round($totalAchieved - $totalTarget, 2),
                ],
            ];
        });
    }
}
