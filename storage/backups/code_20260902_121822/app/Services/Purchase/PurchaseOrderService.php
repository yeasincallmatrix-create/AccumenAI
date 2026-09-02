<?php

namespace App\Services\Purchase;

use App\Models\AuditLog;
use App\Models\InstituteUser;
use App\Models\InventoryItem;
use App\Models\InventoryWarehouse;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseQuotation;
use App\Models\TaxGroup;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly PurchaseNumberingService $numbering,
    ) {}

    /**
     * Create a purchase order in draft status.
     */
    public function create(array $data, int $instituteId, ?int $branchId, int $actorId): PurchaseOrder
    {
        $this->assertBranchScope($branchId);

        $supplierId = $data['supplier_id'] ?? null;
        $this->assertSupplier($instituteId, $branchId, $supplierId);

        if (! empty($data['warehouse_id'])) {
            $this->assertWarehouse($instituteId, $branchId, (int) $data['warehouse_id']);
        }

        $lines = $data['items'] ?? $data['lines'] ?? [];
        $this->validateLines($instituteId, $branchId, $lines);

        return DB::transaction(function () use ($instituteId, $branchId, $data, $actorId, $lines) {
            $calculated = $this->calculateTotals($lines, $instituteId);

            $order = PurchaseOrder::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'order_number' => $this->numbering->nextNumber($instituteId, $branchId, 'order'),
                'reference_number' => $data['reference_number'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
                'subtotal' => $calculated['subtotal'],
                'discount_amount' => $calculated['discount_amount'],
                'discount_type' => $data['discount_type'] ?? 'fixed',
                'tax_amount' => $calculated['tax_amount'],
                'grand_total' => $calculated['grand_total'],
                'status' => PurchaseOrder::STATUS_DRAFT,
                'created_by' => $actorId,
            ]);

            foreach ($calculated['lines'] as $idx => $cl) {
                $raw = $lines[$idx];
                PurchaseOrderLine::create([
                    'institute_id' => $instituteId,
                    'order_id' => $order->id,
                    'inventory_item_id' => $raw['inventory_item_id'] ?? null,
                    'description' => $raw['description'] ?? ($raw['inventory_item_id'] ? (InventoryItem::withoutGlobalScopes()->find($raw['inventory_item_id'])?->name ?? '') : ''),
                    'quantity' => $cl['quantity'],
                    'unit' => $raw['unit'] ?? null,
                    'unit_price' => $cl['unit_price'],
                    'discount_amount' => $cl['discount_amount'],
                    'discount_type' => $cl['discount_type'],
                    'discount_rate' => $cl['discount_rate'],
                    'tax_group_id' => $raw['tax_group_id'] ?? null,
                    'tax_rate' => $cl['tax_rate'],
                    'tax_amount' => $cl['tax_amount'],
                    'line_total' => $cl['line_total'],
                    'sort_order' => $idx,
                ]);
            }

            $this->auditLog($instituteId, $branchId, $actorId, 'create', 'purchase_order', $order->id, null, [
                'order_number' => $order->order_number,
                'grand_total' => $order->grand_total,
                'status' => $order->status,
            ]);

            return $order->load('lines');
        });
    }

    public function update(PurchaseOrder $order, array $data, int $actorId): PurchaseOrder
    {
        $this->assertBranchScope($order->branch_id);

        if (! $order->canEdit()) {
            throw ValidationException::withMessages(['status' => 'Only draft orders can be edited.']);
        }

        $instituteId = $order->institute_id;
        $branchId = $order->branch_id;

        if (isset($data['supplier_id'])) {
            $this->assertSupplier($instituteId, $branchId, (int) $data['supplier_id']);
        }
        if (array_key_exists('warehouse_id', $data) && ! empty($data['warehouse_id'])) {
            $this->assertWarehouse($instituteId, $branchId, (int) $data['warehouse_id']);
        }
        $lines = $data['items'] ?? $data['lines'] ?? null;
        if ($lines !== null) {
            $this->validateLines($instituteId, $branchId, $lines);
        } else {
            $lines = $order->lines->toArray();
        }

        return DB::transaction(function () use ($order, $data, $actorId, $instituteId, $lines) {
            $calculated = $this->calculateTotals($lines, $instituteId);

            $order->update([
                'supplier_id' => $data['supplier_id'] ?? $order->supplier_id,
                'warehouse_id' => array_key_exists('warehouse_id', $data) ? $data['warehouse_id'] : $order->warehouse_id,
                'order_date' => $data['order_date'] ?? $order->order_date,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $order->expected_delivery_date,
                'currency_id' => $data['currency_id'] ?? $order->currency_id,
                'notes' => $data['notes'] ?? $order->notes,
                'terms_conditions' => $data['terms_conditions'] ?? $order->terms_conditions,
                'reference_number' => $data['reference_number'] ?? $order->reference_number,
                'subtotal' => $calculated['subtotal'],
                'discount_amount' => $calculated['discount_amount'],
                'discount_type' => $data['discount_type'] ?? $order->discount_type,
                'tax_amount' => $calculated['tax_amount'],
                'grand_total' => $calculated['grand_total'],
                'updated_by' => $actorId,
            ]);

            // Replace lines
            $order->lines()->delete();
            foreach ($calculated['lines'] as $idx => $cl) {
                $raw = $lines[$idx];
                PurchaseOrderLine::create([
                    'institute_id' => $instituteId,
                    'order_id' => $order->id,
                    'inventory_item_id' => $raw['inventory_item_id'] ?? null,
                    'description' => $raw['description'] ?? '',
                    'quantity' => $cl['quantity'],
                    'unit' => $raw['unit'] ?? null,
                    'unit_price' => $cl['unit_price'],
                    'discount_amount' => $cl['discount_amount'],
                    'discount_type' => $cl['discount_type'],
                    'discount_rate' => $cl['discount_rate'],
                    'tax_group_id' => $raw['tax_group_id'] ?? null,
                    'tax_rate' => $cl['tax_rate'],
                    'tax_amount' => $cl['tax_amount'],
                    'line_total' => $cl['line_total'],
                    'sort_order' => $idx,
                ]);
            }

            $this->auditLog($instituteId, $order->branch_id, $actorId, 'update', 'purchase_order', $order->id, null, [
                'grand_total' => $order->fresh()->grand_total,
                'status' => $order->status,
            ]);

            return $order->fresh('lines');
        });
    }

    public function submit(PurchaseOrder $order, int $actorId): PurchaseOrder
    {
        $this->assertBranchScope($order->branch_id);
        $this->assertTransition($order, PurchaseOrder::STATUS_SUBMITTED);

        return $this->transition($order, PurchaseOrder::STATUS_SUBMITTED, $actorId, ['submitted_at' => now()]);
    }

    public function approve(PurchaseOrder $order, int $actorId): PurchaseOrder
    {
        $this->assertBranchScope($order->branch_id);
        $this->assertTransition($order, PurchaseOrder::STATUS_APPROVED);

        // Check actor has purchase.manage permission (or is owner)
        $actor = InstituteUser::withoutGlobalScopes()->where('id', $actorId)->first();
        if ($actor) {
            if (! $actor->hasPermission('purchase.manage') && ! $actor->isOwner()) {
                // Also allow if actor is not found to be owner via institute ownership check fallback
                throw ValidationException::withMessages(['status' => 'You do not have permission to approve purchase orders.']);
            }
        }

        if ($order->created_by !== null && (int) $order->created_by === (int) $actorId) {
            throw ValidationException::withMessages(['status' => 'You cannot approve your own purchase order.']);
        }

        return $this->transition($order, PurchaseOrder::STATUS_APPROVED, $actorId, ['approved_by' => $actorId, 'approved_at' => now()]);
    }

    public function reject(PurchaseOrder $order, int $actorId): PurchaseOrder
    {
        $this->assertBranchScope($order->branch_id);
        // Spec: submitted -> cancelled (or rejected). PurchaseOrder has no rejected status, use cancelled.
        $this->assertTransition($order, PurchaseOrder::STATUS_CANCELLED);

        if ($order->status !== PurchaseOrder::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Only submitted orders can be rejected.']);
        }

        return $this->transition($order, PurchaseOrder::STATUS_CANCELLED, $actorId);
    }

    public function cancel(PurchaseOrder $order, int $actorId): PurchaseOrder
    {
        $this->assertBranchScope($order->branch_id);

        if (! $order->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => 'Order cannot be cancelled in its current status.']);
        }

        $this->assertTransition($order, PurchaseOrder::STATUS_CANCELLED);

        return $this->transition($order, PurchaseOrder::STATUS_CANCELLED, $actorId);
    }

    public function close(PurchaseOrder $order, int $actorId): PurchaseOrder
    {
        $this->assertBranchScope($order->branch_id);

        if ($order->status !== PurchaseOrder::STATUS_FULLY_RECEIVED) {
            throw ValidationException::withMessages(['status' => 'Only fully received orders can be closed.']);
        }

        $this->assertTransition($order, PurchaseOrder::STATUS_CLOSED);

        return $this->transition($order, PurchaseOrder::STATUS_CLOSED, $actorId);
    }

    /**
     * Create a purchase order from an accepted quotation.
     * Mirrors SalesOrderService::createFromQuotation — preserves supplier & commercial values, never mutates quotation totals.
     * Guards: quotation must be accepted, not already converted, institute/branch must match (404 if not), lock via lockForUpdate, idempotent.
     */
    public function createFromQuotation(PurchaseQuotation $quotation, array $overrides, int $actorId): PurchaseOrder
    {
        if ($quotation->status !== PurchaseQuotation::STATUS_ACCEPTED) {
            throw ValidationException::withMessages(['quotation_id' => 'Only accepted quotations can be converted.']);
        }
        if ($quotation->converted_to_order_id !== null) {
            throw ValidationException::withMessages(['quotation_id' => 'Quotation already converted to order #'.$quotation->converted_to_order_id]);
        }

        // Institute/branch must match — 404 if not (tenant/branch scoped)
        if (TenantContext::enabled() && (int) $quotation->institute_id !== (int) TenantContext::id()) {
            abort(404, 'Quotation not found.');
        }
        // Also ensure actor's institute matches quotation (for direct service calls where TenantContext may not be set)
        $actor = InstituteUser::withoutGlobalScopes()->where('id', $actorId)->first();
        if ($actor && (int) $actor->institute_id !== (int) $quotation->institute_id) {
            throw ValidationException::withMessages(['quotation_id' => 'Quotation not found or not in scope.']);
        }
        $this->assertBranchScope($quotation->branch_id);

        $instituteId = $quotation->institute_id;
        $branchId = $quotation->branch_id;

        return DB::transaction(function () use ($quotation, $overrides, $actorId, $instituteId, $branchId) {
            // Lock quotation row to prevent concurrent conversion
            $locked = PurchaseQuotation::withoutGlobalScopes()
                ->where('id', $quotation->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                abort(404, 'Quotation not found.');
            }

            // Refresh and re-check converted guard inside lock (idempotent)
            $quotation->refresh();
            if ($quotation->converted_to_order_id !== null || $locked->converted_to_order_id !== null) {
                throw ValidationException::withMessages(['quotation_id' => 'Quotation already converted.']);
            }

            // Additional DB check via where converted_to_order_id (as spec states)
            $alreadyConverted = PurchaseQuotation::withoutGlobalScopes()
                ->where('id', $quotation->id)
                ->whereNotNull('converted_to_order_id')
                ->exists();
            if ($alreadyConverted) {
                throw ValidationException::withMessages(['quotation_id' => 'Quotation already converted.']);
            }

            $quotation->load(['lines']);

            if ($quotation->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Quotation has no lines to convert.']);
            }

            // Copy lines (inventory_item_id, description, quantity, unit, unit_price, discount_amount/type, tax_group_id) with BCMath recalc
            $calcLines = [];
            foreach ($quotation->lines as $qLine) {
                $calcLines[] = [
                    'inventory_item_id' => $qLine->inventory_item_id,
                    'description' => $qLine->description,
                    'quantity' => $qLine->quantity,
                    'unit' => $qLine->unit,
                    'unit_price' => $qLine->unit_price,
                    'discount_amount' => $qLine->discount_amount,
                    'discount_type' => $qLine->discount_type,
                    'tax_group_id' => $qLine->tax_group_id,
                    'tax_rate' => $qLine->tax_rate,
                ];
            }

            // BCMath recalc via same logic as calculateLine/calculateTotals
            $calculated = $this->calculateTotals($calcLines, $instituteId);

            // Header discount recovery: preserve supplier/never mutate commercial values
            // If quotation had header discount on top of line discounts, recover it so totals match
            // calculateTotals sums line discounts only; if quotation discount_amount > line sum, treat remainder as header
            $lineDiscountSum = collect($calculated['lines'])->sum(fn ($l) => (float) $l['discount_amount']);
            $quotationHeaderDiscount = max(0, (float) $quotation->discount_amount - $lineDiscountSum);
            if ($quotationHeaderDiscount > 0.0001) {
                // Re-calculate with header discount applied (fixed) to preserve grand_total exactly
                // We treat header discount as additional fixed discount; grand_total recomputed accordingly
                // Keep subtotal/tax as recalculated, but discount_amount becomes quotation discount_amount
                $subtotal = $calculated['subtotal'];
                $taxAmount = $calculated['tax_amount'];
                $discountAmount = $this->round4(number_format((float) $quotation->discount_amount, 4, '.', ''));
                $grandTotal = bcadd(bcsub($subtotal, $discountAmount, 8), $taxAmount, 8);
                if (bccomp($grandTotal, '0', 8) < 0) {
                    $grandTotal = '0';
                }
                $calculated['discount_amount'] = $this->round4($discountAmount);
                $calculated['grand_total'] = $this->round4($grandTotal);
            }

            // Allow overrides for order_date, expected_delivery_date, notes etc. Preserve supplier, never mutate commercial values
            if (! empty($overrides['warehouse_id'])) {
                $this->assertWarehouse($instituteId, $branchId, (int) $overrides['warehouse_id']);
            }

            $order = PurchaseOrder::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'order_number' => $this->numbering->nextNumber($instituteId, $branchId, 'order'),
                'reference_number' => $overrides['reference_number'] ?? null,
                'supplier_id' => $quotation->supplier_id,
                'warehouse_id' => $overrides['warehouse_id'] ?? null,
                'order_date' => $overrides['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $overrides['expected_delivery_date'] ?? null,
                'currency_id' => $overrides['currency_id'] ?? $quotation->currency_id,
                'notes' => $overrides['notes'] ?? $quotation->notes,
                'terms_conditions' => $overrides['terms_conditions'] ?? $quotation->terms_conditions,
                'subtotal' => $calculated['subtotal'],
                'discount_amount' => $calculated['discount_amount'],
                'discount_type' => $overrides['discount_type'] ?? $quotation->discount_type,
                'tax_amount' => $calculated['tax_amount'],
                'grand_total' => $calculated['grand_total'],
                'status' => PurchaseOrder::STATUS_DRAFT,
                'created_by' => $actorId,
            ]);

            foreach ($calculated['lines'] as $idx => $cl) {
                $raw = $calcLines[$idx];
                PurchaseOrderLine::create([
                    'institute_id' => $instituteId,
                    'order_id' => $order->id,
                    'inventory_item_id' => $raw['inventory_item_id'],
                    'description' => $raw['description'],
                    'quantity' => $cl['quantity'],
                    'unit' => $raw['unit'] ?? null,
                    'unit_price' => $cl['unit_price'],
                    'discount_amount' => $cl['discount_amount'],
                    'discount_type' => $cl['discount_type'],
                    'discount_rate' => $cl['discount_rate'],
                    'tax_group_id' => $raw['tax_group_id'] ?? null,
                    'tax_rate' => $cl['tax_rate'],
                    'tax_amount' => $cl['tax_amount'],
                    'line_total' => $cl['line_total'],
                    'sort_order' => $idx,
                ]);
            }

            // PurchaseOrder has no quotation_id FK; linkage is via quotation.converted_to_order_id + converted_at (not need FK on order)
            $quotation->update([
                'converted_to_order_id' => $order->id,
                'converted_at' => now(),
            ]);

            $this->auditLog($instituteId, $branchId, $actorId, 'create', 'purchase_order', $order->id, null, [
                'order_number' => $order->order_number,
                'quotation_id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'grand_total' => $order->grand_total,
                'status' => $order->status,
            ]);

            $this->auditLog($instituteId, $branchId, $actorId, 'update', 'purchase_quotation', $quotation->id, ['converted_to_order_id' => null], ['converted_to_order_id' => $order->id]);

            return $order->load('lines');
        });
    }

    public function recalculate(PurchaseOrder $order): void
    {
        $order->load('lines');
        $subtotal = '0';
        $discount = '0';
        $tax = '0';
        $grand = '0';

        foreach ($order->lines as $line) {
            $qty = $this->toDecimal($line->quantity);
            $unitPrice = $this->toDecimal($line->unit_price);
            $lineSubtotal = bcmul($qty, $unitPrice, 8);
            $subtotal = bcadd($subtotal, $lineSubtotal, 8);
            $discount = bcadd($discount, $this->toDecimal($line->discount_amount), 8);
            $tax = bcadd($tax, $this->toDecimal($line->tax_amount), 8);
            $grand = bcadd($grand, $this->toDecimal($line->line_total), 8);
        }

        $order->update([
            'subtotal' => $this->round4($subtotal),
            'discount_amount' => $this->round4($discount),
            'tax_amount' => $this->round4($tax),
            'grand_total' => $this->round4($grand),
        ]);
    }

    /**
     * Computes line_total = (quantity * unit_price - discount) + tax.
     * Handles discount_type fixed/percentage, discount_rate, discount_amount, tax_rate from TaxGroup if provided.
     */
    public function calculateLine(array $item): array
    {
        $instituteId = $item['institute_id'] ?? null;

        $qty = $this->toDecimal($item['quantity'] ?? 0);
        $unitPrice = $this->toDecimal($item['unit_price'] ?? 0);
        $lineSubtotal = bcmul($qty, $unitPrice, 8);

        $discountType = strtolower($item['discount_type'] ?? 'fixed');
        // normalize percentage variants
        if ($discountType === 'percentage') {
            $discountType = 'percent';
        }

        $discountAmount = '0';
        if ($discountType === 'percent' || $discountType === 'percentage') {
            $rate = $this->toDecimal($item['discount_rate'] ?? $item['discount_amount'] ?? 0);
            $discountAmount = bcdiv(bcmul($lineSubtotal, $rate, 8), '100', 8);
        } else {
            $discountAmount = $this->toDecimal($item['discount_amount'] ?? 0);
            // if discount_rate provided but type is fixed, also consider it as fixed? Use discount_amount.
            // If discount_rate present and discount_amount is 0, treat rate as amount? Keep fixed.
        }

        if (bccomp($discountAmount, $lineSubtotal, 8) > 0) {
            $discountAmount = $lineSubtotal;
        }
        if (bccomp($discountAmount, '0', 8) < 0) {
            $discountAmount = '0';
        }

        $lineNet = bcsub($lineSubtotal, $discountAmount, 8);

        // Resolve tax rate: TaxGroup overrides explicit tax_rate
        $taxRate = $this->toDecimal($item['tax_rate'] ?? 0);
        $taxGroupId = $item['tax_group_id'] ?? null;
        if ($taxGroupId && $instituteId) {
            $taxGroup = TaxGroup::withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->where('id', $taxGroupId)
                ->first();
            if ($taxGroup) {
                $taxRate = $this->toDecimal($taxGroup->rate);
            }
        } elseif ($taxGroupId) {
            // If institute_id not provided, try without institute filter but still resolve rate
            $taxGroup = TaxGroup::withoutGlobalScopes()->where('id', $taxGroupId)->first();
            if ($taxGroup) {
                $taxRate = $this->toDecimal($taxGroup->rate);
            }
        }

        $taxAmount = '0';
        if (bccomp($taxRate, '0', 8) !== 0) {
            $taxAmount = bcdiv(bcmul($lineNet, $taxRate, 8), '100', 8);
        }

        // Explicit tax_amount override if provided? Respect calculated unless item has tax_amount as fixed override
        // For purchase, we calculate from rate; if item provides tax_amount directly and no rate, use it
        if (isset($item['tax_amount']) && bccomp($taxRate, '0', 8) === 0 && (float) ($item['tax_amount'] ?? 0) !== 0.0) {
            $taxAmount = $this->toDecimal($item['tax_amount']);
        }

        $lineTotal = bcadd($lineNet, $taxAmount, 8);
        if (bccomp($lineTotal, '0', 8) < 0) {
            $lineTotal = '0';
        }

        $discountRateSnapshot = $this->toDecimal($item['discount_rate'] ?? ($discountType === 'percent' ? ($item['discount_amount'] ?? 0) : 0));

        return [
            'quantity' => $this->round4($qty),
            'unit_price' => $this->round4($unitPrice),
            'discount_type' => $discountType === 'percent' ? 'percentage' : 'fixed',
            'discount_rate' => $this->round4($discountRateSnapshot),
            'discount_amount' => $this->round4($discountAmount),
            'tax_rate' => $this->round4($taxRate),
            'tax_amount' => $this->round4($taxAmount),
            'line_total' => $this->round4($lineTotal),
            'line_subtotal' => $this->round4($lineSubtotal),
            'line_net' => $this->round4($lineNet),
        ];
    }

    // ------------------------------------------------------------------ helpers

    private function calculateTotals(array $lines, int $instituteId): array
    {
        $subtotal = '0';
        $totalDiscount = '0';
        $totalTax = '0';
        $computedLines = [];

        foreach ($lines as $idx => $line) {
            // Inject institute_id for TaxGroup resolution
            $line['institute_id'] = $instituteId;
            $calc = $this->calculateLine($line);

            $lineSubtotal = $calc['line_subtotal'];
            $subtotal = bcadd($subtotal, $lineSubtotal, 8);
            $totalDiscount = bcadd($totalDiscount, $calc['discount_amount'], 8);
            $totalTax = bcadd($totalTax, $calc['tax_amount'], 8);

            $computedLines[] = $calc;
        }

        $grandTotal = bcadd(bcsub($subtotal, $totalDiscount, 8), $totalTax, 8);
        if (bccomp($grandTotal, '0', 8) < 0) {
            $grandTotal = '0';
        }

        return [
            'subtotal' => $this->round4($subtotal),
            'discount_amount' => $this->round4($totalDiscount),
            'tax_amount' => $this->round4($totalTax),
            'grand_total' => $this->round4($grandTotal),
            'lines' => $computedLines,
        ];
    }

    private function transition(PurchaseOrder $order, string $target, int $actorId, array $extra = []): PurchaseOrder
    {
        $from = $order->status;

        return DB::transaction(function () use ($order, $target, $actorId, $from, $extra) {
            $order->update(array_merge(['status' => $target, 'updated_by' => $actorId], $extra));

            $this->auditLog($order->institute_id, $order->branch_id, $actorId, 'update', 'purchase_order', $order->id, ['status' => $from], ['status' => $target]);

            return $order->fresh('lines');
        });
    }

    private function assertTransition(PurchaseOrder $order, string $target): void
    {
        if (! $order->canTransitionTo($target)) {
            throw ValidationException::withMessages(['status' => mawa_lang('validation_services.common.transition', ['from' => $order->status, 'to' => $target])]);
        }
    }

    private function assertSupplier(int $instituteId, ?int $branchId, ?int $supplierId): void
    {
        if (! $supplierId) {
            throw ValidationException::withMessages(['supplier_id' => 'Supplier is required.']);
        }

        $party = Party::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('id', $supplierId)
            ->whereIn('type', ['supplier', 'both'])
            ->where('is_active', true)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->first();

        if (! $party) {
            throw ValidationException::withMessages(['supplier_id' => 'Supplier not found or not in scope.']);
        }
    }

    private function assertWarehouse(int $instituteId, ?int $branchId, int $warehouseId): void
    {
        $warehouse = InventoryWarehouse::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('id', $warehouseId)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->first();

        if (! $warehouse) {
            throw ValidationException::withMessages(['warehouse_id' => 'Warehouse not found or not in scope.']);
        }
    }

    private function assertItem(int $instituteId, ?int $branchId, array $line, int $idx): void
    {
        if (! empty($line['inventory_item_id'])) {
            $item = InventoryItem::withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->where('id', $line['inventory_item_id'])
                ->where('is_active', true)
                ->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                })
                ->first();

            if (! $item) {
                throw ValidationException::withMessages(["lines.$idx.inventory_item_id" => 'Product not found or not in scope.']);
            }
        }

        if (! empty($line['tax_group_id'])) {
            $tg = TaxGroup::withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->where('id', $line['tax_group_id'])
                ->first();
            if (! $tg) {
                throw ValidationException::withMessages(["lines.$idx.tax_group_id" => 'Tax group not found.']);
            }
        }
    }

    private function validateLines(int $instituteId, ?int $branchId, array $lines): void
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => 'At least one line is required.']);
        }

        foreach ($lines as $idx => $line) {
            // Normalize keys: allow inventory_item_id optional but require description or item
            if (empty($line['description']) && empty($line['inventory_item_id'])) {
                throw ValidationException::withMessages(["lines.$idx.description" => 'Description or product is required.']);
            }

            if (! isset($line['quantity']) || $line['quantity'] === '' || $line['quantity'] === null) {
                throw ValidationException::withMessages(["lines.$idx.quantity" => 'Quantity is required.']);
            }
            if ((float) $line['quantity'] <= 0) {
                throw ValidationException::withMessages(["lines.$idx.quantity" => 'Quantity must be greater than 0.']);
            }

            if (! isset($line['unit_price']) || $line['unit_price'] === '' || $line['unit_price'] === null) {
                throw ValidationException::withMessages(["lines.$idx.unit_price" => 'Unit price is required.']);
            }
            if ((float) $line['unit_price'] < 0) {
                throw ValidationException::withMessages(["lines.$idx.unit_price" => 'Unit price cannot be negative.']);
            }

            // Discount validation - optional but ensure numeric
            if (isset($line['discount_amount']) && (float) $line['discount_amount'] < 0) {
                throw ValidationException::withMessages(["lines.$idx.discount_amount" => 'Discount cannot be negative.']);
            }
            if (isset($line['discount_rate']) && (float) $line['discount_rate'] < 0) {
                throw ValidationException::withMessages(["lines.$idx.discount_rate" => 'Discount rate cannot be negative.']);
            }

            $this->assertItem($instituteId, $branchId, $line, $idx);
        }
    }

    private function assertBranchScope(?int $branchId): void
    {
        if (BranchContext::enabled()) {
            $actorBranch = BranchContext::id();
            // Branch-restricted actor may only act on institute-wide (null) or own branch
            if ($branchId !== null && (int) $branchId !== (int) $actorBranch) {
                throw ValidationException::withMessages(['branch_id' => 'Branch scope violation.']);
            }
        }
    }

    private function auditLog(int $instituteId, ?int $branchId, ?int $actorId, string $action, string $entityType, int $entityId, ?array $before = null, ?array $after = null): void
    {
        // Prefer audit_logs with module='purchase' as per spec; fallback gracefully if request context missing.
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
            // If request() helper unavailable (e.g. CLI), insert without IP/UA
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
                // Audit must never break core flow
            }
        }
    }

    private function toDecimal(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }

    private function round4(string $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
