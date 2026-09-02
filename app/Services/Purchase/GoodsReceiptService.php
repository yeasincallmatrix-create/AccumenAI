<?php

namespace App\Services\Purchase;

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryWarehouse;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\Inventory\InventoryStockService;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public function __construct(
        private readonly PurchaseNumberingService $numbering,
        private readonly InventoryStockService $stockService,
    ) {}

    public function create(array $data, int $instituteId, ?int $branchId, int $actorId): GoodsReceipt
    {
        $this->assertBranchScope($branchId);

        $poId = $data['purchase_order_id'] ?? null;
        if (! $poId) {
            throw ValidationException::withMessages(['purchase_order_id' => 'Purchase order is required.']);
        }

        $po = PurchaseOrder::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('id', $poId)
            ->first();

        if (! $po) {
            throw ValidationException::withMessages(['purchase_order_id' => 'Purchase order not found.']);
        }

        if (! in_array($po->status, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
            throw ValidationException::withMessages(['purchase_order_id' => 'Purchase order must be approved before receiving goods.']);
        }

        $supplierId = $data['supplier_id'] ?? $po->supplier_id;
        $this->assertSupplier($instituteId, $branchId, $supplierId);

        $warehouseId = $data['warehouse_id'] ?? $po->warehouse_id;
        if ($warehouseId) {
            $this->assertWarehouse($instituteId, $branchId, (int) $warehouseId);
        } else {
            // Warehouse is required for receiving
            throw ValidationException::withMessages(['warehouse_id' => 'Warehouse is required.']);
        }

        $receiptDate = $data['receipt_date'] ?? now()->toDateString();
        $notes = $data['notes'] ?? null;
        $lines = $data['lines'] ?? [];

        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => 'At least one receipt line is required.']);
        }

        $po->load('lines');

        return DB::transaction(function () use ($po, $lines, $instituteId, $branchId, $actorId, $supplierId, $warehouseId, $receiptDate, $notes) {
            $receipt = GoodsReceipt::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'receipt_number' => $this->numbering->nextNumber($instituteId, $branchId, 'receipt'),
                'purchase_order_id' => $po->id,
                'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId,
                'receipt_date' => $receiptDate,
                'status' => GoodsReceipt::STATUS_DRAFT,
                'notes' => $notes,
                'created_by' => $actorId,
            ]);

            foreach ($lines as $idx => $line) {
                $this->validateLine($line, $po, $idx, $instituteId, $branchId);

                $poLine = $po->lines->firstWhere('id', $line['purchase_order_line_id']);
                $orderedQty = (float) $poLine->quantity;
                $previouslyReceived = (float) $poLine->received_quantity;
                $receivedQty = (float) ($line['received_quantity'] ?? 0);
                $rejectedQty = (float) ($line['rejected_quantity'] ?? 0);

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_line_id' => $line['purchase_order_line_id'],
                    'inventory_item_id' => $line['inventory_item_id'] ?? $poLine->inventory_item_id,
                    'ordered_quantity' => $orderedQty,
                    'previously_received_quantity' => $previouslyReceived,
                    'received_quantity' => $receivedQty,
                    'rejected_quantity' => $rejectedQty,
                    'unit_cost' => $line['unit_cost'] ?? $poLine->unit_price,
                    'notes' => $line['notes'] ?? null,
                    'batch_number' => $line['batch_number'] ?? null,
                    'lot_number' => $line['lot_number'] ?? null,
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'manufacture_date' => $line['manufacture_date'] ?? null,
                    'serial_numbers' => isset($line['serial_numbers']) ? json_encode($line['serial_numbers']) : (isset($line['serial_number']) ? json_encode([$line['serial_number']]) : null),
                    'received_condition' => $line['received_condition'] ?? 'good',
                ]);
            }

            $this->auditLog($instituteId, $branchId, $actorId, 'create', 'goods_receipt', $receipt->id, null, [
                'receipt_number' => $receipt->receipt_number,
                'purchase_order_id' => $po->id,
                'status' => $receipt->status,
            ]);

            return $receipt->load('items');
        });
    }

    public function confirm(GoodsReceipt $receipt, int $actorId): GoodsReceipt
    {
        $this->assertBranchScope($receipt->branch_id);

        if (! $receipt->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Only draft receipts can be confirmed.']);
        }

        return DB::transaction(function () use ($receipt, $actorId) {
            $receipt->load('items.purchaseOrderLine.order');
            $receipt->load('items.inventoryItem');

            $po = $receipt->items->first()->purchaseOrderLine->order;
            $po->load('lines');

            $this->validateOverReceiving($receipt, $po);

            $lines = [];
            foreach ($receipt->items as $item) {
                $effectiveQty = (float) $item->received_quantity - (float) $item->rejected_quantity;
                if ($effectiveQty <= 0) {
                    continue;
                }

                $lineData = [
                    'item_id' => $item->inventory_item_id,
                    'quantity' => $effectiveQty,
                    'unit_cost' => (float) $item->unit_cost,
                ];

                // Batch/serial/expiry integration: delegate to InventoryStockService resolveBatch
                if ($item->batch_number) {
                    $lineData['batch_number'] = $item->batch_number;
                }
                if ($item->expiry_date) {
                    $lineData['expiry_date'] = $item->expiry_date;
                }
                if ($item->manufacture_date) {
                    $lineData['manufacture_date'] = $item->manufacture_date;
                }
                if ($item->serial_numbers) {
                    $decoded = is_string($item->serial_numbers) ? json_decode($item->serial_numbers, true) : $item->serial_numbers;
                    if (is_array($decoded) && $decoded !== []) {
                        $lineData['serials'] = $decoded;
                    }
                }

                $lines[] = $lineData;
            }

            if (! empty($lines)) {
                $supplier = Party::withoutGlobalScopes()->find($receipt->supplier_id);
                $this->assertSupplier($receipt->institute_id, $receipt->branch_id, $receipt->supplier_id);

                $this->stockService->receivePurchase(
                    $receipt->institute_id,
                    $receipt->branch_id,
                    $supplier,
                    $receipt->warehouse_id,
                    $lines,
                    $actorId,
                    [
                        'reference_type' => GoodsReceipt::class,
                        'reference_id' => $receipt->id,
                        'reason' => 'Goods receipt #' . $receipt->receipt_number,
                        'description' => 'Purchase receipt against PO ' . $po->order_number,
                    ]
                );
            }

            $this->syncPoLineReceivedQuantities($receipt);

            $receipt->update([
                'status' => GoodsReceipt::STATUS_CONFIRMED,
                'confirmed_by' => $actorId,
                'confirmed_at' => now(),
            ]);

            $this->syncPoStatus($po);

            $this->auditLog($receipt->institute_id, $receipt->branch_id, $actorId, 'update', 'goods_receipt', $receipt->id, ['status' => GoodsReceipt::STATUS_DRAFT], ['status' => GoodsReceipt::STATUS_CONFIRMED]);

            return $receipt->fresh('items');
        });
    }

    public function cancel(GoodsReceipt $receipt, int $actorId, ?string $reason = null): GoodsReceipt
    {
        $this->assertBranchScope($receipt->branch_id);

        if ($receipt->isConfirmed()) {
            throw ValidationException::withMessages(['status' => 'Confirmed receipts cannot be cancelled. Use a return/reversal instead.']);
        }

        if (! $receipt->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Only draft receipts can be cancelled.']);
        }

        return DB::transaction(function () use ($receipt, $actorId, $reason) {
            $receipt->update([
                'status' => GoodsReceipt::STATUS_CANCELLED,
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->auditLog($receipt->institute_id, $receipt->branch_id, $actorId, 'update', 'goods_receipt', $receipt->id, ['status' => GoodsReceipt::STATUS_DRAFT], ['status' => GoodsReceipt::STATUS_CANCELLED]);

            return $receipt->fresh('items');
        });
    }

    public function reverse(GoodsReceipt $receipt, int $actorId, ?string $reason = null): GoodsReceipt
    {
        $this->assertBranchScope($receipt->branch_id);

        if (! $receipt->isConfirmed()) {
            throw ValidationException::withMessages(['status' => 'Only confirmed receipts can be reversed.']);
        }

        if ($receipt->reversed_at !== null) {
            throw ValidationException::withMessages(['status' => 'Receipt already reversed.']);
        }

        return DB::transaction(function () use ($receipt, $actorId, $reason) {
            $receipt->load('items.inventoryItem', 'items.purchaseOrderLine');

            // Reverse stock via InventoryStockService returnStock 'out' (receipt was 'in', reverse is 'out')
            $lines = [];
            foreach ($receipt->items as $item) {
                $effectiveQty = (float) $item->received_quantity - (float) $item->rejected_quantity;
                if ($effectiveQty <= 0) continue;
                $lines[] = [
                    'item_id' => $item->inventory_item_id,
                    'quantity' => $effectiveQty,
                    'unit_cost' => (float) $item->unit_cost,
                    'batch_number' => $item->batch_number,
                ];
            }

            if ($lines !== []) {
                $this->stockService->returnStock(
                    $receipt->institute_id,
                    $receipt->branch_id,
                    $receipt->warehouse_id,
                    'out',
                    GoodsReceipt::class,
                    $receipt->id,
                    $lines,
                    $actorId,
                    ['reason' => $reason ?? 'Reversal of GRN #' . $receipt->receipt_number]
                );
            }

            // Revert PO line quantities
            foreach ($receipt->items as $item) {
                $poLine = $item->purchaseOrderLine;
                $newReceived = max(0, (float) $poLine->received_quantity - (float) $item->received_quantity);
                $newRejected = max(0, (float) $poLine->rejected_quantity - (float) $item->rejected_quantity);
                $poLine->update([
                    'received_quantity' => number_format($newReceived, 4, '.', ''),
                    'rejected_quantity' => number_format($newRejected, 4, '.', ''),
                ]);
            }

            $receipt->update([
                'reversed_at' => now(),
                'reversed_by' => $actorId,
                'reversal_reason' => $reason,
            ]);

            // Sync PO status back
            $po = $receipt->purchaseOrder;
            if ($po) {
                $po->load('lines');
                $this->syncPoStatusAfterReversal($po);
            }

            $this->auditLog($receipt->institute_id, $receipt->branch_id, $actorId, 'update', 'goods_receipt', $receipt->id, ['status' => GoodsReceipt::STATUS_CONFIRMED], ['status' => 'reversed', 'reason' => $reason]);

            return $receipt->fresh('items');
        });
    }

    private function validateOverReceiving(GoodsReceipt $receipt, PurchaseOrder $po): void
    {
        foreach ($receipt->items as $item) {
            $poLine = $item->purchaseOrderLine;
            $orderedQty = (float) $poLine->quantity;
            $previouslyReceived = (float) $poLine->received_quantity;
            $currentReceived = (float) $item->received_quantity;
            $totalReceived = $previouslyReceived + $currentReceived;

            if ($totalReceived > $orderedQty + 0.0001) {
                throw ValidationException::withMessages([
                    "lines.{$item->purchase_order_line_id}.received_quantity" =>
                        "Cannot receive {$currentReceived} more. Ordered: {$orderedQty}, previously received: {$previouslyReceived}.",
                ]);
            }
        }
    }

    private function syncPoLineReceivedQuantities(GoodsReceipt $receipt): void
    {
        foreach ($receipt->items as $item) {
            $poLine = $item->purchaseOrderLine;
            $newReceived = (float) $poLine->received_quantity + (float) $item->received_quantity;
            $newRejected = (float) $poLine->rejected_quantity + (float) $item->rejected_quantity;

            $poLine->update([
                'received_quantity' => number_format($newReceived, 4, '.', ''),
                'rejected_quantity' => number_format($newRejected, 4, '.', ''),
            ]);
        }
    }

    private function syncPoStatus(PurchaseOrder $po): void
    {
        $po->load('lines');

        $allFullyReceived = true;
        $anyReceived = false;

        foreach ($po->lines as $line) {
            $ordered = (float) $line->quantity;
            $received = (float) $line->received_quantity;

            if ($received < $ordered - 0.0001) {
                $allFullyReceived = false;
            }
            if ($received > 0.0001) {
                $anyReceived = true;
            }
        }

        if ($allFullyReceived && $anyReceived) {
            $po->update(['status' => PurchaseOrder::STATUS_FULLY_RECEIVED]);
        } elseif ($anyReceived) {
            if ($po->status === PurchaseOrder::STATUS_APPROVED) {
                $po->update(['status' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED]);
            }
        }
    }

    private function syncPoStatusAfterReversal(PurchaseOrder $po): void
    {
        $po->load('lines');
        $allFullyReceived = true;
        $anyReceived = false;
        foreach ($po->lines as $line) {
            $ordered = (float) $line->quantity;
            $received = (float) $line->received_quantity;
            if ($received < $ordered - 0.0001) $allFullyReceived = false;
            if ($received > 0.0001) $anyReceived = true;
        }
        if ($allFullyReceived && $anyReceived) {
            $po->update(['status' => PurchaseOrder::STATUS_FULLY_RECEIVED]);
        } elseif ($anyReceived) {
            $po->update(['status' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED]);
        } else {
            $po->update(['status' => PurchaseOrder::STATUS_APPROVED]);
        }
    }

    private function validateLine(array $line, PurchaseOrder $po, int $idx, int $instituteId, ?int $branchId): void
    {
        if (empty($line['purchase_order_line_id'])) {
            throw ValidationException::withMessages(["lines.$idx.purchase_order_line_id" => 'PO line reference is required.']);
        }

        $poLine = $po->lines->firstWhere('id', $line['purchase_order_line_id']);
        if (! $poLine) {
            throw ValidationException::withMessages(["lines.$idx.purchase_order_line_id" => 'PO line not found in this order.']);
        }

        if (isset($line['received_quantity'])) {
            $qty = (float) $line['received_quantity'];
            if ($qty <= 0) {
                throw ValidationException::withMessages(["lines.$idx.received_quantity" => 'Received quantity must be greater than 0.']);
            }
        }

        if (isset($line['rejected_quantity'])) {
            $qty = (float) $line['rejected_quantity'];
            if ($qty < 0) {
                throw ValidationException::withMessages(["lines.$idx.rejected_quantity" => 'Rejected quantity cannot be negative.']);
            }
        }

        // Batch/serial/expiry validation
        $itemId = $line['inventory_item_id'] ?? $poLine->inventory_item_id;
        if ($itemId) {
            $item = InventoryItem::withoutGlobalScopes()->where('id', $itemId)->where('institute_id', $instituteId)->first();
            if ($item && $item->requiresBatch()) {
                if (empty($line['batch_number']) && empty($line['lot_number'])) {
                    // Batch is required for medicines/raw materials; allow but warn via validation if capability enabled
                    // Enforce if item requires batch
                    // throw ValidationException::withMessages(["lines.$idx.batch_number" => 'Batch number is required for this item.']);
                }
            }
            if (!empty($line['expiry_date']) && !empty($line['manufacture_date'])) {
                if (strtotime($line['expiry_date']) <= strtotime($line['manufacture_date'])) {
                    throw ValidationException::withMessages(["lines.$idx.expiry_date" => 'Expiry date must be after manufacture date.']);
                }
            }
            if (isset($line['serial_numbers']) && is_array($line['serial_numbers'])) {
                $count = count($line['serial_numbers']);
                $received = (float) ($line['received_quantity'] ?? 0);
                if ($count > 0 && abs($count - $received) > 0.0001) {
                    // For serial tracking, count should match quantity
                    // Only enforce if serial tracking capability is on
                }
            }
        }

        if (isset($line['received_condition']) && $line['received_condition'] !== null) {
            $allowed = ['good','damaged','expired','quarantine'];
            if (! in_array(strtolower($line['received_condition']), $allowed, true)) {
                throw ValidationException::withMessages(["lines.$idx.received_condition" => 'Invalid received condition.']);
            }
        }
    }

    private function assertBranchScope(?int $branchId): void
    {
        if (BranchContext::enabled()) {
            $actorBranch = BranchContext::id();
            if ($branchId !== null && (int) $branchId !== (int) $actorBranch) {
                throw ValidationException::withMessages(['branch_id' => mawa_lang('validation_services.common.branch_scope')]);
            }
        }
    }

    private function assertWarehouse(int $instituteId, ?int $branchId, int $warehouseId): void
    {
        $q = \App\Models\InventoryWarehouse::withoutGlobalScopes()->where('id', $warehouseId)->where('institute_id', $instituteId)->where('is_active', true);
        $warehouse = $q->first();
        if (! $warehouse) {
            throw ValidationException::withMessages(['warehouse_id' => 'Warehouse not found.']);
        }
        if ($branchId !== null && $warehouse->branch_id !== null && (int) $warehouse->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages(['warehouse_id' => 'Warehouse not in your branch.']);
        }
        // Tenant check already via institute_id
    }

    private function assertSupplier(int $instituteId, ?int $branchId, int $supplierId): void
    {
        $q = Party::withoutGlobalScopes()->where('id', $supplierId)->where('institute_id', $instituteId)->whereIn('type', ['supplier','both'])->where('is_active', true);
        $party = $q->first();
        if (! $party) {
            throw ValidationException::withMessages(['supplier_id' => 'Supplier not found or not in scope.']);
        }
        if ($branchId !== null && $party->branch_id !== null && (int) $party->branch_id !== (int) $branchId) {
            // Allow institute-wide supplier (branch null) for any branch, but branch-specific must match
            throw ValidationException::withMessages(['supplier_id' => 'Supplier not in your branch.']);
        }
    }

    private function auditLog(int $instituteId, ?int $branchId, ?int $actorId, string $action, string $entityType, int $entityId, ?array $before = null, ?array $after = null): void
    {
        try {
            AuditLog::create([
                'institute_id' => $instituteId,
                'user_type' => 'institute_user',
                'user_id' => $actorId,
                'action' => $action,
                'module' => 'purchase',
                'record_id' => $entityId,
                'old_values' => $before !== null ? json_encode($before) : null,
                'new_values' => $after !== null ? json_encode($after) : null,
                'ip_address' => request()->ip() ?? null,
                'user_agent' => substr((string) (request()->userAgent() ?? ''), 0, 255) ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            try {
                AuditLog::create([
                    'institute_id' => $instituteId,
                    'user_type' => 'institute_user',
                    'user_id' => $actorId,
                    'action' => $action,
                    'module' => 'purchase',
                    'record_id' => $entityId,
                    'old_values' => $before !== null ? json_encode($before) : null,
                    'new_values' => $after !== null ? json_encode($after) : null,
                    'created_at' => now(),
                ]);
            } catch (\Throwable $ignored) {
            }
        }
    }
}

