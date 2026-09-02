<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStockLevel;
use App\Models\InventoryWarehouse;
use App\Models\Party;
use App\Models\PurchaseReturn;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Purchase\GoodsReceiptService;
use App\Services\Purchase\PurchaseInvoiceService;
use App\Services\Purchase\PurchaseOrderService;
use App\Services\Purchase\PurchaseReturnService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseReturnTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function country(): Country { return Country::withoutGlobalScopes()->firstOrCreate(['iso2'=>'BD'],['name'=>'Bangladesh','iso3'=>'BDR','phone_code'=>'880','status'=>true]); }
    private function institute(string $n='PR Inst'): Institute { $c=$this->country(); $inst=Institute::create(['name'=>$n.' '.uniqid(),'slug'=>str()->slug($n.' '.uniqid()),'country'=>$c->name,'country_id'=>$c->id,'industry'=>'retail','status'=>'active']); app(AccountingSetupService::class)->setupForInstitute($inst->id); return $inst; }
    private function branch(Institute $inst, string $n='Main'): Branch { $b=Branch::create(['institute_id'=>$inst->id,'name'=>$n.uniqid(),'status'=>'active']); app(AccountingSetupService::class)->setupForInstitute($inst->id,$b->id); return $b; }
    private function currency(): Currency { return Currency::firstOrCreate(['code'=>'BDT'],['name'=>'Taka','symbol'=>'৳','is_active'=>true,'decimal_places'=>2]); }
    private function supplier(Institute $inst, ?Branch $branch): Party { return Party::create(['institute_id'=>$inst->id,'branch_id'=>$branch?->id,'type'=>'supplier','name'=>'Sup '.uniqid(),'phone'=>'01'.rand(100000000,999999999),'is_active'=>true]); }
    private function warehouse(Institute $inst, ?Branch $branch): InventoryWarehouse { return InventoryWarehouse::create(['institute_id'=>$inst->id,'branch_id'=>$branch?->id,'name'=>'WH '.uniqid(),'code'=>'WH-'.uniqid(),'is_active'=>true]); }
    private function product(Institute $inst, ?Branch $branch, string $type='stock_item', float $price=100): InventoryItem { return InventoryItem::create(['institute_id'=>$inst->id,'branch_id'=>$branch?->id,'name'=>'Prod '.uniqid(),'sku'=>'SKU-'.uniqid(),'item_type'=>$type,'selling_price'=>$price,'purchase_price'=>$price*0.8,'is_active'=>true]); }

    private function createApprovedPO(Institute $inst, ?Branch $branch, Party $supplier, InventoryWarehouse $wh, Currency $cur, array $lines, int $actorId=1): \App\Models\PurchaseOrder
    {
        $svc=app(PurchaseOrderService::class);
        $po=$svc->create(['supplier_id'=>$supplier->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'lines'=>$lines], $inst->id, $branch?->id, $actorId);
        $po=$svc->submit($po,$actorId);
        $po=$svc->approve($po,$actorId+1);
        return $po->fresh('lines');
    }

    private function createConfirmedGR(\App\Models\PurchaseOrder $po, InventoryWarehouse $wh, array $lines, int $actorId=1): \App\Models\GoodsReceipt
    {
        $svc=app(GoodsReceiptService::class);
        $gr=$svc->create(['purchase_order_id'=>$po->id,'supplier_id'=>$po->supplier_id,'warehouse_id'=>$wh->id,'receipt_date'=>now()->toDateString(),'lines'=>$lines], $po->institute_id, $po->branch_id, $actorId);
        return $svc->confirm($gr,$actorId);
    }

    public function test_partial_return(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,'stock_item',100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>10,'unit_cost'=>100]]);

        $svc=app(PurchaseReturnService::class);
        $ret=$svc->create($inst->id,$branch->id,[
            'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'supplier_id'=>$supplier->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),'reason'=>'Damaged',
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>4,'unit_price'=>100]],
        ],1);
        $this->assertSame('draft',$ret->status);
        $ret=$svc->submit($ret,1); $ret=$svc->approve($ret,2); $ret=$svc->post($ret,2);
        $this->assertSame('posted',$ret->status);
        $this->assertNotNull($ret->credit_note_number);
        $this->assertEquals(400,(float)$ret->grand_total);
    }

    public function test_full_return(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,'stock_item',50);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>5,'unit_price'=>50]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>5,'unit_cost'=>50]]);

        $svc=app(PurchaseReturnService::class);
        $ret=$svc->create($inst->id,$branch->id,[
            'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'supplier_id'=>$supplier->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>5,'unit_price'=>50]],
        ],1);
        $ret=$svc->submit($ret,1); $ret=$svc->approve($ret,2); $ret=$svc->post($ret,2);
        $this->assertEquals(250,(float)$ret->grand_total);
    }

    public function test_excessive_return_rejection(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,'stock_item',100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>5,'unit_cost'=>100]]);

        $svc=app(PurchaseReturnService::class);
        $this->expectException(ValidationException::class);
        $svc->create($inst->id,$branch->id,[
            'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'supplier_id'=>$supplier->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>6,'unit_price'=>100]],
        ],1);
    }

    public function test_repeated_return_protection(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,'stock_item',100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>10,'unit_cost'=>100]]);

        $svc=app(PurchaseReturnService::class);
        $ret1=$svc->create($inst->id,$branch->id,[
            'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'supplier_id'=>$supplier->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>6,'unit_price'=>100]],
        ],1);
        $ret1=$svc->submit($ret1,1); $ret1=$svc->approve($ret1,2); $svc->post($ret1,2);

        $this->expectException(ValidationException::class);
        $svc->create($inst->id,$branch->id,[
            'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'supplier_id'=>$supplier->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>5,'unit_price'=>100]],
        ],1);
    }

    public function test_inventory_deduction(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,'stock_item',100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>10,'unit_cost'=>100]]);
        $levelBefore=(float)InventoryStockLevel::withoutGlobalScopes()->where('item_id',$prod->id)->first()->quantity;
        $this->assertEquals(10,$levelBefore);

        $svc=app(PurchaseReturnService::class);
        $ret=$svc->create($inst->id,$branch->id,[
            'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'supplier_id'=>$supplier->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>4,'unit_price'=>100]],
        ],1);
        $ret=$svc->submit($ret,1); $ret=$svc->approve($ret,2); $svc->post($ret,2);

        $levelAfter=(float)InventoryStockLevel::withoutGlobalScopes()->where('item_id',$prod->id)->first()->quantity;
        $this->assertEquals(6,$levelAfter);
        $mov=InventoryMovement::withoutGlobalScopes()->where('reference_type',\App\Models\PurchaseReturn::class)->where('reference_id',$ret->id)->first();
        $this->assertNotNull($mov);
        $this->assertEquals(-4,(float)$mov->quantity);
    }

    public function test_credit_note_calculation(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,'stock_item',100);
        // Line with discount and tax
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100,'discount_amount'=>10,'discount_type'=>'percent','tax_group_id'=>null,'tax_rate'=>10]]);
        // But PO line discount/tax handling may not be fully as expected, we test return calc directly
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>10,'unit_cost'=>100]]);

        $svc=app(PurchaseReturnService::class);
        $ret=$svc->create($inst->id,$branch->id,[
            'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'supplier_id'=>$supplier->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100,'discount_amount'=>10,'discount_type'=>'percent','tax_rate'=>10]],
        ],1);
        // 2*100=200, 10% discount=20, net 180, 10% tax=18, total 198
        $this->assertEquals(198,(float)$ret->grand_total);
    }

    public function test_supplier_credit_refund(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,'stock_item',100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>10,'unit_cost'=>100]]);

        $svc=app(PurchaseReturnService::class);
        $ret=$svc->create($inst->id,$branch->id,[
            'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'supplier_id'=>$supplier->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>5,'unit_price'=>100]],
        ],1);
        $ret=$svc->submit($ret,1); $ret=$svc->approve($ret,2); $ret=$svc->post($ret,2);

        $credit=\App\Models\SupplierCreditBalance::withoutGlobalScopes()->where('purchase_return_id',$ret->id)->first();
        $this->assertNotNull($credit);
        $this->assertEquals(500,(float)$credit->credit_amount);
        $this->assertEquals(500,(float)$credit->remaining_amount);

        $refund=$svc->refund($inst->id,$branch->id,$supplier->id,200,['purchase_return_id'=>$ret->id,'refund_method'=>'cash'],1);
        $this->assertEquals(200,(float)$refund->amount);
        $credit->refresh();
        $this->assertEquals(300,(float)$credit->remaining_amount);

        $this->expectException(ValidationException::class);
        $svc->refund($inst->id,$branch->id,$supplier->id,400,[],1);
    }

    public function test_finance_reversal(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,'stock_item',100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>10,'unit_cost'=>100]]);

        $svc=app(PurchaseReturnService::class);
        $ret=$svc->create($inst->id,$branch->id,[
            'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'supplier_id'=>$supplier->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]],
        ],1);
        $ret=$svc->submit($ret,1); $ret=$svc->approve($ret,2); $ret=$svc->post($ret,2);
        $journalId=$ret->journal_id;
        $this->assertNotNull($journalId);
        $journal=\App\Models\Journal::withoutGlobalScopes()->where('id',$journalId)->first();
        $this->assertSame('posted',$journal->status);

        $svc->reverse($ret->fresh(),1,'test');
        $this->assertSame('reversed',$ret->fresh()->status);
        $journal->refresh();
        $this->assertSame('reversed',$journal->status);
    }

    public function test_duplicate_posting_protection(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,'stock_item',100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>5,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>5,'unit_cost'=>100]]);

        $svc=app(PurchaseReturnService::class);
        $ret=$svc->create($inst->id,$branch->id,[
            'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'supplier_id'=>$supplier->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]],
        ],1);
        $ret=$svc->submit($ret,1); $ret=$svc->approve($ret,2); $ret=$svc->post($ret,2);
        $this->expectException(ValidationException::class);
        $svc->post($ret->fresh(),2);
    }

    public function test_tenant_isolation(): void
    {
        $instA=$this->institute('A'); $instB=$this->institute('B');
        $branchA=$this->branch($instA); $whA=$this->warehouse($instA,$branchA); $supA=$this->supplier($instA,$branchA); $cur=$this->currency();
        $prodA=$this->product($instA,$branchA);
        $po=$this->createApprovedPO($instA,$branchA,$supA,$whA,$cur,[['inventory_item_id'=>$prodA->id,'description'=>$prodA->name,'quantity'=>1,'unit_price'=>10]]);
        $gr=$this->createConfirmedGR($po,$whA,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prodA->id,'received_quantity'=>1,'unit_cost'=>10]]);
        $svc=app(PurchaseReturnService::class);
        $ret=$svc->create($instA->id,$branchA->id,[
            'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'supplier_id'=>$supA->id,'warehouse_id'=>$whA->id,'return_date'=>now()->toDateString(),
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prodA->id,'description'=>$prodA->name,'quantity'=>1,'unit_price'=>10]],
        ],1);
        TenantContext::set($instB->id);
        $this->assertNull(PurchaseReturn::query()->find($ret->id));
        TenantContext::set($instA->id);
        $this->assertNotNull(PurchaseReturn::query()->find($ret->id));
    }

    public function test_branch_isolation(): void
    {
        $inst=$this->institute(); $branchA=$this->branch($inst,'A'); $branchB=$this->branch($inst,'B');
        $whA=$this->warehouse($inst,$branchA); $supA=$this->supplier($inst,$branchA); $cur=$this->currency(); $prodA=$this->product($inst,$branchA);
        $po=$this->createApprovedPO($inst,$branchA,$supA,$whA,$cur,[['inventory_item_id'=>$prodA->id,'description'=>$prodA->name,'quantity'=>1,'unit_price'=>10]]);
        $gr=$this->createConfirmedGR($po,$whA,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prodA->id,'received_quantity'=>1,'unit_cost'=>10]]);
        $svc=app(PurchaseReturnService::class);
        $ret=$svc->create($inst->id,$branchA->id,[
            'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'supplier_id'=>$supA->id,'warehouse_id'=>$whA->id,'return_date'=>now()->toDateString(),
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prodA->id,'description'=>$prodA->name,'quantity'=>1,'unit_price'=>10]],
        ],1);
        BranchContext::set($branchB->id); TenantContext::set($inst->id);
        $this->assertNull(PurchaseReturn::query()->find($ret->id));
        BranchContext::set($branchA->id);
        $this->assertNotNull(PurchaseReturn::query()->find($ret->id));
    }

    public function test_historical_integrity(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,'stock_item',100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>5,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>5,'unit_cost'=>100]]);

        $svc=app(PurchaseReturnService::class);
        $ret=$svc->create($inst->id,$branch->id,[
            'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'supplier_id'=>$supplier->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]],
        ],1);
        $ret=$svc->submit($ret,1); $ret=$svc->approve($ret,2); $ret=$svc->post($ret,2);
        $origTotal=(float)$ret->grand_total;
        $prod->update(['selling_price'=>999]);
        $ret->refresh();
        $this->assertEquals($origTotal,(float)$ret->grand_total);
        $this->assertFalse($ret->canSubmit());
    }
}
