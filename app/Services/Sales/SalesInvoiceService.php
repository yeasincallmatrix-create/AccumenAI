<?php

namespace App\Services\Sales;

use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InventoryItem;
use App\Models\SalesDelivery;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Accounting\InvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesInvoiceService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly SalesSettingsService $settings,
        private readonly DeliveryService $deliveries,
        private readonly SalesCatalogService $catalog,
        private readonly AccountingAuditService $audit,
    ) {}

    public function isEligible(SalesOrder $order): bool
    {
        if (! $this->settings->get($order->institute_id)['invoice_integration']) {
            return false;
        }
        return in_array($order->status, [
            SalesOrder::STATUS_APPROVED,
            SalesOrder::STATUS_PROCESSING,
            SalesOrder::STATUS_READY_FOR_DELIVERY,
            SalesOrder::STATUS_COMPLETED,
        ], true);
    }

    public function remainingForOrder(SalesOrder $order): array
    {
        $out = [];
        foreach ($order->lines as $line) {
            $ordered = (float) $line->quantity;
            $delivered = $this->deliveries->deliveredQuantityForOrderLine($line);
            $invoiced = $this->invoicedQuantityForLine($line);
            $stockable = $line->inventory_item_id ? $this->isStockableLine($line) : false;

            // For stockable, invoicable is delivered - invoiced; for service, ordered - invoiced
            $max = $stockable ? max(0, $delivered - $invoiced) : max(0, $ordered - $invoiced);

            $out[$line->id] = [
                'line' => $line,
                'ordered' => $ordered,
                'delivered' => $delivered,
                'invoiced' => $invoiced,
                'remaining_ordered' => max(0, $ordered - $invoiced),
                'remaining_delivered' => max(0, $delivered - $invoiced),
                'max_invoicable' => round($max, 4),
                'stockable' => $stockable,
            ];
        }
        return $out;
    }

    /**
     * Create a Finance invoice from a Sales Order (optionally scoped to a Delivery).
     *
     * Reuses InvoiceService as the sole journal/AR source of truth.
     * Preserves historical commercial values (quantity, unit_price, discount, tax).
     *
     * @param  array<int, float>  $quantities  map order_line_id => qty to invoice (empty = all remaining)
     */
    public function createFromOrder(
        int $instituteId,
        ?int $branchId,
        int $orderId,
        ?int $deliveryId = null,
        array $quantities = [],
        ?int $actorId = null,
        ?string $note = null,
    ): Invoice {
        return DB::transaction(function () use ($instituteId, $branchId, $orderId, $deliveryId, $quantities, $actorId, $note) {
            $order = SalesOrder::withoutGlobalScopes()
                ->where('id', $orderId)
                ->where('institute_id', $instituteId)
                ->lockForUpdate()
                ->first();

        if (! $order) {
            throw ValidationException::withMessages(['order_id' => 'Sales order not found.']);
        }
        if ($branchId !== null && $order->branch_id !== null && (int) $order->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages(['order_id' => 'Sales order not in your branch.']);
        }
        if (! $this->isEligible($order)) {
            throw ValidationException::withMessages(['order_id' => 'Order status '.$order->status.' is not eligible for invoicing.']);
        }

        $order->load('lines.inventoryItem');

        $delivery = null;
        if ($deliveryId !== null) {
            $delivery = SalesDelivery::withoutGlobalScopes()
                ->where('id', $deliveryId)
                ->where('institute_id', $instituteId)
                ->where('order_id', $order->id)
                ->first();
            if (! $delivery) {
                throw ValidationException::withMessages(['delivery_id' => 'Delivery not found for this order.']);
            }
            if ($delivery->status !== SalesDelivery::STATUS_CONFIRMED) {
                throw ValidationException::withMessages(['delivery_id' => 'Only confirmed deliveries can be invoiced.']);
            }
            if ($branchId !== null && $delivery->branch_id !== null && (int) $delivery->branch_id !== (int) $branchId) {
                throw ValidationException::withMessages(['delivery_id' => 'Delivery not in your branch.']);
            }
        }

        // Resolve remaining and build invoice lines
        $remainingMap = $this->remainingForOrder($order);
        $invoiceLines = [];
        $invoiceMetaLines = [];

        foreach ($order->lines as $line) {
            $lineId = $line->id;
            $max = $remainingMap[$lineId]['max_invoicable'];
            $stockable = $remainingMap[$lineId]['stockable'];

            // Determine invoiced qty for this line
            $qtyToInvoice = null;
            if (array_key_exists($lineId, $quantities)) {
                $qtyToInvoice = (float) $quantities[$lineId];
            } elseif (empty($quantities)) {
                // No explicit map: invoice full remaining if >0
                $qtyToInvoice = $max;
            } else {
                // Not mentioned in partial map -> skip line
                continue;
            }

            if ($qtyToInvoice <= 0.00005) {
                continue;
            }

            // Tampering check: cannot exceed max invoicable
            if ($qtyToInvoice - $max > 0.00005) {
                throw ValidationException::withMessages([
                    "lines.$lineId" => "Invoicing qty {$qtyToInvoice} exceeds invoicable {$max} for line {$line->description} (stockable=".($stockable?'yes':'no').").",
                ]);
            }

            // Historical values snapshot from the order line
            $orderedQty = (float) $line->quantity;
            if ($orderedQty <= 0.00005) {
                continue;
            }
            $ratio = $qtyToInvoice / $orderedQty;

            $unitPrice = (float) $line->unit_price;
            $discountAmount = round((float) $line->discount_amount * $ratio, 4);
            $taxAmount = round((float) $line->tax_amount * $ratio, 4);
            $lineTotal = round((float) $line->line_total * $ratio, 4);

            // Resolve income COA: item sales_account_id or default income
            $coaId = $this->resolveIncomeAccount($instituteId, $branchId, $line);

            $invoiceLines[] = [
                'description' => $line->description,
                'amount' => $lineTotal,
                'coa_id' => $coaId,
                'inventory_item_id' => $line->inventory_item_id,
                'quantity' => round($qtyToInvoice, 4),
                'unit_price' => round($unitPrice, 4),
                'discount_amount' => $discountAmount,
                'tax_rate' => (float) $line->tax_rate,
                'tax_amount' => $taxAmount,
                'sales_order_line_id' => $lineId,
                'tax_group_id' => $line->tax_group_id,
            ];

            $invoiceMetaLines[] = [
                'order_line_id' => $lineId,
                'delivery_id' => $delivery?->id,
                'quantity' => round($qtyToInvoice, 4),
                'unit_price' => round($unitPrice, 4),
                'discount_amount' => $discountAmount,
                'tax_rate' => (float) $line->tax_rate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
            ];
        }

        if (empty($invoiceLines)) {
            throw ValidationException::withMessages(['lines' => 'No invoicable lines remaining. Delivery reconciliation prevents over-invoicing.']);
        }

        // Duplicate invoicing guard: ensure at least one line has qty, and total not already fully invoiced
        // Already enforced via max check

        // Build invoice payload for Finance source of truth
        // Compute totals: total = sum(lineTotals), discount is header discount prorated, but order header discount already distributed?
        // For correctness, order discount_amount includes line+header. line discount already in lineTotal.
        // To preserve grand_total proportionality, we compute header discount share
        $headerDiscount = $this->headerDiscount($order);
        $grandTotal = (float) $order->grand_total;
        $sumLineTotalsAll = $order->lines->sum(fn ($l) => (float) $l->line_total); // all lines full
        $sumInvoicedLineTotals = array_sum(array_column($invoiceLines, 'amount'));

        // Prorate header discount: headerShare = headerDiscount * (sumInvoiced / sumAll)
        $headerShare = 0.0;
        if ($sumLineTotalsAll > 0.00005 && $headerDiscount > 0.00005) {
            $headerShare = round($headerDiscount * ($sumInvoicedLineTotals / $sumLineTotalsAll), 4);
        }

        // Invoice discount = header share (line discounts already in amount)
        $invoiceDiscount = $headerShare;

        // Invoice type: map sales to Finance 'other'
        // For sales invoices, do NOT trigger Inventory saleIssue via InvoiceService —
        // stock was already moved at delivery (DeliveryService::confirmDelivery).
        // Setting inventory_item_id to null prevents the double-issue. The original
        // item is preserved in invoice_meta.sales_lines.
        $payload = [
            'party_id' => $order->customer_id,
            'invoice_type' => 'other',
            'currency_id' => $order->currency_id,
            'discount' => $invoiceDiscount,
            'due_date' => $this->resolveDueDate($order),
            'note' => $note,
            'items' => array_map(fn ($l) => [
                'description' => $l['description'],
                'amount' => $l['amount'],
                'coa_id' => $l['coa_id'],
                'inventory_item_id' => null,
                'quantity' => $l['quantity'],
                'tax_group_id' => $l['tax_group_id'],
            ], $invoiceLines),
        ];

        // Create via Finance InvoiceService (respects invoice_auto_post, period closure, journal posting)
        $invoice = $this->invoices->create($instituteId, $branchId, $payload, $actorId);

        // Attach sales linkage: store sales ids in invoices + invoice_items, and snapshot in meta
        DB::transaction(function () use ($invoice, $order, $delivery, $invoiceLines, $invoiceMetaLines) {
            $invoice->forceFill([
                'sales_order_id' => $order->id,
                'sales_delivery_id' => $delivery?->id,
                'invoice_meta' => array_merge($invoice->invoice_meta ?? [], [
                    'sales_order_id' => $order->id,
                    'sales_order_number' => $order->order_number,
                    'sales_delivery_id' => $delivery?->id,
                    'sales_delivery_number' => $delivery?->delivery_number,
                    'source' => 'sales',
                    'sales_lines' => $invoiceMetaLines,
                    'sales_grand_total' => $order->grand_total,
                    'sales_subtotal' => $order->subtotal,
                    'sales_discount' => $order->discount_amount,
                    'sales_tax' => $order->tax_amount,
                ]),
            ])->save();

            // Update invoice items with historical snapshot
            $items = $invoice->items()->orderBy('id')->get();
            foreach ($items as $idx => $item) {
                if (! isset($invoiceLines[$idx])) {
                    continue;
                }
                $snap = $invoiceLines[$idx];
                $item->forceFill([
                    'sales_order_line_id' => $snap['sales_order_line_id'],
                    'quantity' => $snap['quantity'],
                    'unit_price' => $snap['unit_price'],
                    'discount_amount' => $snap['discount_amount'],
                    'tax_rate' => $snap['tax_rate'],
                    'tax_amount' => $snap['tax_amount'],
                ])->save();
            }
        });

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'sales_invoice',
            'entity_id' => $invoice->id,
            'after_payload' => [
                'sales_order_id' => $order->id,
                'sales_order_number' => $order->order_number,
                'delivery_id' => $delivery?->id,
                'invoice_number' => $invoice->invoice_number,
                'payable' => $invoice->payable_amount,
            ],
        ]);

            return $invoice->fresh(['items', 'party', 'journal']);
        });
    }

    public function createFromDelivery(int $instituteId, ?int $branchId, int $deliveryId, ?int $actorId = null): Invoice
    {
        $delivery = SalesDelivery::withoutGlobalScopes()
            ->where('id', $deliveryId)
            ->where('institute_id', $instituteId)
            ->first();
        if (! $delivery) {
            throw ValidationException::withMessages(['delivery_id' => 'Delivery not found.']);
        }
        if ($branchId !== null && $delivery->branch_id !== null && (int) $delivery->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages(['delivery_id' => 'Delivery not in your branch.']);
        }
        return $this->createFromOrder($instituteId, $branchId, $delivery->order_id, $deliveryId, [], $actorId);
    }

    public function invoicesForOrder(SalesOrder $order)
    {
        return Invoice::where('institute_id', $order->institute_id)
            ->where('sales_order_id', $order->id)
            ->whereNotIn('status', ['cancelled'])
            ->orderByDesc('id')
            ->get();
    }

    private function invoicedQuantityForLine(SalesOrderLine $line): float
    {
        // Lock rows when inside a transaction to prevent concurrent over-invoicing (P2 race)
        $query = InvoiceItem::query()
            ->where('sales_order_line_id', $line->id)
            ->whereHas('invoice', fn ($q) => $q->whereNotIn('status', ['cancelled']));
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        return (float) $query->sum('quantity');
    }

    private function isStockableLine(SalesOrderLine $line): bool
    {
        if (! $line->inventory_item_id) {
            return false;
        }
        $order = $line->relationLoaded('order') ? $line->order : SalesOrder::withoutGlobalScopes()->find($line->order_id);
        $branchId = $order?->branch_id;
        $item = $this->catalog->resolve($line->institute_id, $branchId, (int) $line->inventory_item_id);
        return $this->catalog->isStockable($item);
    }

    private function resolveIncomeAccount(int $instituteId, ?int $branchId, SalesOrderLine $line): ?int
    {
        if ($line->inventory_item_id) {
            $item = InventoryItem::withoutGlobalScopes()
                ->where('id', $line->inventory_item_id)
                ->where('institute_id', $instituteId)
                ->first();
            if ($item && $item->sales_account_id) {
                return (int) $item->sales_account_id;
            }
            if ($item && $item->category && $item->category->sales_account_id) {
                return (int) $item->category->sales_account_id;
            }
        }
        // Fallback to default income
        $coa = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('type', 'income')
            ->where('is_active', true)
            ->orderBy('code')
            ->first();
        if ($coa) {
            return (int) $coa->id;
        }
        $coa = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('type', 'income')
            ->where('is_active', true)
            ->orderBy('code')
            ->first();
        return $coa ? (int) $coa->id : null;
    }

    private function headerDiscount(SalesOrder $order): float
    {
        $lineDiscountSum = $order->lines->sum(fn ($l) => (float) $l->discount_amount);
        return max(0, (float) $order->discount_amount - $lineDiscountSum);
    }

    private function resolveDueDate(SalesOrder $order): ?string
    {
        // Use payment_terms if net_XX, else order date + 15
        if ($order->payment_terms && preg_match('/net_(\d+)/', $order->payment_terms, $m)) {
            $days = (int) $m[1];
            return \Carbon\Carbon::parse($order->order_date)->addDays($days)->toDateString();
        }
        return \Carbon\Carbon::parse($order->order_date)->addDays(15)->toDateString();
    }
}
