<?php

namespace App\Services\Purchase;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Party;
use App\Models\PurchaseQuotation;
use App\Models\PurchaseQuotationLine;
use App\Models\TaxGroup;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseQuotationService
{
    public function __construct(
        private readonly PurchaseNumberingService $numbering,
    ) {}

    /**
     * Create a purchase quotation in draft status.
     * Mirrors Sales\QuotationService::createDraft but supplier-side.
     */
    public function createDraft(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): PurchaseQuotation
    {
        $this->assertBranchScope($branchId);
        $this->assertSupplierScope($instituteId, $branchId, $data['supplier_id'] ?? null);
        $lines = $data['lines'] ?? $data['items'] ?? [];
        $this->validateLines($instituteId, $branchId, $lines);

        return DB::transaction(function () use ($instituteId, $branchId, $data, $actorId, $lines) {
            $calculated = $this->calculateTotals($lines, $instituteId);

            $quotation = PurchaseQuotation::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'quotation_number' => $this->numbering->nextNumber($instituteId, $branchId, 'quotation'),
                'supplier_id' => $data['supplier_id'],
                'quotation_date' => $data['quotation_date'] ?? now()->toDateString(),
                'validity_date' => $data['validity_date'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
                'subtotal' => $calculated['subtotal'],
                'discount_amount' => $calculated['discount_amount'],
                'discount_type' => $data['discount_type'] ?? 'fixed',
                'tax_amount' => $calculated['tax_amount'],
                'grand_total' => $calculated['grand_total'],
                'status' => PurchaseQuotation::STATUS_DRAFT,
                'created_by' => $actorId,
            ]);

            foreach ($calculated['lines'] as $idx => $cl) {
                $raw = $lines[$idx];
                PurchaseQuotationLine::create([
                    'institute_id' => $instituteId,
                    'quotation_id' => $quotation->id,
                    'inventory_item_id' => $raw['inventory_item_id'] ?? null,
                    'description' => $raw['description'] ?? ($raw['inventory_item_id'] ? (InventoryItem::withoutGlobalScopes()->find($raw['inventory_item_id'])?->name ?? '') : ''),
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

            $this->auditLog($instituteId, $branchId, $actorId, 'create', 'purchase_quotation', $quotation->id, null, [
                'quotation_number' => $quotation->quotation_number,
                'grand_total' => $quotation->grand_total,
                'status' => $quotation->status,
            ]);

            return $quotation->load('lines');
        });
    }

    public function updateDraft(PurchaseQuotation $quotation, array $data, ?int $actorId = null): PurchaseQuotation
    {
        $this->assertBranchScope($quotation->branch_id);

        if (! $quotation->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Only draft quotations can be edited.']);
        }

        $instituteId = $quotation->institute_id;
        $branchId = $quotation->branch_id;

        if (isset($data['supplier_id'])) {
            $this->assertSupplierScope($instituteId, $branchId, $data['supplier_id']);
        }

        $lines = $data['lines'] ?? $data['items'] ?? null;
        if ($lines !== null) {
            $this->validateLines($instituteId, $branchId, $lines);
        } else {
            $quotation->loadMissing('lines');
            $lines = $quotation->lines->toArray();
            // normalize lines to expected input format
            $lines = array_map(function ($l) {
                return [
                    'inventory_item_id' => $l['inventory_item_id'] ?? null,
                    'description' => $l['description'] ?? '',
                    'quantity' => $l['quantity'] ?? 0,
                    'unit' => $l['unit'] ?? null,
                    'unit_price' => $l['unit_price'] ?? 0,
                    'discount_amount' => $l['discount_amount'] ?? 0,
                    'discount_type' => $l['discount_type'] ?? 'fixed',
                    'tax_group_id' => $l['tax_group_id'] ?? null,
                    'tax_rate' => $l['tax_rate'] ?? 0,
                ];
            }, $lines);
        }

        return DB::transaction(function () use ($quotation, $data, $actorId, $instituteId, $lines) {
            $calculated = $this->calculateTotals($lines, $instituteId);

            $quotation->update([
                'supplier_id' => $data['supplier_id'] ?? $quotation->supplier_id,
                'quotation_date' => $data['quotation_date'] ?? $quotation->quotation_date,
                'validity_date' => $data['validity_date'] ?? $quotation->validity_date,
                'currency_id' => $data['currency_id'] ?? $quotation->currency_id,
                'notes' => $data['notes'] ?? $quotation->notes,
                'terms_conditions' => $data['terms_conditions'] ?? $quotation->terms_conditions,
                'subtotal' => $calculated['subtotal'],
                'discount_amount' => $calculated['discount_amount'],
                'discount_type' => $data['discount_type'] ?? $quotation->discount_type,
                'tax_amount' => $calculated['tax_amount'],
                'grand_total' => $calculated['grand_total'],
                'updated_by' => $actorId,
            ]);

            $quotation->lines()->delete();
            foreach ($calculated['lines'] as $idx => $cl) {
                $raw = $lines[$idx];
                PurchaseQuotationLine::create([
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

            $this->auditLog($instituteId, $quotation->branch_id, $actorId, 'update', 'purchase_quotation', $quotation->id, null, [
                'grand_total' => $quotation->fresh()->grand_total,
                'status' => $quotation->status,
            ]);

            return $quotation->fresh('lines');
        });
    }

    public function send(PurchaseQuotation $quotation, ?int $actorId = null): PurchaseQuotation
    {
        $this->assertBranchScope($quotation->branch_id);
        $this->assertTransition($quotation, PurchaseQuotation::STATUS_SENT);

        return $this->transition($quotation, PurchaseQuotation::STATUS_SENT, $actorId);
    }

    public function accept(PurchaseQuotation $quotation, ?int $actorId = null): PurchaseQuotation
    {
        $this->assertBranchScope($quotation->branch_id);
        $this->assertTransition($quotation, PurchaseQuotation::STATUS_ACCEPTED);

        return $this->transition($quotation, PurchaseQuotation::STATUS_ACCEPTED, $actorId);
    }

    public function reject(PurchaseQuotation $quotation, ?int $actorId = null): PurchaseQuotation
    {
        $this->assertBranchScope($quotation->branch_id);
        $this->assertTransition($quotation, PurchaseQuotation::STATUS_REJECTED);

        return $this->transition($quotation, PurchaseQuotation::STATUS_REJECTED, $actorId);
    }

    public function cancel(PurchaseQuotation $quotation, ?int $actorId = null): PurchaseQuotation
    {
        $this->assertBranchScope($quotation->branch_id);
        $this->assertTransition($quotation, PurchaseQuotation::STATUS_CANCELLED);

        return $this->transition($quotation, PurchaseQuotation::STATUS_CANCELLED, $actorId);
    }

    public function expire(PurchaseQuotation $quotation, ?int $actorId = null): PurchaseQuotation
    {
        $this->assertBranchScope($quotation->branch_id);

        if ($quotation->status !== PurchaseQuotation::STATUS_SENT) {
            throw ValidationException::withMessages(['status' => 'Only sent quotations can be expired.']);
        }

        return $this->transition($quotation, PurchaseQuotation::STATUS_EXPIRED, $actorId);
    }

    public function expireIfNeeded(PurchaseQuotation $quotation, ?int $actorId = null): ?PurchaseQuotation
    {
        if ($quotation->isExpiredByDate()) {
            return $this->expire($quotation, $actorId);
        }

        return null;
    }

    public function canConvertToOrder(PurchaseQuotation $quotation): bool
    {
        return $quotation->status === PurchaseQuotation::STATUS_ACCEPTED
            && $quotation->converted_to_order_id === null;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Computes line_total = (quantity * unit_price - discount) + tax.
     * Mirrors PurchaseOrderService::calculateLine BCMath logic.
     */
    public function calculateLine(array $item): array
    {
        $instituteId = $item['institute_id'] ?? null;

        $qty = $this->toDecimal($item['quantity'] ?? 0);
        $unitPrice = $this->toDecimal($item['unit_price'] ?? 0);
        $lineSubtotal = bcmul($qty, $unitPrice, 8);

        $discountType = strtolower($item['discount_type'] ?? 'fixed');
        if ($discountType === 'percentage') {
            $discountType = 'percent';
        }

        $discountAmount = '0';
        if ($discountType === 'percent' || $discountType === 'percentage') {
            $rate = $this->toDecimal($item['discount_rate'] ?? $item['discount_amount'] ?? 0);
            $discountAmount = bcdiv(bcmul($lineSubtotal, $rate, 8), '100', 8);
        } else {
            $discountAmount = $this->toDecimal($item['discount_amount'] ?? 0);
        }

        if (bccomp($discountAmount, $lineSubtotal, 8) > 0) {
            $discountAmount = $lineSubtotal;
        }
        if (bccomp($discountAmount, '0', 8) < 0) {
            $discountAmount = '0';
        }

        $lineNet = bcsub($lineSubtotal, $discountAmount, 8);

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
            $taxGroup = TaxGroup::withoutGlobalScopes()->where('id', $taxGroupId)->first();
            if ($taxGroup) {
                $taxRate = $this->toDecimal($taxGroup->rate);
            }
        }

        $taxAmount = '0';
        if (bccomp($taxRate, '0', 8) !== 0) {
            $taxAmount = bcdiv(bcmul($lineNet, $taxRate, 8), '100', 8);
        }

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

    private function calculateTotals(array $lines, int $instituteId): array
    {
        $subtotal = '0';
        $totalDiscount = '0';
        $totalTax = '0';
        $computedLines = [];

        foreach ($lines as $idx => $line) {
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

    private function transition(PurchaseQuotation $quotation, string $target, ?int $actorId): PurchaseQuotation
    {
        $from = $quotation->status;

        return DB::transaction(function () use ($quotation, $target, $actorId, $from) {
            $quotation->update(['status' => $target, 'updated_by' => $actorId]);

            $this->auditLog($quotation->institute_id, $quotation->branch_id, $actorId, 'update', 'purchase_quotation', $quotation->id, ['status' => $from], ['status' => $target]);

            return $quotation->fresh('lines');
        });
    }

    private function assertTransition(PurchaseQuotation $quotation, string $target): void
    {
        if (! $quotation->canTransitionTo($target)) {
            throw ValidationException::withMessages(['status' => "Cannot transition from {$quotation->status} to {$target}."]);
        }
    }

    private function assertSupplierScope(int $instituteId, ?int $branchId, ?int $supplierId): void
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
            if ($branchId !== null && (int) $branchId !== (int) $actorBranch) {
                throw ValidationException::withMessages(['branch_id' => 'Branch scope violation.']);
            }
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

    private function toDecimal(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }

    private function round4(string $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
