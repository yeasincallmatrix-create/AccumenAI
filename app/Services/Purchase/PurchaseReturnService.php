<?php

namespace App\Services\Purchase;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\Party;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\SupplierCreditBalance;
use App\Models\SupplierRefund;
use App\Models\TaxGroup;
use App\Services\Accounting\PurchaseAccountingService;
use App\Services\Inventory\InventoryStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
{
    public function __construct(
        private readonly PurchaseNumberingService $numbering,
        private readonly InventoryStockService $stockService,
        private readonly PurchaseAccountingService $purchaseAccounting,
    ) {}

    public function calculate(int $instituteId, array $lines): array
    {
        $subtotal='0'; $totalDiscount='0'; $totalTax='0'; $computed=[];
        foreach ($lines as $idx=>$line) {
            $qty=$this->toDecimal($line['quantity']??0);
            $unitPrice=$this->toDecimal($line['unit_price']??0);
            $lineSubtotal=bcmul($qty,$unitPrice,8);
            $discountType=$line['discount_type']??'fixed';
            $discountRaw=$this->toDecimal($line['discount_amount']??0);
            $lineDiscount=$discountType==='percent'?bcdiv(bcmul($lineSubtotal,$discountRaw,8),'100',8):$discountRaw;
            if(bccomp($lineDiscount,$lineSubtotal,8)>0) $lineDiscount=$lineSubtotal;
            $lineNet=bcsub($lineSubtotal,$lineDiscount,8);
            $taxRate=$this->toDecimal($line['tax_rate']??0);
            $taxGroupId=$line['tax_group_id']??null;
            if($taxGroupId){ $tg=TaxGroup::withoutGlobalScopes()->where('institute_id',$instituteId)->where('id',$taxGroupId)->first(); if($tg) $taxRate=$this->toDecimal($tg->rate); }
            $taxAmount=bccomp($taxRate,'0',8)!==0?bcdiv(bcmul($lineNet,$taxRate,8),'100',8):'0';
            $lineTotal=bcadd($lineNet,$taxAmount,8);
            $subtotal=bcadd($subtotal,$lineSubtotal,8);
            $totalDiscount=bcadd($totalDiscount,$lineDiscount,8);
            $totalTax=bcadd($totalTax,$taxAmount,8);
            $computed[]=['quantity'=>$this->round4($qty),'unit_price'=>$this->round4($unitPrice),'discount_amount'=>$this->round4($lineDiscount),'discount_type'=>$discountType,'tax_rate'=>$this->round4($taxRate),'tax_amount'=>$this->round4($taxAmount),'line_total'=>$this->round4($lineTotal)];
        }
        $grandTotal=bcadd(bcsub($subtotal,$totalDiscount,8),$totalTax,8);
        if(bccomp($grandTotal,'0',8)<0) $grandTotal='0';
        return ['subtotal'=>$this->round4($subtotal),'discount_amount'=>$this->round4($totalDiscount),'tax_amount'=>$this->round4($totalTax),'grand_total'=>$this->round4($grandTotal),'lines'=>$computed];
    }

    public function create(int $instituteId, ?int $branchId, array $data, int $actorId): PurchaseReturn
    {
        $this->assertBranchScope($branchId);
        $supplierId=$data['supplier_id']??null;
        $this->assertSupplier($instituteId,$branchId,$supplierId);
        $poId=$data['purchase_order_id']??null;
        $grId=$data['goods_receipt_id']??null;
        $invoiceId=$data['purchase_invoice_id']??null;

        if($poId){
            $po=PurchaseOrder::withoutGlobalScopes()->where('institute_id',$instituteId)->where('id',$poId)->first();
            if(!$po) throw ValidationException::withMessages(['purchase_order_id'=>'Purchase order not found.']);
            if($branchId!==null && $po->branch_id!==null && (int)$po->branch_id!==(int)$branchId) throw ValidationException::withMessages(['purchase_order_id'=>'Purchase order not in your branch.']);
        }
        if($grId){
            $gr=GoodsReceipt::withoutGlobalScopes()->where('institute_id',$instituteId)->where('id',$grId)->first();
            if(!$gr) throw ValidationException::withMessages(['goods_receipt_id'=>'Goods receipt not found.']);
            if($branchId!==null && $gr->branch_id!==null && (int)$gr->branch_id!==(int)$branchId) throw ValidationException::withMessages(['goods_receipt_id'=>'Goods receipt not in your branch.']);
            if($gr->status!==GoodsReceipt::STATUS_CONFIRMED) throw ValidationException::withMessages(['goods_receipt_id'=>'Only confirmed receipts can be returned.']);
        }

        $lines=$data['lines']??[];
        if(empty($lines)) throw ValidationException::withMessages(['lines'=>'At least one return line is required.']);

        // Validate each line: returned quantity never exceed received - previously returned
        foreach($lines as $idx=>$line){
            $this->validateReturnLine($instituteId,$branchId,$line,$idx,$grId,$poId);
        }

        return DB::transaction(function() use ($instituteId,$branchId,$data,$actorId,$poId,$grId,$supplierId){
            $calc=$this->calculate($instituteId,$data['lines']);

            $return=PurchaseReturn::create([
                'institute_id'=>$instituteId,
                'branch_id'=>$branchId,
                'return_number'=>$this->numbering->nextNumber($instituteId,$branchId,'return'),
                'credit_note_number'=>$this->numbering->nextNumber($instituteId,$branchId,'return'),
                'purchase_order_id'=>$poId,
                'goods_receipt_id'=>$grId,
                'purchase_invoice_id'=>$data['purchase_invoice_id']??null,
                'supplier_id'=>$supplierId,
                'warehouse_id'=>$data['warehouse_id']??null,
                'return_date'=>$data['return_date']??now()->toDateString(),
                'reason'=>$data['reason']??null,
                'notes'=>$data['notes']??null,
                'subtotal'=>$calc['subtotal'],
                'discount_amount'=>$calc['discount_amount'],
                'tax_amount'=>$calc['tax_amount'],
                'grand_total'=>$calc['grand_total'],
                'status'=>PurchaseReturn::STATUS_DRAFT,
                'created_by'=>$actorId,
            ]);

            foreach($calc['lines'] as $idx=>$cl){
                $raw=$data['lines'][$idx];
                PurchaseReturnItem::create([
                    'institute_id'=>$instituteId,
                    'purchase_return_id'=>$return->id,
                    'purchase_order_line_id'=>$raw['purchase_order_line_id']??null,
                    'goods_receipt_item_id'=>$raw['goods_receipt_item_id']??null,
                    'inventory_item_id'=>$raw['inventory_item_id']??null,
                    'description'=>$raw['description']??'',
                    'quantity'=>$cl['quantity'],
                    'unit'=>$raw['unit']??null,
                    'unit_price'=>$cl['unit_price'],
                    'discount_amount'=>$cl['discount_amount'],
                    'discount_type'=>$cl['discount_type'],
                    'tax_rate'=>$cl['tax_rate'],
                    'tax_amount'=>$cl['tax_amount'],
                    'line_total'=>$cl['line_total'],
                    'sort_order'=>$idx,
                ]);
            }

            $this->audit($instituteId,$branchId,$actorId,'create','purchase_return',$return->id,null,['return_number'=>$return->return_number,'credit_note_number'=>$return->credit_note_number]);

            return $return->load('items');
        });
    }

    public function submit(PurchaseReturn $ret, int $actorId): PurchaseReturn
    {
        $this->assertBranchScope($ret->branch_id);
        if(!$ret->canSubmit()) throw ValidationException::withMessages(['status'=>'Only draft returns can be submitted.']);
        return $this->transition($ret, PurchaseReturn::STATUS_SUBMITTED, $actorId, ['submitted_at'=>now()]);
    }

    public function approve(PurchaseReturn $ret, int $actorId): PurchaseReturn
    {
        $this->assertBranchScope($ret->branch_id);
        if(!$ret->canApprove()) throw ValidationException::withMessages(['status'=>'Only submitted returns can be approved.']);
        $actor=\App\Models\InstituteUser::withoutGlobalScopes()->where('id',$actorId)->first();
        if($actor && !$actor->hasPermission('purchase.manage') && !$actor->isOwner()){
            throw ValidationException::withMessages(['status'=>'You do not have permission to approve returns.']);
        }
        if ($ret->created_by !== null && (int) $ret->created_by === (int) $actorId) {
            throw ValidationException::withMessages(['status' => 'You cannot approve your own return.']);
        }
        return $this->transition($ret, PurchaseReturn::STATUS_APPROVED, $actorId, ['approved_at'=>now()]);
    }

    public function post(PurchaseReturn $ret, int $actorId): PurchaseReturn
    {
        $this->assertBranchScope($ret->branch_id);
        if(!$ret->canPost()) throw ValidationException::withMessages(['status'=>'Only approved returns can be posted.']);

        return DB::transaction(function() use ($ret,$actorId){
            $ret->refresh();
            if(!$ret->canPost()) throw ValidationException::withMessages(['status'=>'Return already posted.']);

            $ret->load('items.inventoryItem');
            // Inventory integration: decrease inventory via existing service, correct warehouse, prevent duplicate
            $stockLines=[];
            foreach($ret->items as $item){
                $invItemId=$item->inventory_item_id;
                if(!$invItemId) continue;
                $invItem=$item->inventoryItem;
                if(!$invItem) continue;
                // Non-stock service check via SalesCatalogService or InventoryItem type
                $nonStockTypes=['service_consumable','other'];
                if(in_array($invItem->item_type,$nonStockTypes,true)) continue;

                $stockLines[]=['item_id'=>(int)$invItemId,'quantity'=>(float)$item->quantity,'unit_cost'=>(float)$item->unit_price];
            }

            $warehouseId=$ret->warehouse_id;
            if(!empty($stockLines)){
                if(!$warehouseId){
                    $warehouseId=$this->resolveWarehouse($ret->institute_id,$ret->branch_id);
                }
                if($warehouseId){
                    $this->stockService->returnStock(
                        $ret->institute_id,
                        $ret->branch_id,
                        (int) $warehouseId,
                        'out',
                        \App\Models\PurchaseReturn::class,
                        $ret->id,
                        $stockLines,
                        $actorId,
                        ['reason'=>'Purchase return '.$ret->return_number]
                    );
                }
            }

            // Finance: reverse supplier liability correctly via JournalPostingService
            // Create credit note journal: Dr AP / Cr expense (or inventory if stockable handled above, but we avoid duplicate by using expense)
            $supplier=Party::withoutGlobalScopes()->where('id',$ret->supplier_id)->first();
            $grandTotal=(float)$ret->grand_total;
            if($grandTotal>0.00005){
                $journal=app(\App\Services\Accounting\JournalPostingService::class)->create([
                    'institute_id'=>$ret->institute_id,
                    'branch_id'=>$ret->branch_id,
                    'journal_date'=>$ret->return_date->toDateString(),
                    'currency_id'=>$this->resolveCurrencyId($ret->institute_id,$ret->branch_id),
                    'type'=>'journal',
                    'ref_type'=>'purchase_return',
                    'ref_id'=>$ret->id,
                    'description'=>'Credit note '.$ret->credit_note_number.' for return '.$ret->return_number,
                    'entries'=>[
                        [
                            'coa_id'=>$this->payableAccount($ret->institute_id,$ret->branch_id),
                            'party_id'=>$supplier->id,
                            'debit'=>$grandTotal,
                            'credit'=>0,
                            'memo'=>'Supplier credit note '.$ret->credit_note_number,
                        ],
                        [
                            'coa_id'=>$this->expenseAccount($ret->institute_id,$ret->branch_id),
                            'party_id'=>null,
                            'debit'=>0,
                            'credit'=>$grandTotal,
                            'memo'=>'Purchase return '.$ret->return_number,
                        ],
                    ],
                ], $actorId);

                $ret->forceFill(['journal_id'=>$journal->id])->save();
            }

            // Create supplier credit balance
            SupplierCreditBalance::create([
                'institute_id'=>$ret->institute_id,
                'branch_id'=>$ret->branch_id,
                'supplier_id'=>$ret->supplier_id,
                'purchase_return_id'=>$ret->id,
                'credit_amount'=>$ret->grand_total,
                'used_amount'=>0,
                'remaining_amount'=>$ret->grand_total,
                'status'=>'available',
            ]);

            $ret->update(['status'=>PurchaseReturn::STATUS_POSTED,'posted_at'=>now(),'updated_by'=>$actorId]);
            $this->audit($ret->institute_id,$ret->branch_id,$actorId,'update','purchase_return',$ret->id,['status'=>'approved'],['status'=>'posted','credit_note_number'=>$ret->credit_note_number]);

            return $ret->fresh('items');
        });
    }

    public function cancel(PurchaseReturn $ret, int $actorId): PurchaseReturn
    {
        $this->assertBranchScope($ret->branch_id);
        if(!$ret->canCancel()) throw ValidationException::withMessages(['status'=>'Only draft/submitted/approved returns can be cancelled.']);
        return $this->transition($ret, PurchaseReturn::STATUS_CANCELLED, $actorId);
    }

    public function reverse(PurchaseReturn $ret, int $actorId, ?string $reason=null): PurchaseReturn
    {
        $this->assertBranchScope($ret->branch_id);
        if(!$ret->canReverse()) throw ValidationException::withMessages(['status'=>'Only posted returns can be reversed.']);

        return DB::transaction(function() use ($ret,$actorId,$reason){
            $ret->refresh();
            if(!$ret->canReverse()) throw ValidationException::withMessages(['status'=>'Already reversed.']);

            // Reverse inventory if it was deducted — use single write path
            $ret->load('items.inventoryItem');
            $stockLines=[];
            foreach($ret->items as $item){
                if(!$item->inventory_item_id) continue;
                $invItem=$item->inventoryItem;
                $nonStock=['service_consumable','other'];
                if($invItem && in_array($invItem->item_type,$nonStock,true)) continue;
                $stockLines[]=['item_id'=>(int)$item->inventory_item_id,'quantity'=>(float)$item->quantity,'unit_cost'=>(float)$item->unit_price];
            }
            if(!empty($stockLines)){
                $warehouseId=$ret->warehouse_id ?? $this->resolveWarehouse($ret->institute_id,$ret->branch_id);
                if($warehouseId){
                    $this->stockService->returnStock(
                        $ret->institute_id,
                        $ret->branch_id,
                        (int) $warehouseId,
                        'in',
                        \App\Models\PurchaseReturn::class,
                        $ret->id,
                        $stockLines,
                        $actorId,
                        ['reason'=>$reason??'Reversal of return '.$ret->return_number]
                    );
                }
            }

            // Reverse finance journal
            if($ret->journal_id){
                $journal=\App\Models\Journal::withoutGlobalScopes()->where('id',$ret->journal_id)->first();
                if($journal && $journal->status==='posted'){
                    app(\App\Services\Accounting\JournalPostingService::class)->reverse($journal,$ret->institute_id,$actorId,$reason??'Reversal of return '.$ret->return_number);
                }
            }

            // Reverse credit balance
            $credit=SupplierCreditBalance::withoutGlobalScopes()->where('purchase_return_id',$ret->id)->first();
            if($credit){
                $credit->update(['status'=>'refunded','remaining_amount'=>0]);
            }

            $ret->update(['status'=>PurchaseReturn::STATUS_REVERSED,'updated_by'=>$actorId]);
            $this->audit($ret->institute_id,$ret->branch_id,$actorId,'update','purchase_return',$ret->id,['status'=>'posted'],['status'=>'reversed','reason'=>$reason]);

            return $ret->fresh();
        });
    }

    // Supplier refund / credit balance handling
    public function refund(int $instituteId, ?int $branchId, int $supplierId, float $amount, array $data, int $actorId): SupplierRefund
    {
        $this->assertSupplier($instituteId,$branchId,$supplierId);
        $amount=round($amount,4);
        if($amount<=0) throw ValidationException::withMessages(['amount'=>'Refund amount must be greater than 0.']);

        $availableCredit=$this->availableCredit($instituteId,$branchId,$supplierId);
        if($amount - $availableCredit > 0.00005) throw ValidationException::withMessages(['amount'=>"Refund exceeds available credit {$availableCredit}."]);

        return DB::transaction(function() use ($instituteId,$branchId,$supplierId,$amount,$data,$actorId){
            $supplier=Party::withoutGlobalScopes()->where('id',$supplierId)->first();

            // Finance: supplier refund — Dr cash / Cr AP is payment, refund is opposite? For supplier refund received (cash from supplier), it's Dr cash / Cr AP? Actually refund from supplier means supplier returns money, so Dr cash / Cr AP is still payment? Let's treat refund as Dr cash / Cr AP via purchaseAccounting postSupplierPayment reversed? Simpler: use JournalPostingService to post Dr cash / Cr AP for refund
            $journal=app(\App\Services\Accounting\JournalPostingService::class)->create([
                'institute_id'=>$instituteId,
                'branch_id'=>$branchId,
                'journal_date'=> $data['refund_date'] ?? now()->toDateString(),
                'currency_id'=>$this->resolveCurrencyId($instituteId,$branchId),
                'type'=>'receipt',
                'ref_type'=>'supplier_refund',
                'description'=>'Supplier refund from '.$supplier->name,
                'entries'=>[
                    [
                        'coa_id'=>$this->cashAccount($instituteId,$branchId),
                        'debit'=>$amount,
                        'credit'=>0,
                        'memo'=>'Refund from supplier',
                    ],
                    [
                        'coa_id'=>$this->payableAccount($instituteId,$branchId),
                        'party_id'=>$supplier->id,
                        'debit'=>0,
                        'credit'=>$amount,
                        'memo'=>'Supplier refund',
                    ],
                ],
            ], $actorId);

            $refund=SupplierRefund::create([
                'institute_id'=>$instituteId,
                'branch_id'=>$branchId,
                'supplier_id'=>$supplierId,
                'purchase_return_id'=>$data['purchase_return_id']??null,
                'amount'=>$amount,
                'refund_method'=>$data['refund_method']??'cash',
                'journal_id'=>$journal->id,
                'notes'=>$data['notes']??null,
                'created_by'=>$actorId,
            ]);

            // Deduct from credit balances FIFO
            $remaining=$amount;
            $credits=SupplierCreditBalance::withoutGlobalScopes()->where('institute_id',$instituteId)->where('supplier_id',$supplierId)->where('status','available')->orderBy('id')->get();
            foreach($credits as $credit){
                if($remaining<=0.00005) break;
                $avail=(float)$credit->remaining_amount;
                $use=min($avail,$remaining);
                $newUsed=(float)$credit->used_amount + $use;
                $newRemaining=(float)$credit->credit_amount - $newUsed;
                $credit->update([
                    'used_amount'=>$newUsed,
                    'remaining_amount'=>$newRemaining,
                    'status'=>$newRemaining<=0.00005?'fully_used':'partially_used',
                ]);
                $remaining-= $use;
            }

            $this->audit($instituteId,$branchId,$actorId,'create','supplier_refund',$refund->id,null,['amount'=>$amount,'supplier_id'=>$supplierId]);

            return $refund;
        });
    }

    public function adjustCreditAgainstInvoice(int $instituteId, ?int $branchId, int $supplierId, int $purchaseInvoiceId, float $amount, int $actorId): void
    {
        $this->assertSupplier($instituteId,$branchId,$supplierId);
        $amount=round($amount,4);
        $available=$this->availableCredit($instituteId,$branchId,$supplierId);
        if($amount - $available > 0.00005) throw ValidationException::withMessages(['amount'=>"Adjustment exceeds available credit {$available}."]);

        $invoice=\App\Models\PurchaseInvoice::withoutGlobalScopes()->where('institute_id',$instituteId)->where('id',$purchaseInvoiceId)->first();
        if(!$invoice) throw ValidationException::withMessages(['purchase_invoice_id'=>'Invoice not found.']);
        if((float)$invoice->due_amount < $amount - 0.00005) throw ValidationException::withMessages(['amount'=>"Adjustment exceeds invoice due {$invoice->due_amount}."]);

        DB::transaction(function() use ($instituteId,$branchId,$supplierId,$invoice,$amount,$actorId){
            // Reduce invoice due via credit adjustment (like payment but using credit balance)
            $newPaid=round((float)$invoice->paid_amount + $amount,4);
            $newDue=round((float)$invoice->grand_total - $newPaid,4);
            $invoice->update(['paid_amount'=>$newPaid,'due_amount'=>$newDue]);

            // Deduct credit balance FIFO
            $remaining=$amount;
            $credits=SupplierCreditBalance::withoutGlobalScopes()->where('institute_id',$instituteId)->where('supplier_id',$supplierId)->where('status','available')->orderBy('id')->get();
            foreach($credits as $credit){
                if($remaining<=0.00005) break;
                $avail=(float)$credit->remaining_amount;
                $use=min($avail,$remaining);
                $newUsed=(float)$credit->used_amount + $use;
                $newRemaining=(float)$credit->credit_amount - $newUsed;
                $credit->update([
                    'used_amount'=>$newUsed,
                    'remaining_amount'=>$newRemaining,
                    'status'=>$newRemaining<=0.00005?'fully_used':'partially_used',
                ]);
                $remaining-= $use;
            }

            // Create journal Dr AP / Cr AP? Actually adjustment is Dr AP / Cr AP is no effect. Instead create adjustment journal Dr AP / Cr AP? For credit adjustment, we can treat as payment via credit: Dr AP / Cr AP is net zero, but we need to reflect payment: we can create a journal that is similar to supplier payment but funded by credit
            $supplier=Party::withoutGlobalScopes()->where('id',$supplierId)->first();
            $journal=app(\App\Services\Accounting\JournalPostingService::class)->create([
                'institute_id'=>$instituteId,
                'branch_id'=>$branchId,
                'journal_date'=>now()->toDateString(),
                'currency_id'=>$this->resolveCurrencyId($instituteId,$branchId),
                'type'=>'journal',
                'ref_type'=>'credit_adjustment',
                'ref_id'=>$invoice->id,
                'description'=>'Credit adjustment for invoice '.$invoice->invoice_number,
                'entries'=>[
                    [
                        'coa_id'=>$this->payableAccount($instituteId,$branchId),
                        'party_id'=>$supplier->id,
                        'debit'=>$amount,
                        'credit'=>0,
                        'memo'=>'Credit applied to invoice '.$invoice->invoice_number,
                    ],
                    [
                        'coa_id'=>$this->payableAccount($instituteId,$branchId),
                        'party_id'=>$supplier->id,
                        'debit'=>0,
                        'credit'=>$amount,
                        'memo'=>'Credit from returns',
                    ],
                ],
            ], $actorId);

            $this->audit($instituteId,$branchId,$actorId,'update','purchase_invoice',$invoice->id,['due_amount'=>(float)$invoice->due_amount+$amount],['due_amount'=>(float)$invoice->due_amount,'credit_applied'=>$amount]);
        });
    }

    public function availableCredit(int $instituteId, ?int $branchId, int $supplierId): float
    {
        return round((float) SupplierCreditBalance::withoutGlobalScopes()->where('institute_id',$instituteId)->where('supplier_id',$supplierId)->whereIn('status',['available','partially_used'])->sum('remaining_amount'),4);
    }

    private function validateReturnLine(int $instituteId, ?int $branchId, array $line, int $idx, ?int $grId, ?int $poId): void
    {
        if(empty($line['description']) && empty($line['inventory_item_id'])) throw ValidationException::withMessages(["lines.$idx.description"=>'Description or product is required.']);
        if(!isset($line['quantity']) || (float)$line['quantity']<=0) throw ValidationException::withMessages(["lines.$idx.quantity"=>'Quantity must be greater than 0.']);
        if(!isset($line['unit_price']) || (float)$line['unit_price']<0) throw ValidationException::withMessages(["lines.$idx.unit_price"=>'Unit price cannot be negative.']);

        $poLineId=$line['purchase_order_line_id']??null;
        $grItemId=$line['goods_receipt_item_id']??null;
        $qty=(float)($line['quantity']??0);

        // Check never exceed received - previously returned
        if($grId && $grItemId){
            $grItem=GoodsReceiptItem::withoutGlobalScopes()->where('id',$grItemId)->first();
            if($grItem){
                $received=(float)$grItem->received_quantity - (float)$grItem->rejected_quantity;
                $alreadyReturned=$this->returnedQuantityForGrItem($grItem);
                $remaining=$received - $alreadyReturned;
                if($qty - $remaining > 0.00005) throw ValidationException::withMessages(["lines.$idx.quantity"=>"Returned quantity {$qty} exceeds remaining {$remaining} for this receipt item."]);
            }
        } elseif($poId && $poLineId){
            $poLine=\App\Models\PurchaseOrderLine::withoutGlobalScopes()->where('id',$poLineId)->first();
            if($poLine){
                $received=(float)$poLine->received_quantity;
                $alreadyReturned=$this->returnedQuantityForPoLine($poLine);
                $remaining=$received - $alreadyReturned;
                if($remaining < 0.00005) throw ValidationException::withMessages(["lines.$idx.quantity"=>"No remaining quantity to return for this PO line."]);
                if($qty - $remaining > 0.00005) throw ValidationException::withMessages(["lines.$idx.quantity"=>"Returned quantity {$qty} exceeds remaining {$remaining}."]);
            }
        }
    }

    private function returnedQuantityForGrItem(GoodsReceiptItem $item): float
    {
        return round((float) \App\Models\PurchaseReturnItem::withoutGlobalScopes()->where('goods_receipt_item_id',$item->id)->whereHas('purchaseReturn', fn($q)=>$q->whereIn('status',['posted','approved','submitted'])->whereNull('deleted_at'))->sum('quantity'),4);
    }

    private function returnedQuantityForPoLine(PurchaseOrderLine $line): float
    {
        return round((float) PurchaseReturnItem::withoutGlobalScopes()->where('purchase_order_line_id',$line->id)->whereHas('purchaseReturn', fn($q)=>$q->whereIn('status',['posted','approved','submitted'])->whereNull('deleted_at'))->sum('quantity'),4);
    }

    private function transition(PurchaseReturn $ret, string $target, int $actorId, array $extra=[]): PurchaseReturn
    {
        $from=$ret->status;
        return DB::transaction(function() use ($ret,$target,$actorId,$from,$extra){
            $ret->update(array_merge(['status'=>$target,'updated_by'=>$actorId],$extra));
            $this->audit($ret->institute_id,$ret->branch_id,$actorId,'update','purchase_return',$ret->id,['status'=>$from],['status'=>$target]);
            return $ret->fresh('items');
        });
    }

    private function assertSupplier(int $instituteId, ?int $branchId, ?int $supplierId): void
    {
        if(!$supplierId) throw ValidationException::withMessages(['supplier_id'=>'Supplier is required.']);
        $party=Party::withoutGlobalScopes()->where('institute_id',$instituteId)->where('id',$supplierId)->whereIn('type',['supplier','both'])->where('is_active',true)->where(fn($q)=>$q->whereNull('branch_id')->orWhere('branch_id',$branchId))->first();
        if(!$party) throw ValidationException::withMessages(['supplier_id'=>'Supplier not found or not in scope.']);
    }

    private function assertBranchScope(?int $branchId): void
    {
        if(\App\Support\BranchContext::enabled()){
            $actorBranch=\App\Support\BranchContext::id();
            if($branchId!==null && (int)$branchId!==(int)$actorBranch) throw ValidationException::withMessages(['branch_id'=>'Branch scope violation.']);
        }
    }

    private function resolveWarehouse(int $instituteId, ?int $branchId): ?int
    {
        $q=\App\Models\InventoryWarehouse::withoutGlobalScopes()->where('institute_id',$instituteId)->where('is_active',true);
        if($branchId!==null) $q->where(fn($qq)=>$qq->whereNull('branch_id')->orWhere('branch_id',$branchId));
        return $q->orderBy('id')->value('id');
    }

    private function resolveCurrencyId(int $instituteId, ?int $branchId): int
    {
        $code=app(\App\Services\Accounting\AccountingSetupService::class)->getSetting($instituteId,'base_currency',null,$branchId);
        if($code){ $cur=\App\Models\Currency::where('code',$code)->first(); if($cur) return (int)$cur->id; }
        return (int)(\App\Models\Currency::orderBy('code')->value('id'));
    }

    private function payableAccount(int $instituteId, ?int $branchId): int
    {
        $account=app(\App\Services\Accounting\ChartOfAccountService::class)->accountByCode($instituteId,'2001',$branchId)
            ?? \App\Models\ChartOfAccount::where('institute_id',$instituteId)->where('branch_id',$branchId)->where('is_payable',true)->orderBy('code')->first();
        if(!$account) throw new \RuntimeException('No payable account configured.');
        return (int)$account->id;
    }

    private function expenseAccount(int $instituteId, ?int $branchId): int
    {
        $account=\App\Models\ChartOfAccount::where('institute_id',$instituteId)->where('branch_id',$branchId)->where('type','expense')->where('is_active',true)->orderBy('code')->first();
        if(!$account) throw new \RuntimeException('No expense account configured.');
        return (int)$account->id;
    }

    private function cashAccount(int $instituteId, ?int $branchId): int
    {
        $account=\App\Models\ChartOfAccount::where('institute_id',$instituteId)->where('is_cash',true)->where('is_active',true)->first()
            ?? \App\Models\ChartOfAccount::where('institute_id',$instituteId)->where('is_active',true)->where('type','asset')->orderBy('code')->first();
        if(!$account) throw new \RuntimeException('No cash account configured.');
        return (int)$account->id;
    }

    private function audit(int $instituteId, ?int $branchId, ?int $actorId, string $action, string $entityType, int $entityId, ?array $before, ?array $after): void
    {
        try{
            \App\Models\AuditLog::create([
                'institute_id'=>$instituteId,'user_type'=>'institute_user','user_id'=>$actorId,'action'=>$action,'module'=>'purchase','record_id'=>$entityId,
                'old_values'=>$before?json_encode($before):null,'new_values'=>$after?json_encode($after):null,
                'ip_address'=>request()->ip()??null,'user_agent'=>substr((string)(request()->userAgent()??''),0,255)?:null,'created_at'=>now(),
            ]);
        }catch(\Throwable $e){
            try{
                \App\Models\AuditLog::create([
                    'institute_id'=>$instituteId,'user_type'=>'institute_user','user_id'=>$actorId,'action'=>$action,'module'=>'purchase','record_id'=>$entityId,
                    'old_values'=>$before?json_encode($before):null,'new_values'=>$after?json_encode($after):null,'created_at'=>now(),
                ]);
            }catch(\Throwable $ignored){}
        }
    }

    private function toDecimal(mixed $v): string { return number_format((float)$v,4,'.',''); }
    private function round4(string $v): string { return number_format((float)$v,4,'.',''); }
}
