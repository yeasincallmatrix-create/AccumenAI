<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\InventoryStockLevel;
use Illuminate\Support\Facades\DB;

/**
 * Inventory integrity checks. The movement ledger is the source of truth;
 * inventory_stock_levels is a cached balance that must always equal the sum of
 * signed movements per (warehouse, item, batch). reconcile() verifies that
 * invariant and rebuilds any drifted rows so the cache cannot silently diverge
 * from the ledger.
 */
class InventoryReconciliationService
{
    /**
     * Compare cached stock levels against the movement ledger and rebuild
     * drifted rows. Returns a report of what was checked and fixed.
     */
    public function reconcile(
        int $instituteId,
        ?int $branchId,
        ?int $warehouseId = null,
        ?int $itemId = null,
    ): array {
        return DB::transaction(function () use ($instituteId, $branchId, $warehouseId, $itemId) {
            $ledgerTotals = InventoryMovement::query()
                ->where('institute_id', $instituteId)
                ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->when($itemId !== null, fn ($q) => $q->where('item_id', $itemId))
                ->get()
                ->groupBy(fn ($m) => $m->warehouse_id.':'.$m->item_id.':'.($m->batch_id ?? 'null'))
                ->map(function ($group) {
                    $qty = round($group->sum(fn ($m) => (float) $m->quantity), 4);
                    $value = round($group->sum(fn ($m) => (float) $m->quantity * (float) $m->unit_cost), 4);

                    return [
                        'first' => $group->first(),
                        'quantity' => $qty,
                        'avg_cost' => $qty != 0 ? round($value / $qty, 4) : 0,
                    ];
                });

            $levels = InventoryStockLevel::query()
                ->where('institute_id', $instituteId)
                ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->when($itemId !== null, fn ($q) => $q->where('item_id', $itemId))
                ->get()
                ->keyBy(fn ($l) => $l->warehouse_id.':'.$l->item_id.':'.($l->batch_id ?? 'null'));

            $keys = $ledgerTotals->keys()->merge($levels->keys())->unique()->sort()->values();

            $checked = 0;
            $rebuilt = 0;
            $discrepancies = [];

            foreach ($keys as $key) {
                $ledger = $ledgerTotals->get($key);
                $level = $levels->get($key);
                $checked++;

                $ledgerQty = $ledger !== null ? $ledger['quantity'] : 0.0;
                $ledgerAvg = $ledger !== null ? $ledger['avg_cost'] : 0.0;
                $levelQty = $level !== null ? round((float) $level->quantity, 4) : 0.0;
                $levelAvg = $level !== null ? round((float) $level->avg_cost, 4) : 0.0;

                $quantityDrift = abs($levelQty - $ledgerQty) > 0.00005;
                $costDrift = abs($levelAvg - $ledgerAvg) > 0.00005;

                if (! $quantityDrift && ! $costDrift) {
                    continue;
                }

                $discrepancies[] = [
                    'key' => $key,
                    'ledger_quantity' => $ledgerQty,
                    'cached_quantity' => $levelQty,
                    'ledger_avg_cost' => $ledgerAvg,
                    'cached_avg_cost' => $levelAvg,
                ];

                if ($level === null) {
                    InventoryStockLevel::create([
                        'institute_id' => $instituteId,
                        'branch_id' => $ledger['first']->branch_id,
                        'warehouse_id' => $ledger['first']->warehouse_id,
                        'item_id' => $ledger['first']->item_id,
                        'batch_id' => $ledger['first']->batch_id,
                        'quantity' => $ledgerQty,
                        'avg_cost' => $ledgerAvg,
                    ]);
                } else {
                    $level->forceFill([
                        'quantity' => $ledgerQty,
                        'avg_cost' => $ledgerAvg,
                    ])->save();
                }

                $rebuilt++;
            }

            return [
                'checked' => $checked,
                'discrepancies' => count($discrepancies),
                'rebuilt' => $rebuilt,
                'drifted' => $discrepancies,
            ];
        });
    }
}
