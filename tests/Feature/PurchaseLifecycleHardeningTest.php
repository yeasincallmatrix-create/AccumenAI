<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Currency;
use App\Models\GoodsReceipt;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\InventoryItem;
use App\Models\InventoryStockLevel;
use App\Models\InventoryWarehouse;
use App\Models\Party;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Purchase\GoodsReceiptService;
use App\Services\Purchase\PurchaseInvoiceService;
use App\Services\Purchase\PurchaseOrderService;
use App\Services\Purchase\PurchasePaymentService;
use App\Services\Purchase\PurchaseReturnService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseLifecycleHardeningTest extends TestCase
{
    use DatabaseTransactions;
    protected function tearDown(): void { TenantContext::clear(); BranchContext::clear(); parent::tearDown(); }
    private function country(): Country { return Country::withoutGlobalScopes()->firstOrCreate(['iso2'=>'BD'],['name'=>'Bangladesh','iso3'=>'BGD','phone_code'=>'880','status'=>true]); }
    private function institute(?Country $c=null): Institute { $c??=$this->country(); $i=Institute::create(['name'=>'P9 '.uniqid(),'slug'=>'p9-'.uniqid(),'industry'=>'retail','country'=>$c->name,'country_id'=>$c->id,'status'=>'active']); app(AccountingSetupService::class)->setupForInstitute($i->id); return $i; }
    private function branch(Institute $i): Branch { $b=Branch::create(['institute_id'=>$i->id,'name'=>'B '.uniqid(),'status'=>'active']); app(AccountingSetupService::class)->setupForInstitute($i->id,$b->id); return $b; }
    private function user(Institute $i,string $role,?int $branch=null): InstituteUser { return InstituteUser::create(['institute_id'=>$i->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'branch_id'=>$branch,'first_name'=>ucfirst($role),'last_name'=>'U','email'=>$role.'-'.uniqid().'@test.test','phone'=>'01700'.rand(100000,999999),'password_hash'=>bcrypt('secret'),'status'=>'active']); }
    private function supplier(Institute $i,?int $branch=null): Party { return Party::create(['institute_id'=>$i->id,'branch_id'=>$branch,'type'=>'supplier','name'=>'Sup '.uniqid(),'phone'=>'01'.rand(100000000,999999999),'is_active'=>true]); }
    private function warehouse(Institute $i,?int $branch=null): InventoryWarehouse { return InventoryWarehouse::create(['institute_id'=>$i->id,'branch_id'=>$branch,'name'=>'WH '.uniqid(),'code'=>'WH-'.uniqid(),'is_active'=>true]); }
    private function item(Institute $i,?int $branch=null): InventoryItem { return InventoryItem::create(['institute_id'=>$i->id,'branch_id'=>$branch,'name'=>'Item '.uniqid(),'sku'=>'SKU-'.uniqid(),'item_type'=>'stock_item','purchase_price'=>50,'selling_price'=>80,'is_active'=>true]); }
    private function currency(): Currency { return Currency::firstOrCreate(['code'=>'BDT'],['name'=>'Taka','symbol'=>'T','is_active'=>true,'decimal_places'=>2]); }

    public function test_valid_transitions(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>2,'unit_price'=>100]]],$inst->id,null,$this->user($inst,'institute-owner')->id);
        $this->assertEquals('draft',$order->status);
        $order=app(PurchaseOrderService::class)->submit($order,$this->user($inst,'institute-owner')->id);
        $this->assertEquals('submitted',$order->status);
        $order=app(PurchaseOrderService::class)->approve($order,$this->user($inst,'institute-admin')->id);
        $this->assertEquals('approved',$order->status);
        $gr=app(GoodsReceiptService::class)->create(['purchase_order_id'=>$order->id,'warehouse_id'=>$wh->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'received_quantity'=>1,'unit_cost'=>50]]],$inst->id,null,$this->user($inst,'institute-owner')->id);
        $this->assertEquals('draft',$gr->status);
        $gr=app(GoodsReceiptService::class)->confirm($gr,$this->user($inst,'institute-owner')->id);
        $this->assertEquals('confirmed',$gr->status);
        $this->assertEquals('partially_received',$order->fresh()->status);
        $inv=app(PurchaseInvoiceService::class)->create($inst->id,null,['supplier_id'=>$sup->id,'purchase_order_id'=>$order->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'quantity'=>1,'unit_price'=>100,'description'=>'Test']]], $this->user($inst,'institute-owner')->id);
        $this->assertEquals('draft',$inv->status);
        $inv=app(PurchaseInvoiceService::class)->post($inv,$this->user($inst,'institute-owner')->id);
        $this->assertEquals('posted',$inv->status);
        $pay=app(PurchasePaymentService::class)->pay($inst->id,null,$inv->id,['amount'=>50,'payment_method'=>'cash'], $this->user($inst,'institute-owner')->id);
        $this->assertNotNull($pay->journal_id);
        $this->assertEquals('partially_received',$order->fresh()->status);
    }

    public function test_invalid_transitions_blocked(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>1,'unit_price'=>100]]],$inst->id,null,1);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PurchaseOrderService::class)->approve($order,1);
    }

    public function test_posted_documents_not_editable(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>1,'unit_price'=>100]]],$inst->id,null,1);
        $order=app(PurchaseOrderService::class)->submit($order,1); $order=app(PurchaseOrderService::class)->approve($order,$this->user($inst,'institute-admin')->id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PurchaseOrderService::class)->update($order,['supplier_id'=>$sup->id],1);
    }

    public function test_po_number_unique(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $a=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>1,'unit_price'=>10]]],$inst->id,null,1);
        $b=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>1,'unit_price'=>10]]],$inst->id,null,1);
        $this->assertNotEquals($a->order_number,$b->order_number);
    }

    public function test_grn_duplicate_confirm_blocked(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>2,'unit_price'=>10]]],$inst->id,null,1);
        $order=app(PurchaseOrderService::class)->submit($order,1); $order=app(PurchaseOrderService::class)->approve($order,$this->user($inst,'institute-admin')->id);
        $gr=app(GoodsReceiptService::class)->create(['purchase_order_id'=>$order->id,'warehouse_id'=>$wh->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'received_quantity'=>1,'unit_cost'=>10]]],$inst->id,null,1);
        $gr=app(GoodsReceiptService::class)->confirm($gr,1);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(GoodsReceiptService::class)->confirm($gr,1);
    }

    public function test_invoice_duplicate_post_blocked(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>1,'unit_price'=>100]]],$inst->id,null,1);
        $order=app(PurchaseOrderService::class)->submit($order,1); $order=app(PurchaseOrderService::class)->approve($order,$this->user($inst,'institute-admin')->id);
        $gr=app(GoodsReceiptService::class)->create(['purchase_order_id'=>$order->id,'warehouse_id'=>$wh->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'received_quantity'=>1,'unit_cost'=>100]]],$inst->id,null,1);
        $gr=app(GoodsReceiptService::class)->confirm($gr,1);
        $inv=app(PurchaseInvoiceService::class)->create($inst->id,null,['supplier_id'=>$sup->id,'purchase_order_id'=>$order->id,'goods_receipt_id'=>$gr->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'goods_receipt_item_id'=>$gr->items->first()->id,'quantity'=>1,'unit_price'=>100,'description'=>'Test']]],1);
        $inv=app(PurchaseInvoiceService::class)->post($inv,1);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PurchaseInvoiceService::class)->post($inv,1);
    }

    public function test_over_invoicing_blocked(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>2,'unit_price'=>100]]],$inst->id,null,1);
        $order=app(PurchaseOrderService::class)->submit($order,1); $order=app(PurchaseOrderService::class)->approve($order,$this->user($inst,'institute-admin')->id);
        $gr=app(GoodsReceiptService::class)->create(['purchase_order_id'=>$order->id,'warehouse_id'=>$wh->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'received_quantity'=>1,'unit_cost'=>100]]],$inst->id,null,1);
        $gr=app(GoodsReceiptService::class)->confirm($gr,1);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PurchaseInvoiceService::class)->create($inst->id,null,['supplier_id'=>$sup->id,'purchase_order_id'=>$order->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'quantity'=>2,'unit_price'=>100,'description'=>'Over']]],1);
    }

    public function test_payment_overpayment_blocked(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>1,'unit_price'=>100]]],$inst->id,null,1);
        $order=app(PurchaseOrderService::class)->submit($order,1); $order=app(PurchaseOrderService::class)->approve($order,$this->user($inst,'institute-admin')->id);
        $gr=app(GoodsReceiptService::class)->create(['purchase_order_id'=>$order->id,'warehouse_id'=>$wh->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'received_quantity'=>1,'unit_cost'=>100]]],$inst->id,null,1);
        $gr=app(GoodsReceiptService::class)->confirm($gr,1);
        $inv=app(PurchaseInvoiceService::class)->create($inst->id,null,['supplier_id'=>$sup->id,'purchase_order_id'=>$order->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'quantity'=>1,'unit_price'=>100,'description'=>'T']]],1);
        $inv=app(PurchaseInvoiceService::class)->post($inv,1);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PurchasePaymentService::class)->pay($inst->id,null,$inv->id,['amount'=>200,'payment_method'=>'cash'],1);
    }

    public function test_tenant_isolation(): void
    {
        $a=$this->institute(); $b=$this->institute(); $supA=$this->supplier($a); $whA=$this->warehouse($a); $itemA=$this->item($a); $cur=$this->currency();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$supA->id,'warehouse_id'=>$whA->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$itemA->id,'quantity'=>1,'unit_price'=>10]]],$a->id,null,1);
        $this->assertNull(PurchaseOrder::withoutGlobalScopes()->where('institute_id',$b->id)->where('id',$order->id)->first());
    }

    public function test_branch_isolation(): void
    {
        $inst=$this->institute(); $branchA=$this->branch($inst); $branchB=$this->branch($inst);
        $supA=$this->supplier($inst,$branchA->id); $whA=$this->warehouse($inst,$branchA->id); $itemA=$this->item($inst,$branchA->id);
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$supA->id,'warehouse_id'=>$whA->id,'order_date'=>now()->toDateString(),'currency_id'=>$this->currency()->id,'items'=>[['inventory_item_id'=>$itemA->id,'quantity'=>1,'unit_price'=>10]]],$inst->id,$branchA->id,1);
        TenantContext::set($inst->id); BranchContext::set($branchB->id);
        $this->assertNull(\App\Models\PurchaseOrder::query()->find($order->id));
        BranchContext::clear();
    }

    public function test_inventory_integrity(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $sup=$this->supplier($inst,$branch->id); $wh=$this->warehouse($inst,$branch->id); $item=$this->item($inst,$branch->id);
        $before=(float)InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$item->id)->value('quantity') ?? 0;
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$this->currency()->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>5,'unit_price'=>10]]],$inst->id,$branch->id,1);
        $this->assertEquals($before, (float)InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$item->id)->value('quantity') ?? 0);
        $order=app(PurchaseOrderService::class)->submit($order,1); $order=app(PurchaseOrderService::class)->approve($order,$this->user($inst,'institute-admin')->id);
        $gr=app(GoodsReceiptService::class)->create(['purchase_order_id'=>$order->id,'warehouse_id'=>$wh->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'received_quantity'=>5,'unit_cost'=>10]]],$inst->id,$branch->id,1);
        $gr=app(GoodsReceiptService::class)->confirm($gr,1);
        $this->assertEquals($before+5, (float)InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$item->id)->value('quantity'));
        $inv=app(PurchaseInvoiceService::class)->create($inst->id,$branch->id,['supplier_id'=>$sup->id,'purchase_order_id'=>$order->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'quantity'=>5,'unit_price'=>10,'description'=>'Inv']]],1);
        $this->assertEquals($before+5, (float)InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$item->id)->value('quantity'));
    }

    public function test_audit_logging(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $before=DB::table('audit_logs')->where('module','purchase')->count();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>1,'unit_price'=>10]]],$inst->id,null,1);
        $after=DB::table('audit_logs')->where('module','purchase')->count();
        $this->assertGreaterThan($before,$after);
    }

    public function test_historical_immutability(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>1,'unit_price'=>100]]],$inst->id,null,1);
        $order=app(PurchaseOrderService::class)->submit($order,1); $order=app(PurchaseOrderService::class)->approve($order,$this->user($inst,'institute-admin')->id);
        $gr=app(GoodsReceiptService::class)->create(['purchase_order_id'=>$order->id,'warehouse_id'=>$wh->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'received_quantity'=>1,'unit_cost'=>100]]],$inst->id,null,1);
        $gr=app(GoodsReceiptService::class)->confirm($gr,1);
        $inv=app(PurchaseInvoiceService::class)->create($inst->id,null,['supplier_id'=>$sup->id,'purchase_order_id'=>$order->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'quantity'=>1,'unit_price'=>100,'description'=>'T']]],1);
        $inv=app(PurchaseInvoiceService::class)->post($inv,1);
        $origTotal=(float)$inv->grand_total;
        $inv->refresh();
        $this->assertEquals($origTotal,(float)$inv->grand_total);
        $this->assertTrue($inv->isPosted());
        $this->assertFalse($inv->canEdit());
    }

    public function test_approval_controls(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $owner=$this->user($inst,'institute-owner'); $receptionist=$this->user($inst,'receptionist');
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>1,'unit_price'=>100]]],$inst->id,null,$owner->id);
        $order=app(PurchaseOrderService::class)->submit($order,$owner->id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PurchaseOrderService::class)->approve($order,$receptionist->id);
    }

    public function test_return_and_credit_note_integrity(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $sup=$this->supplier($inst,$branch->id); $wh=$this->warehouse($inst,$branch->id); $item=$this->item($inst,$branch->id);
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$this->currency()->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>5,'unit_price'=>100]]],$inst->id,$branch->id,$this->user($inst,'institute-owner')->id);
        $order=app(PurchaseOrderService::class)->submit($order,1); $order=app(PurchaseOrderService::class)->approve($order,$this->user($inst,'institute-admin')->id);
        $gr=app(GoodsReceiptService::class)->create(['purchase_order_id'=>$order->id,'warehouse_id'=>$wh->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'received_quantity'=>5,'unit_cost'=>100]]],$inst->id,$branch->id,1);
        $gr=app(GoodsReceiptService::class)->confirm($gr,1);
        $inv=app(PurchaseInvoiceService::class)->create($inst->id,$branch->id,['supplier_id'=>$sup->id,'purchase_order_id'=>$order->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'quantity'=>5,'unit_price'=>100,'description'=>'Inv']]],1);
        $inv=app(PurchaseInvoiceService::class)->post($inv,1);
        $retService=app(\App\Services\Purchase\PurchaseReturnService::class);
        $ret=$retService->create($inst->id,$branch->id,['supplier_id'=>$sup->id,'purchase_order_id'=>$order->id,'goods_receipt_id'=>$gr->id,'purchase_invoice_id'=>$inv->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),'reason'=>'Damaged','lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'quantity'=>2,'unit_price'=>100,'description'=>'Return']]],1);
        $ret=$retService->submit($ret,1);
        $ret=$retService->approve($ret,$this->user($inst,'institute-admin')->id);
        $ret=$retService->post($ret,1);
        $this->assertEquals('posted',$ret->status);
        $this->assertNotNull($ret->journal_id);
        // Credit note reduces liability
        $credit=\App\Models\SupplierCreditBalance::where('purchase_return_id',$ret->id)->first();
        $this->assertNotNull($credit);
        $this->assertEqualsWithDelta(200,$credit->credit_amount,0.01);
    }

    public function test_finance_reconciliation(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>1,'unit_price'=>100]]],$inst->id,null,1);
        $order=app(PurchaseOrderService::class)->submit($order,1); $order=app(PurchaseOrderService::class)->approve($order,$this->user($inst,'institute-admin')->id);
        $gr=app(GoodsReceiptService::class)->create(['purchase_order_id'=>$order->id,'warehouse_id'=>$wh->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'received_quantity'=>1,'unit_cost'=>100]]],$inst->id,null,1);
        $gr=app(GoodsReceiptService::class)->confirm($gr,1);
        $inv=app(PurchaseInvoiceService::class)->create($inst->id,null,['supplier_id'=>$sup->id,'purchase_order_id'=>$order->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'quantity'=>1,'unit_price'=>100,'description'=>'T']]],1);
        $inv=app(PurchaseInvoiceService::class)->post($inv,1);
        $this->assertEqualsWithDelta(100,(float)$inv->due_amount,0.01);
        $pay=app(PurchasePaymentService::class)->pay($inst->id,null,$inv->id,['amount'=>40,'payment_method'=>'cash'],1);
        $inv->refresh();
        $this->assertEqualsWithDelta(60,(float)$inv->due_amount,0.01);
        $pay2=app(PurchasePaymentService::class)->pay($inst->id,null,$inv->id,['amount'=>60,'payment_method'=>'cash'],1);
        $inv->refresh();
        $this->assertEqualsWithDelta(0,(float)$inv->due_amount,0.01);
        $this->assertEquals('posted',$inv->fresh()->status);
    }

    public function test_supplier_statement_correctness(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>1,'unit_price'=>200]]],$inst->id,null,1);
        $order=app(PurchaseOrderService::class)->submit($order,1); $order=app(PurchaseOrderService::class)->approve($order,$this->user($inst,'institute-admin')->id);
        $gr=app(GoodsReceiptService::class)->create(['purchase_order_id'=>$order->id,'warehouse_id'=>$wh->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'received_quantity'=>1,'unit_cost'=>200]]],$inst->id,null,1);
        $gr=app(GoodsReceiptService::class)->confirm($gr,1);
        $inv=app(PurchaseInvoiceService::class)->create($inst->id,null,['supplier_id'=>$sup->id,'purchase_order_id'=>$order->id,'lines'=>[['purchase_order_line_id'=>$order->lines->first()->id,'quantity'=>1,'unit_price'=>200,'description'=>'T']]],1);
        $inv=app(PurchaseInvoiceService::class)->post($inv,1);
        app(PurchasePaymentService::class)->pay($inst->id,null,$inv->id,['amount'=>50,'payment_method'=>'cash'],1);
        $report=app(\App\Services\Purchase\PurchaseReportService::class)->supplierStatement($inst->id,null,$sup->id,[]);
        $this->assertEqualsWithDelta(150,$report['closing_balance'],0.01);
        $this->assertEquals(1,$report['invoices_count']);
        $this->assertEquals(1,$report['payments_count']);
    }

    public function test_permissions_matrix(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>1,'unit_price'=>10]]],$inst->id,null,1);
        $receptionist=$this->user($inst,'receptionist');
        TenantContext::set($inst->id);
        $this->actingAs($receptionist,'institute_user')->get(route('purchase.orders.index'))->assertForbidden();
        $this->actingAs($receptionist,'institute_user')->get(route('purchase.receipts.index'))->assertForbidden();
        $owner=$this->user($inst,'institute-owner');
        $this->actingAs($owner,'institute_user')->get(route('purchase.orders.index'))->assertOk();
        $this->actingAs($owner,'institute_user')->get(route('purchase.receipts.index'))->assertOk();
    }

    public function test_readonly_reporting_safety(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst); $cur=$this->currency();
        $order=app(PurchaseOrderService::class)->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'items'=>[['inventory_item_id'=>$item->id,'quantity'=>2,'unit_price'=>100]]],$inst->id,null,1);
        $countsBefore=['orders'=>PurchaseOrder::withoutGlobalScopes()->where('institute_id',$inst->id)->count(),'receipts'=>GoodsReceipt::withoutGlobalScopes()->where('institute_id',$inst->id)->count(),'invoices'=>PurchaseInvoice::withoutGlobalScopes()->where('institute_id',$inst->id)->count()];
        $svc=app(\App\Services\Purchase\PurchaseReportService::class);
        $svc->dashboardMetrics($inst->id,null,[]);
        $svc->inventoryReconciliation($inst->id,null,[],5);
        $svc->supplierStatement($inst->id,null,$sup->id,[]);
        $countsAfter=['orders'=>PurchaseOrder::withoutGlobalScopes()->where('institute_id',$inst->id)->count(),'receipts'=>GoodsReceipt::withoutGlobalScopes()->where('institute_id',$inst->id)->count(),'invoices'=>PurchaseInvoice::withoutGlobalScopes()->where('institute_id',$inst->id)->count()];
        $this->assertEquals($countsBefore,$countsAfter);
    }
}
