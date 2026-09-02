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
use App\Models\Party;
use App\Models\Role;
use App\Models\PurchaseOrder;
use App\Services\Purchase\PurchaseOrderService;
use App\Services\Purchase\PurchaseReturnService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesOrderService;
use App\Services\Reports\ReportRegistry;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class P59ProductionIntegrityTest extends TestCase
{
    use DatabaseTransactions;
    protected function tearDown(): void { TenantContext::clear(); BranchContext::clear(); parent::tearDown(); }

    private function country(): Country { return Country::withoutGlobalScopes()->firstOrCreate(['iso2'=>'BD'],['name'=>'Bangladesh','iso3'=>'BGD','phone_code'=>'880','status'=>true]); }
    private function institute(?Country $c=null): Institute { $c??=$this->country(); $inst=Institute::create(['name'=>'P59 '.uniqid(),'slug'=>'p59-'.uniqid(),'country'=>$c->name,'country_id'=>$c->id,'industry'=>'retail','status'=>'active']); $premium=DB::table('subscription_packages')->where('slug','PREMIUM')->value('id'); if($premium) $inst->forceFill(['package_id'=>$premium])->save(); app(\App\Services\ModuleAccessService::class)->flushCache($inst->id); app(\App\Services\Accounting\AccountingSetupService::class)->setupForInstitute($inst->id); return $inst; }
    private function branch(Institute $i,string $n='B'): Branch { return Branch::create(['institute_id'=>$i->id,'name'=>$n.' '.uniqid(),'status'=>'active']); }
    private function user(Institute $i,string $role,?int $branchId=null): InstituteUser { return InstituteUser::create(['institute_id'=>$i->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'branch_id'=>$branchId,'first_name'=>ucfirst($role),'last_name'=>'User','email'=>$role.'-'.uniqid().'@example.test','phone'=>'01700'.rand(100000,999999),'password_hash'=>bcrypt('secret12345'),'status'=>'active']); }
    private function currency(): Currency { return Currency::firstOrCreate(['code'=>'BDT'],['name'=>'Taka','symbol'=>'৳','is_active'=>true,'decimal_places'=>2]); }
    private function supplier(Institute $i,?int $branchId=null): Party { return Party::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'type'=>'supplier','name'=>'Sup '.uniqid(),'phone'=>'01'.rand(100000000,999999999),'is_active'=>true]); }
    private function customer(Institute $i,?int $branchId=null): Party { return Party::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'type'=>'customer','name'=>'Cust '.uniqid(),'phone'=>'017'.rand(10000000,99999999),'is_active'=>true]); }
    private function category(Institute $i,?int $branchId=null): InventoryCategory { return InventoryCategory::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'name'=>'Cat '.uniqid(),'is_active'=>true]); }
    private function item(Institute $i,?int $branchId=null,array $ovr=[]): InventoryItem { $cat=$this->category($i,$branchId); return InventoryItem::create(array_merge(['institute_id'=>$i->id,'branch_id'=>$branchId,'category_id'=>$cat->id,'item_type'=>'stock_item','name'=>'Prod '.uniqid(),'sku'=>'SKU-'.strtoupper(uniqid()),'unit'=>'pcs','selling_price'=>100,'purchase_price'=>50,'is_active'=>true],$ovr)); }
    private function warehouse(Institute $i,?int $branchId=null): InventoryWarehouse { return InventoryWarehouse::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'name'=>'WH '.uniqid(),'code'=>'WH-'.uniqid(),'is_active'=>true]); }

    public function test_purchase_return_uses_single_write_path(): void
    {
        // Verify PurchaseReturnService::post delegates to InventoryStockService (single path)
        $inst=$this->institute(); $branch=$this->branch($inst); app(\App\Services\Accounting\AccountingSetupService::class)->setupForInstitute($inst->id,$branch->id);
        $sup=$this->supplier($inst,$branch->id); $item=$this->item($inst,$branch->id); $wh=$this->warehouse($inst,$branch->id);
        $sup2=$this->supplier($inst,$branch->id);
        // receive stock
        app(\App\Services\Inventory\InventoryStockService::class)->receivePurchase($inst->id,$branch->id,$sup2,$wh->id,[['item_id'=>$item->id,'quantity'=>10,'unit_cost'=>50]],null,['reference_type'=>'test']);
        $poSvc=app(PurchaseOrderService::class);
        $po=$poSvc->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$this->currency()->id,'lines'=>[['inventory_item_id'=>$item->id,'description'=>$item->name,'quantity'=>5,'unit_price'=>80]]], $inst->id, $branch->id, $this->user($inst,'institute-owner')->id);
        $po=$poSvc->submit($po,$po->created_by); // need actor? use owner
        $owner=$this->user($inst,'institute-owner');
        // Actually create should be by owner, then approve by admin
        $admin=$this->user($inst,'institute-admin');
        $po=$poSvc->approve($po,$admin->id);
        // Create goods receipt
        $grSvc=app(\App\Services\Purchase\GoodsReceiptService::class);
        $gr=$grSvc->create(['purchase_order_id'=>$po->id,'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'received_quantity'=>5,'inventory_item_id'=>$item->id]]], $inst->id,$branch->id, $admin->id);
        $gr=$grSvc->confirm($gr,$admin->id);
        $qtyBefore=(float)\App\Models\InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$item->id)->value('quantity');
        // Create purchase return
        $retSvc=app(PurchaseReturnService::class);
        $ret=$retSvc->create($inst->id,$branch->id,['supplier_id'=>$sup->id,'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),'reason'=>'P59 test','lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$item->id,'description'=>$item->name,'quantity'=>2,'unit_price'=>80]]], $owner->id);
        $ret=$retSvc->submit($ret,$owner->id);
        $admin2=$this->user($inst,'institute-admin');
        $ret=$retSvc->approve($ret,$admin2->id);
        $ret=$retSvc->post($ret,$admin2->id);
        $qtyAfter=(float)\App\Models\InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$item->id)->value('quantity');
        $this->assertEqualsWithDelta($qtyBefore-2,$qtyAfter,0.01);
        $mov=DB::table('inventory_movements')->where('reference_type',\App\Models\PurchaseReturn::class)->where('reference_id',$ret->id)->where('movement_type','return_out')->first();
        $this->assertNotNull($mov, 'Inventory movement should be via InventoryStockService with reference_type PurchaseReturn');
        // Verify no second write path: movement_no should be IVM-... random, not RTN-... count+1
        $this->assertStringStartsWith('IVM-',$mov->movement_no);
        $this->assertEquals('return_out', $mov->movement_type);
    }

    public function test_invoice_concurrency_sales(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->customer($inst,$branch->id); $cur=$this->currency(); $item=$this->item($inst,$branch->id);
        $wh=$this->warehouse($inst,$branch->id);
        $sup=Party::create(['institute_id'=>$inst->id,'branch_id'=>$branch->id,'type'=>'supplier','name'=>'Sup','phone'=>'01'.rand(100000000,999999999),'is_active'=>true]);
        app(\App\Services\Accounting\AccountingSetupService::class)->setupForInstitute($inst->id, $branch->id);
        app(\App\Services\Inventory\InventoryStockService::class)->receivePurchase($inst->id,$branch->id,$sup,$wh->id,[['item_id'=>$item->id,'quantity'=>10,'unit_cost'=>50]],null,['reference_type'=>'test']);
        $orderSvc=app(SalesOrderService::class);
        $order=$orderSvc->createDraft($inst->id,$branch->id,['customer_id'=>$cust->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'lines'=>[['inventory_item_id'=>$item->id,'description'=>$item->name,'quantity'=>5,'unit_price'=>100]]],null);
        $order=$orderSvc->submit($order,null); $admin=$this->user($inst,'institute-admin'); $order=$orderSvc->approve($order,$admin->id);
        $order=$orderSvc->markProcessing($order,$admin->id); $order=$orderSvc->markReadyForDelivery($order,$admin->id);
        $del=app(\App\Services\Sales\DeliveryService::class)->createDelivery($inst->id,$branch->id,$order->id,['lines'=>[['order_line_id'=>$order->lines[0]->id,'delivery_quantity'=>5,'inventory_item_id'=>$item->id]]],null);
        $del=app(\App\Services\Sales\DeliveryService::class)->confirmDelivery($del,null);
        $invSvc=app(SalesInvoiceService::class);
        $inv1=$invSvc->createFromDelivery($inst->id,$branch->id,$del->id,null);
        $this->assertNotNull($inv1->id);
        // Second attempt to invoice same delivery should be blocked (no remaining)
        $this->expectException(ValidationException::class);
        $invSvc->createFromDelivery($inst->id,$branch->id,$del->id,null);
    }

    public function test_purchase_self_approval_blocked(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $sup=$this->supplier($inst,$branch->id);
        $creator=$this->user($inst,'institute-owner');
        $poSvc=app(PurchaseOrderService::class);
        $po=$poSvc->create(['supplier_id'=>$sup->id,'warehouse_id'=>$this->warehouse($inst,$branch->id)->id,'order_date'=>now()->toDateString(),'currency_id'=>$this->currency()->id,'lines'=>[['inventory_item_id'=>$this->item($inst,$branch->id)->id,'description'=>'Test','quantity'=>2,'unit_price'=>50]]], $inst->id, $branch->id, $creator->id);
        $po=$poSvc->submit($po,$creator->id);
        $this->expectException(ValidationException::class);
        $poSvc->approve($po,$creator->id);
        // Another user can approve
        $admin=$this->user($inst,'institute-admin');
        $po=$poSvc->approve($po,$admin->id);
        $this->assertEquals(PurchaseOrder::STATUS_APPROVED,$po->status);
    }

    public function test_purchase_return_self_approval_blocked(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); app(\App\Services\Accounting\AccountingSetupService::class)->setupForInstitute($inst->id,$branch->id);
        $sup=$this->supplier($inst,$branch->id); $item=$this->item($inst,$branch->id); $wh=$this->warehouse($inst,$branch->id);
        $sup2=$this->supplier($inst,$branch->id);
        app(\App\Services\Inventory\InventoryStockService::class)->receivePurchase($inst->id,$branch->id,$sup2,$wh->id,[['item_id'=>$item->id,'quantity'=>10,'unit_cost'=>50]],null,['reference_type'=>'test']);
        $poSvc=app(PurchaseOrderService::class);
        $po=$poSvc->create(['supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'currency_id'=>$this->currency()->id,'lines'=>[['inventory_item_id'=>$item->id,'description'=>$item->name,'quantity'=>5,'unit_price'=>80]]], $inst->id, $branch->id, $this->user($inst,'institute-owner')->id);
        $po=$poSvc->submit($po,$po->created_by);
        $admin=$this->user($inst,'institute-admin');
        $po=$poSvc->approve($po,$admin->id);
        $grSvc=app(\App\Services\Purchase\GoodsReceiptService::class);
        $gr=$grSvc->create(['purchase_order_id'=>$po->id,'lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'received_quantity'=>5,'inventory_item_id'=>$item->id]]], $inst->id,$branch->id, $admin->id);
        $gr=$grSvc->confirm($gr,$admin->id);
        $creator=$this->user($inst,'institute-owner');
        $retSvc=app(PurchaseReturnService::class);
        $ret=$retSvc->create($inst->id,$branch->id,['supplier_id'=>$sup->id,'purchase_order_id'=>$po->id,'goods_receipt_id'=>$gr->id,'warehouse_id'=>$wh->id,'return_date'=>now()->toDateString(),'reason'=>'Test','lines'=>[['purchase_order_line_id'=>$po->lines[0]->id,'goods_receipt_item_id'=>$gr->items[0]->id,'inventory_item_id'=>$item->id,'description'=>$item->name,'quantity'=>1,'unit_price'=>80]]], $creator->id);
        $ret=$retSvc->submit($ret,$creator->id);
        $this->expectException(ValidationException::class);
        $retSvc->approve($ret,$creator->id);
        $admin2=$this->user($inst,'institute-admin');
        $ret=$retSvc->approve($ret,$admin2->id);
        $this->assertEquals(\App\Models\PurchaseReturn::STATUS_APPROVED,$ret->status);
    }

    public function test_report_registry_remains_code_based(): void
    {
        $all=ReportRegistry::all();
        $this->assertGreaterThan(20, count($all));
        // Verify industry mapping still correct after hardening (no flatten)
        $eduInstitute=Institute::create(['name'=>'Edu '.uniqid(),'slug'=>'edu-'.uniqid(),'country'=>'Bangladesh','country_id'=>$this->country()->id,'industry'=>'education','sub_industry'=>'school','status'=>'active']);
        $retailInstitute=Institute::create(['name'=>'Retail '.uniqid(),'slug'=>'retail-'.uniqid(),'country'=>'Bangladesh','country_id'=>$this->country()->id,'industry'=>'retail','sub_industry'=>'general_store','status'=>'active']);
        $ownerEdu=$this->user($eduInstitute,'institute-owner'); $ownerRetail=$this->user($retailInstitute,'institute-owner');
        $eduReports=ReportRegistry::forInstitute($eduInstitute, null, $ownerEdu);
        $retailReports=ReportRegistry::forInstitute($retailInstitute, null, $ownerRetail);
        $this->assertContains('education.students', array_column($eduReports,'key'));
        $this->assertNotContains('education.students', array_column($retailReports,'key'));
    }
}
