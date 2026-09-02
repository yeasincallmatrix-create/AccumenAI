<?php

namespace App\Services\Accounting;

use App\Models\InventoryMovement;
use App\Services\Inventory\InventoryReportService;
use Illuminate\Support\Facades\DB;

/**
 * STEP 83 — Inventory Accounting Reports.
 *
 * Stock valuation, inventory movement, COGS, and slow-moving inventory.
 * Reuses InventoryReportService for stock-on-hand, valuation, and movement
 * ledger queries. COGS derived from posted journal entries on account 5007.
 */
class InventoryAccountingReportService
{
    public function __construct(
        private readonly InventoryReportService $inventoryReports,
    ) {}

    /**
     * Stock valuation report: current on-hand with value, grouped by warehouse.
     */
    public function stockValuation(int $instituteId, ?int $branchId, ?int $warehouseId = null): array
    {
        $stock = $this->inventoryReports->stockOnHand($instituteId, $branchId, $warehouseId);

        $totalValue = array_sum(array_column($stock, 'value'));
        $totalQuantity = array_sum(array_column($stock, 'quantity'));

        $byWarehouse = [];
        foreach ($stock as $row) {
            $whId = $row['warehouse_id'];
            if (!isset($byWarehouse[$whId])) {
                $byWarehouse[$whId] = [
                    'warehouse_id' => $whId,
                    'warehouse_name' => $row['warehouse_name'],
                    'items' => 0,
                    'quantity' => 0.0,
                    'value' => 0.0,
                ];
            }
            $byWarehouse[$whId]['items']++;
            $byWarehouse[$whId]['quantity'] += $row['quantity'];
            $byWarehouse[$whId]['value'] += $row['value'];
        }

        return [
            'items' => $stock,
            'summary' => [
                'total_items' => count($stock),
                'total_quantity' => round($totalQuantity, 4),
                'total_value' => round($totalValue, 4),
            ],
            'by_warehouse' => array_values($byWarehouse),
        ];
    }

    /**
     * Inventory movement report: filtered movement ledger.
     *
     * @param array{warehouse_id?: int, item_id?: int, movement_type?: string, from?: string, to?: string} $filters
     */
    public function inventoryMovement(int $instituteId, ?int $branchId, array $filters = []): array
    {
        $movements = $this->inventoryReports->movements($instituteId, $branchId, $filters)
            ->get();

        $inbound = $movements->where('quantity', '>', 0);
        $outbound = $movements->where('quantity', '<', 0);

        return [
            'movements' => $movements,
            'summary' => [
                'total_movements' => $movements->count(),
                'inbound_count' => $inbound->count(),
                'outbound_count' => $outbound->count(),
                'inbound_value' => round((float) $inbound->sum(fn ($m) => abs((float) $m->quantity) * (float) $m->unit_cost), 4),
                'outbound_value' => round((float) $outbound->sum(fn ($m) => abs((float) $m->quantity) * (float) $m->unit_cost), 4),
            ],
        ];
    }

    /**
     * COGS report: total COGS from posted journal entries on account 5007
     * in a date range.
     */
    public function cogsReport(int $instituteId, ?int $branchId, string $from, string $to): array
    {
        $cogsAccount = DB::table('chart_of_accounts')
            ->where('institute_id', $instituteId)
            ->where('code', '5007')
            ->first();

        if (!$cogsAccount) {
            return [
                'total_cogs' => 0.0,
                'transactions' => collect(),
                'from' => $from,
                'to' => $to,
            ];
        }

        $entries = DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('je.institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('je.branch_id', $branchId))
            ->where('je.coa_id', $cogsAccount->id)
            ->where('j.status', 'posted')
            ->whereNull('j.reversal_of')
            ->whereDate('je.journal_date', '>=', $from)
            ->whereDate('je.journal_date', '<=', $to)
            ->select('je.*', 'j.journal_no', 'j.description as journal_description')
            ->orderBy('je.journal_date')
            ->get();

        $totalCogs = (float) $entries->sum('debit');

        return [
            'total_cogs' => round($totalCogs, 4),
            'transactions' => $entries,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Slow-moving inventory: items with no movements in the last N days
     * but still having stock on hand.
     *
     * @param int $days Number of days to look back (default 90).
     */
    public function slowMovingInventory(int $instituteId, ?int $branchId, int $days = 90): array
    {
        $since = now()->subDays($days)->toDateString();

        $stock = $this->inventoryReports->stockOnHand($instituteId, $branchId);

        $activeItemIds = InventoryMovement::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereDate('occurred_at', '>=', $since)
            ->pluck('item_id')
            ->unique()
            ->all();

        $slowMoving = array_filter($stock, function ($item) use ($activeItemIds) {
            return !in_array($item['item_id'], $activeItemIds);
        });

        $totalValue = array_sum(array_column($slowMoving, 'value'));

        return [
            'items' => array_values($slowMoving),
            'summary' => [
                'total_slow_moving' => count($slowMoving),
                'total_value' => round($totalValue, 4),
                'period_days' => $days,
            ],
        ];
    }
}
