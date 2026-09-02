<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStockLevel;
use App\Models\InventoryWarehouse;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Party;
use App\Models\Role;
use App\Models\SalesDelivery;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\FinancialReportService;
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

class SalesLifecycleHardeningTest extends TestCase
{
    use DatabaseTransactions;
    protected function tearDown(): void { TenantContext::clear(); BranchContext::clear(); parent::tearDown(); }

    private function country(): Country { return Country::withoutGlobalScopes()->firstOrCreate(['iso2'=>'BD'],['name'=>'Bangladesh','iso3'=>'BGD','phone_code'=>'880','status'=>true]); }
    private function institute(?Country $c=null): Institute { $c??=$this->country(); $inst=Institute::create(['name'=>'Hardening '.uniqid(),'slug'=>'hard-'.uniqid(),'country'=>$c->name,'country_id'=>$c->id,'industry'=>'retail','status'=>'active']); app(AccountingSetupService::class)->setupForInstitute($inst->id); $premiumId = \Illuminate\Support\Facades\DB::table("subscription_packages")->where("slug","PREMIUM")->value("id"); if($premiumId) { $inst->forceFill(["package_id"=>$premiumId])->save(); app(\App\Services\ModuleAccessService::class)->flushCache($inst->id); } return $inst; }
    private function branch(Institute $i,string $n='Branch'): Branch { return Branch::create(['institute_id'=>$i->id,'name'=>$n.' '.uniqid(),'status'=>'active']); }
    private function user(Institute $i,string $role,?int $branchId=null): InstituteUser { return InstituteUser::create(['institute_id'=>$i->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'branch_id'=>$branchId,'first_name'=>ucfirst($role),'last_name'=>'User','email'=>$role.'-'.uniqid().'@example.test','phone'=>'01700'.rand(100000,999999),'password_hash'=>bcrypt('secret12345'),'status'=>'active']); }
    private function currency(): Currency { return Currency::firstOrCreate(['code'=>'BDT'],['name'=>'Taka','symbol'=>'৳','is_active'=>true,'decimal_places'=>2]); }
    private function party(Institute $i,?int $branchId=null): Party { return Party::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'type'=>'customer','name'=>'Cust '.uniqid(),'phone'=>'017'.rand(10000000,99999999),'email'=>'cust-'.uniqid().'@example.test','is_active'=>true]); }
    private function category(Institute $i,?int $branchId=null): InventoryCategory { return InventoryCategory::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'name'=>'Cat '.uniqid(),'is_active'=>true]); }
    private function item(Institute $i,?int $branchId=null,array $ovr=[]): InventoryItem { $cat=$this->category($i,$branchId); return InventoryItem::create(array_merge(['institute_id'=>$i->id,'branch_id'=>$branchId,'category_id'=>$cat->id,'item_type'=>'stock_item','name'=>'Prod '.uniqid(),'sku'=>'SKU-'.strtoupper(uniqid()),'unit'=>'pcs','selling_price'=>100,'purchase_price'=>50,'is_active'=>true],$ovr)); }
    private function warehouse(Institute $i,?int $branchId=null): InventoryWarehouse { return InventoryWarehouse::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'name'=>'WH '.uniqid(),'code'=>'WH-'.uniqid(),'is_active'=>true]); }
    private function setupAccounting(Institute $i,?Branch $b=null): void { (new AccountingSetupService(app(\App\Services\Accounting\ChartOfAccountService::class)))->setupForInstitute($i->id,$b?->id); }
    private function stockUp(Institute $i, Branch $b, InventoryItem $item, float $qty=100): InventoryWarehouse {
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

    public function test_valid_transitions(): void
    {
        $inst=$this->institute(); $cust=$this->party($inst); $cur=$this->currency();
        $order=app(SalesOrderService::class)->createDraft($inst->id,null,['customer_id'=>$cust->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'lines'=>[['description'=>'A','quantity'=>1,'unit_price'=>100]]],null);
        $this->assertTrue($order->canTransitionTo(SalesOrder::STATUS_PENDING_APPROVAL));
        $order=app(SalesOrderService::class)->submit($order,null);
        $this->assertEquals(SalesOrder::STATUS_PENDING_APPROVAL,$order->status);
        $admin=$this->user($inst,'institute-admin');
        $order=app(SalesOrderService::class)->approve($order,$admin->id);
        $this->assertEquals(SalesOrder::STATUS_APPROVED,$order->status);
        $order=app(SalesOrderService::class)->markProcessing($order,$admin->id);
        $this->assertEquals(SalesOrder::STATUS_PROCESSING,$order->status);
        // return valid transition draft->posted (use separate institute to avoid SO number duplicate per institute unique index)
        $inst2=$this->institute(); $branch2=$this->branch($inst2); $prod2=$this->item($inst2,$branch2->id); $this->setupAccounting($inst2,$branch2);
        $wh2=$this->stockUp($inst2,$branch2,$prod2,50);
        $custB2=$this->party($inst2,$branch2->id); $cur2=$this->currency(); $admin2b=$this->user($inst2,'institute-admin');
        $order2=$this->approvedOrder($inst2,$branch2,$custB2,$cur2,[['inventory_item_id'=>$prod2->id,'description'=>$prod2->name,'quantity'=>2,'unit_price'=>100]]);
        $os2=app(SalesOrderService::class); $order2=$os2->markProcessing($order2,$admin2b->id); $order2=$os2->markReadyForDelivery($order2,$admin2b->id);
        $del=app(DeliveryService::class)->createDelivery($inst2->id,$branch2->id,$order2->id,['lines'=>[['order_line_id'=>$order2->lines[0]->id,'delivery_quantity'=>2,'inventory_item_id'=>$prod2->id]]],null);
        $del=app(DeliveryService::class)->confirmDelivery($del,null);
        $inv=app(SalesInvoiceService::class)->createFromDelivery($inst2->id,$branch2->id,$del->id,null);
        $ret=app(SalesReturnService::class)->createDraft($inst2->id,$branch2->id,$inv->id,$wh2->id,now()->toDateString(),'Valid',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
        $this->assertTrue($ret->canTransitionTo(SalesReturn::STATUS_POSTED));
    }

    public function test_invalid_transitions_rejected(): void
    {
        $inst=$this->institute(); $cust=$this->party($inst); $cur=$this->currency();
        $order=app(SalesOrderService::class)->createDraft($inst->id,null,['customer_id'=>$cust->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'lines'=>[['description'=>'A','quantity'=>1,'unit_price'=>100]]],null);
        $this->expectException(ValidationException::class);
        app(SalesOrderService::class)->complete($order,null);
    }

    public function test_posted_order_cannot_be_edited(): void
    {
        $inst=$this->institute(); $cust=$this->party($inst); $cur=$this->currency();
        $order=$this->approvedOrder($inst,null,$cust,$cur,[['description'=>'A','quantity'=>1,'unit_price'=>100]]);
        $this->assertFalse($order->canEdit());
        $this->expectException(ValidationException::class);
        app(SalesOrderService::class)->updateDraft($order,['customer_id'=>$cust->id,'lines'=>[['description'=>'B','quantity'=>1,'unit_price'=>200]]],null);
    }

    public function test_self_approval_blocked(): void
    {
        $inst=$this->institute(); $cust=$this->party($inst); $cur=$this->currency();
        $creator=$this->user($inst,'institute-owner');
        $order=app(SalesOrderService::class)->createDraft($inst->id,null,['customer_id'=>$cust->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'lines'=>[['description'=>'A','quantity'=>1,'unit_price'=>100]]],$creator->id);
        $order=app(SalesOrderService::class)->submit($order,$creator->id);
        $this->expectException(ValidationException::class);
        app(SalesOrderService::class)->approve($order,$creator->id);
        // return self-approve
        $branch=$this->branch($inst); $prod=$this->item($inst,$branch->id); $this->setupAccounting($inst,$branch); $wh=$this->stockUp($inst,$branch,$prod,50); $custB=$this->party($inst,$branch->id);
        $order2=$this->approvedOrder($inst,$branch,$custB,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]]);
        $os=app(SalesOrderService::class); $admin=$this->user($inst,'institute-admin'); $order2=$os->markProcessing($order2,$admin->id); $order2=$os->markReadyForDelivery($order2,$admin->id);
        $del=app(DeliveryService::class)->createDelivery($inst->id,$branch->id,$order2->id,['lines'=>[['order_line_id'=>$order2->lines[0]->id,'delivery_quantity'=>2,'inventory_item_id'=>$prod->id]]],null);
        $del=app(DeliveryService::class)->confirmDelivery($del,null);
        $inv=app(SalesInvoiceService::class)->createFromDelivery($inst->id,$branch->id,$del->id,null);
        $ret=app(SalesReturnService::class)->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Self',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],$creator->id);
        $this->expectException(ValidationException::class);
        app(SalesReturnService::class)->approve($inst->id,$branch->id,$ret->id,$creator->id);
    }

    public function test_approval_permissions(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->party($inst,$branch->id);
        $receptionist=$this->user($inst,'receptionist',$branch->id);
        TenantContext::set($inst->id); BranchContext::set($branch->id);
        $this->actingAs($receptionist,'institute_user')->post(route('sales.orders.approve',['order'=>999]))->assertNotFound();
        // actual approve route via service requires sales.manage; receptionist lacks it
        $order=app(SalesOrderService::class)->createDraft($inst->id,$branch->id,['customer_id'=>$cust->id,'order_date'=>now()->toDateString(),'currency_id'=>$this->currency()->id,'lines'=>[['description'=>'A','quantity'=>1,'unit_price'=>100]]],null);
        $order=app(SalesOrderService::class)->submit($order,null);
        TenantContext::set($inst->id); BranchContext::set($branch->id);
        $this->actingAs($receptionist,'institute_user')->post(route('sales.orders.approve',$order))->assertForbidden();
    }

    public function test_posting_idempotency(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->party($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        $this->setupAccounting($inst,$branch); $wh=$this->stockUp($inst,$branch,$prod,50);
        $order=$this->approvedOrder($inst,$branch,$cust,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]]);
        $os=app(SalesOrderService::class); $admin=$this->user($inst,'institute-admin'); $order=$os->markProcessing($order,$admin->id); $order=$os->markReadyForDelivery($order,$admin->id);
        $del=app(DeliveryService::class)->createDelivery($inst->id,$branch->id,$order->id,['lines'=>[['order_line_id'=>$order->lines[0]->id,'delivery_quantity'=>2,'inventory_item_id'=>$prod->id]]],null);
        $del=app(DeliveryService::class)->confirmDelivery($del,null);
        $inv=app(SalesInvoiceService::class)->createFromDelivery($inst->id,$branch->id,$del->id,null);
        $journalsBefore=Journal::where('institute_id',$inst->id)->count();
        $ret=app(SalesReturnService::class)->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'PostOnce',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
        $ret=app(SalesReturnService::class)->post($inst->id,$branch->id,$ret->id,null);
        $journalsAfter=Journal::where('institute_id',$inst->id)->count();
        $this->assertEquals($journalsBefore+1,$journalsAfter);
        $this->expectException(ValidationException::class);
        app(SalesReturnService::class)->post($inst->id,$branch->id,$ret->id,null);
        $this->assertEquals($journalsAfter, Journal::where('institute_id',$inst->id)->count());
    }

    public function test_inventory_idempotency(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $prod=$this->item($inst,$branch->id); $cust=$this->party($inst,$branch->id); $cur=$this->currency();
        $this->setupAccounting($inst,$branch); $wh=$this->stockUp($inst,$branch,$prod,100);
        $order=$this->approvedOrder($inst,$branch,$cust,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>5,'unit_price'=>100]]);
        $os=app(SalesOrderService::class); $admin=$this->user($inst,'institute-admin'); $order=$os->markProcessing($order,$admin->id); $order=$os->markReadyForDelivery($order,$admin->id);
        $del=app(DeliveryService::class)->createDelivery($inst->id,$branch->id,$order->id,['lines'=>[['order_line_id'=>$order->lines[0]->id,'delivery_quantity'=>5,'inventory_item_id'=>$prod->id]]],null);
        $qtyBefore=(float)InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$prod->id)->value('quantity');
        $del=app(DeliveryService::class)->confirmDelivery($del,null);
        $qtyAfter=(float)InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$prod->id)->value('quantity');
        $this->assertEqualsWithDelta($qtyBefore-5,$qtyAfter,0.01);
        $this->expectException(ValidationException::class);
        app(DeliveryService::class)->confirmDelivery($del,null);
        $this->assertEqualsWithDelta($qtyAfter,(float)InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$prod->id)->value('quantity'),0.01);
    }

    public function test_return_refund_integrity(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->party($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        $this->setupAccounting($inst,$branch); $wh=$this->stockUp($inst,$branch,$prod,50);
        $order=$this->approvedOrder($inst,$branch,$cust,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>4,'unit_price'=>100]]);
        $os=app(SalesOrderService::class); $admin=$this->user($inst,'institute-admin'); $order=$os->markProcessing($order,$admin->id); $order=$os->markReadyForDelivery($order,$admin->id);
        $del=app(DeliveryService::class)->createDelivery($inst->id,$branch->id,$order->id,['lines'=>[['order_line_id'=>$order->lines[0]->id,'delivery_quantity'=>4,'inventory_item_id'=>$prod->id]]],null);
        $del=app(DeliveryService::class)->confirmDelivery($del,null);
        $inv=app(SalesInvoiceService::class)->createFromDelivery($inst->id,$branch->id,$del->id,null);
        $ret=app(SalesReturnService::class)->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Integrity',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>2]],null);
        $invPayableBefore=(float)$inv->payable_amount;
        $ret=app(SalesReturnService::class)->post($inst->id,$branch->id,$ret->id,null);
        $this->assertEqualsWithDelta(200,(float)$ret->grand_total,0.01);
        $inv->refresh(); $this->assertEqualsWithDelta($invPayableBefore,(float)$inv->payable_amount,0.01);
    }

    public function test_cancellation_reversal(): void
    {
        $inst=$this->institute(); $cust=$this->party($inst); $cur=$this->currency();
        $order=app(SalesOrderService::class)->createDraft($inst->id,null,['customer_id'=>$cust->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'lines'=>[['description'=>'A','quantity'=>1,'unit_price'=>100]]],null);
        $order=app(SalesOrderService::class)->cancel($order,null);
        $this->assertEquals(SalesOrder::STATUS_CANCELLED,$order->status);
        $this->expectException(ValidationException::class);
        app(SalesOrderService::class)->approve($order,null);
        // return cancel vs reverse
        $branch=$this->branch($inst); $prod=$this->item($inst,$branch->id); $this->setupAccounting($inst,$branch); $wh=$this->stockUp($inst,$branch,$prod,50); $custB=$this->party($inst,$branch->id);
        $order2=$this->approvedOrder($inst,$branch,$custB,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]]);
        $os=app(SalesOrderService::class); $admin=$this->user($inst,'institute-admin'); $order2=$os->markProcessing($order2,$admin->id); $order2=$os->markReadyForDelivery($order2,$admin->id);
        $del=app(DeliveryService::class)->createDelivery($inst->id,$branch->id,$order2->id,['lines'=>[['order_line_id'=>$order2->lines[0]->id,'delivery_quantity'=>2,'inventory_item_id'=>$prod->id]]],null);
        $del=app(DeliveryService::class)->confirmDelivery($del,null);
        $inv=app(SalesInvoiceService::class)->createFromDelivery($inst->id,$branch->id,$del->id,null);
        $ret=app(SalesReturnService::class)->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'CancelDraft',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
        $ret=app(SalesReturnService::class)->cancel($inst->id,$branch->id,$ret->id,null);
        $this->assertEquals(SalesReturn::STATUS_CANCELLED,$ret->status);
        $this->expectException(ValidationException::class);
        app(SalesReturnService::class)->post($inst->id,$branch->id,$ret->id,null);
        // posted then reverse
        $ret2=app(SalesReturnService::class)->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'ToReverse',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
        $ret2=app(SalesReturnService::class)->post($inst->id,$branch->id,$ret2->id,null);
        $ret2=app(SalesReturnService::class)->reverse($inst->id,$branch->id,$ret2->id,null);
        $this->assertEquals(SalesReturn::STATUS_REVERSED,$ret2->status);
    }

    public function test_tenant_isolation(): void
    {
        $a=$this->institute(); $b=$this->institute();
        $branchA=$this->branch($a); $custA=$this->party($a,$branchA->id); $cur=$this->currency();
        $order=app(SalesOrderService::class)->createDraft($a->id,$branchA->id,['customer_id'=>$custA->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'lines'=>[['description'=>'A','quantity'=>1,'unit_price'=>100]]],null);
        // cross-tenant DB isolation
        $found=SalesOrder::withoutGlobalScopes()->where('id',$order->id)->where('institute_id',$b->id)->first();
        $this->assertNull($found);
        // HTTP isolation: user from B cannot view A's order
        $mgrB=$this->user($b,'institute-owner');
        TenantContext::set($b->id); BranchContext::clear();
        $this->actingAs($mgrB,'institute_user')->get(route('sales.orders.show',$order))->assertNotFound();
        // return isolation too
        TenantContext::clear(); BranchContext::clear();
        $this->setupAccounting($a,$branchA); $prod=$this->item($a,$branchA->id); $wh=$this->stockUp($a,$branchA,$prod,50);
        $order2=$this->approvedOrder($a,$branchA,$custA,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]]);
        $os=app(SalesOrderService::class); $admin=$this->user($a,'institute-admin'); $order2=$os->markProcessing($order2,$admin->id); $order2=$os->markReadyForDelivery($order2,$admin->id);
        $del=app(DeliveryService::class)->createDelivery($a->id,$branchA->id,$order2->id,['lines'=>[['order_line_id'=>$order2->lines[0]->id,'delivery_quantity'=>2,'inventory_item_id'=>$prod->id]]],null);
        $del=app(DeliveryService::class)->confirmDelivery($del,null);
        $inv=app(SalesInvoiceService::class)->createFromDelivery($a->id,$branchA->id,$del->id,null);
        $ret=app(SalesReturnService::class)->createDraft($a->id,$branchA->id,$inv->id,$wh->id,now()->toDateString(),'Tenant',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
        $this->expectException(ValidationException::class);
        app(SalesReturnService::class)->find($b->id,null,$ret->id);
    }

    public function test_branch_isolation(): void
    {
        $inst=$this->institute(); $branchA=$this->branch($inst,'A'); $branchB=$this->branch($inst,'B');
        $this->setupAccounting($inst,$branchA); $this->setupAccounting($inst,$branchB);
        $custA=$this->party($inst,$branchA->id); $cur=$this->currency();
        $order=app(SalesOrderService::class)->createDraft($inst->id,$branchA->id,['customer_id'=>$custA->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'lines'=>[['description'=>'A','quantity'=>1,'unit_price'=>100]]],null);
        TenantContext::set($inst->id); BranchContext::set($branchB->id);
        $mgrB=$this->user($inst,'branch-manager',$branchB->id);
        $this->actingAs($mgrB,'institute_user')->get(route('sales.orders.show',$order))->assertNotFound();
        // return branch isolation
        TenantContext::clear(); BranchContext::clear();
        $prod=$this->item($inst,$branchA->id); $wh=$this->stockUp($inst,$branchA,$prod,50);
        $order2=$this->approvedOrder($inst,$branchA,$custA,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]]);
        $os=app(SalesOrderService::class); $admin=$this->user($inst,'institute-admin'); $order2=$os->markProcessing($order2,$admin->id); $order2=$os->markReadyForDelivery($order2,$admin->id);
        $del=app(DeliveryService::class)->createDelivery($inst->id,$branchA->id,$order2->id,['lines'=>[['order_line_id'=>$order2->lines[0]->id,'delivery_quantity'=>2,'inventory_item_id'=>$prod->id]]],null);
        $del=app(DeliveryService::class)->confirmDelivery($del,null);
        $inv=app(SalesInvoiceService::class)->createFromDelivery($inst->id,$branchA->id,$del->id,null);
        $ret=app(SalesReturnService::class)->createDraft($inst->id,$branchA->id,$inv->id,$wh->id,now()->toDateString(),'Branch',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
        $this->expectException(ValidationException::class);
        app(SalesReturnService::class)->find($inst->id,$branchB->id,$ret->id);
    }

    public function test_authorization(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->party($inst,$branch->id);
        $teacher=$this->user($inst,'teacher',$branch->id);
        TenantContext::set($inst->id); BranchContext::set($branch->id);
        $this->actingAs($teacher,'institute_user')->get(route('sales.orders.index'))->assertForbidden();
        $this->actingAs($teacher,'institute_user')->get(route('sales.returns.index'))->assertForbidden();
    }

    public function test_audit_logging(): void
    {
        $inst=$this->institute(); $cust=$this->party($inst); $cur=$this->currency();
        $order=app(SalesOrderService::class)->createDraft($inst->id,null,['customer_id'=>$cust->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'lines'=>[['description'=>'A','quantity'=>1,'unit_price'=>100]]],null);
        $before=DB::table('accounting_audit_trails')->where('entity_type','sales_order')->where('entity_id',$order->id)->count();
        $this->assertGreaterThan(0,$before);
        $order=app(SalesOrderService::class)->submit($order,null);
        $after=DB::table('accounting_audit_trails')->where('entity_type','sales_order')->where('entity_id',$order->id)->count();
        $this->assertGreaterThan($before,$after);
    }

    public function test_finance_reconciliation(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->party($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        $this->setupAccounting($inst,$branch); app(\App\Services\Accounting\AccountingSetupService::class)->setSetting($inst->id,'invoice_auto_post',true,$branch->id); $wh=$this->stockUp($inst,$branch,$prod,50);
        $order=$this->approvedOrder($inst,$branch,$cust,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]]);
        $os=app(SalesOrderService::class); $admin=$this->user($inst,'institute-admin'); $order=$os->markProcessing($order,$admin->id); $order=$os->markReadyForDelivery($order,$admin->id);
        $del=app(DeliveryService::class)->createDelivery($inst->id,$branch->id,$order->id,['lines'=>[['order_line_id'=>$order->lines[0]->id,'delivery_quantity'=>2,'inventory_item_id'=>$prod->id]]],null);
        $del=app(DeliveryService::class)->confirmDelivery($del,null);
        $inv=app(SalesInvoiceService::class)->createFromDelivery($inst->id,$branch->id,$del->id,null);
        $j=Journal::find($inv->journal_id); $this->assertEquals('posted',$j->status); $this->assertEqualsWithDelta((float)$inv->payable_amount, $j->entries()->sum('debit'),0.01);
        $ret=app(SalesReturnService::class)->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Recon',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
        $ret=app(SalesReturnService::class)->post($inst->id,$branch->id,$ret->id,null);
        $rj=Journal::find($ret->journal_id); $this->assertEquals('posted',$rj->status);
        $this->assertEqualsWithDelta((float)$ret->grand_total,$rj->entries()->where('credit','>',0)->sum('credit'),0.01);
    }

    public function test_inventory_reconciliation(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $prod=$this->item($inst,$branch->id);
        $this->setupAccounting($inst,$branch); $wh=$this->stockUp($inst,$branch,$prod,100);
        $initial=(float)InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$prod->id)->value('quantity');
        $cust=$this->party($inst,$branch->id); $cur=$this->currency();
        $order=$this->approvedOrder($inst,$branch,$cust,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100]]);
        $os=app(SalesOrderService::class); $admin=$this->user($inst,'institute-admin'); $order=$os->markProcessing($order,$admin->id); $order=$os->markReadyForDelivery($order,$admin->id);
        $del=app(DeliveryService::class)->createDelivery($inst->id,$branch->id,$order->id,['lines'=>[['order_line_id'=>$order->lines[0]->id,'delivery_quantity'=>10,'inventory_item_id'=>$prod->id]]],null);
        $del=app(DeliveryService::class)->confirmDelivery($del,null);
        $afterDelivery=(float)InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$prod->id)->value('quantity');
        $this->assertEqualsWithDelta($initial-10,$afterDelivery,0.01);
        $inv=app(SalesInvoiceService::class)->createFromDelivery($inst->id,$branch->id,$del->id,null);
        $ret=app(SalesReturnService::class)->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Recon',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>4]],null);
        $ret=app(SalesReturnService::class)->post($inst->id,$branch->id,$ret->id,null);
        $afterReturn=(float)InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$prod->id)->value('quantity');
        $this->assertEqualsWithDelta($afterDelivery+4,$afterReturn,0.01);
        $movSum=(float)DB::table('inventory_movements')->where('warehouse_id',$wh->id)->where('item_id',$prod->id)->sum('quantity');
        $this->assertEqualsWithDelta($afterReturn,$movSum,0.01);
    }

    public function test_historical_immutability(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->party($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        $this->setupAccounting($inst,$branch); $wh=$this->stockUp($inst,$branch,$prod,50);
        $order=$this->approvedOrder($inst,$branch,$cust,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]]);
        $os=app(SalesOrderService::class); $admin=$this->user($inst,'institute-admin'); $order=$os->markProcessing($order,$admin->id); $order=$os->markReadyForDelivery($order,$admin->id);
        $del=app(DeliveryService::class)->createDelivery($inst->id,$branch->id,$order->id,['lines'=>[['order_line_id'=>$order->lines[0]->id,'delivery_quantity'=>2,'inventory_item_id'=>$prod->id]]],null);
        $del=app(DeliveryService::class)->confirmDelivery($del,null);
        $inv=app(SalesInvoiceService::class)->createFromDelivery($inst->id,$branch->id,$del->id,null);
        $orderTotalBefore=(float)$order->grand_total; $invTotalBefore=(float)$inv->payable_amount;
        $ret=app(SalesReturnService::class)->createDraft($inst->id,$branch->id,$inv->id,$wh->id,now()->toDateString(),'Immutable',null,[['invoice_item_id'=>$inv->items[0]->id,'quantity'=>1]],null);
        $ret=app(SalesReturnService::class)->post($inst->id,$branch->id,$ret->id,null);
        $order->refresh(); $inv->refresh();
        $this->assertEqualsWithDelta($orderTotalBefore,(float)$order->grand_total,0.01);
        $this->assertEqualsWithDelta($invTotalBefore,(float)$inv->payable_amount,0.01);
        $this->assertTrue($ret->isImmutable());
        $this->assertFalse($ret->canEdit());
    }

    public function test_reports_are_readonly(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->party($inst,$branch->id); $cur=$this->currency(); $prod=$this->item($inst,$branch->id);
        $this->setupAccounting($inst,$branch); $wh=$this->stockUp($inst,$branch,$prod,50);
        $order=$this->approvedOrder($inst,$branch,$cust,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>2,'unit_price'=>100]]);
        $os=app(SalesOrderService::class); $admin=$this->user($inst,'institute-admin'); $order=$os->markProcessing($order,$admin->id); $order=$os->markReadyForDelivery($order,$admin->id);
        $del=app(DeliveryService::class)->createDelivery($inst->id,$branch->id,$order->id,['lines'=>[['order_line_id'=>$order->lines[0]->id,'delivery_quantity'=>2,'inventory_item_id'=>$prod->id]]],null);
        $del=app(DeliveryService::class)->confirmDelivery($del,null);
        $inv=app(SalesInvoiceService::class)->createFromDelivery($inst->id,$branch->id,$del->id,null);
        $countsBefore=['orders'=>SalesOrder::withoutGlobalScopes()->where('institute_id',$inst->id)->count(),'journals'=>Journal::withoutGlobalScopes()->where('institute_id',$inst->id)->count(),'stock'=>DB::table('inventory_stock_levels')->where('warehouse_id',$wh->id)->sum('quantity')];
        $svc=app(FinancialReportService::class);
        $svc->trialBalance($inst->id,$branch->id,null,null);
        $svc->incomeStatement($inst->id,$branch->id,null,null);
        $countsAfter=['orders'=>SalesOrder::withoutGlobalScopes()->where('institute_id',$inst->id)->count(),'journals'=>Journal::withoutGlobalScopes()->where('institute_id',$inst->id)->count(),'stock'=>DB::table('inventory_stock_levels')->where('warehouse_id',$wh->id)->sum('quantity')];
        $this->assertEquals($countsBefore,$countsAfter);
    }
}
