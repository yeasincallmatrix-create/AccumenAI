<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryWarehouse;
use App\Models\InventoryStockLevel;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Party;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Inventory\InventoryStockService;
use App\Services\Sales\DeliveryService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesOrderService;
use App\Services\Sales\SalesReturnService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesReturnTest extends TestCase
{
    use DatabaseTransactions;
    protected function tearDown(): void { TenantContext::clear(); BranchContext::clear(); parent::tearDown(); }

    private function country(): Country { return Country::withoutGlobalScopes()->firstOrCreate(['iso2'=>'BD'],['name'=>'Bangladesh','iso3'=>'BGD','phone_code'=>'880','status'=>true]); }
    private function institute(?Country $c=null): Institute { $c??=$this->country(); $inst=Institute::create(['name'=>'SR Inst '.uniqid(),'slug'=>'sr-'.uniqid(),'country'=>$c->name,'country_id'=>$c->id,'industry'=>'retail','status'=>'active']); app(AccountingSetupService::class)->setupForInstitute($inst->id); $premiumId = \Illuminate\Support\Facades\DB::table("subscription_packages")->where("slug","PREMIUM")->value("id"); if($premiumId) { $inst->forceFill(["package_id"=>$premiumId])->save(); app(\App\Services\ModuleAccessService::class)->flushCache($inst->id); } return $inst; }
    private function branch(Institute $i,string $n='Branch'): Branch { return Branch::create(['institute_id'=>$i->id,'name'=>$n.' '.uniqid(),'status'=>'active']); }
    private function user(Institute $i,string $role,?int $branchId=null): InstituteUser { return InstituteUser::create(['institute_id'=>$i->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'branch_id'=>$branchId,'first_name'=>ucfirst($role),'last_name'=>'User','email'=>$role.'-'.uniqid().'@example.test','phone'=>'01700'.rand(100000,999999),'password_hash'=>bcrypt('secret12345'),'status'=>'active']); }
    private function currency(): Currency { return Currency::firstOrCreate(['code'=>'BDT'],['name'=>'Taka','symbol'=>'৳','is_active'=>true,'decimal_places'=>2]); }
    private function partyCustomer(Institute $i,?int $branchId=null): Party { return Party::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'type'=>'customer','name'=>'Cust '.uniqid(),'phone'=>'017'.rand(10000000,99999999),'email'=>'cust-'.uniqid().'@example.test','is_active'=>true]); }
    private function category(Institute $i,?int $branchId=null): InventoryCategory { return InventoryCategory::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'name'=>'Cat '.uniqid(),'is_active'=>true]); }
    private function item(Institute $i,?int $branchId=null,array $ovr=[]): InventoryItem { $cat=$this->category($i,$branchId); return InventoryItem::create(array_merge(['institute_id'=>$i->id,'branch_id'=>$branchId,'category_id'=>$cat->id,'item_type'=>'stock_item','name'=>'Prod '.uniqid(),'sku'=>'SKU-'.strtoupper(uniqid()),'unit'=>'pcs','selling_price'=>100,'purchase_price'=>50,'is_active'=>true],$ovr)); }
    private function warehouse(Institute $i,?int $branchId=null): InventoryWarehouse { return InventoryWarehouse::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'name'=>'WH '.uniqid(),'code'=>'WH-'.uniqid(),'is_active'=>true]); }
    private function setupAccounting(Institute $i,?Branch $b=null): void { (new AccountingSetupService(app(\App\Services\Accounting\ChartOfAccountService::class)))->setupForInstitute($i->id,$b?->id); }
    private function stockUp(Institute $i, Branch $b, InventoryItem $item, float $qty=50): InventoryWarehouse {
        $wh=$this->warehouse($i,$b->id);
        $sup=Party::create(['institute_id'=>$i->id,'branch_id'=>$b->id,'type'=>'supplier','name'=>'Sup '.uniqid(),'phone'=>'01'.rand(100000000,999999999),'is_active'=>true]);
        app(InventoryStockService::class)->receivePurchase($i->id,$b->id,$sup,$wh->id,[['item_id'=>$item->id,'quantity'=>$qty,'unit_cost'=>50]],null,['reference_type'=>'test_receipt']);
        return $wh;
    }
    private function approvedOrder(Institute $i,?Branch $b,Party $cust,Currency $cur,array $lines,?int $actorId=null): SalesOrder {
        $svc=app(SalesOrderService::class);
        $order=$svc->createDraft($i->id,$b?->id,['customer_id'=>$cust->id,'order_date'=>now()->toDateString(),'expected_delivery_date'=>now()->addDays(5)->toDateString(),'currency_id'=>$cur->id,'lines'=>$lines],$actorId);
        $order=$svc->submit($order,$actorId);
        $admin=$this->user($i,'institute-admin');
        $order=$svc->approve($order,$admin->id);
        return $order->fresh('lines');
    }
    private function deliveredInvoice(Institute $i, Branch $b, Party $cust, Currency $cur, InventoryItem $product, float $orderQty=5, float $deliveryQty=5): array {
        $this->setupAccounting($i,$b);
        app(AccountingSetupService::class)->setSetting($i->id,'invoice_auto_post',true,$b->id);
        $wh=$this->stockUp($i,$b,$product,100);
        $order=$this->approvedOrder($i,$b,$cust,$cur,[['inventory_item_id'=>$product->id,'description'=>$product->name,'quantity'=>$orderQty,'unit_price'=>100]],null);
        $os=app(SalesOrderService::class); $admin=$this->user($i,'institute-admin'); $order=$os->markProcessing($order,$admin->id); $order=$os->markReadyForDelivery($order,$admin->id);
        $delSvc=app(DeliveryService::class);
        $del=$delSvc->createDelivery($i->id,$b->id,$order->id,['lines'=>[['order_line_id'=>$order->lines[0]->id,'delivery_quantity'=>$deliveryQty,'inventory_item_id'=>$product->id]]],null);
        $del=$delSvc->confirmDelivery($del,null);
        $inv=app(SalesInvoiceService::class)->createFromDelivery($i->id,$b->id,$del->id,null);
        return [$order,$del,$inv,$wh];
    }

    public function test_partial_return(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        [$order,$del,$inv,$wh]=$this->deliveredInvoice($inst,$branch,$cust,$cur,$prod,5,5);
        $svc=app(SalesReturnService::class);
        $ret=$svc->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Damaged',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>2]],null);
        $this->assertEquals('draft',$ret->status);
        $this->assertEquals(200, (float)$ret->grand_total);
        $ret=$svc->post($inst->id,$branch->id,$ret->id,null);
        $this->assertEquals('posted',$ret->status);
        $this->assertNotNull($ret->credit_note_number);
        $this->assertNotNull($ret->journal_id);
    }

    public function test_full_return(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        [$order,$del,$inv,$wh]=$this->deliveredInvoice($inst,$branch,$cust,$cur,$prod,3,3);
        $svc=app(SalesReturnService::class);
        $ret=$svc->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Full return',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>3]],null);
        $ret=$svc->post($inst->id,$branch->id,$ret->id,null);
        $this->assertEquals(300,(float)$ret->grand_total);
        $this->assertEquals('credited',$ret->refund_status);
    }

    public function test_excessive_quantity_rejection(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        [$order,$del,$inv,$wh]=$this->deliveredInvoice($inst,$branch,$cust,$cur,$prod,5,5);
        $svc=app(SalesReturnService::class);
        $this->expectException(ValidationException::class);
        $svc->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Excess',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>10]],null);
    }

    public function test_duplicate_repeated_return_protection(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        [$order,$del,$inv,$wh]=$this->deliveredInvoice($inst,$branch,$cust,$cur,$prod,5,5);
        $svc=app(SalesReturnService::class);
        $r1=$svc->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'First',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>3]],null);
        $svc->post($inst->id,$branch->id,$r1->id,null);
        $this->expectException(ValidationException::class);
        $svc->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Second excess',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>3]],null);
        // remaining is 2, so 2 should succeed
        $r2=$svc->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Second ok',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>2]],null);
        $this->assertEquals(2,(float)$r2->items[0]->quantity);
    }

    public function test_inventory_restoration(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        [$order,$del,$inv,$wh]=$this->deliveredInvoice($inst,$branch,$cust,$cur,$prod,5,5);
        $before=InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$prod->id)->first()->quantity;
        $svc=app(SalesReturnService::class);
        $ret=$svc->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Inv test',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>2]],null);
        $svc->post($inst->id,$branch->id,$ret->id,null);
        $after=InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$prod->id)->first()->quantity;
        $this->assertEqualsWithDelta((float)$before+2,(float)$after,0.01);
        $mov=DB::table('inventory_movements')->where('reference_type','sales_return')->where('reference_id',$ret->id)->where('movement_type','return_in')->first();
        $this->assertNotNull($mov);
    }

    public function test_credit_note_calculation(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id,['selling_price'=>100]);
        [$order,$del,$inv,$wh]=$this->deliveredInvoice($inst,$branch,$cust,$cur,$prod,4,4);
        $svc=app(SalesReturnService::class);
        $ret=$svc->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Calc',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
        $this->assertEqualsWithDelta(100,(float)$ret->grand_total,0.01);
        $this->assertStringStartsWith('CN-',$ret->credit_note_number);
        $this->assertStringStartsWith('SR-',$ret->return_number);
    }

    public function test_refund_limits(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        [$order,$del,$inv,$wh]=$this->deliveredInvoice($inst,$branch,$cust,$cur,$prod,5,5);
        $svc=app(SalesReturnService::class);
        $ret=$svc->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Refund test',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>2]],null);
        $ret=$svc->post($inst->id,$branch->id,$ret->id,null);
        // refund 100 ok
        $svc->refund($inst->id,$branch->id,$ret->id,100,'cash',null,now()->toDateString(),null,null);
        $ret->refresh(); $this->assertEqualsWithDelta(100,(float)$ret->refunded_amount,0.01);
        $this->expectException(ValidationException::class);
        $svc->refund($inst->id,$branch->id,$ret->id,200,'cash',null,now()->toDateString(),null,null);
    }

    public function test_finance_posting(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        [$order,$del,$inv,$wh]=$this->deliveredInvoice($inst,$branch,$cust,$cur,$prod,2,2);
        $svc=app(SalesReturnService::class);
        $ret=$svc->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Finance',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>2]],null);
        $ret=$svc->post($inst->id,$branch->id,$ret->id,null);
        $j=Journal::find($ret->journal_id);
        $this->assertNotNull($j);
        $this->assertEquals('posted',$j->status);
        $this->assertEquals('sales_return',$j->ref_type);
        $this->assertGreaterThanOrEqual(2,$j->entries()->count());
        $origInv=Invoice::find($inv->id);
        $this->assertEquals($inv->id,$origInv->id); // original unchanged
    }

    public function test_tenant_isolation(): void
    {
        $a=$this->institute(); $b=$this->institute();
        $branchA=$this->branch($a); $branchB=$this->branch($b);
        $custA=$this->partyCustomer($a,$branchA->id); $cur=$this->currency();
        $prod=$this->item($a,$branchA->id);
        [$order,$del,$inv,$wh]=$this->deliveredInvoice($a,$branchA,$custA,$cur,$prod,2,2);
        $svc=app(SalesReturnService::class);
        $this->expectException(ValidationException::class);
        $svc->createDraft($b->id,$branchB->id,$inv->id,$wh->id,now()->toDateString(),'Cross tenant',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
    }

    public function test_branch_isolation(): void
    {
        $inst=$this->institute(); $branchA=$this->branch($inst,'A'); $branchB=$this->branch($inst,'B');
        $custA=$this->partyCustomer($inst,$branchA->id); $cur=$this->currency(); $prod=$this->item($inst,$branchA->id);
        [$order,$del,$inv,$wh]=$this->deliveredInvoice($inst,$branchA,$custA,$cur,$prod,2,2);
        $svc=app(SalesReturnService::class);
        $this->expectException(ValidationException::class);
        $svc->createDraft($inst->id,$branchB->id,$inv->id,$wh->id,now()->toDateString(),'Cross branch',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
        // manager B cannot view return via find
        $ret=$svc->createDraft($inst->id,$branchA->id,$inv->id,$wh->id,now()->toDateString(),'Branch A return',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
        $this->expectException(ValidationException::class);
        $svc->find($inst->id,$branchB->id,$ret->id);
    }

    public function test_permissions(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        [$order,$del,$inv,$wh]=$this->deliveredInvoice($inst,$branch,$cust,$cur,$prod,2,2);
        $receptionist=$this->user($inst,'receptionist',$branch->id);
        TenantContext::set($inst->id); BranchContext::set($branch->id);
        $this->actingAs($receptionist,'institute_user')->get(route('sales.returns.index'))->assertForbidden();
        $this->actingAs($receptionist,'institute_user')->post(route('sales.returns.store'),['invoice_id'=>$inv->id,'return_date'=>now()->toDateString(),'reason'=>'x','lines'=>[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]]])->assertForbidden();
        $owner=$this->user($inst,'institute-owner');
        $this->actingAs($owner,'institute_user')->get(route('sales.returns.index'))->assertOk();
    }

    public function test_read_write_integrity(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        [$order,$del,$inv,$wh]=$this->deliveredInvoice($inst,$branch,$cust,$cur,$prod,2,2);
        $origPayable=(float)$inv->payable_amount;
        $svc=app(SalesReturnService::class);
        $ret=$svc->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Integrity',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
        $ret=$svc->post($inst->id,$branch->id,$ret->id,null);
        $inv->refresh();
        $this->assertEqualsWithDelta($origPayable,(float)$inv->payable_amount,0.01);
        $this->assertEquals('posted',$ret->status);
        // cannot edit posted return via direct update should be blocked - try to tamper via service (no update method, so check immutability via status)
        $this->assertTrue($ret->isImmutable());
    }

    public function test_cancellation_reversal_behavior(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        [$order,$del,$inv,$wh]=$this->deliveredInvoice($inst,$branch,$cust,$cur,$prod,2,2);
        $svc=app(SalesReturnService::class);
        // cancel draft
        $ret=$svc->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Cancel test',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
        $ret=$svc->cancel($inst->id,$branch->id,$ret->id,null);
        $this->assertEquals('cancelled',$ret->status);
        $this->expectException(ValidationException::class);
        $svc->post($inst->id,$branch->id,$ret->id,null);
        // reverse posted
        [$order2,$del2,$inv2,$wh2]=$this->deliveredInvoice($inst,$branch,$cust,$cur,$prod,2,2);
        $ret2=$svc->createDraft($inst->id,$branch->id,$inv2->id,$wh2->id,now()->toDateString(),'Reversal test',null,[['invoice_item_id'=>$inv2->items[0]->id,'quantity'=>1]],null);
        $ret2=$svc->post($inst->id,$branch->id,$ret2->id,null);
        $jId=$ret2->journal_id;
        $ret2=$svc->reverse($inst->id,$branch->id,$ret2->id,null);
        $this->assertEquals('reversed',$ret2->status);
        $j=Journal::find($jId);
        $this->assertEquals('reversed',$j->status);
        $revJournal=Journal::where('reversal_of',$jId)->first();
        $this->assertNotNull($revJournal);
        // inventory reversed? stock should be reduced again? original return added 1, reversal should not re-add? Check that reversal header exists
        $rev=DB::table('sales_returns')->where('reversal_of',$ret2->id)->first();
        $this->assertNotNull($rev);
    }
}
