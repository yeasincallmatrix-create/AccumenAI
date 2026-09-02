<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryWarehouse;
use App\Models\JournalEntry;
use App\Models\Party;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Purchase\GoodsReceiptService;
use App\Services\Purchase\PurchaseInvoiceService;
use App\Services\Purchase\PurchaseOrderService;
use App\Services\Purchase\PurchasePaymentService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseFinanceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function country(): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(['iso2'=>'BD'],['name'=>'Bangladesh','iso3'=>'BDR','phone_code'=>'880','status'=>true]);
    }

    private function institute(string $name='PF Inst'): Institute
    {
        $c=$this->country();
        $inst=Institute::create(['name'=>$name.' '.uniqid(),'slug'=>str()->slug($name.' '.uniqid()),'country'=>$c->name,'country_id'=>$c->id,'industry'=>'retail','status'=>'active']);
        app(AccountingSetupService::class)->setupForInstitute($inst->id);
        return $inst;
    }

    private function branch(Institute $inst, string $name='Main'): Branch
    {
        $b=Branch::create(['institute_id'=>$inst->id,'name'=>$name.uniqid(),'status'=>'active']);
        app(AccountingSetupService::class)->setupForInstitute($inst->id,$b->id);
        return $b;
    }

    private function currency(): Currency { return Currency::firstOrCreate(['code'=>'BDT'],['name'=>'Taka','symbol'=>'৳','is_active'=>true,'decimal_places'=>2]); }

    private function supplier(Institute $inst, ?Branch $branch): Party
    {
        return Party::create(['institute_id'=>$inst->id,'branch_id'=>$branch?->id,'type'=>'supplier','name'=>'Sup '.uniqid(),'phone'=>'01'.rand(100000000,999999999),'is_active'=>true]);
    }

    private function warehouse(Institute $inst, ?Branch $branch): InventoryWarehouse
    {
        return InventoryWarehouse::create(['institute_id'=>$inst->id,'branch_id'=>$branch?->id,'name'=>'WH '.uniqid(),'code'=>'WH-'.uniqid(),'is_active'=>true]);
    }

    private function product(Institute $inst, ?Branch $branch, float $price=100): InventoryItem
    {
        return InventoryItem::create(['institute_id'=>$inst->id,'branch_id'=>$branch?->id,'name'=>'Prod '.uniqid(),'sku'=>'SKU-'.uniqid(),'item_type'=>'stock_item','selling_price'=>$price,'purchase_price'=>$price*0.8,'is_active'=>true]);
    }

    private function createApprovedPO(Institute $inst, ?Branch $branch, Party $supplier, InventoryWarehouse $wh, Currency $cur, array $lines, int $actorId=1): PurchaseOrder
    {
        $svc=app(PurchaseOrderService::class);
        $po=$svc->create([
            'supplier_id'=>$supplier->id,
            'warehouse_id'=>$wh->id,
            'order_date'=>now()->toDateString(),
            'currency_id'=>$cur->id,
            'lines'=>$lines,
        ], $inst->id, $branch?->id, $actorId);
        $po=$svc->submit($po, $actorId);
        $po=$svc->approve($po, $actorId+1);
        return $po->fresh('lines');
    }

    private function createConfirmedGR(PurchaseOrder $po, InventoryWarehouse $wh, array $lines, int $actorId=1): \App\Models\GoodsReceipt
    {
        $svc=app(GoodsReceiptService::class);
        $gr=$svc->create([
            'purchase_order_id'=>$po->id,
            'supplier_id'=>$po->supplier_id,
            'warehouse_id'=>$wh->id,
            'receipt_date'=>now()->toDateString(),
            'lines'=>$lines,
        ], $po->institute_id, $po->branch_id, $actorId);
        return $svc->confirm($gr, $actorId);
    }

    public function test_invoice_creation_from_grn(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100,'discount_amount'=>0]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>10,'unit_cost'=>100]]);

        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),
            'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100]],
        ], 1);

        $this->assertSame('draft', $inv->status);
        $this->assertEquals(1000, (float)$inv->grand_total);
        $this->assertSame($po->id, (int)$inv->purchase_order_id);
        $this->assertSame($gr->id, (int)$inv->goods_receipt_id);
    }

    public function test_partial_invoice(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,50);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>50]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>10,'unit_cost'=>50]]);

        $svc=app(PurchaseInvoiceService::class);
        $inv1=$svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>4,'unit_price'=>50]],
        ],1);
        $this->assertEquals(200, (float)$inv1->grand_total);

        $inv2=$svc->create($inst->id,$branch->id,[
            'purchase_order_id'=>$po->id,'supplier_id'=>$supplier->id,'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>6,'unit_price'=>50]],
        ],1);
        $this->assertEquals(300, (float)$inv2->grand_total);
    }

    public function test_quantity_limits_exceed_received(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>5,'unit_cost'=>100]]);

        $svc=app(PurchaseInvoiceService::class);
        $this->expectException(ValidationException::class);
        $svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>6,'unit_price'=>100]],
        ],1);
    }

    public function test_supplier_liability_journal(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>2,'unit_cost'=>100]]);

        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]],
        ],1);
        $inv=$svc->post($inv,1);

        $this->assertSame('posted', $inv->status);
        $this->assertNotNull($inv->journal_id);
        $journal=$inv->journal;
        $this->assertSame('purchase', $journal->type);
        // Journal must balance
        $debit = $journal->entries()->sum('debit');
        $credit = $journal->entries()->sum('credit');
        $this->assertEquals(round($debit,4), round($credit,4));
        // AP credit for supplier
        $apEntry = $journal->entries()->where('party_id',$supplier->id)->first();
        $this->assertNotNull($apEntry);
        $this->assertEquals(200, (float)$apEntry->credit);
    }

    public function test_no_duplicate_journal(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>1,'unit_cost'=>100]]);

        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>100]],
        ],1);
        $inv=$svc->post($inv,1);
        $firstJournalId=$inv->journal_id;

        $this->expectException(ValidationException::class);
        $svc->post($inv->fresh(),1);
        $this->assertEquals($firstJournalId, $inv->fresh()->journal_id);
    }

    public function test_partial_payment(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>2,'unit_cost'=>100]]);

        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]],
        ],1);
        $inv=$svc->post($inv,1);

        $paySvc=app(PurchasePaymentService::class);
        $pay=$paySvc->pay($inst->id,$branch->id,$inv->id,['amount'=>80,'payment_method'=>'cash'],1);
        $inv->refresh();
        $this->assertEquals(80, (float)$inv->paid_amount);
        $this->assertEquals(120, (float)$inv->due_amount);
        $this->assertNotNull($pay->journal_id);
    }

    public function test_full_payment(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>1,'unit_cost'=>100]]);

        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>100]],
        ],1);
        $inv=$svc->post($inv,1);

        $paySvc=app(PurchasePaymentService::class);
        $paySvc->pay($inst->id,$branch->id,$inv->id,['amount'=>100,'payment_method'=>'cash'],1);
        $this->assertEquals(0, (float)$inv->fresh()->due_amount);
        $this->assertEquals(100, (float)$inv->fresh()->paid_amount);
    }

    public function test_overpayment_protection(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>1,'unit_cost'=>100]]);

        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>100]],
        ],1);
        $inv=$svc->post($inv,1);

        $paySvc=app(PurchasePaymentService::class);
        $paySvc->pay($inst->id,$branch->id,$inv->id,['amount'=>60,'payment_method'=>'cash'],1);
        $this->expectException(ValidationException::class);
        $paySvc->pay($inst->id,$branch->id,$inv->id,['amount'=>50,'payment_method'=>'cash'],1);
    }

    public function test_payment_reversal(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>1,'unit_cost'=>100]]);

        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>100]],
        ],1);
        $inv=$svc->post($inv,1);

        $paySvc=app(PurchasePaymentService::class);
        $pay=$paySvc->pay($inst->id,$branch->id,$inv->id,['amount'=>100,'payment_method'=>'cash'],1);
        $this->assertEquals(0, (float)$inv->fresh()->due_amount);

        $paySvc->reverse($pay->fresh(),1,'test');
        $this->assertEquals(100, (float)$inv->fresh()->due_amount);
        $this->assertEquals(0, (float)$inv->fresh()->paid_amount);
        $this->assertNull(\App\Models\PurchaseSupplierPayment::find($pay->id));
    }

    public function test_inventory_not_double_increased(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>10,'unit_cost'=>100]]);

        $movesBefore = InventoryMovement::withoutGlobalScopes()->where('institute_id',$inst->id)->where('item_id',$prod->id)->count();

        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100]],
        ],1);
        $svc->post($inv,1);

        $movesAfter = InventoryMovement::withoutGlobalScopes()->where('institute_id',$inst->id)->where('item_id',$prod->id)->count();
        $this->assertEquals($movesBefore, $movesAfter);
    }

    public function test_supplier_linkage(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>5,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>5,'unit_cost'=>100]]);

        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>5,'unit_price'=>100]],
        ],1);

        $this->assertEquals($supplier->id, (int)$inv->supplier_id);
        $this->assertEquals($po->id, (int)$inv->purchase_order_id);
        $this->assertEquals($gr->id, (int)$inv->goods_receipt_id);
        $this->assertEquals($po->supplier_id, $inv->supplier_id);
    }

    public function test_tenant_isolation(): void
    {
        $instA=$this->institute('A'); $instB=$this->institute('B');
        $branchA=$this->branch($instA); $whA=$this->warehouse($instA,$branchA);
        $supplierA=$this->supplier($instA,$branchA); $cur=$this->currency();
        $prodA=$this->product($instA,$branchA);
        $po=$this->createApprovedPO($instA,$branchA,$supplierA,$whA,$cur,[['inventory_item_id'=>$prodA->id,'description'=>$prodA->name,'quantity'=>1,'unit_price'=>10]]);
        $gr=$this->createConfirmedGR($po,$whA,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prodA->id,'received_quantity'=>1,'unit_cost'=>10]]);
        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($instA->id,$branchA->id,$gr->id,['invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prodA->id,'description'=>$prodA->name,'quantity'=>1,'unit_price'=>10]]],1);

        TenantContext::set($instB->id);
        $this->assertNull(PurchaseInvoice::query()->find($inv->id));
        TenantContext::set($instA->id);
        $this->assertNotNull(PurchaseInvoice::query()->find($inv->id));
    }

    public function test_branch_isolation(): void
    {
        $inst=$this->institute(); $branchA=$this->branch($inst,'A'); $branchB=$this->branch($inst,'B');
        $whA=$this->warehouse($inst,$branchA); $customerA=$this->supplier($inst,$branchA); // supplier acts as customer for branch A
        $cur=$this->currency(); $prod=$this->product($inst,$branchA);
        $po=$this->createApprovedPO($inst,$branchA,$customerA,$whA,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>10]]);
        $gr=$this->createConfirmedGR($po,$whA,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>1,'unit_cost'=>10]]);
        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($inst->id,$branchA->id,$gr->id,['invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>10]]],1);

        BranchContext::set($branchB->id); TenantContext::set($inst->id);
        $this->assertNull(PurchaseInvoice::query()->find($inv->id));
        BranchContext::set($branchA->id);
        $this->assertNotNull(PurchaseInvoice::query()->find($inv->id));
    }

    public function test_idempotency_post_twice(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>1,'unit_cost'=>100]]);

        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>100]],
        ],1);
        $inv=$svc->post($inv,1);
        $firstJournal=$inv->journal_id;
        $this->expectException(ValidationException::class);
        $svc->post($inv->fresh(),1);
        $this->assertEquals($firstJournal, $inv->fresh()->journal_id);
    }

    public function test_historical_immutability(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>100]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>1,'unit_cost'=>100]]);

        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>1,'unit_price'=>100]],
        ],1);
        $inv=$svc->post($inv,1);
        $originalTotal=(float)$inv->grand_total;

        $inv->refresh();
        $this->assertFalse($inv->canEdit());
        $this->assertEquals($originalTotal, (float)$inv->fresh()->grand_total);
        // Posted invoice cannot be cancelled if paid, but can be reversed — ensure direct cancel fails if not draft
        $this->expectException(ValidationException::class);
        $svc->cancel($inv->fresh(),1);
    }

    public function test_journal_correctness(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $wh=$this->warehouse($inst,$branch);
        $supplier=$this->supplier($inst,$branch); $cur=$this->currency();
        $prod=$this->product($inst,$branch,100);
        $po=$this->createApprovedPO($inst,$branch,$supplier,$wh,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100,'tax_group_id'=>null]]);
        $gr=$this->createConfirmedGR($po,$wh,[['purchase_order_line_id'=>$po->lines[0]->id,'inventory_item_id'=>$prod->id,'received_quantity'=>2,'unit_cost'=>100]]);

        $svc=app(PurchaseInvoiceService::class);
        $inv=$svc->createFromGoodsReceipt($inst->id,$branch->id,$gr->id,[
            'invoice_date'=>now()->toDateString(),'currency_id'=>$cur->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]],
        ],1);
        $inv=$svc->post($inv,1);

        $entries=JournalEntry::withoutGlobalScopes()->where('journal_id',$inv->journal_id)->get();
        $debit=$entries->sum('debit');
        $credit=$entries->sum('credit');
        $this->assertEquals(round($debit,4), round($credit,4));
        $this->assertEquals(200, round($debit,4));
    }
}
