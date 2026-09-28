<?php

namespace App\Services\Dealership\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Sales report — aggregates order lines by brand / product / channel.
 */
class SalesReportService extends BaseReportService
{
    public function calculate(array $filters, int $instituteId): array
    {
        $groupBy = $filters['group_by'] ?? 'channel';
        if (! in_array($groupBy, ['brand', 'product', 'channel'], true)) {
            $groupBy = 'channel';
        }

        $reportKey = match ($groupBy) {
            'brand' => 'brand_sales_summary',
            'product' => 'product_sales_summary',
            default => 'channel_sales_summary',
        };

        return $this->cached($reportKey, $filters + ['group_by' => $groupBy], $instituteId, function () use ($filters, $groupBy, $instituteId) {
            $items = DB::table('dealership_sr_order_items as i')
                ->join('dealership_sr_orders as o', 'o.id', '=', 'i.sr_order_id')
                ->leftJoin('dealership_products as p', 'p.id', '=', 'i.product_id')
                ->where('o.institute_id', $instituteId);
            $this->applyDateRange($items, $filters, 'o.created_at');

            if (! empty($filters['brand'])) {
                $items->where('p.brand_id', $filters['brand']);
            }
            if (! empty($filters['product'])) {
                $items->where('i.product_id', $filters['product']);
            }
            if (! empty($filters['channel'])) {
                $items->where('o.channel', $filters['channel']);
            }

            [$select, $groupCol, $label] = match ($groupBy) {
                'brand' => ['p.brand_id AS gkey', 'p.brand_id', 'brand'],
                'product' => ['i.product_id AS gkey', 'i.product_id', 'product'],
                default => ['o.channel AS gkey', 'o.channel', 'channel'],
            };

            $rows = $items
                ->select(
                    DB::raw($select),
                    DB::raw('COALESCE(SUM(i.qty), 0) AS qty'),
                    DB::raw('COALESCE(SUM(i.line_total), 0) AS amount')
                )
                ->groupBy(DB::raw($groupCol))
                ->orderBy(DB::raw($groupCol))
                ->get();

            $out = [];
            $totalQty = 0;
            $totalAmount = 0;
            foreach ($rows as $r) {
                $out[] = [
                    $label => $r->gkey,
                    'qty' => (float) $r->qty,
                    'amount' => (float) $r->amount,
                ];
                $totalQty += (float) $r->qty;
                $totalAmount += (float) $r->amount;
            }

            return [
                'group_by' => $groupBy,
                'rows' => $out,
                'totals' => ['qty' => $totalQty, 'amount' => round($totalAmount, 2)],
            ];
        });
    }
}
