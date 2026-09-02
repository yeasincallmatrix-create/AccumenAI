<?php

namespace App\Services\Sales;

use App\Models\InventoryWarehouse;
use App\Models\SalesDelivery;
use App\Models\SalesDeliveryLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Inventory\InventoryStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(
        private readonly AccountingAuditService $audit,
        private readonly SalesNumberingService $numbering,
        private readonly InventoryStockService $stockService,
        private readonly SalesCatalogService $catalog,
    ) {}

    public function createDelivery(int $instituteId, ?int $branchId, int $orderId, array $data, ?int $actorId = null): SalesDelivery
    {
        $order = $this->assertOrderScope($instituteId, $branchId, $orderId);

        if (! $order->isAwaitingDelivery()) {
            throw ValidationException::withMessages(['order_id' => 'Order is not in a deliverable state.']);
        }

        $lines = $data['lines'] ?? [];
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => 'At least one delivery line is required.']);
        }

        $warehouseId = $data['warehouse_id'] ?? $this->resolveWarehouse($instituteId, $branchId);
        if ($warehouseId !== null) {
            $this->assertWarehouse($instituteId, $branchId, (int) $warehouseId);
        }

        return DB::transaction(function () use ($instituteId, $branchId, $order, $lines, $data, $actorId, $warehouseId) {
            $delivery = SalesDelivery::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId ?? $order->branch_id,
                'delivery_number' => $this->numbering->nextNumber($instituteId, $branchId, 'delivery'),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'warehouse_id' => $warehouseId,
                'delivery_date' => $data['delivery_date'] ?? now()->toDateString(),
                'shipping_address' => $data['shipping_address'] ?? $order->shipping_address,
                'notes' => $data['notes'] ?? null,
                'status' => SalesDelivery::STATUS_DRAFT,
                'created_by' => $actorId,
            ]);

            foreach ($lines as $line) {
                $orderLine = SalesOrderLine::withoutGlobalScopes()->where('institute_id', $instituteId)->where('order_id', $order->id)->where('id', $line['order_line_id'])->first();
                if (! $orderLine) {
                    throw ValidationException::withMessages(['lines' => 'Order line not found or not in scope.']);
                }

                $deliveryQty = (float) ($line['delivery_quantity'] ?? 0);
                if ($deliveryQty <= 0) {
                    throw ValidationException::withMessages(['lines' => 'Delivery quantity must be greater than 0.']);
                }

                // Check tampering: ordered_quantity must match order line
                $orderedQty = (float) $orderLine->quantity;
                $previouslyDelivered = $this->previouslyDeliveredQuantity($orderLine);
                $remaining = $orderedQty - $previouslyDelivered;

                if ($deliveryQty - $remaining > 0.00005) {
                    throw ValidationException::withMessages(['lines' => "Delivery quantity {$deliveryQty} exceeds remaining {$remaining} for line {$orderLine->id}."]);
                }

                // Product scope check
                if ($orderLine->inventory_item_id) {
                    $item = $this->catalog->resolve($instituteId, $branchId, (int) $orderLine->inventory_item_id);
                    // Ensure line inventory_item matches order line
                    if ((int) ($line['inventory_item_id'] ?? $orderLine->inventory_item_id) !== (int) $orderLine->inventory_item_id) {
                        throw ValidationException::withMessages(['lines' => 'Product mismatch for order line.']);
                    }
                }

                SalesDeliveryLine::create([
                    'institute_id' => $instituteId,
                    'delivery_id' => $delivery->id,
                    'order_line_id' => $orderLine->id,
                    'inventory_item_id' => $orderLine->inventory_item_id,
                    'description' => $orderLine->description,
                    'ordered_quantity' => $orderedQty,
                    'previously_delivered_quantity' => $previouslyDelivered,
                    'delivery_quantity' => $deliveryQty,
                    'unit' => $orderLine->unit,
                ]);
            }

            $this->audit->log($instituteId, [
                'branch_id' => $delivery->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'create',
                'entity_type' => 'sales_delivery',
                'entity_id' => $delivery->id,
                'after_payload' => ['delivery_number' => $delivery->delivery_number, 'order_id' => $order->id],
            ]);

            return $delivery->load('lines');
        });
    }

    public function confirmDelivery(SalesDelivery $delivery, ?int $actorId = null): SalesDelivery
    {
        if (! $delivery->canConfirm()) {
            throw ValidationException::withMessages(['status' => 'Only draft deliveries can be confirmed. Duplicate confirmation is not allowed.']);
        }
        if (! $delivery->canTransitionTo(SalesDelivery::STATUS_CONFIRMED)) {
            throw ValidationException::withMessages(['status' => "Cannot transition from {$delivery->status} to confirmed."]);
        }

        $order = $delivery->order;

        return DB::transaction(function () use ($delivery, $actorId, $order) {
            // Re-check status inside transaction for duplicate protection (lock)
            $delivery->refresh();
            if (! $delivery->canConfirm()) {
                throw ValidationException::withMessages(['status' => 'Delivery already confirmed.']);
            }

            $delivery->load('lines.inventoryItem');

            // Separate stockable vs non-stock service lines
            $stockLines = [];
            foreach ($delivery->lines as $dLine) {
                $itemId = $dLine->inventory_item_id;
                if (! $itemId) {
                    continue; // manual description without product — no inventory
                }

                $item = $dLine->inventoryItem ?? $this->catalog->resolve($delivery->institute_id, $delivery->branch_id, (int) $itemId);
                if (! $this->catalog->isStockable($item)) {
                    continue; // service — no inventory movement
                }

                // Insufficient stock check — respects allow_negative_stock via InventoryStockService assert
                // Also pre-check for clearer error before attempting movement
                $stockLines[] = [
                    'item_id' => (int) $item->id,
                    'quantity' => (float) $dLine->delivery_quantity,
                    'unit_cost' => null, // use avg_cost from stock level
                ];
            }

            // Atomic inventory OUT via existing source-of-truth service
            $warehouseId = $delivery->warehouse_id ?? $this->resolveWarehouse($delivery->institute_id, $delivery->branch_id);
            if (! empty($stockLines)) {
                $warehouse = $this->assertWarehouse($delivery->institute_id, $delivery->branch_id, (int) $warehouseId);
                $this->stockService->saleIssue(
                    $delivery->institute_id,
                    $delivery->branch_id,
                    $warehouse->id,
                    'sales_delivery',
                    $delivery->id,
                    $stockLines,
                    $actorId,
                    ['reason' => 'Delivery ' . $delivery->delivery_number, 'occurred_at' => $delivery->delivery_date->toDateString()]
                );
            }

            $delivery->update([
                'status' => SalesDelivery::STATUS_CONFIRMED,
                'delivered_by' => $actorId,
                'delivered_at' => now(),
                'updated_by' => $actorId,
            ]);

            // Optionally move order to processing/ready state if needed
            // Leave order status management to separate workflow — delivery does not auto-complete order

            $this->audit->log($delivery->institute_id, [
                'branch_id' => $delivery->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'update',
                'entity_type' => 'sales_delivery',
                'entity_id' => $delivery->id,
                'before_payload' => ['status' => 'draft'],
                'after_payload' => ['status' => 'confirmed', 'warehouse_id' => $warehouseId],
            ]);

            return $delivery->fresh('lines');
        });
    }

    public function cancelDelivery(SalesDelivery $delivery, ?int $actorId = null): SalesDelivery
    {
        if (! $delivery->canCancel()) {
            throw ValidationException::withMessages(['status' => 'Delivery cannot be cancelled from current status.']);
        }

        return DB::transaction(function () use ($delivery, $actorId) {
            $from = $delivery->status;

            if ($from === SalesDelivery::STATUS_CONFIRMED) {
                // Reverse inventory via existing reversal mechanism (returnStock)
                $delivery->load('lines.inventoryItem');
                $returnLines = [];
                foreach ($delivery->lines as $dLine) {
                    $itemId = $dLine->inventory_item_id;
                    if (! $itemId) {
                        continue;
                    }
                    $item = $dLine->inventoryItem;
                    if ($item && ! $this->catalog->isStockable($item)) {
                        continue;
                    }
                    $returnLines[] = [
                        'item_id' => (int) $itemId,
                        'quantity' => (float) $dLine->delivery_quantity,
                    ];
                }

                if (! empty($returnLines)) {
                    $warehouseId = $delivery->warehouse_id ?? $this->resolveWarehouse($delivery->institute_id, $delivery->branch_id);
                    $warehouse = $this->assertWarehouse($delivery->institute_id, $delivery->branch_id, (int) $warehouseId);
                    // Use returnStock with direction 'in' to restock
                    $this->stockService->returnStock(
                        $delivery->institute_id,
                        $delivery->branch_id,
                        $warehouse->id,
                        'in',
                        'sales_delivery',
                        $delivery->id,
                        $returnLines,
                        $actorId,
                        ['reason' => 'Delivery cancellation ' . $delivery->delivery_number]
                    );
                }
            }

            $delivery->update([
                'status' => SalesDelivery::STATUS_CANCELLED,
                'updated_by' => $actorId,
            ]);

            $this->audit->log($delivery->institute_id, [
                'branch_id' => $delivery->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'update',
                'entity_type' => 'sales_delivery',
                'entity_id' => $delivery->id,
                'before_payload' => ['status' => $from],
                'after_payload' => ['status' => 'cancelled'],
            ]);

            return $delivery->fresh('lines');
        });
    }

    public function deliveredQuantityForOrderLine(SalesOrderLine $orderLine): float
    {
        return (float) SalesDeliveryLine::withoutGlobalScopes()
            ->where('institute_id', $orderLine->institute_id)
            ->where('order_line_id', $orderLine->id)
            ->whereHas('delivery', fn ($q) => $q->whereIn('status', [SalesDelivery::STATUS_CONFIRMED, SalesDelivery::STATUS_DELIVERED])->whereNull('deleted_at'))
            ->sum('delivery_quantity');
    }

    public function remainingQuantityForOrderLine(SalesOrderLine $orderLine): float
    {
        $ordered = (float) $orderLine->quantity;
        $delivered = $this->deliveredQuantityForOrderLine($orderLine);

        return round($ordered - $delivered, 4);
    }

    public function isOrderFullyDelivered(SalesOrder $order): bool
    {
        foreach ($order->lines as $line) {
            if ($this->remainingQuantityForOrderLine($line) > 0.00005) {
                return false;
            }
        }

        return true;
    }

    private function previouslyDeliveredQuantity(SalesOrderLine $orderLine): float
    {
        return $this->deliveredQuantityForOrderLine($orderLine);
    }

    private function assertOrderScope(int $instituteId, ?int $branchId, int $orderId): SalesOrder
    {
        $order = SalesOrder::withoutGlobalScopes()->where('institute_id', $instituteId)->where('id', $orderId)->first();

        if (! $order) {
            throw ValidationException::withMessages(['order_id' => 'Sales order not found or not in scope.']);
        }

        if ($branchId !== null && $order->branch_id !== null && (int) $order->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages(['order_id' => 'Sales order not in your branch.']);
        }

        return $order;
    }

    private function resolveWarehouse(int $instituteId, ?int $branchId): ?int
    {
        $q = InventoryWarehouse::withoutGlobalScopes()->where('institute_id', $instituteId)->where('is_active', true);
        if ($branchId !== null) {
            $q->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }
        $warehouse = $q->orderBy('id')->first();

        return $warehouse?->id;
    }

    private function assertWarehouse(int $instituteId, ?int $branchId, int $warehouseId): InventoryWarehouse
    {
        $warehouse = InventoryWarehouse::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('id', $warehouseId)
            ->where('is_active', true)
            ->where(function ($q) use ($branchId) {
                if ($branchId !== null) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                }
            })
            ->first();

        if (! $warehouse) {
            throw ValidationException::withMessages(['warehouse_id' => 'Warehouse not found or not in scope.']);
        }

        return $warehouse;
    }
}
