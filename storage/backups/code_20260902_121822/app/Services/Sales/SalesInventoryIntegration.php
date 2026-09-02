<?php

namespace App\Services\Sales;

use App\Models\InventoryItem;
use App\Models\InventoryStockLevel;
use Illuminate\Support\Collection;

/**
 * Clean integration point to existing Inventory source of truth.
 * No tables created here — delegates to InventoryItem / InventoryStockLevel / InventoryWarehouse.
 * Later Sales steps (S-2 quotation/order/delivery) will call these helpers.
 */
final class SalesInventoryIntegration
{
    public function findItem(int $instituteId, ?int $branchId, int $itemId): ?InventoryItem
    {
        $q = InventoryItem::withoutGlobalScopes()->where('institute_id', $instituteId)->where('id', $itemId);
        if ($branchId !== null) {
            $q->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }

        return $q->first();
    }

    public function checkStock(int $instituteId, ?int $branchId, int $itemId, float $requiredQty = 1): array
    {
        $item = $this->findItem($instituteId, $branchId, $itemId);
        if (! $item) {
            return ['available' => false, 'reason' => 'Item not found in this institute/branch.', 'on_hand' => 0];
        }

        // Non-stockable (service) is always available
        if (in_array($item->item_type, SalesCatalogService::NON_STOCKABLE_TYPES, true)) {
            return ['available' => true, 'reason' => null, 'on_hand' => 0, 'item' => $item];
        }

        $onHand = (float) InventoryStockLevel::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('item_id', $itemId)
            ->when($branchId !== null, function ($query) use ($branchId) {
                $query->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });
            })
            ->sum('quantity');

        return [
            'available' => $onHand >= $requiredQty,
            'reason' => $onHand >= $requiredQty ? null : 'Insufficient stock.',
            'on_hand' => $onHand,
            'item' => $item,
        ];
    }

    public function availableItems(int $instituteId, ?int $branchId = null): Collection
    {
        $q = InventoryItem::withoutGlobalScopes()->where('institute_id', $instituteId)->where('is_active', true);
        if ($branchId !== null) {
            $q->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }

        return $q->orderBy('name')->limit(200)->get();
    }
}
