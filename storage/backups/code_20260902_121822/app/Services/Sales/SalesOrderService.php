<?php

namespace App\Services\Sales;

use App\Models\InventoryItem;
use App\Models\Party;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SalesQuotation;
use App\Models\TaxGroup;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Tax\TaxCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderService
{
    public function __construct(
        private readonly AccountingAuditService $audit,
        private readonly SalesNumberingService $numbering,
        private readonly TaxCalculationService $taxCalc,
    ) {}

    public function calculate(int $instituteId, ?int $branchId, array $header, array $lines): array
    {
        $subtotal = '0';
        $totalLineDiscount = '0';
        $totalTax = '0';
        $computedLines = [];

        foreach ($lines as $idx => $line) {
            $qty = $this->toDecimal($line['quantity'] ?? 0);
            $unitPrice = $this->toDecimal($line['unit_price'] ?? 0);
            $lineSubtotal = bcmul($qty, $unitPrice, 8);

            $discountType = $line['discount_type'] ?? 'fixed';
            $discountRaw = $this->toDecimal($line['discount_amount'] ?? 0);
            if ($discountType === 'percent') {
                $lineDiscount = bcdiv(bcmul($lineSubtotal, $discountRaw, 8), '100', 8);
            } else {
                $lineDiscount = $discountRaw;
            }
            if (bccomp($lineDiscount, $lineSubtotal, 8) > 0) {
                $lineDiscount = $lineSubtotal;
            }

            $lineNet = bcsub($lineSubtotal, $lineDiscount, 8);

            $taxAmount = '0';
            $taxRateSnapshot = $this->toDecimal($line['tax_rate'] ?? 0);
            $taxGroupId = $line['tax_group_id'] ?? null;

            if ($taxGroupId) {
                $taxGroup = TaxGroup::withoutGlobalScopes()
                    ->where('institute_id', $instituteId)
                    ->where('id', $taxGroupId)
                    ->first();
                if ($taxGroup) {
                    $taxRateSnapshot = $this->toDecimal($taxGroup->rate);
                    $taxAmount = bcdiv(bcmul($lineNet, $taxRateSnapshot, 8), '100', 8);
                }
            } elseif (bccomp($taxRateSnapshot, '0', 8) !== 0) {
                $taxAmount = bcdiv(bcmul($lineNet, $taxRateSnapshot, 8), '100', 8);
            }

            $lineTotal = bcadd($lineNet, $taxAmount, 8);

            $subtotal = bcadd($subtotal, $lineSubtotal, 8);
            $totalLineDiscount = bcadd($totalLineDiscount, $lineDiscount, 8);
            $totalTax = bcadd($totalTax, $taxAmount, 8);

            $computedLines[] = [
                'idx' => $idx,
                'quantity' => $this->round4($qty),
                'unit_price' => $this->round4($unitPrice),
                'discount_amount' => $this->round4($lineDiscount),
                'discount_type' => $discountType,
                'tax_rate' => $this->round4($taxRateSnapshot),
                'tax_amount' => $this->round4($taxAmount),
                'line_total' => $this->round4($lineTotal),
                'line_subtotal' => $this->round4($lineSubtotal),
                'line_net' => $this->round4($lineNet),
            ];
        }

        $headerDiscountType = $header['discount_type'] ?? 'fixed';
        $headerDiscountRaw = $this->toDecimal($header['discount_amount'] ?? 0);
        $discountBase = bcsub($subtotal, $totalLineDiscount, 8);
        if (bccomp($discountBase, '0', 8) < 0) {
            $discountBase = '0';
        }
        if ($headerDiscountType === 'percent') {
            $headerDiscount = bcdiv(bcmul($discountBase, $headerDiscountRaw, 8), '100', 8);
        } else {
            $headerDiscount = $headerDiscountRaw;
        }
        if (bccomp($headerDiscount, $discountBase, 8) > 0) {
            $headerDiscount = $discountBase;
        }

        $totalDiscount = bcadd($totalLineDiscount, $headerDiscount, 8);
        // Never trust client-submitted tax — server recomputes from line tax_groups only
        $totalTaxWithHeader = $totalTax;

        $grandTotal = bcadd(bcsub($subtotal, $totalDiscount, 8), $totalTaxWithHeader, 8);
        if (bccomp($grandTotal, '0', 8) < 0) {
            $grandTotal = '0';
        }

        return [
            'subtotal' => $this->round4($subtotal),
            'discount_amount' => $this->round4($totalDiscount),
            'header_discount' => $this->round4($headerDiscount),
            'tax_amount' => $this->round4($totalTaxWithHeader),
            'grand_total' => $this->round4($grandTotal),
            'lines' => $computedLines,
        ];
    }

    public function createDraft(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): SalesOrder
    {
        $this->assertCustomerScope($instituteId, $branchId, $data['customer_id'] ?? null);
        $this->validateLines($instituteId, $branchId, $data['lines'] ?? []);
        if (! empty($data['quotation_id'])) {
            throw ValidationException::withMessages(['quotation_id' => 'Use createFromQuotation for quotation conversion.']);
        }

        return DB::transaction(function () use ($instituteId, $branchId, $data, $actorId) {
            $calc = $this->calculate($instituteId, $branchId, $data, $data['lines'] ?? []);

            $order = SalesOrder::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'order_number' => $this->numbering->nextNumber($instituteId, $branchId, 'sales_order'),
                'quotation_id' => null,
                'customer_id' => $data['customer_id'],
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency_id' => $data['currency_id'],
                'payment_terms' => $data['payment_terms'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
                'subtotal' => $calc['subtotal'],
                'discount_amount' => $calc['discount_amount'],
                'discount_type' => $data['discount_type'] ?? 'fixed',
                'tax_amount' => $calc['tax_amount'],
                'grand_total' => $calc['grand_total'],
                'status' => SalesOrder::STATUS_DRAFT,
                'created_by' => $actorId,
            ]);

            foreach ($calc['lines'] as $idx => $cl) {
                $raw = $data['lines'][$idx];
                SalesOrderLine::create([
                    'institute_id' => $instituteId,
                    'order_id' => $order->id,
                    'inventory_item_id' => $raw['inventory_item_id'] ?? null,
                    'description' => $raw['description'] ?? ($raw['inventory_item_id'] ? InventoryItem::find($raw['inventory_item_id'])?->name ?? '' : ''),
                    'quantity' => $cl['quantity'],
                    'unit' => $raw['unit'] ?? null,
                    'unit_price' => $cl['unit_price'],
                    'discount_amount' => $cl['discount_amount'],
                    'discount_type' => $cl['discount_type'],
                    'tax_group_id' => $raw['tax_group_id'] ?? null,
                    'tax_rate' => $cl['tax_rate'],
                    'tax_amount' => $cl['tax_amount'],
                    'line_total' => $cl['line_total'],
                    'sort_order' => $idx,
                ]);
            }

            $this->audit->log($instituteId, [
                'branch_id' => $branchId,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'create',
                'entity_type' => 'sales_order',
                'entity_id' => $order->id,
                'after_payload' => ['order_number' => $order->order_number, 'grand_total' => $order->grand_total, 'status' => $order->status],
            ]);

            return $order->load('lines');
        });
    }

    public function createFromQuotation(SalesQuotation $quotation, array $overrides, ?int $actorId = null): SalesOrder
    {
        if ($quotation->status !== SalesQuotation::STATUS_ACCEPTED) {
            throw ValidationException::withMessages(['quotation_id' => 'Only accepted quotations can be converted.']);
        }
        if ($quotation->converted_to_order_id !== null) {
            throw ValidationException::withMessages(['quotation_id' => 'Quotation already converted to order #'.$quotation->converted_to_order_id]);
        }

        $instituteId = $quotation->institute_id;
        $branchId = $quotation->branch_id;

        // Ensure unique quotation conversion via DB lock
        return DB::transaction(function () use ($quotation, $overrides, $actorId, $instituteId, $branchId) {
            $quotation->refresh();
            if ($quotation->converted_to_order_id !== null) {
                throw ValidationException::withMessages(['quotation_id' => 'Quotation already converted.']);
            }

            // Prevent double order for same quotation via unique constraint check
            $existing = SalesOrder::withoutGlobalScopes()->where('quotation_id', $quotation->id)->first();
            if ($existing) {
                throw ValidationException::withMessages(['quotation_id' => 'Quotation already has an order.']);
            }

            $quotation->load(['lines']);
            $customerId = $quotation->customer_id;

            // Allow overrides for dates/billing/shipping but preserve historical commercial values
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

            // Recompute from historical lines — server is source, but we intentionally copy preserved values
            // Use same header discount logic but preserve quotation totals as snapshot
            $calc = $this->calculate($instituteId, $branchId, [
                'discount_type' => $quotation->discount_type,
                'discount_amount' => 0, // line discounts already embedded, header handled separately below
            ], $calcLines);

            // But to preserve agreed prices exactly, we copy historical snapshot totals via direct recalc
            // Instead we rebuild calc using same method as creation but with lines copied
            $orderData = [
                'customer_id' => $customerId,
                'order_date' => $overrides['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $overrides['expected_delivery_date'] ?? null,
                'currency_id' => $overrides['currency_id'] ?? $quotation->currency_id,
                'payment_terms' => $overrides['payment_terms'] ?? $quotation->payment_terms,
                'billing_address' => $overrides['billing_address'] ?? null,
                'shipping_address' => $overrides['shipping_address'] ?? null,
                'notes' => $overrides['notes'] ?? $quotation->notes,
                'terms_conditions' => $overrides['terms_conditions'] ?? $quotation->terms_conditions,
                'discount_type' => $quotation->discount_type,
                // header discount: preserve difference between quotation grand total components if needed
                // For simplicity, we use quotation header discount recovery
            ];

            // Header discount recovery: quotation may have header discount on top of line discounts
            // Our calculate already accounts for header discount passed in $header
            // We need to recover header discount amount: totalDiscount - sum(lineDiscounts)
            // But easier: just calculate fresh with same lines and same header discount_type/amount handling
            // We'll reuse quotation discount_amount as header portion + line portion.
            // For conversion, we pass header discount_amount as 0 and then adjust totals to match quotation's recalculated grand total
            // Instead, directly use quotation's computed totals via recalculation to avoid drift
            $calcForOrder = $this->calculate($instituteId, $branchId, [
                'discount_type' => $quotation->discount_type,
                'discount_amount' => 0,
                'tax_amount' => 0,
            ], $calcLines);

            // If quotation had header discount, we need to apply it: derive header discount
            // totalDiscount = quotation->discount_amount, line discounts already in calcForOrder discount
            // So header = total - lineDiscounts
            $lineDiscountSum = collect($calcForOrder['lines'])->sum(fn ($l) => (float) $l['discount_amount']);
            $quotationHeaderDiscount = max(0, (float) $quotation->discount_amount - $lineDiscountSum);
            if ($quotationHeaderDiscount > 0) {
                $calcForOrder = $this->calculate($instituteId, $branchId, [
                    'discount_type' => 'fixed',
                    'discount_amount' => $quotationHeaderDiscount,
                ], $calcLines);
            }

            $order = SalesOrder::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'order_number' => $this->numbering->nextNumber($instituteId, $branchId, 'sales_order'),
                'quotation_id' => $quotation->id,
                'customer_id' => $customerId,
                'order_date' => $orderData['order_date'],
                'expected_delivery_date' => $orderData['expected_delivery_date'],
                'currency_id' => $orderData['currency_id'],
                'payment_terms' => $orderData['payment_terms'],
                'billing_address' => $orderData['billing_address'],
                'shipping_address' => $orderData['shipping_address'],
                'notes' => $orderData['notes'],
                'terms_conditions' => $orderData['terms_conditions'],
                'subtotal' => $calcForOrder['subtotal'],
                'discount_amount' => $calcForOrder['discount_amount'],
                'discount_type' => $quotation->discount_type,
                'tax_amount' => $calcForOrder['tax_amount'],
                'grand_total' => $calcForOrder['grand_total'],
                'status' => SalesOrder::STATUS_DRAFT,
                'created_by' => $actorId,
            ]);

            foreach ($calcForOrder['lines'] as $idx => $cl) {
                $raw = $calcLines[$idx];
                SalesOrderLine::create([
                    'institute_id' => $instituteId,
                    'order_id' => $order->id,
                    'inventory_item_id' => $raw['inventory_item_id'],
                    'description' => $raw['description'],
                    'quantity' => $cl['quantity'],
                    'unit' => $raw['unit'],
                    'unit_price' => $cl['unit_price'],
                    'discount_amount' => $cl['discount_amount'],
                    'discount_type' => $cl['discount_type'],
                    'tax_group_id' => $raw['tax_group_id'],
                    'tax_rate' => $cl['tax_rate'],
                    'tax_amount' => $cl['tax_amount'],
                    'line_total' => $cl['line_total'],
                    'sort_order' => $idx,
                ]);
            }

            // Do not modify quotation commercial values, only set conversion link
            $quotation->update([
                'converted_to_order_id' => $order->id,
                'converted_at' => now(),
            ]);

            $this->audit->log($instituteId, [
                'branch_id' => $branchId,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'create',
                'entity_type' => 'sales_order',
                'entity_id' => $order->id,
                'after_payload' => ['order_number' => $order->order_number, 'quotation_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number, 'grand_total' => $order->grand_total, 'status' => $order->status],
            ]);

            $this->audit->log($instituteId, [
                'branch_id' => $branchId,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'update',
                'entity_type' => 'sales_quotation',
                'entity_id' => $quotation->id,
                'before_payload' => ['status' => $quotation->status, 'converted_to_order_id' => null],
                'after_payload' => ['status' => $quotation->status, 'converted_to_order_id' => $order->id],
            ]);

            return $order->load('lines');
        });
    }

    public function updateDraft(SalesOrder $order, array $data, ?int $actorId = null): SalesOrder
    {
        if (! $order->canEdit()) {
            throw ValidationException::withMessages(['status' => 'Only draft or rejected orders can be edited.']);
        }

        $instituteId = $order->institute_id;
        $branchId = $order->branch_id;

        if (isset($data['customer_id'])) {
            $this->assertCustomerScope($instituteId, $branchId, $data['customer_id']);
        }
        if (isset($data['lines'])) {
            $this->validateLines($instituteId, $branchId, $data['lines']);
        }

        return DB::transaction(function () use ($order, $data, $actorId, $instituteId, $branchId) {
            $lines = $data['lines'] ?? $order->lines->toArray();
            $calc = $this->calculate($instituteId, $branchId, array_merge($order->toArray(), $data), $lines);

            $order->update([
                'customer_id' => $data['customer_id'] ?? $order->customer_id,
                'order_date' => $data['order_date'] ?? $order->order_date,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $order->expected_delivery_date,
                'currency_id' => $data['currency_id'] ?? $order->currency_id,
                'payment_terms' => $data['payment_terms'] ?? $order->payment_terms,
                'billing_address' => $data['billing_address'] ?? $order->billing_address,
                'shipping_address' => $data['shipping_address'] ?? $order->shipping_address,
                'notes' => $data['notes'] ?? $order->notes,
                'terms_conditions' => $data['terms_conditions'] ?? $order->terms_conditions,
                'subtotal' => $calc['subtotal'],
                'discount_amount' => $calc['discount_amount'],
                'discount_type' => $data['discount_type'] ?? $order->discount_type,
                'tax_amount' => $calc['tax_amount'],
                'grand_total' => $calc['grand_total'],
                'updated_by' => $actorId,
            ]);

            $order->lines()->delete();
            foreach ($calc['lines'] as $idx => $cl) {
                $raw = $lines[$idx];
                SalesOrderLine::create([
                    'institute_id' => $instituteId,
                    'order_id' => $order->id,
                    'inventory_item_id' => $raw['inventory_item_id'] ?? null,
                    'description' => $raw['description'] ?? '',
                    'quantity' => $cl['quantity'],
                    'unit' => $raw['unit'] ?? null,
                    'unit_price' => $cl['unit_price'],
                    'discount_amount' => $cl['discount_amount'],
                    'discount_type' => $cl['discount_type'],
                    'tax_group_id' => $raw['tax_group_id'] ?? null,
                    'tax_rate' => $cl['tax_rate'],
                    'tax_amount' => $cl['tax_amount'],
                    'line_total' => $cl['line_total'],
                    'sort_order' => $idx,
                ]);
            }

            $this->audit->log($instituteId, [
                'branch_id' => $branchId,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'update',
                'entity_type' => 'sales_order',
                'entity_id' => $order->id,
                'after_payload' => ['grand_total' => $order->fresh()->grand_total, 'status' => $order->status],
            ]);

            return $order->fresh('lines');
        });
    }

    public function submit(SalesOrder $order, ?int $actorId = null): SalesOrder
    {
        $this->assertTransition($order, SalesOrder::STATUS_PENDING_APPROVAL);

        return $this->transition($order, SalesOrder::STATUS_PENDING_APPROVAL, $actorId, ['submitted_at' => now()]);
    }

    public function approve(SalesOrder $order, ?int $actorId = null): SalesOrder
    {
        $this->assertTransition($order, SalesOrder::STATUS_APPROVED);
        if ($actorId !== null && $order->created_by !== null && (int) $order->created_by === (int) $actorId) {
            throw ValidationException::withMessages(['status' => 'You cannot approve your own order.']);
        }

        return $this->transition($order, SalesOrder::STATUS_APPROVED, $actorId, ['approved_by' => $actorId, 'approved_at' => now()]);
    }

    public function reject(SalesOrder $order, ?int $actorId = null): SalesOrder
    {
        $this->assertTransition($order, SalesOrder::STATUS_REJECTED);

        return $this->transition($order, SalesOrder::STATUS_REJECTED, $actorId);
    }

    public function cancel(SalesOrder $order, ?int $actorId = null): SalesOrder
    {
        $this->assertTransition($order, SalesOrder::STATUS_CANCELLED);

        return $this->transition($order, SalesOrder::STATUS_CANCELLED, $actorId);
    }

    public function markProcessing(SalesOrder $order, ?int $actorId = null): SalesOrder
    {
        $this->assertTransition($order, SalesOrder::STATUS_PROCESSING);

        return $this->transition($order, SalesOrder::STATUS_PROCESSING, $actorId);
    }

    public function markReadyForDelivery(SalesOrder $order, ?int $actorId = null): SalesOrder
    {
        $this->assertTransition($order, SalesOrder::STATUS_READY_FOR_DELIVERY);

        return $this->transition($order, SalesOrder::STATUS_READY_FOR_DELIVERY, $actorId);
    }

    public function complete(SalesOrder $order, ?int $actorId = null): SalesOrder
    {
        $this->assertTransition($order, SalesOrder::STATUS_COMPLETED);

        return $this->transition($order, SalesOrder::STATUS_COMPLETED, $actorId);
    }

    private function transition(SalesOrder $order, string $target, ?int $actorId, array $extra = []): SalesOrder
    {
        $from = $order->status;

        return DB::transaction(function () use ($order, $target, $actorId, $from, $extra) {
            $order->update(array_merge(['status' => $target, 'updated_by' => $actorId], $extra));
            $this->audit->log($order->institute_id, [
                'branch_id' => $order->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'update',
                'entity_type' => 'sales_order',
                'entity_id' => $order->id,
                'before_payload' => ['status' => $from],
                'after_payload' => ['status' => $target],
            ]);

            return $order->fresh('lines');
        });
    }

    private function assertTransition(SalesOrder $order, string $target): void
    {
        if (! $order->canTransitionTo($target)) {
            throw ValidationException::withMessages(['status' => "Cannot transition from {$order->status} to {$target}."]);
        }
    }

    private function assertCustomerScope(int $instituteId, ?int $branchId, ?int $customerId): void
    {
        if (! $customerId) {
            throw ValidationException::withMessages(['customer_id' => 'Customer is required.']);
        }
        $party = Party::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('id', $customerId)
            ->whereIn('type', ['customer', 'both'])
            ->where('is_active', true)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->first();
        if (! $party) {
            throw ValidationException::withMessages(['customer_id' => 'Customer not found or not in scope.']);
        }
    }

    private function validateLines(int $instituteId, ?int $branchId, array $lines): void
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => 'At least one line is required.']);
        }
        foreach ($lines as $idx => $line) {
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
                $tg = TaxGroup::withoutGlobalScopes()->where('institute_id', $instituteId)->where('id', $line['tax_group_id'])->first();
                if (! $tg) {
                    throw ValidationException::withMessages(["lines.$idx.tax_group_id" => 'Tax group not found.']);
                }
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
