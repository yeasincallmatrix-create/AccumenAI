<?php

namespace App\Services\Purchase;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\Party;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\TaxGroup;
use App\Services\Accounting\PurchaseAccountingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceService
{
    public function __construct(
        private readonly PurchaseNumberingService $numbering,
        private readonly PurchaseAccountingService $purchaseAccounting,
    ) {}

    public function calculate(int $instituteId, array $lines, array $header = []): array
    {
        $subtotal = '0';
        $totalDiscount = '0';
        $totalTax = '0';
        $computed = [];

        foreach ($lines as $idx => $line) {
            $qty = $this->toDecimal($line['quantity'] ?? 0);
            $unitPrice = $this->toDecimal($line['unit_price'] ?? 0);
            $lineSubtotal = bcmul($qty, $unitPrice, 8);

            $discountType = $line['discount_type'] ?? 'fixed';
            $discountRaw = $this->toDecimal($line['discount_amount'] ?? 0);
            $lineDiscount = $discountType === 'percent' ? bcdiv(bcmul($lineSubtotal, $discountRaw, 8), '100', 8) : $discountRaw;
            if (bccomp($lineDiscount, $lineSubtotal, 8) > 0) $lineDiscount = $lineSubtotal;

            $lineNet = bcsub($lineSubtotal, $lineDiscount, 8);

            $taxRate = $this->toDecimal($line['tax_rate'] ?? 0);
            $taxGroupId = $line['tax_group_id'] ?? null;
            if ($taxGroupId) {
                $tg = TaxGroup::withoutGlobalScopes()->where('institute_id', $instituteId)->where('id', $taxGroupId)->first();
                if ($tg) $taxRate = $this->toDecimal($tg->rate);
            }
            $taxAmount = bccomp($taxRate, '0', 8) !== 0 ? bcdiv(bcmul($lineNet, $taxRate, 8), '100', 8) : '0';
            $lineTotal = bcadd($lineNet, $taxAmount, 8);

            $subtotal = bcadd($subtotal, $lineSubtotal, 8);
            $totalDiscount = bcadd($totalDiscount, $lineDiscount, 8);
            $totalTax = bcadd($totalTax, $taxAmount, 8);

            $computed[] = [
                'quantity' => $this->round4($qty),
                'unit_price' => $this->round4($unitPrice),
                'discount_amount' => $this->round4($lineDiscount),
                'discount_type' => $discountType,
                'tax_rate' => $this->round4($taxRate),
                'tax_amount' => $this->round4($taxAmount),
                'line_total' => $this->round4($lineTotal),
                'line_subtotal' => $this->round4($lineSubtotal),
                'line_net' => $this->round4($lineNet),
            ];
        }

        $headerDiscountType = $header['discount_type'] ?? 'fixed';
        $headerDiscountRaw = $this->toDecimal($header['discount_amount'] ?? 0);
        $discountBase = bcsub($subtotal, $totalDiscount, 8);
        if (bccomp($discountBase, '0', 8) < 0) $discountBase = '0';
        $headerDiscount = $headerDiscountType === 'percent' ? bcdiv(bcmul($discountBase, $headerDiscountRaw, 8), '100', 8) : $headerDiscountRaw;
        if (bccomp($headerDiscount, $discountBase, 8) > 0) $headerDiscount = $discountBase;
        $totalDiscount = bcadd($totalDiscount, $headerDiscount, 8);

        $grandTotal = bcadd(bcsub($subtotal, $totalDiscount, 8), $totalTax, 8);
        if (bccomp($grandTotal, '0', 8) < 0) $grandTotal = '0';

        return [
            'subtotal' => $this->round4($subtotal),
            'discount_amount' => $this->round4($totalDiscount),
            'tax_amount' => $this->round4($totalTax),
            'grand_total' => $this->round4($grandTotal),
            'lines' => $computed,
        ];
    }

    public function eligibleForInvoicing(PurchaseOrder $order): array
    {
        $order->load('lines');
        $eligible = [];
        foreach ($order->lines as $line) {
            $received = (float) $line->received_quantity;
            $invoiced = $this->invoicedQuantityForOrderLine($line);
            $remaining = $received - $invoiced;
            if ($remaining > 0.00005) {
                $eligible[] = [
                    'purchase_order_line_id' => $line->id,
                    'inventory_item_id' => $line->inventory_item_id,
                    'description' => $line->description,
                    'ordered_quantity' => (float) $line->quantity,
                    'received_quantity' => $received,
                    'invoiced_quantity' => $invoiced,
                    'remaining' => round($remaining, 4),
                    'unit_price' => (float) $line->unit_price,
                    'unit' => $line->unit,
                    'tax_group_id' => $line->tax_group_id,
                ];
            }
        }
        return $eligible;
    }

    public function createFromGoodsReceipt(int $instituteId, ?int $branchId, int $goodsReceiptId, array $data, int $actorId): PurchaseInvoice
    {
        $gr = GoodsReceipt::withoutGlobalScopes()->where('institute_id', $instituteId)->where('id', $goodsReceiptId)->first();
        if (! $gr) throw ValidationException::withMessages(['goods_receipt_id' => 'Goods receipt not found.']);
        if ($branchId !== null && $gr->branch_id !== null && (int) $gr->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages(['goods_receipt_id' => 'Goods receipt not in your branch.']);
        }
        if ($gr->status !== GoodsReceipt::STATUS_CONFIRMED) {
            throw ValidationException::withMessages(['goods_receipt_id' => 'Only confirmed goods receipts can be invoiced.']);
        }

        $po = $gr->purchaseOrder;
        $supplierId = $gr->supplier_id;

        return $this->create($instituteId, $branchId, array_merge($data, [
            'purchase_order_id' => $po->id,
            'goods_receipt_id' => $gr->id,
            'supplier_id' => $supplierId,
        ]), $actorId);
    }

    public function createFromPurchaseOrder(int $instituteId, ?int $branchId, int $purchaseOrderId, array $data, int $actorId): PurchaseInvoice
    {
        $po = PurchaseOrder::withoutGlobalScopes()->where('institute_id', $instituteId)->where('id', $purchaseOrderId)->first();
        if (! $po) throw ValidationException::withMessages(['purchase_order_id' => 'Purchase order not found.']);
        if ($branchId !== null && $po->branch_id !== null && (int) $po->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages(['purchase_order_id' => 'Purchase order not in your branch.']);
        }
        if (! in_array($po->status, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_FULLY_RECEIVED], true)) {
            throw ValidationException::withMessages(['purchase_order_id' => 'Purchase order must be approved before invoicing.']);
        }

        return $this->create($instituteId, $branchId, array_merge($data, [
            'purchase_order_id' => $po->id,
            'supplier_id' => $po->supplier_id,
        ]), $actorId);
    }

    public function create(int $instituteId, ?int $branchId, array $data, int $actorId): PurchaseInvoice
    {
        $this->assertBranchScope($branchId);
        $this->assertSupplier($instituteId, $branchId, $data['supplier_id'] ?? null);
        $this->validateLines($instituteId, $branchId, $data);

        $poId = $data['purchase_order_id'] ?? null;
        $grId = $data['goods_receipt_id'] ?? null;

        // Idempotency: prevent duplicate invoice number via transaction, but also check for duplicate submission via unique invoice_number
        return DB::transaction(function () use ($instituteId, $branchId, $data, $actorId, $poId, $grId) {
            // Prevent duplicate invoice for same GRN if already invoiced fully — lock parent rows to prevent concurrent over-invoicing (P0 fix)
            if ($grId) {
                $gr = GoodsReceipt::withoutGlobalScopes()->where('id', $grId)->lockForUpdate()->first();
                $this->assertNotOverInvoicing($instituteId, $gr, $data['lines'] ?? []);
            }
            if ($poId) {
                $po = PurchaseOrder::withoutGlobalScopes()->where('id', $poId)->lockForUpdate()->first();
                $this->assertNotOverInvoicingPO($instituteId, $po, $data['lines'] ?? []);
            }

            $calc = $this->calculate($instituteId, $data['lines'] ?? [], $data);

            $invoice = PurchaseInvoice::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'invoice_number' => $this->numbering->nextNumber($instituteId, $branchId, 'invoice'),
                'purchase_order_id' => $poId,
                'goods_receipt_id' => $grId,
                'supplier_id' => $data['supplier_id'],
                'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'currency_id' => $data['currency_id'] ?? $this->resolveCurrencyId($instituteId, $branchId),
                'payment_terms' => $data['payment_terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
                'subtotal' => $calc['subtotal'],
                'discount_amount' => $calc['discount_amount'],
                'discount_type' => $data['discount_type'] ?? 'fixed',
                'tax_amount' => $calc['tax_amount'],
                'grand_total' => $calc['grand_total'],
                'paid_amount' => 0,
                'due_amount' => $calc['grand_total'],
                'status' => PurchaseInvoice::STATUS_DRAFT,
                'created_by' => $actorId,
            ]);

            foreach ($calc['lines'] as $idx => $cl) {
                $raw = $data['lines'][$idx];
                PurchaseInvoiceItem::create([
                    'institute_id' => $instituteId,
                    'purchase_invoice_id' => $invoice->id,
                    'purchase_order_line_id' => $raw['purchase_order_line_id'] ?? null,
                    'goods_receipt_item_id' => $raw['goods_receipt_item_id'] ?? null,
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

            $this->auditLog($instituteId, $branchId, $actorId, 'create', 'purchase_invoice', $invoice->id, null, ['invoice_number' => $invoice->invoice_number, 'grand_total' => $invoice->grand_total]);

            return $invoice->load('items');
        });
    }

    public function post(PurchaseInvoice $invoice, int $actorId): PurchaseInvoice
    {
        if (! $invoice->isDraft()) throw ValidationException::withMessages(['status' => 'Only draft invoices can be posted.']);
        $this->assertBranchScope($invoice->branch_id);

        return DB::transaction(function () use ($invoice, $actorId) {
            $invoice->refresh();
            if (! $invoice->isDraft()) throw ValidationException::withMessages(['status' => 'Invoice already posted.']);

            $supplier = Party::withoutGlobalScopes()->where('id', $invoice->supplier_id)->first();
            $this->assertSupplier($invoice->institute_id, $invoice->branch_id, $invoice->supplier_id);

            // Re-validate not over-invoicing at post time (prevent TOCTOU) — lock parents
            if ($invoice->goods_receipt_id) {
                $gr = GoodsReceipt::withoutGlobalScopes()->where('id', $invoice->goods_receipt_id)->lockForUpdate()->first();
                $this->assertNotOverInvoicing($invoice->institute_id, $gr, $invoice->items->toArray(), $invoice->id);
            }
            if ($invoice->purchase_order_id) {
                $po = PurchaseOrder::withoutGlobalScopes()->where('id', $invoice->purchase_order_id)->lockForUpdate()->first();
                $this->assertNotOverInvoicingPO($invoice->institute_id, $po, $invoice->items->toArray(), $invoice->id);
            }

            // Supplier liability via existing Finance source of truth — NO duplicate journal
            // Inventory must NOT increase again (GRN already did)
            $journal = $this->purchaseAccounting->postPurchase(
                $invoice->institute_id,
                $invoice->branch_id,
                $supplier,
                (float) $invoice->grand_total,
                null, // expense account resolved via defaultExpenseAccount
                null,
                null,
                $actorId,
                $invoice->invoice_date->toDateString(),
                'Purchase Invoice ' . $invoice->invoice_number,
                'purchase_invoice',
                $invoice->id,
                ['currency_id' => $invoice->currency_id]
            );

            $invoice->update([
                'status' => PurchaseInvoice::STATUS_POSTED,
                'journal_id' => $journal->id,
                'updated_by' => $actorId,
            ]);

            $this->auditLog($invoice->institute_id, $invoice->branch_id, $actorId, 'update', 'purchase_invoice', $invoice->id, ['status' => 'draft'], ['status' => 'posted', 'journal_id' => $journal->id]);

            return $invoice->fresh('items');
        });
    }

    public function cancel(PurchaseInvoice $invoice, int $actorId): PurchaseInvoice
    {
        if (! $invoice->canCancel()) throw ValidationException::withMessages(['status' => 'Only draft invoices can be cancelled.']);
        $this->assertBranchScope($invoice->branch_id);
        if ((float) $invoice->paid_amount > 0.00005) throw ValidationException::withMessages(['status' => 'Paid invoices cannot be cancelled. Reverse payments first.']);

        return DB::transaction(function () use ($invoice, $actorId) {
            $invoice->update(['status' => PurchaseInvoice::STATUS_CANCELLED, 'updated_by' => $actorId]);
            $this->auditLog($invoice->institute_id, $invoice->branch_id, $actorId, 'update', 'purchase_invoice', $invoice->id, ['status' => 'draft'], ['status' => 'cancelled']);
            return $invoice->fresh();
        });
    }

    public function reverse(PurchaseInvoice $invoice, int $actorId, ?string $reason = null): PurchaseInvoice
    {
        if (! $invoice->canReverse()) throw ValidationException::withMessages(['status' => 'Only posted invoices can be reversed.']);
        $this->assertBranchScope($invoice->branch_id);
        if ((float) $invoice->paid_amount > 0.00005) throw ValidationException::withMessages(['status' => 'Paid invoices cannot be reversed. Reverse payments first.']);

        return DB::transaction(function () use ($invoice, $actorId, $reason) {
            $journal = $invoice->journal;
            if ($journal) {
                $this->purchaseAccounting->reversePurchase($journal, $invoice->institute_id, $actorId, $reason ?? 'Purchase invoice ' . $invoice->invoice_number . ' reversed');
            }
            $invoice->update(['status' => PurchaseInvoice::STATUS_REVERSED, 'updated_by' => $actorId]);
            $this->auditLog($invoice->institute_id, $invoice->branch_id, $actorId, 'update', 'purchase_invoice', $invoice->id, ['status' => 'posted'], ['status' => 'reversed', 'reason' => $reason]);
            return $invoice->fresh();
        });
    }

    public function canEdit(PurchaseInvoice $invoice): bool { return $invoice->canEdit(); }

    private function assertNotOverInvoicing(int $instituteId, GoodsReceipt $gr, array $lines, ?int $excludeInvoiceId = null): void
    {
        $gr->load('items');
        $po = $gr->purchaseOrder;
        $po->load('lines');

        foreach ($lines as $line) {
            $poLineId = $line['purchase_order_line_id'] ?? null;
            $grItemId = $line['goods_receipt_item_id'] ?? null;
            $qty = (float) ($line['quantity'] ?? 0);

            if ($poLineId) {
                $poLine = $po->lines->firstWhere('id', $poLineId);
                if ($poLine) {
                    $received = (float) $poLine->received_quantity;
                    $alreadyInvoiced = $this->invoicedQuantityForOrderLine($poLine, $excludeInvoiceId);
                    if ($qty - ($received - $alreadyInvoiced) > 0.00005) {
                        throw ValidationException::withMessages(['quantity' => "Quantity {$qty} exceeds remaining invoicable for PO line. Received: {$received}, already invoiced: {$alreadyInvoiced}."]);
                    }
                }
            }
            if ($grItemId) {
                $grItem = $gr->items->firstWhere('id', $grItemId);
                if ($grItem) {
                    $received = (float) $grItem->received_quantity - (float) $grItem->rejected_quantity;
                    $alreadyInvoiced = $this->invoicedQuantityForGrItem($grItem, $excludeInvoiceId);
                    if ($qty - ($received - $alreadyInvoiced) > 0.00005) {
                        throw ValidationException::withMessages(['quantity' => "Quantity {$qty} exceeds remaining for GRN item."]);
                    }
                }
            }
        }
    }

    private function assertNotOverInvoicingPO(int $instituteId, PurchaseOrder $po, array $lines, ?int $excludeInvoiceId = null): void
    {
        $po->load('lines');
        foreach ($lines as $line) {
            $poLineId = $line['purchase_order_line_id'] ?? null;
            if (! $poLineId) continue;
            $poLine = $po->lines->firstWhere('id', $poLineId);
            if (! $poLine) continue;
            $received = (float) $poLine->received_quantity;
            if ($received < 0.00005) continue; // allow invoicing even if not yet received? But spec says eligible PO/GR, so we check received
            $qty = (float) ($line['quantity'] ?? 0);
            $alreadyInvoiced = $this->invoicedQuantityForOrderLine($poLine, $excludeInvoiceId);
            if ($qty - ($received - $alreadyInvoiced) > 0.00005) {
                throw ValidationException::withMessages(['quantity' => "Quantity {$qty} exceeds remaining invoicable. Received: {$received}, invoiced: {$alreadyInvoiced}."]);
            }
        }
    }

    private function invoicedQuantityForOrderLine(PurchaseOrderLine $line, ?int $excludeId = null): float
    {
        $q = PurchaseInvoiceItem::withoutGlobalScopes()->where('purchase_order_line_id', $line->id);
        if ($excludeId) $q->where('purchase_invoice_id', '!=', $excludeId);
        $q->whereHas('invoice', fn ($qq) => $qq->whereNotIn('status', [PurchaseInvoice::STATUS_CANCELLED, PurchaseInvoice::STATUS_REVERSED])->whereNull('deleted_at'));
        if (DB::transactionLevel() > 0) $q->lockForUpdate();
        return (float) $q->sum('quantity');
    }

    private function invoicedQuantityForGrItem(GoodsReceiptItem $item, ?int $excludeId = null): float
    {
        $q = PurchaseInvoiceItem::withoutGlobalScopes()->where('goods_receipt_item_id', $item->id);
        if ($excludeId) $q->where('purchase_invoice_id', '!=', $excludeId);
        $q->whereHas('invoice', fn ($qq) => $qq->whereNotIn('status', [PurchaseInvoice::STATUS_CANCELLED, PurchaseInvoice::STATUS_REVERSED])->whereNull('deleted_at'));
        if (DB::transactionLevel() > 0) $q->lockForUpdate();
        return (float) $q->sum('quantity');
    }

    public function invoicedQuantityForOrderLinePublic(PurchaseOrderLine $line): float { return $this->invoicedQuantityForOrderLine($line); }
    public function remainingQuantityForOrderLine(PurchaseOrderLine $line): float
    {
        $received = (float) $line->received_quantity;
        $invoiced = $this->invoicedQuantityForOrderLine($line);
        return round($received - $invoiced, 4);
    }

    private function assertSupplier(int $instituteId, ?int $branchId, ?int $supplierId): void
    {
        if (! $supplierId) throw ValidationException::withMessages(['supplier_id' => 'Supplier is required.']);
        $party = Party::withoutGlobalScopes()->where('institute_id', $instituteId)->where('id', $supplierId)->whereIn('type', ['supplier','both'])->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))->first();
        if (! $party) throw ValidationException::withMessages(['supplier_id' => 'Supplier not found or not in scope.']);
    }

    private function validateLines(int $instituteId, ?int $branchId, array $data): void
    {
        $lines = $data['lines'] ?? [];
        if (empty($lines)) throw ValidationException::withMessages(['lines' => 'At least one line is required.']);
        foreach ($lines as $idx => $line) {
            if (empty($line['description']) && empty($line['inventory_item_id'])) throw ValidationException::withMessages(["lines.$idx.description" => 'Description or product is required.']);
            if (! isset($line['quantity']) || $line['quantity'] === '' || $line['quantity'] === null) throw ValidationException::withMessages(["lines.$idx.quantity" => 'Quantity is required.']);
            if ((float) $line['quantity'] <= 0) throw ValidationException::withMessages(["lines.$idx.quantity" => 'Quantity must be greater than 0.']);
            if (! isset($line['unit_price']) || $line['unit_price'] === '' || $line['unit_price'] === null) throw ValidationException::withMessages(["lines.$idx.unit_price" => 'Unit price is required.']);
            if ((float) $line['unit_price'] < 0) throw ValidationException::withMessages(["lines.$idx.unit_price" => 'Unit price cannot be negative.']);
            if (! empty($line['inventory_item_id'])) {
                $item = InventoryItem::withoutGlobalScopes()->where('institute_id', $instituteId)->where('id', $line['inventory_item_id'])->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))->first();
                if (! $item) throw ValidationException::withMessages(["lines.$idx.inventory_item_id" => 'Product not found or not in scope.']);
            }
            if (! empty($line['tax_group_id'])) {
                $tg = TaxGroup::withoutGlobalScopes()->where('institute_id', $instituteId)->where('id', $line['tax_group_id'])->first();
                if (! $tg) throw ValidationException::withMessages(["lines.$idx.tax_group_id" => 'Tax group not found.']);
            }
        }
    }

    private function assertBranchScope(?int $branchId): void
    {
        if (\App\Support\BranchContext::enabled()) {
            $actorBranch = \App\Support\BranchContext::id();
            if ($branchId !== null && (int) $branchId !== (int) $actorBranch) throw ValidationException::withMessages(['branch_id' => 'Branch scope violation.']);
        }
    }

    private function resolveCurrencyId(int $instituteId, ?int $branchId): int
    {
        $code = app(\App\Services\Accounting\AccountingSetupService::class)->getSetting($instituteId, 'base_currency', null, $branchId);
        if ($code) { $cur = \App\Models\Currency::where('code', $code)->first(); if ($cur) return (int) $cur->id; }
        return (int) (\App\Models\Currency::orderBy('code')->value('id'));
    }

    private function auditLog(int $instituteId, ?int $branchId, ?int $actorId, string $action, string $entityType, int $entityId, ?array $before, ?array $after): void
    {
        try {
            \App\Models\AuditLog::create([
                'institute_id' => $instituteId, 'user_type' => 'institute_user', 'user_id' => $actorId, 'action' => $action, 'module' => 'purchase', 'record_id' => $entityId,
                'old_values' => $before ? json_encode($before) : null, 'new_values' => $after ? json_encode($after) : null,
                'ip_address' => request()->ip() ?? null, 'user_agent' => substr((string)(request()->userAgent() ?? ''),0,255) ?: null, 'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            try {
                \App\Models\AuditLog::create([
                    'institute_id' => $instituteId, 'user_type' => 'institute_user', 'user_id' => $actorId, 'action' => $action, 'module' => 'purchase', 'record_id' => $entityId,
                    'old_values' => $before ? json_encode($before) : null, 'new_values' => $after ? json_encode($after) : null, 'created_at' => now(),
                ]);
            } catch (\Throwable $ignored) {}
        }
    }

    private function toDecimal(mixed $v): string { return number_format((float) $v, 4, '.', ''); }
    private function round4(string $v): string { return number_format((float) $v, 4, '.', ''); }
}
