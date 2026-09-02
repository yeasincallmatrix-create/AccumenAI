<?php

namespace App\Services\Sales;

use App\Models\InventoryItem;
use App\Models\InventoryStockLevel;
use App\Models\TaxGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * S-2 Product/Service integration — Inventory is source of truth.
 * Supports stockable and non-stockable items without duplicating SKU, name, unit, category, stock.
 * Provides reusable availability query for Sales.
 */
final class SalesCatalogService
{
    public const STOCKABLE_TYPES = ['stock_item', 'medicine', 'raw_material', 'finished_good', 'spare_part', 'consumable'];
    public const NON_STOCKABLE_TYPES = ['service_consumable', 'other'];

    public function isStockable(InventoryItem $item): bool
    {
        return in_array($item->item_type, self::STOCKABLE_TYPES, true);
    }

    public function isService(InventoryItem $item): bool
    {
        return in_array($item->item_type, self::NON_STOCKABLE_TYPES, true) || $item->item_type === 'other';
    }

    /**
     * Resolve item with strict tenant/branch checks (prevents ID bypass).
     */
    public function resolve(int $instituteId, ?int $branchId, int $itemId): InventoryItem
    {
        $item = InventoryItem::withoutGlobalScopes()
            ->where('id', $itemId)
            ->where('institute_id', $instituteId)
            ->first();

        if (! $item) {
            abort(404, 'Item not found.');
        }

        if ($branchId !== null) {
            if ($item->branch_id !== null && (int) $item->branch_id !== (int) $branchId) {
                abort(404, 'Item not found.');
            }
        }

        if (! $item->is_active) {
            throw ValidationException::withMessages(['item' => 'Selected item is inactive.']);
        }

        return $item;
    }

    /**
     * Search items — text search (name/sku/barcode), code/SKU search, pagination, tenant+branch scoped.
     */
    public function search(int $instituteId, ?int $branchId, ?string $search, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $q = InventoryItem::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('is_active', true);

        // Branch visibility: shared (null) or matching branch
        if ($branchId !== null) {
            $q->where(function (Builder $query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }

        if (filled($filters['item_type'] ?? null)) {
            $q->where('item_type', $filters['item_type']);
        }

        if (isset($filters['stockable'])) {
            if ($filters['stockable']) {
                $q->whereIn('item_type', self::STOCKABLE_TYPES);
            } else {
                $q->whereIn('item_type', self::NON_STOCKABLE_TYPES);
            }
        }

        if (filled($filters['category_id'] ?? null)) {
            $q->where('category_id', (int) $filters['category_id']);
        }

        if (filled($search)) {
            $like = '%'.trim($search).'%';
            $q->where(function (Builder $query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like);
            });
        }

        $q->orderBy('name');

        return $q->paginate($perPage);
    }

    /**
     * Reusable availability payload for Sales.
     * Does NOT reduce inventory.
     */
    public function availability(int $instituteId, ?int $branchId, int $itemId, float $requestedQty = 1): array
    {
        $item = $this->resolve($instituteId, $branchId, $itemId);

        $onHand = 0;
        $available = true;
        $reason = null;
        $stockable = $this->isStockable($item);

        if ($stockable) {
            // Branch-specific stock level, fallback to institute-wide if branch stock not found
            $level = InventoryStockLevel::withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->where('item_id', $itemId)
                ->when($branchId !== null, function (Builder $query) use ($branchId) {
                    // Prefer branch-specific, else any
                    $query->where('branch_id', $branchId);
                })
                ->first();

            // If no branch-specific level, try shared (branch_id null) or sum across warehouses
            if (! $level && $branchId !== null) {
                $level = InventoryStockLevel::withoutGlobalScopes()
                    ->where('institute_id', $instituteId)
                    ->where('item_id', $itemId)
                    ->whereNull('branch_id')
                    ->first();
            }

            if (! $level) {
                // Fallback: sum all warehouses for institute/branch visibility
                $onHand = (float) InventoryStockLevel::withoutGlobalScopes()
                    ->where('institute_id', $instituteId)
                    ->where('item_id', $itemId)
                    ->when($branchId !== null, function (Builder $query) use ($branchId) {
                        $query->where(function (Builder $q) use ($branchId) {
                            $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                        });
                    })
                    ->sum('quantity');
            } else {
                $onHand = (float) $level->quantity;
            }

            $available = $onHand >= $requestedQty;
            if (! $available) {
                $reason = 'Insufficient stock. On hand: '.number_format($onHand, 4);
            }
        } else {
            // Non-stockable (service) is always available if active
            $available = true;
            $onHand = 0;
        }

        return [
            'item' => $item,
            'identity' => [
                'id' => $item->id,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'name' => $item->name,
            ],
            'type' => $item->item_type,
            'is_stockable' => $stockable,
            'is_service' => ! $stockable,
            'selling_price' => (float) $item->selling_price,
            'purchase_price' => (float) $item->purchase_price,
            'unit' => $item->unit,
            'category_id' => $item->category_id,
            'tax_group_id' => $item->tax_group_id,
            'tax' => $item->taxGroup ? [
                'id' => $item->taxGroup->id,
                'name' => $item->taxGroup->name,
                'rate' => $item->taxGroup->rate ?? null,
            ] : null,
            'discount_eligible' => $this->isDiscountEligible($item),
            'branch_available' => $available,
            'stock' => [
                'on_hand' => $onHand,
                'available' => $available,
                'reason' => $reason,
                'requested_qty' => $requestedQty,
            ],
        ];
    }

    private function isDiscountEligible(InventoryItem $item): bool
    {
        // Simple rule: active items with selling_price > 0 are discount eligible
        // Future: could check category discount flag or item_meta
        return $item->is_active && (float) $item->selling_price > 0;
    }
}
