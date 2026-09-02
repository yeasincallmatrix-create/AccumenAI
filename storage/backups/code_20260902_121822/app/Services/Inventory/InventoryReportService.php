<?php

namespace App\Services\Inventory;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStockLevel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-side queries for the inventory subsystem: on-hand stock, valuation,
 * low-stock alerts, batch/expiry views and the movement ledger. Everything is
 * scoped to the active tenant (+ optional branch) and, for as-of reporting,
 * derives balances from the movement ledger so history is auditable.
 */
class InventoryReportService
{
    /**
     * On-hand stock with valuation, optionally as of a date (derived from the
     * movement ledger) and/or restricted to one warehouse.
     */
    public function stockOnHand(
        int $instituteId,
        ?int $branchId,
        ?int $warehouseId = null,
        ?int $categoryId = null,
        ?string $asOfDate = null,
    ): array {
        if ($asOfDate !== null) {
            return $this->onHandFromLedger($instituteId, $branchId, $warehouseId, $categoryId, $asOfDate);
        }

        $query = InventoryStockLevel::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->where('quantity', '<>', 0)
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->with(['item', 'warehouse', 'batch']);

        if ($categoryId !== null) {
            $query->whereHas('item', fn ($q) => $q->where('category_id', $categoryId));
        }

        return $query->get()
            ->map(fn (InventoryStockLevel $level) => [
                'item_id' => $level->item_id,
                'item_name' => $level->item?->name,
                'item_type' => $level->item?->item_type,
                'sku' => $level->item?->sku,
                'warehouse_id' => $level->warehouse_id,
                'warehouse_name' => $level->warehouse?->name,
                'batch_id' => $level->batch_id,
                'batch_number' => $level->batch?->batch_number,
                'quantity' => (float) $level->quantity,
                'avg_cost' => (float) $level->avg_cost,
                'value' => $level->value(),
            ])
            ->values()
            ->all();
    }

    /**
     * Stock value grouped by warehouse (current balances, for the stock
     * valuation report).
     */
    public function stockValueByWarehouse(int $instituteId, ?int $branchId): array
    {
        return InventoryStockLevel::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->with('warehouse')
            ->get()
            ->groupBy('warehouse_id')
            ->map(fn ($levels) => [
                'warehouse_id' => $levels->first()->warehouse_id,
                'warehouse_name' => $levels->first()->warehouse?->name,
                'items' => $levels->where('quantity', '<>', 0)->count(),
                'quantity' => round($levels->sum(fn ($l) => (float) $l->quantity), 4),
                'value' => round($levels->sum(fn ($l) => $l->value()), 4),
            ])
            ->values()
            ->all();
    }

    /**
     * Items at or below their reorder level (low-stock alert).
     */
    public function lowStock(int $instituteId, ?int $branchId, ?int $warehouseId = null): array
    {
        return InventoryItem::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->where('is_active', true)
            ->with('stockLevels.warehouse')
            ->get()
            ->filter(fn (InventoryItem $item) => $item->stockLevels->isNotEmpty())
            ->map(function (InventoryItem $item) use ($warehouseId) {
                return $item->stockLevels
                    ->filter(fn ($level) => $warehouseId === null || (int) $level->warehouse_id === $warehouseId)
                    ->filter(fn ($level) => (float) $level->quantity <= (float) $item->reorder_level)
                    ->map(fn ($level) => [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'sku' => $item->sku,
                        'warehouse_id' => $level->warehouse_id,
                        'warehouse_name' => $level->warehouse?->name,
                        'quantity' => (float) $level->quantity,
                        'reorder_level' => (float) $item->reorder_level,
                        'status' => (float) $level->quantity <= 0 ? 'out_of_stock' : 'low_stock',
                    ]);
            })
            ->flatten(1)
            ->values()
            ->all();
    }

    /**
     * Batches with expiry information (near-expiry / expired flags).
     */
    public function batches(int $instituteId, ?int $branchId, ?string $expiryStatus = null, ?int $warehouseId = null): array
    {
        $query = InventoryBatch::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->with(['item', 'warehouse'])
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId));

        $today = now()->toDateString();

        return $query->get()
            ->map(function ($batch) use ($expiryStatus, $today) {
                $status = 'valid';
                if ($batch->expiry_date !== null) {
                    if ($batch->expiry_date->lt($today)) {
                        $status = 'expired';
                    } elseif ($batch->expiry_date->lt(now()->addDays(30)->toDateString())) {
                        $status = 'near_expiry';
                    }
                }

                if ($expiryStatus !== null && $status !== $expiryStatus) {
                    return null;
                }

                return [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'item_id' => $batch->item_id,
                    'item_name' => $batch->item?->name,
                    'warehouse_id' => $batch->warehouse_id,
                    'warehouse_name' => $batch->warehouse?->name,
                    'quantity' => (float) $batch->quantity,
                    'unit_cost' => (float) $batch->unit_cost,
                    'manufacture_date' => $batch->manufacture_date?->toDateString(),
                    'expiry_date' => $batch->expiry_date?->toDateString(),
                    'status' => $status,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Movement ledger (audit trail) with filters.
     */
    public function movements(
        int $instituteId,
        ?int $branchId,
        array $filters = [],
    ): Builder {
        return InventoryMovement::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->when(filled($filters['warehouse_id'] ?? null), fn ($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(filled($filters['item_id'] ?? null), fn ($q) => $q->where('item_id', $filters['item_id']))
            ->when(filled($filters['movement_type'] ?? null), fn ($q) => $q->where('movement_type', $filters['movement_type']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('occurred_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('occurred_at', '<=', $filters['to']))
            ->with(['item', 'warehouse', 'batch', 'journal'])
            ->orderByDesc('occurred_at');
    }

    /**
     * On-hand as of a date by summing signed movements up to that date.
     */
    private function onHandFromLedger(int $instituteId, ?int $branchId, ?int $warehouseId, ?int $categoryId, string $asOfDate): array
    {
        $movements = InventoryMovement::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereDate('occurred_at', '<=', $asOfDate)
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($categoryId !== null, fn ($q) => $q->whereHas('item', fn ($q2) => $q2->where('category_id', $categoryId)))
            ->with(['item', 'warehouse', 'batch'])
            ->get();

        return $movements
            ->groupBy(fn ($m) => $m->warehouse_id.':'.$m->item_id.':'.($m->batch_id ?? 'null'))
            ->map(function ($group) {
                $first = $group->first();
                $quantity = round($group->sum(fn ($m) => (float) $m->quantity), 4);
                $value = round($group->sum(fn ($m) => (float) $m->quantity * (float) $m->unit_cost), 4);

                return [
                    'item_id' => $first->item_id,
                    'item_name' => $first->item?->name,
                    'warehouse_id' => $first->warehouse_id,
                    'warehouse_name' => $first->warehouse?->name,
                    'batch_id' => $first->batch_id,
                    'batch_number' => $first->batch?->batch_number,
                    'quantity' => $quantity,
                    'avg_cost' => $quantity != 0 ? round($value / $quantity, 4) : 0,
                    'value' => $value,
                ];
            })
            ->values()
            ->all();
    }
}
