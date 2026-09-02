<?php

namespace App\Services\Inventory;

use App\Models\InventoryAdjustment;
use App\Models\InventoryBatch;
use App\Models\InventoryCount;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventorySerialNumber;
use App\Models\InventoryStockLevel;
use App\Models\InventoryTransfer;
use App\Models\InventoryWarehouse;
use App\Models\Journal;
use App\Models\Party;
use App\Services\Accounting\AccountingSetupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inventory stock engine — the single write path for every quantity change.
 *
 * inventory_movements is the source of truth; inventory_stock_levels is the
 * cached balance (per item + warehouse + optional batch) kept in sync with
 * weighted-average costing (the single supported valuation method). Every
 * mutation runs inside a transaction and locks the affected stock-level rows
 * (SELECT ... FOR UPDATE) so concurrent postings cannot corrupt balances.
 *
 * Accounting consequences are delegated to InventoryAccountingService, which
 * posts through JournalPostingService (balance, ownership, fiscal-period
 * locking, immutability, reversals all unchanged). Non-financial events
 * (transfers) post movements only.
 */
class InventoryStockService
{
    public const NEGATIVE_STOCK_EPSILON = 0.00005;

    public function __construct(
        private readonly InventoryCapabilityService $capabilities,
        private readonly InventoryAccountingService $accounting,
        private readonly AccountingSetupService $settings,
    ) {}

    /**
     * Receive purchased goods into a warehouse.
     *
     * Dr Inventory / Cr AP (or cash/bank when paid immediately). Creates
     * receipt movements, updates stock levels (weighted average), creates
     * batches/serials when tracked.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $options
     * @return array{movements: array<int, InventoryMovement>, journal: Journal}
     */
    public function receivePurchase(
        int $instituteId,
        ?int $branchId,
        Party $supplier,
        int $warehouseId,
        array $lines,
        ?int $actorId = null,
        array $options = [],
    ): array {
        $this->capabilities->assert($instituteId, 'inventory.purchase_receipt', $branchId);
        $warehouse = $this->assertWarehouse($instituteId, $branchId, $warehouseId);

        return DB::transaction(function () use ($instituteId, $branchId, $supplier, $warehouse, $lines, $actorId, $options) {
            $movements = [];
            $journalLines = [];

            foreach ($lines as $line) {
                $item = $this->assertItem($instituteId, $branchId, (int) $line['item_id']);
                $quantity = round((float) $line['quantity'], 4);
                $unitCost = round((float) $line['unit_cost'], 4);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'lines.*.quantity' => 'Receipt quantity must be greater than zero.',
                    ]);
                }
                if ($unitCost < 0) {
                    throw ValidationException::withMessages([
                        'lines.*.unit_cost' => 'Receipt unit cost cannot be negative.',
                    ]);
                }

                $batch = $this->resolveBatch($instituteId, $branchId, $item, $warehouse, $line, $unitCost);

                $movement = $this->move(
                    instituteId: $instituteId,
                    branchId: $branchId,
                    warehouseId: $warehouse->id,
                    itemId: $item->id,
                    batchId: $batch?->id,
                    movementType: 'receipt',
                    quantity: $quantity,
                    unitCost: $unitCost,
                    referenceType: $options['reference_type'] ?? 'purchase',
                    referenceId: $options['reference_id'] ?? null,
                    actorId: $actorId,
                    reason: $options['reason'] ?? 'Purchase receipt',
                    occurredAt: $options['occurred_at'] ?? null,
                );

                $this->registerSerials($instituteId, $branchId, $item, $warehouse, $batch, $line['serials'] ?? [], $actorId);

                $movements[] = $movement;
                $journalLines[] = [
                    'item' => $item,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ];
            }

            $journal = $this->accounting->purchaseReceiptJournal(
                $instituteId,
                $branchId,
                $supplier,
                $journalLines,
                actorId: $actorId,
                journalDate: $options['occurred_at'] ?? null,
                description: $options['description'] ?? null,
                refType: $options['reference_type'] ?? 'inventory_receipt',
                refId: $options['reference_id'] ?? null,
                options: $options,
            );

            foreach ($movements as $movement) {
                $movement->forceFill(['journal_id' => $journal->id])->save();
            }

