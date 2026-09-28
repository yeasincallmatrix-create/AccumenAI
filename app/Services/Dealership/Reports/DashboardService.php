<?php

namespace App\Services\Dealership\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Dashboard KPI rollup built on the four report services.
 */
class DashboardService extends BaseReportService
{
    public function __construct(
        private readonly SrSalesReportService $srSales,
        private readonly CollectionReportService $collections,
        private readonly TargetReportService $targets,
        private readonly SalesReportService $sales,
    ) {}

    public function calculate(array $filters, int $instituteId): array
    {
        return $this->cached('dashboard_kpis', $filters, $instituteId, function () use ($filters, $instituteId) {
            $sr = $this->srSales->calculate($filters, $instituteId);
            $coll = $this->collections->calculate($filters, $instituteId);
            $tgt = $this->targets->calculate($filters, $instituteId);

            $collectedTotal = 0;
            foreach ($coll['by_sr'] as $row) {
                $collectedTotal += $row['amount'];
            }

            $pendingCommission = (float) DB::table('dealership_sr_commission')
                ->where('institute_id', $instituteId)
                ->where('status', 'pending')
                ->sum('commission_amount');

            $presentToday = DB::table('dealership_attendance')
                ->where('institute_id', $instituteId)
                ->whereDate('attendance_date', today()->toDateString())
                ->where('status', 'present')
                ->count();

            $salesTotal = $sr['totals']['amount'] ?? 0;
            $collectionRate = $salesTotal > 0 ? round($collectedTotal / $salesTotal * 100, 2) : 0;

            return [
                'total_orders' => $sr['totals']['orders'] ?? 0,
                'total_sales' => $salesTotal,
                'total_collected' => round($collectedTotal, 2),
                'collection_rate' => $collectionRate,
                'pending_commission' => round($pendingCommission, 2),
                'target_total' => $tgt['totals']['target'] ?? 0,
                'achieved_total' => $tgt['totals']['achieved'] ?? 0,
                'present_today' => $presentToday,
            ];
        });
    }
}
