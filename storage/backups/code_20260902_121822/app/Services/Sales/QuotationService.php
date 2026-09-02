<?php

namespace App\Services\Sales;

use App\Models\Currency;
use App\Models\InventoryItem;
use App\Models\Party;
use App\Models\SalesQuotation;
use App\Models\SalesQuotationLine;
use App\Models\TaxGroup;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Tax\TaxCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(
        private readonly AccountingAuditService $audit,
        private readonly SalesNumberingService $numbering,
        private readonly TaxCalculationService $taxCalc,
    ) {}

    /**
     * Centralized decimal-safe calculation.
     * Returns [subtotal, discount_amount, tax_amount, grand_total, lines[]]
     */
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

            // Line discount
            $discountType = $line['discount_type'] ?? 'fixed';
            $discountRaw = $this->toDecimal($line['discount_amount'] ?? 0);
            if ($discountType === 'percent') {
                $lineDiscount = bcdiv(bcmul($lineSubtotal, $discountRaw, 8), '100', 8);
            } else {
                $lineDiscount = $discountRaw;
            }
            // Cap discount to line subtotal
            if (bccomp($lineDiscount, $lineSubtotal, 8) > 0) {
                $lineDiscount = $lineSubtotal;
            }

            $lineNet = bcsub($lineSubtotal, $lineDiscount, 8);

            // Line tax — resolve via TaxGroup if provided
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
                    // Use TaxCalculationService if available for compound/inclusive logic
                    // For quotation, simple percent on net
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

        // Header discount
        $headerDiscountType = $header['discount_type'] ?? 'fixed';
        $headerDiscountRaw = $this->toDecimal($header['discount_amount'] ?? 0);
        $discountBase = bcsub($subtotal, $totalLineDiscount, 8); // apply header discount on subtotal after line discounts
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

        // Header tax (if provided, additional on top of line taxes)
        $headerTaxRaw = $this->toDecimal($header['tax_amount'] ?? 0);
        // If header tax is provided as fixed, add it; otherwise line taxes already sum
        // For simplicity, if header tax_amount provided, use it as additional
        $totalTaxWithHeader = bcadd($totalTax, $headerTaxRaw, 8);

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

    public function createDraft(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): SalesQuotation
    {
        $this->assertCustomerScope($instituteId, $branchId, $data['customer_id'] ?? null);
        $this->validateLines($instituteId, $branchId, $data['lines'] ?? []);

        return DB::transaction(function () use ($instituteId, $branchId, $data, $actorId) {
            $calc = $this->calculate($instituteId, $branchId, $data, $data['lines'] ?? []);

            // Tampered totals are ignored — server calc is source of truth
            $quotation = SalesQuotation::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'quotation_number' => $this->numbering->nextNumber($instituteId, $branchId, 'quotation'),
                'customer_id' => $data['customer_id'],
                'quotation_date' => $data['quotation_date'],
                'validity_date' => $data['validity_date'],
                'currency_id' => $data['currency_id'],
                'payment_terms' => $data['payment_terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
                'subtotal' => $calc['subtotal'],
                'discount_amount' => $calc['discount_amount'],
                'discount_type' => $data['discount_type'] ?? 'fixed',
                'tax_amount' => $calc['tax_amount'],
                'grand_total' => $calc['grand_total'],
                'status' => SalesQuotation::STATUS_DRAFT,
                'created_by' => $actorId,
            ]);

            foreach ($calc['lines'] as $idx => $cl) {
                $raw = $data['lines'][$idx];
                SalesQuotationLine::create([
                    'institute_id' => $instituteId,
                    'quotation_id' => $quotation->id,
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
                'entity_type' => 'sales_quotation',
                'entity_id' => $quotation->id,
                'after_payload' => ['quotation_number' => $quotation->quotation_number, 'grand_total' => $quotation->grand_total],
            ]);

            return $quotation->load('lines');
        });
    }

    public function updateDraft(SalesQuotation $quotation, array $data, ?int $actorId = null): SalesQuotation
    {
        if (! $quotation->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Only draft quotations can be edited.']);
        }

        // Historical safety: if terminal, already blocked above
        $instituteId = $quotation->institute_id;
        $branchId = $quotation->branch_id;

        if (isset($data['customer_id'])) {
            $this->assertCustomerScope($instituteId, $branchId, $data['customer_id']);
        }
        if (isset($data['lines'])) {
            $this->validateLines($instituteId, $branchId, $data['lines']);
        }

        return DB::transaction(function () use ($quotation, $data, $actorId, $instituteId, $branchId) {
            $lines = $data['lines'] ?? $quotation->lines->toArray();
            // Normalize lines to expected format
            $calc = $this->calculate($instituteId, $branchId, array_merge($quotation->toArray(), $data), $lines);

            $quotation->update([
                'customer_id' => $data['customer_id'] ?? $quotation->customer_id,
                'quotation_date' => $data['quotation_date'] ?? $quotation->quotation_date,
                'validity_date' => $data['validity_date'] ?? $quotation->validity_date,
                'currency_id' => $data['currency_id'] ?? $quotation->currency_id,
                'payment_terms' => $data['payment_terms'] ?? $quotation->payment_terms,
                'notes' => $data['notes'] ?? $quotation->notes,
                'terms_conditions' => $data['terms_conditions'] ?? $quotation->terms_conditions,
                'subtotal' => $calc['subtotal'],
                'discount_amount' => $calc['discount_amount'],
                'discount_type' => $data['discount_type'] ?? $quotation->discount_type,
                'tax_amount' => $calc['tax_amount'],
                'grand_total' => $calc['grand_total'],
                'updated_by' => $actorId,
            ]);

            // Replace lines
            $quotation->lines()->delete();
            foreach ($calc['lines'] as $idx => $cl) {
                $raw = $lines[$idx];
                SalesQuotationLine::create([
                    'institute_id' => $instituteId,
                    'quotation_id' => $quotation->id,
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
                'entity_type' => 'sales_quotation',
                'entity_id' => $quotation->id,
                'after_payload' => ['grand_total' => $quotation->fresh()->grand_total],
            ]);

            return $quotation->fresh('lines');
        });
    }

    public function send(SalesQuotation $quotation, ?int $actorId = null): SalesQuotation
    {
        $this->assertTransition($quotation, SalesQuotation::STATUS_SENT);

        return $this->transition($quotation, SalesQuotation::STATUS_SENT, $actorId);
    }

    public function accept(SalesQuotation $quotation, ?int $actorId = null): SalesQuotation
    {
        $this->assertTransition($quotation, SalesQuotation::STATUS_ACCEPTED);

        return $this->transition($quotation, SalesQuotation::STATUS_ACCEPTED, $actorId);
    }

    public function reject(SalesQuotation $quotation, ?int $actorId = null): SalesQuotation
    {
        $this->assertTransition($quotation, SalesQuotation::STATUS_REJECTED);

        return $this->transition($quotation, SalesQuotation::STATUS_REJECTED, $actorId);
    }

    public function cancel(SalesQuotation $quotation, ?int $actorId = null): SalesQuotation
    {
        $this->assertTransition($quotation, SalesQuotation::STATUS_CANCELLED);

        return $this->transition($quotation, SalesQuotation::STATUS_CANCELLED, $actorId);
    }

    public function expire(SalesQuotation $quotation, ?int $actorId = null): SalesQuotation
    {
        // Expire can be triggered from sent when validity_date is past
        if ($quotation->status !== SalesQuotation::STATUS_SENT) {
            throw ValidationException::withMessages(['status' => 'Only sent quotations can be expired.']);
        }

        return $this->transition($quotation, SalesQuotation::STATUS_EXPIRED, $actorId);
    }

    public function expireIfNeeded(SalesQuotation $quotation, ?int $actorId = null): ?SalesQuotation
    {
        if ($quotation->isExpiredByDate()) {
            return $this->expire($quotation, $actorId);
        }

        return null;
    }

    public function canConvertToOrder(SalesQuotation $quotation): bool
    {
        return $quotation->status === SalesQuotation::STATUS_ACCEPTED
            && $quotation->converted_to_order_id === null;
    }

    private function transition(SalesQuotation $quotation, string $target, ?int $actorId): SalesQuotation
    {
        $from = $quotation->status;

        return DB::transaction(function () use ($quotation, $target, $actorId, $from) {
            $quotation->update(['status' => $target, 'updated_by' => $actorId]);

            $this->audit->log($quotation->institute_id, [
                'branch_id' => $quotation->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'update',
                'entity_type' => 'sales_quotation',
                'entity_id' => $quotation->id,
                'before_payload' => ['status' => $from],
                'after_payload' => ['status' => $target],
            ]);

            return $quotation->fresh('lines');
        });
    }

    private function assertTransition(SalesQuotation $quotation, string $target): void
    {
        if (! $quotation->canTransitionTo($target)) {
            throw ValidationException::withMessages(['status' => "Cannot transition from {$quotation->status} to {$target}."]);
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
                $tg = TaxGroup::withoutGlobalScopes()
                    ->where('institute_id', $instituteId)
                    ->where('id', $line['tax_group_id'])
                    ->first();
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