            return ['movements' => $movements, 'journal' => $journal];
        });
    }

    /**
     * Issue stock for a sale / consumption (Dr COGS / Cr Inventory).
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $options
     * @return array{movements: array<int, InventoryMovement>, journal: Journal}
     */
    public function saleIssue(
        int $instituteId,
        ?int $branchId,
        int $warehouseId,
        ?string $referenceType,
        ?int $referenceId,
        array $lines,
        ?int $actorId = null,
        array $options = [],
    ): array {
        $this->capabilities->assert($instituteId, 'inventory.sales_issue', $branchId);
        $warehouse = $this->assertWarehouse($instituteId, $branchId, $warehouseId);

        return DB::transaction(function () use ($instituteId, $branchId, $warehouse, $referenceType, $referenceId, $lines, $actorId, $options) {
            $movements = [];
            $journalLines = [];

            foreach ($lines as $line) {
                $item = $this->assertItem($instituteId, $branchId, (int) $line['item_id']);
                $quantity = round((float) $line['quantity'], 4);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'lines.*.quantity' => 'Issue quantity must be greater than zero.',
                    ]);
                }

                $batch = $this->assertBatch($instituteId, $branchId, $item, $warehouse->id, $line);
                $level = $this->stockLevel($instituteId, $branchId, $warehouse->id, $item->id, $batch?->id, forUpdate: true);

                $unitCost = round((float) ($line['unit_cost'] ?? $level->avg_cost), 4);
                $this->assertSufficientStock($instituteId, $branchId, $level, -$quantity);

                $movement = $this->move(
                    instituteId: $instituteId,
                    branchId: $branchId,
                    warehouseId: $warehouse->id,
                    itemId: $item->id,
                    batchId: $batch?->id,
                    movementType: 'issue',
                    quantity: -$quantity,
                    unitCost: $unitCost,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    actorId: $actorId,
                    reason: $options['reason'] ?? 'Sales issue',
                    occurredAt: $options['occurred_at'] ?? null,
                );

                $this->consumeSerials($instituteId, $branchId, $item, $line['serials'] ?? [], $actorId);

                $movements[] = $movement;
                $journalLines[] = [
                    'item' => $item,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ];
            }

            $journal = $this->accounting->cogsJournal(
                $instituteId,
                $branchId,
                $journalLines,
                actorId: $actorId,
                journalDate: $options['occurred_at'] ?? null,
                description: $options['description'] ?? null,
                refType: $referenceType,
                refId: $referenceId,
                options: $options,
            );

            foreach ($movements as $movement) {
                $movement->forceFill(['journal_id' => $journal->id])->save();
            }

            return ['movements' => $movements, 'journal' => $journal];
        });
    }

    /**
     * Stock transfer between two warehouses of the same tenant (no journal —
     * it has no financial effect).
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $options
     */
    public function transfer(
        int $instituteId,
        ?int $branchId,
        int $sourceWarehouseId,
        int $destinationWarehouseId,
        array $lines,
        ?int $actorId = null,
        array $options = [],
    ): InventoryTransfer {
        $this->capabilities->assert($instituteId, 'inventory.stock_transfer', $branchId);

        $source = $this->assertWarehouse($instituteId, $branchId, $sourceWarehouseId);
        $destination = $this->assertWarehouse($instituteId, $branchId, $destinationWarehouseId);

        if ($source->id === $destination->id) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => 'Source and destination warehouses must differ.',
            ]);
        }

        return DB::transaction(function () use ($instituteId, $branchId, $source, $destination, $lines, $actorId, $options) {
            $transfer = InventoryTransfer::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'source_warehouse_id' => $source->id,
                'destination_warehouse_id' => $destination->id,
                'transfer_no' => $this->documentNumber('IVT', $instituteId, 'inventory_transfers', 'transfer_no'),
                'status' => 'posted',
                'notes' => $options['notes'] ?? null,
                'created_by' => $actorId,
                'posted_by' => $actorId,
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                $item = $this->assertItem($instituteId, $branchId, (int) $line['item_id']);
                $quantity = round((float) $line['quantity'], 4);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'lines.*.quantity' => 'Transfer quantity must be greater than zero.',
                    ]);
                }

                $batch = $this->assertBatch($instituteId, $branchId, $item, $source->id, $line);
                $level = $this->stockLevel($instituteId, $branchId, $source->id, $item->id, $batch?->id, forUpdate: true);
                $this->assertSufficientStock($instituteId, $branchId, $level, -$quantity);
                $unitCost = round((float) $level->avg_cost, 4);

                $this->move(
                    instituteId: $instituteId,
                    branchId: $branchId,
                    warehouseId: $source->id,
                    itemId: $item->id,
                    batchId: $batch?->id,
                    movementType: 'transfer_out',
                    quantity: -$quantity,
                    unitCost: $unitCost,
                    referenceType: 'inventory_transfer',
                    referenceId: $transfer->id,
                    actorId: $actorId,
                    reason: 'Transfer to '.$destination->name,
                    occurredAt: $options['occurred_at'] ?? null,
                );

                $this->move(
                    instituteId: $instituteId,
                    branchId: $branchId,
                    warehouseId: $destination->id,
                    itemId: $item->id,
                    batchId: $batch?->id,
                    movementType: 'transfer_in',
                    quantity: $quantity,
                    unitCost: $unitCost,
                    referenceType: 'inventory_transfer',
                    referenceId: $transfer->id,
                    actorId: $actorId,
                    reason: 'Transfer from '.$source->name,
                    occurredAt: $options['occurred_at'] ?? null,
                );

                $transfer->items()->create([
                    'institute_id' => $instituteId,
                    'branch_id' => $branchId,
                    'item_id' => $item->id,
                    'batch_id' => $batch?->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ]);
            }

            return $transfer->load('items');
        });
    }

    /**
     * Controlled stock adjustment (surplus / deficit / wastage). The journal
     * posts to Inventory Adjustment Income (4005) or Adjustment/Wastage
     * expense (5008 / 5009).
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $options
     * @return array{adjustment: InventoryAdjustment, journal: Journal|null}
     */
    public function postAdjustment(
        int $instituteId,
        ?int $branchId,
        int $warehouseId,
        string $adjustmentType,
        string $reason,
        array $lines,
        ?int $actorId = null,
        array $options = [],
    ): array {
        $capability = $adjustmentType === 'wastage' ? 'inventory.wastage' : 'inventory.stock_adjustment';
        $this->capabilities->assert($instituteId, $capability, $branchId);
        $warehouse = $this->assertWarehouse($instituteId, $branchId, $warehouseId);

        return DB::transaction(function () use ($instituteId, $branchId, $warehouse, $adjustmentType, $reason, $lines, $actorId, $options) {
            $adjustment = InventoryAdjustment::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouse->id,
                'adjustment_no' => $this->documentNumber('IVA', $instituteId, 'inventory_adjustments', 'adjustment_no'),
                'adjustment_type' => $adjustmentType,
                'reason' => $reason,
                'status' => 'posted',
                'created_by' => $actorId,
                'posted_by' => $actorId,
                'posted_at' => now(),
            ]);

            $movements = [];
            $journalLines = [];

            foreach ($lines as $line) {
                $item = $this->assertItem($instituteId, $branchId, (int) $line['item_id']);
                $systemQty = round((float) ($line['system_qty'] ?? 0), 4);
                $countedQty = round((float) ($line['counted_qty'] ?? 0), 4);
                $difference = round($countedQty - $systemQty, 4);

                $batch = $this->assertBatch($instituteId, $branchId, $item, $warehouse->id, $line);
                $level = $this->stockLevel($instituteId, $branchId, $warehouse->id, $item->id, $batch?->id, forUpdate: true);
                $unitCost = round((float) $level->avg_cost, 4);

                if (abs($difference) >= self::NEGATIVE_STOCK_EPSILON) {
                    if ($difference < 0) {
                        $this->assertSufficientStock($instituteId, $branchId, $level, $difference);
                    }

                    $movementType = $adjustmentType === 'wastage'
                        ? 'wastage_out'
                        : ($difference > 0 ? 'adjustment_in' : 'adjustment_out');

                    $movements[] = $this->move(
                        instituteId: $instituteId,
                        branchId: $branchId,
                        warehouseId: $warehouse->id,
                        itemId: $item->id,
                        batchId: $batch?->id,
                        movementType: $movementType,
                        quantity: $difference,
                        unitCost: $unitCost,
                        referenceType: 'inventory_adjustment',
                        referenceId: $adjustment->id,
                        actorId: $actorId,
                        reason: $reason,
                        occurredAt: $options['occurred_at'] ?? null,
                    );

                    $journalLines[] = [
                        'item' => $item,
                        'quantity' => abs($difference),
                        'unit_cost' => $unitCost,
                        'difference' => $difference,
                    ];
                }

                $adjustment->items()->create([
                    'institute_id' => $instituteId,
                    'branch_id' => $branchId,
                    'item_id' => $item->id,
                    'batch_id' => $batch?->id,
                    'system_qty' => $systemQty,
                    'counted_qty' => $countedQty,
                    'difference' => $difference,
                    'unit_cost' => $unitCost,
                ]);
            }

            $journal = $this->accounting->adjustmentJournal(
                $instituteId,
                $branchId,
                $adjustmentType === 'wastage' ? 'wastage_out' : 'adjustment',
                $journalLines,
                actorId: $actorId,
                journalDate: $options['occurred_at'] ?? null,
                description: $reason,
                refType: 'inventory_adjustment',
                refId: $adjustment->id,
                options: $options,
            );

            foreach ($movements as $movement) {
                $movement->forceFill(['journal_id' => $journal?->id])->save();
            }

            if ($journal !== null) {
                $adjustment->forceFill(['journal_id' => $journal->id])->save();
            }

            return ['adjustment' => $adjustment->load('items'), 'journal' => $journal];
        });
    }

    /**
     * Post an APPROVED physical count: applies system-vs-counted differences as
     * stock adjustments (reuses postAdjustment semantics).
     *
     * @param  array<string, mixed>  $options
     * @return array{count: InventoryCount, adjustment: InventoryAdjustment, journal: Journal|null}
     */
    public function postCount(
        int $instituteId,
        ?int $branchId,
        int $countId,
        ?int $actorId = null,
        array $options = [],
    ): array {
        $this->capabilities->assert($instituteId, 'inventory.stock_count', $branchId);

        return DB::transaction(function () use ($instituteId, $branchId, $countId, $actorId, $options) {
            $count = InventoryCount::query()
                ->where('institute_id', $instituteId)
                ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->with('items')
                ->find($countId);

            if ($count === null) {
                throw ValidationException::withMessages(['count_id' => 'The stock count does not exist.']);
            }

            if ($count->status !== 'approved') {
                throw ValidationException::withMessages([
                    'status' => 'Only an approved stock count can be posted.',
                ]);
            }

            if ($count->isPosted()) {
                throw ValidationException::withMessages([
                    'status' => 'The stock count has already been posted.',
                ]);
            }

            $lines = $count->items->map(fn ($item) => [
                'item_id' => $item->item_id,
                'batch_id' => $item->batch_id,
                'system_qty' => $item->system_qty,
                'counted_qty' => $item->counted_qty,
            ])->all();

            ['adjustment' => $adjustment, 'journal' => $journal] = $this->postAdjustment(
                $instituteId,
                $branchId,
                (int) $count->warehouse_id,
                'adjustment',
                'Stock count '.$count->count_no,
                $lines,
                actorId: $actorId,
                options: $options,
            );

            $count->forceFill([
                'status' => 'posted',
                'posted_by' => $actorId,
                'posted_at' => now(),
            ])->save();

            return ['count' => $count, 'adjustment' => $adjustment, 'journal' => $journal];
        });
    }

    /**
     * Return stock into a warehouse (sales return / purchase return reversal).
     * Restocks the item and reverses the financial journal that recorded the
     * original issue/receipt when one is found (reversal_of convention).
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $options
     * @return array{movements: array<int, InventoryMovement>, journal: Journal|null}
     */
    public function returnStock(
        int $instituteId,
        ?int $branchId,
        int $warehouseId,
        string $direction,
        ?string $referenceType,
        ?int $referenceId,
        array $lines,
        ?int $actorId = null,
        array $options = [],
    ): array {
        $this->capabilities->assert($instituteId, 'inventory.stock_return', $branchId);
        $warehouse = $this->assertWarehouse($instituteId, $branchId, $warehouseId);

        return DB::transaction(function () use ($instituteId, $branchId, $warehouse, $direction, $referenceType, $referenceId, $lines, $actorId, $options) {
            $movements = [];

            foreach ($lines as $line) {
                $item = $this->assertItem($instituteId, $branchId, (int) $line['item_id']);
                $quantity = round((float) $line['quantity'], 4);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'lines.*.quantity' => 'Return quantity must be greater than zero.',
                    ]);
                }

                $batch = $this->assertBatch($instituteId, $branchId, $item, $warehouse->id, $line);
                $quantity = $this->capReturnQuantity($instituteId, $direction, $referenceType, $referenceId, $item->id, $batch?->id, $quantity);

                if ($quantity <= 0) {
                    continue;
                }

                $level = $this->stockLevel($instituteId, $branchId, $warehouse->id, $item->id, $batch?->id, forUpdate: true);

                $unitCost = round((float) ($line['unit_cost'] ?? $level->avg_cost), 4);

                $movements[] = $this->move(
                    instituteId: $instituteId,
                    branchId: $branchId,
                    warehouseId: $warehouse->id,
                    itemId: $item->id,
                    batchId: $batch?->id,
                    movementType: $direction === 'in' ? 'return_in' : 'return_out',
                    quantity: $direction === 'in' ? $quantity : -$quantity,
                    unitCost: $unitCost,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    actorId: $actorId,
                    reason: $options['reason'] ?? 'Stock return',
                    occurredAt: $options['occurred_at'] ?? null,
                );

                if ($direction === 'in') {
                    $this->registerSerials($instituteId, $branchId, $item, $warehouse, $batch, $line['serials'] ?? [], $actorId);
                } else {
                    $this->consumeSerials($instituteId, $branchId, $item, $line['serials'] ?? [], $actorId);
                }
            }

            $journal = $this->reverseFinancialJournal($instituteId, $branchId, $direction, $referenceType, $referenceId, $actorId, $options);

            foreach ($movements as $movement) {
                $movement->forceFill(['journal_id' => $journal?->id])->save();
            }

            return ['movements' => $movements, 'journal' => $journal];
        });
    }

    /**
     * Cap a return at the quantity originally moved by the reference document,
     * so returns are idempotent (calling twice can never over-return).
     */
    private function capReturnQuantity(int $instituteId, string $direction, ?string $referenceType, ?int $referenceId, int $itemId, ?int $batchId, float $requested): float
    {
        if ($referenceType === null || $referenceId === null) {
            return $requested;
        }
        // Sales/purchase returns are verified against invoice/receipt remaining, not prior stock movements — do not cap.
        if ($referenceType === 'sales_return' || str_contains(strtolower($referenceType), 'return')) {
            return $requested;
        }

        $originalType = $direction === 'in' ? 'issue' : 'receipt';
        $returnType = $direction === 'in' ? 'return_in' : 'return_out';

        $original = round((float) InventoryMovement::query()
            ->where('institute_id', $instituteId)
            ->where('item_id', $itemId)
            ->where('batch_id', $batchId)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('movement_type', $originalType)
            ->sum(DB::raw('ABS(quantity)')), 4);

        $alreadyReturned = round((float) InventoryMovement::query()
            ->where('institute_id', $instituteId)
            ->where('item_id', $itemId)
            ->where('batch_id', $batchId)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('movement_type', $returnType)
            ->sum('quantity'), 4);

        return round(min($requested, $original - $alreadyReturned), 4);
    }

    /**
     * Reverse every movement of a reference document in one direction
     * (e.g. restock everything an invoice issued when it is cancelled).
     *
     * @param  array<string, mixed>  $options
     * @return array{movements: array<int, InventoryMovement>, journal: Journal|null}
     */
    public function returnForReference(
        int $instituteId,
        ?int $branchId,
        string $direction,
        string $referenceType,
        ?int $referenceId,
        ?int $actorId = null,
        array $options = [],
    ): array {
        $originalTypes = $direction === 'in' ? ['issue'] : ['receipt'];

        $movements = InventoryMovement::query()
            ->where('institute_id', $instituteId)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereIn('movement_type', $originalTypes)
            ->get();

        if ($movements->isEmpty()) {
            return ['movements' => [], 'journal' => null];
        }

        $lines = $movements->map(fn ($m) => [
            'item_id' => $m->item_id,
            'batch_id' => $m->batch_id,
            'quantity' => abs((float) $m->quantity),
            'unit_cost' => (float) $m->unit_cost,
        ])->all();

        return $this->returnStock(
            $instituteId,
            $branchId,
            (int) $movements->first()->warehouse_id,
            $direction,
            $referenceType,
            $referenceId,
            $lines,
            $actorId,
            $options,
        );
    }

    /**
     * Raw movement primitive — the ONLY writer of inventory_movements and
     * inventory_stock_levels.
     */
    private function move(
        int $instituteId,
        ?int $branchId,
        int $warehouseId,
        int $itemId,
        ?int $batchId,
        string $movementType,
        float $quantity,
        float $unitCost,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $actorId = null,
        ?string $reason = null,
        ?string $occurredAt = null,
        array $lineMeta = [],
    ): InventoryMovement {
        $quantity = round($quantity, 4);
        $unitCost = round($unitCost, 4);

        $level = $this->stockLevel($instituteId, $branchId, $warehouseId, $itemId, $batchId, forUpdate: true);
        $oldQty = round((float) $level->quantity, 4);
        $oldAvg = round((float) $level->avg_cost, 4);

        if ($quantity > 0) {
            $newQty = round($oldQty + $quantity, 4);
            $newAvg = $oldQty > self::NEGATIVE_STOCK_EPSILON
                ? round(($oldQty * $oldAvg + $quantity * $unitCost) / $newQty, 4)
                : $unitCost;
            $effectiveCost = $unitCost;
        } else {
            $this->assertSufficientStock($instituteId, $branchId, $level, abs($quantity));
            $newQty = round($oldQty + $quantity, 4);
            $newAvg = $oldAvg;
            $effectiveCost = $level->avg_cost > 0 ? round((float) $level->avg_cost, 4) : $unitCost;
        }

        $movement = InventoryMovement::create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'batch_id' => $batchId,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'unit_cost' => $effectiveCost,
            'movement_no' => $this->movementNumber($instituteId),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'occurred_at' => $occurredAt ?? now()->toDateString(),
            'reason' => $reason,
            'line_meta' => $lineMeta ?: null,
            'status' => 'posted',
            'created_by' => $actorId,
        ]);

        $level->forceFill([
            'quantity' => $newQty,
            'avg_cost' => $newAvg,
        ])->save();

        if ($batchId !== null) {
            $batch = InventoryBatch::query()->find($batchId);
            if ($batch !== null) {
                $batchQty = round((float) $batch->quantity + $quantity, 4);
                $batch->forceFill([
                    'quantity' => max($batchQty, 0),
                    'unit_cost' => $quantity > 0 ? $effectiveCost : $batch->unit_cost,
                ])->save();
            }
        }

        return $movement;
    }

    /**
     * Lock (or create) the stock-level row for the item/warehouse/batch key.
     */
    private function stockLevel(int $instituteId, ?int $branchId, int $warehouseId, int $itemId, ?int $batchId, bool $forUpdate = false): InventoryStockLevel
    {
        $query = InventoryStockLevel::query()
            ->where('institute_id', $instituteId)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->where('batch_id', $batchId);

        if ($forUpdate) {
            $level = $query->lockForUpdate()->first();
        } else {
            $level = $query->first();
        }

        if ($level === null) {
            $level = InventoryStockLevel::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'item_id' => $itemId,
                'batch_id' => $batchId,
                'quantity' => 0,
                'avg_cost' => 0,
            ]);
        }

        return $level;
    }

    private function assertSufficientStock(int $instituteId, ?int $branchId, InventoryStockLevel $level, float $quantity): void
    {
        $allowNegative = (bool) $this->settings->getSetting($instituteId, 'inventory.allow_negative_stock', false, $branchId);

        if (! $allowNegative && round((float) $level->quantity, 4) + $quantity < -self::NEGATIVE_STOCK_EPSILON) {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient stock: only '.round((float) $level->quantity, 4).' available.',
            ]);
        }
    }

    private function registerSerials(int $instituteId, ?int $branchId, InventoryItem $item, InventoryWarehouse $warehouse, ?InventoryBatch $batch, array $serials, ?int $actorId): void
    {
        if ($serials === []) {
            return;
        }

        foreach ($serials as $serialNumber) {
            InventorySerialNumber::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'item_id' => $item->id,
                'batch_id' => $batch?->id,
                'warehouse_id' => $warehouse->id,
                'serial_number' => (string) $serialNumber,
                'status' => 'in_stock',
                'created_by' => $actorId,
            ]);
        }
    }

    private function consumeSerials(int $instituteId, ?int $branchId, InventoryItem $item, array $serials, ?int $actorId): void
    {
        if ($serials === []) {
            return;
        }

        foreach ($serials as $serialNumber) {
            $serial = InventorySerialNumber::query()
                ->where('institute_id', $instituteId)
                ->where('item_id', $item->id)
                ->where('serial_number', (string) $serialNumber)
                ->lockForUpdate()
                ->first();

            if ($serial === null || $serial->status !== 'in_stock') {
                throw ValidationException::withMessages([
                    'serials' => 'Serial number "'.$serialNumber.'" is not available for issue.',
                ]);
            }

            $serial->forceFill(['status' => 'sold'])->save();
        }
    }

    private function resolveBatch(int $instituteId, ?int $branchId, InventoryItem $item, InventoryWarehouse $warehouse, array $line, float $unitCost): ?InventoryBatch
    {
        $batchId = $line['batch_id'] ?? null;

        if ($batchId !== null) {
            return $this->assertBatch($instituteId, $branchId, $item, $warehouse->id, $line);
        }

        $tracking = $this->capabilities->has($instituteId, 'inventory.batch_tracking', $branchId);
        $batchNumber = $line['batch_number'] ?? null;

        if (! $tracking && ! $item->requiresBatch() && $batchNumber === null) {
            return null;
        }

        if ($batchNumber === null) {
            throw ValidationException::withMessages([
                'lines.*.batch_number' => 'A batch number is required for this item.',
            ]);
        }

        $batch = InventoryBatch::query()
            ->where('institute_id', $instituteId)
            ->where('item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('batch_number', $batchNumber)
            ->first();

        if ($batch === null) {
            $batch = InventoryBatch::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'batch_number' => $batchNumber,
                'manufacture_date' => $line['manufacture_date'] ?? null,
                'expiry_date' => $line['expiry_date'] ?? null,
                'quantity' => 0,
                'unit_cost' => $unitCost,
                'created_by' => $line['created_by'] ?? null,
            ]);
        }

        return $batch;
    }

    private function assertBatch(int $instituteId, ?int $branchId, InventoryItem $item, int $warehouseId, array $line): ?InventoryBatch
    {
        $batchId = $line['batch_id'] ?? null;

        if ($batchId === null) {
            return null;
        }

        $batch = InventoryBatch::query()
            ->where('institute_id', $instituteId)
            ->where('item_id', $item->id)
            ->where('warehouse_id', $warehouseId)
            ->find((int) $batchId);

        if ($batch === null) {
            throw ValidationException::withMessages([
                'lines.*.batch_id' => 'The selected batch does not exist in this warehouse.',
            ]);
        }

        return $batch;
    }

    private function assertItem(int $instituteId, ?int $branchId, int $itemId): InventoryItem
    {
        $item = InventoryItem::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->where('is_active', true)
            ->find($itemId);

        if ($item === null) {
            throw ValidationException::withMessages([
                'item_id' => 'The selected inventory item does not exist.',
            ]);
        }

        return $item;
    }

    private function assertWarehouse(int $instituteId, ?int $branchId, int $warehouseId): InventoryWarehouse
    {
        $warehouse = InventoryWarehouse::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->where('is_active', true)
            ->find($warehouseId);

        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'The selected warehouse does not exist.',
            ]);
        }

        return $warehouse;
    }

    private function reverseFinancialJournal(
        int $instituteId,
        ?int $branchId,
        string $direction,
        ?string $referenceType,
        ?int $referenceId,
        ?int $actorId,
        array $options,
    ): ?Journal {
        if ($referenceType === null || $referenceId === null) {
            return null;
        }

        $type = $direction === 'in' ? 'adjustment' : 'purchase';

        return $this->accounting->reverseJournal(
            $instituteId,
            $branchId,
            $type,
            $referenceType,
            $referenceId,
            $actorId,
            $options['reason'] ?? 'Stock return reversal',
        );
    }

    private function movementNumber(int $instituteId): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = 'IVM-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
            $exists = InventoryMovement::query()->where('institute_id', $instituteId)->where('movement_no', $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
        }
        throw new \RuntimeException('Could not allocate a unique inventory movement number.');
    }

    private function documentNumber(string $prefix, int $instituteId, string $table, string $column): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
            $exists = DB::table($table)->where('institute_id', $instituteId)->where($column, $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
        }
        throw new \RuntimeException("Could not allocate a unique document number for {$prefix}.");
    }
}
