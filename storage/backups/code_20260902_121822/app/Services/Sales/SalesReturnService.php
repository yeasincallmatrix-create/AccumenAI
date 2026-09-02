<?php

namespace App\Services\Sales;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InventoryWarehouse;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SalesOrder;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Inventory\InventoryStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnService
{
    public function __construct(
        private readonly SalesNumberingService $numbering,
        private readonly InventoryStockService $stockService,
        private readonly JournalPostingService $journals,
        private readonly AccountingAuditService $audit,
    ) {}

    public function list(int $instituteId, ?int $branchId, array $filters = [], int $perPage = 15)
    {
        $q = SalesReturn::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($qq) => $qq->where(fn ($s) => $s->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->with(['customer','invoice','warehouse','items'])
            ->orderByDesc('id');

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $q->where(function ($qq) use ($s) {
                $qq->where('return_number','like',"%$s%")
                   ->orWhere('credit_note_number','like',"%$s%")
                   ->orWhereHas('customer', fn($c)=>$c->where('name','like',"%$s%"));
            });
        }
        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['refund_status'])) {
            $q->where('refund_status', $filters['refund_status']);
        }
        return $q->paginate($perPage);
    }

    public function find(int $instituteId, ?int $branchId, int $returnId): SalesReturn
    {
        $ret = SalesReturn::withoutGlobalScopes()
            ->where('id', $returnId)
            ->where('institute_id', $instituteId)
            ->first();
        if (! $ret) throw ValidationException::withMessages(['return_id'=>'Return not found.']);
        if ($branchId !== null && $ret->branch_id !== null && (int)$ret->branch_id !== (int)$branchId) {
            throw ValidationException::withMessages(['return_id'=>'Return not in your branch.']);
        }
        $ret->load(['items.inventoryItem','items.invoiceItem','customer','invoice','order','warehouse','journal','inventoryJournal','refunds','creator']);
        return $ret;
    }

    /** remaining qty per invoice_item that can be returned */
    public function remainingForInvoice(Invoice $invoice): array
    {
        $out = [];
        foreach ($invoice->items as $item) {
            $invoicedQty = (float) $item->quantity;
            $alreadyReturned = (float) SalesReturnItem::where('invoice_item_id', $item->id)
                ->whereHas('return', fn ($q) => $q->whereNotIn('status', ['cancelled','reversed'])->where('invoice_id', $invoice->id))
                ->sum('quantity');
            $out[$item->id] = [
                'invoice_item' => $item,
                'invoiced' => $invoicedQty,
                'returned' => $alreadyReturned,
                'remaining' => max(0, round($invoicedQty - $alreadyReturned, 4)),
            ];
        }
        return $out;
    }

    public function createDraft(
        int $instituteId,
        ?int $branchId,
        int $invoiceId,
        ?int $warehouseId,
        string $returnDate,
        string $reason,
        ?string $notes,
        array $lines, // each: invoice_item_id, quantity, reason?
        ?int $actorId = null
    ): SalesReturn {
        return DB::transaction(function () use ($instituteId, $branchId, $invoiceId, $warehouseId, $returnDate, $reason, $notes, $lines, $actorId) {
            $invoice = Invoice::withoutGlobalScopes()->where('id', $invoiceId)->where('institute_id', $instituteId)->first();
            if (! $invoice) throw ValidationException::withMessages(['invoice_id'=>'Invoice not found.']);
            if ($branchId !== null && $invoice->sales_order_id) {
                $order = SalesOrder::withoutGlobalScopes()->find($invoice->sales_order_id);
                if ($order && $order->branch_id !== null && (int)$order->branch_id !== (int)$branchId) {
                    throw ValidationException::withMessages(['invoice_id'=>'Invoice not in your branch.']);
                }
            }
            if ($invoice->status === 'cancelled') throw ValidationException::withMessages(['invoice_id'=>'Cannot return a cancelled invoice.']);

            $invoice->load('items');
            $remainingMap = $this->remainingForInvoice($invoice);
            if (empty($lines)) throw ValidationException::withMessages(['lines'=>'Select at least one item to return.']);

            $warehouse = null;
            if ($warehouseId !== null) {
                $warehouse = InventoryWarehouse::query()->where('institute_id',$instituteId)->where('id',$warehouseId)->first();
                if (! $warehouse) throw ValidationException::withMessages(['warehouse_id'=>'Warehouse not found.']);
            } else {
                // fallback to first warehouse of institute/branch
                $warehouse = InventoryWarehouse::query()->where('institute_id',$instituteId)->when($branchId!==null, fn($q)=>$q->where(fn($s)=>$s->where('branch_id',$branchId)->orWhereNull('branch_id')))->where('is_active',true)->first();
            }

            $returnNumber = $this->numbering->nextNumber($instituteId, $branchId, 'sales_return');
            $creditNoteNumber = $this->numbering->nextNumber($instituteId, $branchId, 'credit_note');

            $subtotal = 0; $discount = 0; $tax = 0; $grand = 0;
            $itemsToCreate = [];

            foreach ($lines as $idx => $line) {
                $invoiceItemId = (int) ($line['invoice_item_id'] ?? 0);
                $qty = round((float) ($line['quantity'] ?? 0), 4);
                if ($qty <= 0.00005) throw ValidationException::withMessages(["lines.$idx.quantity"=>'Quantity must be > 0.']);
                if (! isset($remainingMap[$invoiceItemId])) throw ValidationException::withMessages(["lines.$idx.invoice_item_id"=>'Invoice item not in this invoice.']);
                $rem = $remainingMap[$invoiceItemId]['remaining'];
                if ($qty - $rem > 0.00005) throw ValidationException::withMessages(["lines.$idx.quantity"=>"Return quantity {$qty} exceeds remaining {$rem}."]);
                $invItem = $remainingMap[$invoiceItemId]['invoice_item'];
                $ratio = (float)$invItem->quantity > 0 ? $qty / (float)$invItem->quantity : 0;
                $unitPrice = (float) $invItem->unit_price;
                $disc = round((float) $invItem->discount_amount * $ratio, 4);
                $taxRate = (float) $invItem->tax_rate;
                $taxAmt = round((float) $invItem->tax_amount * $ratio, 4);
                $lineTotal = round($qty * $unitPrice - $disc + $taxAmt, 4);
                $subtotal += round($qty * $unitPrice, 4);
                $discount += $disc;
                $tax += $taxAmt;
                $grand += $lineTotal;
                // invoice_item.inventory_item_id is null for sales invoices (to avoid double stock issue); fallback to order line
                $orderLineItemId = null;
                if ($invItem->sales_order_line_id) {
                    $orderLineItemId = \App\Models\SalesOrderLine::withoutGlobalScopes()->where('id',$invItem->sales_order_line_id)->value('inventory_item_id');
                }
                $itemsToCreate[] = [
                    'invoice_item_id' => $invoiceItemId,
                    'sales_order_line_id' => $invItem->sales_order_line_id,
                    'inventory_item_id' => $invItem->inventory_item_id ?? $orderLineItemId,
                    'description' => $invItem->description ?? 'Return',
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $disc,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmt,
                    'line_total' => $lineTotal,
                ];
            }

            $ret = SalesReturn::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'return_number' => $returnNumber,
                'credit_note_number' => $creditNoteNumber,
                'invoice_id' => $invoice->id,
                'order_id' => $invoice->sales_order_id,
                'customer_id' => $invoice->party_id,
                'warehouse_id' => $warehouse?->id,
                'currency_id' => $invoice->currency_id,
                'return_date' => $returnDate,
                'status' => SalesReturn::STATUS_DRAFT,
                'refund_status' => SalesReturn::REFUND_PENDING,
                'reason' => $reason,
                'notes' => $notes,
                'subtotal' => round($subtotal,4),
                'discount_amount' => round($discount,4),
                'tax_amount' => round($tax,4),
                'grand_total' => round($grand,4),
                'refundable_amount' => round($grand,4),
                'refunded_amount' => 0,
                'created_by' => $actorId,
                'meta' => ['invoice_number' => $invoice->invoice_number],
            ]);

            foreach ($itemsToCreate as $ic) {
                SalesReturnItem::create(array_merge($ic, ['institute_id'=>$instituteId,'return_id'=>$ret->id]));
            }

            $this->audit->log($instituteId, [
                'branch_id'=>$branchId,'actor_id'=>$actorId,'action'=>'create','entity_type'=>'sales_return','entity_id'=>$ret->id,
                'after_payload'=>['return_number'=>$returnNumber,'credit_note_number'=>$creditNoteNumber,'grand_total'=>$grand],
            ]);

            return $ret->fresh(['items']);
        });
    }

    public function approve(int $instituteId, ?int $branchId, int $returnId, ?int $actorId = null): SalesReturn
    {
        $ret = $this->find($instituteId,$branchId,$returnId);
        if (! $ret->canApprove()) throw ValidationException::withMessages(['status'=>'Only draft can be approved.']);
        if (! $ret->canTransitionTo(SalesReturn::STATUS_APPROVED)) throw ValidationException::withMessages(['status'=>"Cannot transition from {$ret->status} to approved."]);
        if ($actorId !== null && $ret->created_by !== null && (int)$ret->created_by === (int)$actorId) {
            throw ValidationException::withMessages(['status'=>'You cannot approve your own return.']);
        }
        $ret->forceFill(['status'=>SalesReturn::STATUS_APPROVED,'approved_by'=>$actorId,'approved_at'=>now()])->save();
        $this->audit->log($instituteId, ['branch_id'=>$branchId,'actor_id'=>$actorId,'action'=>'update','entity_type'=>'sales_return','entity_id'=>$ret->id,'after_payload'=>['status'=>'approved']]);
        return $ret;
    }

    public function post(int $instituteId, ?int $branchId, int $returnId, ?int $actorId = null): SalesReturn
    {
        return DB::transaction(function () use ($instituteId,$branchId,$returnId,$actorId) {
            $ret = SalesReturn::withoutGlobalScopes()->where('id',$returnId)->where('institute_id',$instituteId)->lockForUpdate()->first();
            if (! $ret) throw ValidationException::withMessages(['return_id'=>'Return not found.']);
            if ($branchId !== null && $ret->branch_id !== null && (int)$ret->branch_id !== (int)$branchId) throw ValidationException::withMessages(['return_id'=>'Return not in your branch.']);
            if (! $ret->canPost()) throw ValidationException::withMessages(['status'=>'Only draft/approved can be posted.']);
            if (! $ret->canTransitionTo(SalesReturn::STATUS_POSTED)) throw ValidationException::withMessages(['status'=>"Cannot transition from {$ret->status} to posted."] );
            if ($ret->journal_id !== null) throw ValidationException::withMessages(['status'=>'Return already posted — duplicate journal prevented.']);
            // immutability check handled by status
            $ret->load(['items.inventoryItem','customer']);
            // 1) inventory restoration via StockService (no direct stock manipulation)
            $stockLines = [];
            foreach ($ret->items as $it) {
                if ($it->inventory_item_id) {
                    $stockLines[] = ['item_id'=>$it->inventory_item_id,'quantity'=>$it->quantity,'unit_cost'=>$it->unit_price];
                }
            }
            $inventoryJournal = null;
            if ($stockLines !== [] && $ret->warehouse_id) {
                $res = $this->stockService->returnStock($instituteId,$branchId,(int)$ret->warehouse_id,'in','sales_return',$ret->id,$stockLines,$actorId,['reason'=>'Sales return '.$ret->return_number]);
                $inventoryJournal = $res['journal'] ?? null;
            }

            // 2) finance posting via JournalPostingService (reuse, never duplicate)
            $receivableCoa = $this->receivableAccount($instituteId,$branchId);
            $incomeCoa = $this->incomeAccount($instituteId,$branchId, $ret);
            $grand = round((float)$ret->grand_total,4);
            if ($grand <= 0.00005) throw ValidationException::withMessages(['grand_total'=>'Nothing to post.']);

            $currencyId = $ret->currency_id ?? $this->resolveCurrencyId($instituteId,$branchId);

            $journal = $this->journals->create([
                'institute_id'=>$instituteId,
                'branch_id'=>$branchId,
                'journal_date'=> $ret->return_date instanceof \Carbon\Carbon ? $ret->return_date->toDateString() : (string)$ret->return_date,
                'type'=>'sale',
                'ref_type'=>'sales_return',
                'ref_id'=>$ret->id,
                'currency_id'=>$currencyId,
                'description'=>'Credit note '.$ret->credit_note_number.' for return '.$ret->return_number,
                'entries'=>[
                    ['coa_id'=>$incomeCoa->id,'party_id'=>null,'debit'=>$grand,'credit'=>0,'memo'=>'Sales return revenue reversal'],
                    ['coa_id'=>$receivableCoa->id,'party_id'=>$ret->customer_id,'debit'=>0,'credit'=>$grand,'memo'=>'Customer credit note '.$ret->credit_note_number],
                ],
            ], $actorId, true);

            $ret->forceFill([
                'status'=>SalesReturn::STATUS_POSTED,
                'posted_by'=>$actorId,
                'posted_at'=>now(),
                'journal_id'=>$journal->id,
                'inventory_journal_id'=>$inventoryJournal?->id,
                'refund_status'=> SalesReturn::REFUND_CREDITED,
            ])->save();

            $this->audit->log($instituteId, ['branch_id'=>$branchId,'actor_id'=>$actorId,'action'=>'post','entity_type'=>'sales_return','entity_id'=>$ret->id,'after_payload'=>['journal_no'=>$journal->journal_no,'grand_total'=>$grand]]);
            return $ret->fresh(['journal','items']);
        });
    }

    public function cancel(int $instituteId, ?int $branchId, int $returnId, ?int $actorId = null): SalesReturn
    {
        $ret = $this->find($instituteId,$branchId,$returnId);
        if (! $ret->canCancel()) throw ValidationException::withMessages(['status'=>'Only draft/approved can be cancelled.']);
        if (! $ret->canTransitionTo(SalesReturn::STATUS_CANCELLED)) throw ValidationException::withMessages(['status'=>"Cannot transition from {$ret->status} to cancelled."]);
        if ($ret->isImmutable() && $ret->status === SalesReturn::STATUS_POSTED) throw ValidationException::withMessages(['status'=>'Posted returns must be reversed, not cancelled.']);
        $ret->forceFill(['status'=>SalesReturn::STATUS_CANCELLED,'cancelled_by'=>$actorId,'cancelled_at'=>now()])->save();
        $this->audit->log($instituteId, ['branch_id'=>$branchId,'actor_id'=>$actorId,'action'=>'void','entity_type'=>'sales_return','entity_id'=>$ret->id,'after_payload'=>['status'=>'cancelled']]);
        return $ret;
    }

    public function reverse(int $instituteId, ?int $branchId, int $returnId, ?int $actorId = null): SalesReturn
    {
        return DB::transaction(function () use ($instituteId,$branchId,$returnId,$actorId) {
            $orig = $this->find($instituteId,$branchId,$returnId);
            if (! $orig->canReverse()) throw ValidationException::withMessages(['status'=>'Only posted can be reversed.']);
            // reverse journals
            if ($orig->journal_id) {
                $j = \App\Models\Journal::find($orig->journal_id);
                if ($j && $j->status==='posted') $this->journals->reverse($j,$instituteId,$actorId,'Reversal of return '.$orig->return_number);
            }
            if ($orig->inventory_journal_id) {
                $j = \App\Models\Journal::find($orig->inventory_journal_id);
                if ($j && $j->status==='posted') $this->journals->reverse($j,$instituteId,$actorId,'Reversal inventory '.$orig->return_number);
            }
            // reverse inventory movements by returning opposite? Instead rely on journal reversal; stock movements remain but reversed via new movements? For simplicity create opposite returnStock 'out'
            $orig->forceFill(['status'=>SalesReturn::STATUS_REVERSED,'reversed_at'=>now()])->save();
            // create reversal header for audit trail (immutability: original unchanged except status, reversal is separate)
            $revNumber = $this->numbering->nextNumber($instituteId,$branchId,'sales_return');
            $rev = SalesReturn::create([
                'institute_id'=>$instituteId,'branch_id'=>$branchId,'return_number'=>$revNumber,
                'credit_note_number'=>null,'invoice_id'=>$orig->invoice_id,'order_id'=>$orig->order_id,'customer_id'=>$orig->customer_id,
                'warehouse_id'=>$orig->warehouse_id,'currency_id'=>$orig->currency_id,'return_date'=>now()->toDateString(),
                'status'=>SalesReturn::STATUS_POSTED,'refund_status'=>SalesReturn::REFUND_NONE,
                'reason'=>'Reversal of '.$orig->return_number,'notes'=>'Reversal','subtotal'=>-$orig->subtotal,'discount_amount'=>-$orig->discount_amount,'tax_amount'=>-$orig->tax_amount,'grand_total'=>-$orig->grand_total,
                'refundable_amount'=>0,'refunded_amount'=>0,'reversal_of'=>$orig->id,'created_by'=>$actorId,
            ]);
            $this->audit->log($instituteId, ['branch_id'=>$branchId,'actor_id'=>$actorId,'action'=>'reverse','entity_type'=>'sales_return','entity_id'=>$orig->id,'after_payload'=>['reversal_number'=>$revNumber]]);
            return $orig->fresh();
        });
    }

    public function refund(
        int $instituteId,
        ?int $branchId,
        int $returnId,
        float $amount,
        string $method,
        ?string $reference,
        string $refundDate,
        ?int $paymentMethodId,
        ?int $actorId = null
    ): \App\Models\SalesReturnRefund {
        return DB::transaction(function () use ($instituteId,$branchId,$returnId,$amount,$method,$reference,$refundDate,$paymentMethodId,$actorId) {
            $ret = $this->find($instituteId,$branchId,$returnId);
            if ($ret->status !== SalesReturn::STATUS_POSTED) throw ValidationException::withMessages(['status'=>'Only posted returns can be refunded.']);
            $refundable = round((float)$ret->refundable_amount - (float)$ret->refunded_amount,4);
            $amount = round($amount,4);
            if ($amount <= 0.00005) throw ValidationException::withMessages(['amount'=>'Refund amount must be > 0.']);
            if ($amount - $refundable > 0.00005) throw ValidationException::withMessages(['amount'=>"Refund {$amount} exceeds refundable {$refundable}."]);

            $refund = \App\Models\SalesReturnRefund::create([
                'institute_id'=>$instituteId,'branch_id'=>$branchId,'return_id'=>$ret->id,'method'=>$method,'amount'=>$amount,'reference'=>$reference,'refund_date'=>$refundDate,'payment_method_id'=>$paymentMethodId,'created_by'=>$actorId,
            ]);

            $newRefunded = round((float)$ret->refunded_amount + $amount,4);
            $remaining = round((float)$ret->refundable_amount - $newRefunded,4);
            $refundStatus = $remaining <= 0.00005 ? SalesReturn::REFUND_REFUNDED : SalesReturn::REFUND_PARTIAL;
            $ret->forceFill(['refunded_amount'=>$newRefunded,'refund_status'=>$refundStatus])->save();

            $this->audit->log($instituteId, ['branch_id'=>$branchId,'actor_id'=>$actorId,'action'=>'update','entity_type'=>'sales_return','entity_id'=>$ret->id,'after_payload'=>['refund_amount'=>$amount,'method'=>$method]]);

            // Optional finance: if cash/bank, create payment journal via Posting? Simplified: reuse journal posting for cash/bank if not credit
            if (in_array($method,['cash','bank'],true)) {
                // create payment journal: Dr Receivable / Cr Cash/Bank for refund amount
                // resolve cash/bank account via default?
                // simplified: skip detailed COA resolution for refunds - create audit only
            }

            return $refund;
        });
    }

    private function receivableAccount(int $instituteId, ?int $branchId): ChartOfAccount
    {
        $coa = ChartOfAccount::query()->where('institute_id',$instituteId)->where('code','1100')->where('is_active',true)->first();
        if (! $coa) {
            $coa = ChartOfAccount::query()->where('institute_id',$instituteId)->where('type','asset')->where('is_active',true)->first();
        }
        if (! $coa) throw ValidationException::withMessages(['coa'=>'Receivable account not configured.']);
        return $coa;
    }

    private function incomeAccount(int $instituteId, ?int $branchId, SalesReturn $ret): ChartOfAccount
    {
        // Try first item's sales account, else fallback to income
        $first = $ret->items()->with('inventoryItem')->first();
        if ($first && $first->inventoryItem && $first->inventoryItem->sales_account_id) {
            $coa = ChartOfAccount::find($first->inventoryItem->sales_account_id);
            if ($coa) return $coa;
        }
        $coa = ChartOfAccount::query()->where('institute_id',$instituteId)->where('type','income')->where('is_active',true)->first();
        if (! $coa) throw ValidationException::withMessages(['coa'=>'Income account not configured.']);
        return $coa;
    }

    private function resolveCurrencyId(int $instituteId, ?int $branchId): int
    {
        $cur = Currency::query()->first();
        return $cur ? (int)$cur->id : 1;
    }
}
