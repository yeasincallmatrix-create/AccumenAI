<?php

namespace App\Services\Dealership\Reports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Collection report — aggregates sr_collections by SR/method, aging
 * buckets for pending collections, and per-customer outstanding
 * (ordered − collected).
 */
class CollectionReportService extends BaseReportService
{
    public function buckets(): array
    {
        return [
            'current' => ['label' => 'Current (0-30)', 'min' => 0, 'max' => 30],
            'd31_60' => ['label' => '31-60 Days', 'min' => 31, 'max' => 60],
            'd61_90' => ['label' => '61-90 Days', 'min' => 61, 'max' => 90],
            'd91_plus' => ['label' => '91+ Days', 'min' => 91, 'max' => null],
        ];
    }

    public function calculate(array $filters, int $instituteId): array
    {
        return $this->cached('sr_collection_summary', $filters, $instituteId, function () use ($filters, $instituteId) {
            $base = DB::table('dealership_sr_collections')->where('institute_id', $instituteId);
            $this->applyDateRange($base, $filters, 'collected_on');

            if (! empty($filters['sr'])) {
                $base->where('sales_force_id', $filters['sr']);
            }
            if (! empty($filters['customer'])) {
                $base->where('customer_id', $filters['customer']);
            }
            if (! empty($filters['status'])) {
                $base->where('status', $filters['status']);
            }

            $bySr = (clone $base)
                ->select('sales_force_id', DB::raw('COALESCE(SUM(amount), 0) AS amount'), DB::raw('COUNT(*) AS receipts'))
                ->groupBy('sales_force_id')
                ->orderBy('sales_force_id')
                ->get();

            $byMethod = (clone $base)
                ->select('method', DB::raw('COALESCE(SUM(amount), 0) AS amount'))
                ->groupBy('method')
                ->orderBy('method')
                ->get();

            $asOf = ! empty($filters['as_of']) ? Carbon::parse($filters['as_of']) : now();
            $pending = (clone $base)->where('status', 'pending')->get();

            $buckets = [];
            foreach ($this->buckets() as $key => $def) {
                $buckets[$key] = ['label' => $def['label'], 'amount' => 0, 'count' => 0];
            }
            foreach ($pending as $row) {
                $days = abs((int) $asOf->diffInDays(Carbon::parse($row->collected_on)));
                foreach ($this->buckets() as $key => $def) {
                    if ($days >= $def['min'] && ($def['max'] === null || $days <= $def['max'])) {
                        $buckets[$key]['amount'] += (float) $row->amount;
                        $buckets[$key]['count']++;
                        break;
                    }
                }
            }

            // Customer outstanding: ordered − collected (scoped to institute).
            $ordered = DB::table('dealership_sr_orders')
                ->where('institute_id', $instituteId)
                ->select('customer_id', DB::raw('COALESCE(SUM(total), 0) AS ordered'))
                ->groupBy('customer_id')
                ->pluck('ordered', 'customer_id');
            $collected = DB::table('dealership_sr_collections')
                ->where('institute_id', $instituteId)
                ->select('customer_id', DB::raw('COALESCE(SUM(amount), 0) AS collected'))
                ->groupBy('customer_id')
                ->pluck('collected', 'customer_id');

            $outstanding = [];
            foreach ($ordered as $customerId => $ord) {
                $coll = (float) ($collected[$customerId] ?? 0);
                $outstanding[] = [
                    'customer_id' => (int) $customerId,
                    'ordered' => (float) $ord,
                    'collected' => $coll,
                    'outstanding' => round((float) $ord - $coll, 2),
                ];
            }

            return [
                'by_sr' => $bySr->map(fn ($r) => [
                    'sales_force_id' => (int) $r->sales_force_id,
                    'receipts' => (int) $r->receipts,
                    'amount' => (float) $r->amount,
                ])->all(),
                'by_method' => $byMethod->map(fn ($r) => [
                    'method' => $r->method,
                    'amount' => (float) $r->amount,
                ])->all(),
                'aging' => $buckets,
                'customer_outstanding' => $outstanding,
            ];
        });
    }
}
