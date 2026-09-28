<?php

namespace App\Services\Dealership\Reports;

use Illuminate\Support\Facades\DB;

/**
 * SR sales summary — aggregates sr_orders (+ items qty) per SR.
 */
class SrSalesReportService extends BaseReportService
{
    public function calculate(array $filters, int $instituteId): array
    {
        return $this->cached('sr_sales_summary', $filters, $instituteId, function () use ($filters, $instituteId) {
            $orders = DB::table('dealership_sr_orders')->where('institute_id', $instituteId);
            $this->applyDateRange($orders, $filters);

            if (! empty($filters['sr'])) {
                $orders->where('sales_force_id', $filters['sr']);
            }
            if (! empty($filters['status'])) {
                $orders->where('status', $filters['status']);
            }

            $rows = $orders
                ->select(
                    'sales_force_id',
                    DB::raw('COUNT(*) AS orders'),
                    DB::raw('COALESCE(SUM(total), 0) AS amount')
                )
                ->groupBy('sales_force_id')
                ->orderBy('sales_force_id')
                ->get();

            $orderIds = DB::table('dealership_sr_orders')
                ->where('institute_id', $instituteId)
                ->when(! empty($filters['sr']), fn ($q) => $q->where('sales_force_id', $filters['sr']))
                ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
                ->when(! empty($filters['from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
                ->when(! empty($filters['to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
                ->pluck('id');

            $qtyBySr = DB::table('dealership_sr_order_items as i')
                ->join('dealership_sr_orders as o', 'o.id', '=', 'i.sr_order_id')
                ->whereIn('i.sr_order_id', $orderIds->all() ?: [0])
                ->select('o.sales_force_id', DB::raw('COALESCE(SUM(i.qty), 0) AS qty'))
                ->groupBy('o.sales_force_id')
                ->pluck('qty', 'sales_force_id');

            $out = [];
            $totalOrders = 0;
            $totalAmount = 0;
            foreach ($rows as $r) {
                $qty = (float) ($qtyBySr[$r->sales_force_id] ?? 0);
                $out[] = [
                    'sales_force_id' => (int) $r->sales_force_id,
                    'orders' => (int) $r->orders,
                    'qty' => $qty,
                    'amount' => (float) $r->amount,
                ];
                $totalOrders += (int) $r->orders;
                $totalAmount += (float) $r->amount;
            }

            return [
                'rows' => $out,
                'totals' => ['orders' => $totalOrders, 'amount' => round($totalAmount, 2)],
            ];
        });
    }
}
