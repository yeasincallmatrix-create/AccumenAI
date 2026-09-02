<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\InventoryItem;
use App\Models\InventoryStockLevel;
use App\Models\InventoryWarehouse;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Role;
use App\Services\Accounting\AccountingSetupService;
use App\Support\TenantContext;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\Country;

class GoodsReceiptP5AdditionalTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void { TenantContext::clear(); BranchContext::clear(); parent::tearDown(); }

    private function institute(): Institute
    {
        $c = Country::withoutGlobalScopes()->firstOrCreate(['iso2'=>'BD'],['name'=>'Bangladesh','iso3'=>'BDR','phone_code'=>'880','status'=>true]);
        $inst = Institute::create(['name'=>'P5 '.uniqid(),'slug'=>'p5-'.uniqid(),'industry'=>'retail','country'=>$c->name,'country_id'=>$c->id,'status'=>'active']);
        app(AccountingSetupService::class)->setupForInstitute($inst->id, null);
        return $inst;
    }
    private function branch(Institute $i): \App\Models\Branch { $b=\App\Models\Branch::create(['institute_id'=>$i->id,'name'=>'B '.uniqid(),'status'=>'active']); app(AccountingSetupService::class)->setupForInstitute($i->id,$b->id); return $b; }
    private function user(Institute $i,string $role,?int $branchId=null): InstituteUser { return InstituteUser::create(['institute_id'=>$i->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'branch_id'=>$branchId,'first_name'=>ucfirst($role),'last_name'=>'User','email'=>$role.'-'.uniqid().'@test.test','phone'=>'01700'.rand(100000,999999),'password_hash'=>bcrypt('secret'),'status'=>'active']); }
    private function supplier(Institute $i,?int $branch=null): Party { return Party::create(['institute_id'=>$i->id,'branch_id'=>$branch,'type'=>'supplier','name'=>'Sup '.uniqid(),'phone'=>'01'.rand(100000000,999999999),'is_active'=>true]); }
    private function warehouse(Institute $i,?int $branch=null): InventoryWarehouse { return InventoryWarehouse::create(['institute_id'=>$i->id,'branch_id'=>$branch,'name'=>'WH '.uniqid(),'code'=>'WH-'.uniqid(),'is_active'=>true]); }
    private function item(Institute $i,?int $branch=null,string $type='stock_item'): InventoryItem { return InventoryItem::create(['institute_id'=>$i->id,'branch_id'=>$branch,'name'=>'Item '.uniqid(),'sku'=>'SKU-'.uniqid(),'item_type'=>$type,'purchase_price'=>50,'selling_price'=>80,'is_active'=>true]); }
    private function createApprovedPo(Institute $i, ?\App\Models\Branch $b, Party $sup, InventoryWarehouse $wh, InventoryItem $item, int $qty=10): PurchaseOrder
    {
        $po = PurchaseOrder::create(['institute_id'=>$i->id,'branch_id'=>$b?->id,'order_number'=>'PO-'.uniqid(),'supplier_id'=>$sup->id,'warehouse_id'=>$wh->id,'order_date'=>now()->toDateString(),'status'=>PurchaseOrder::STATUS_APPROVED,'subtotal'=>$qty*50,'grand_total'=>$qty*50,'created_by'=>1,'approved_by'=>1,'approved_at'=>now()]);
        PurchaseOrderLine::create(['institute_id'=>$i->id,'order_id'=>$po->id,'inventory_item_id'=>$item->id,'description'=>$item->name,'quantity'=>$qty,'unit_price'=>50,'line_total'=>$qty*50,'sort_order'=>0]);
        return $po->fresh('lines');
    }

    private function loginAs(InstituteUser $u, Institute $i): string
    {
        $res = $this->postJson('/api/login', ['email'=>$u->email,'password'=>'secret','institute_id'=>$i->id]);
        // fallback to owner creation if login fails due to password mismatch? Use institute owner directly via actingAs for web
        return $res->json('data.token') ?? 'dummy';
    }

    public function test_warehouse_isolation(): void
    {
        $inst=$this->institute(); $branchA=$this->branch($inst); $branchB=$this->branch($inst);
        $supA=$this->supplier($inst,$branchA->id); $whA=$this->warehouse($inst,$branchA->id); $whB=$this->warehouse($inst,$branchB->id);
        $item=$this->item($inst,$branchA->id); $po=$this->createApprovedPo($inst,$branchA,$supA,$whA,$item,10);
        // Try to create receipt with warehouse from other branch -> should fail 422 or 404
        $owner=$this->user($inst,'institute-owner',$branchA->id);
        TenantContext::set($inst->id); BranchContext::set($branchA->id);
        $token=$this->loginAs($owner,$inst);
        // Use branchA context but try to use whB (branchB warehouse)
        $res=$this->postJson('/api/goods-receipts',[
            'purchase_order_id'=>$po->id,
            'warehouse_id'=>$whB->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines->first()->id,'received_quantity'=>5,'unit_cost'=>50]]
        ], ['Authorization'=>'Bearer '.$token]);
        $res->assertStatus(422);
        $this->assertStringContainsString('Warehouse', $res->json('message') ?? json_encode($res->json()));
    }

    public function test_tenant_isolation(): void
    {
        $a=$this->institute(); $b=$this->institute();
        $supA=$this->supplier($a); $whA=$this->warehouse($a); $itemA=$this->item($a);
        $poA=$this->createApprovedPo($a,null,$supA,$whA,$itemA,10);
        $ownerB=$this->user($b,'institute-owner');
        TenantContext::set($b->id);
        $tokenB=$this->loginAs($ownerB,$b);
        $this->getJson("/api/goods-receipts/{$poA->id}", ['Authorization'=>'Bearer '.$tokenB])->assertStatus(404);
        // Try to create receipt for A's PO using B's token
        $this->postJson('/api/goods-receipts',[
            'purchase_order_id'=>$poA->id,
            'lines'=>[['purchase_order_line_id'=>$poA->lines->first()->id,'received_quantity'=>5]]
        ], ['Authorization'=>'Bearer '.$tokenB])->assertStatus(422);
    }

    public function test_branch_isolation(): void
    {
        $inst=$this->institute(); $branchA=$this->branch($inst); $branchB=$this->branch($inst);
        $sup=$this->supplier($inst,$branchA->id); $whA=$this->warehouse($inst,$branchA->id); $item=$this->item($inst,$branchA->id);
        $po=$this->createApprovedPo($inst,$branchA,$sup,$whA,$item,10);
        TenantContext::set($inst->id); BranchContext::set($branchA->id);
        $ownerA=$this->user($inst,'institute-owner',$branchA->id);
        $svc=app(\App\Services\Purchase\GoodsReceiptService::class);
        $receipt=$svc->create(['purchase_order_id'=>$po->id,'warehouse_id'=>$whA->id,'lines'=>[['purchase_order_line_id'=>$po->lines->first()->id,'received_quantity'=>5,'unit_cost'=>50]]], $inst->id,$branchA->id,$ownerA->id);
        // Branch B should not see Branch A's receipt via BranchContext scoping
        TenantContext::set($inst->id); BranchContext::set($branchB->id);
        $this->assertNull(\App\Models\GoodsReceipt::query()->find($receipt->id));
        BranchContext::clear();
        $this->assertNotNull(\App\Models\GoodsReceipt::withoutGlobalScopes()->find($receipt->id));
    }

    public function test_permission_enforcement(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst);
        $po=$this->createApprovedPo($inst,null,$sup,$wh,$item,10);
        $receptionist=$this->user($inst,'receptionist');
        TenantContext::set($inst->id);
        $token=$this->loginAs($receptionist,$inst);
        $this->postJson('/api/goods-receipts',[
            'purchase_order_id'=>$po->id,
            'lines'=>[['purchase_order_line_id'=>$po->lines->first()->id,'received_quantity'=>5]]
        ], ['Authorization'=>'Bearer '.$token])->assertStatus(403);
    }

    public function test_reversal_of_confirmed_receipt(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $sup=$this->supplier($inst,$branch->id); $wh=$this->warehouse($inst,$branch->id); $item=$this->item($inst,$branch->id);
        $po=$this->createApprovedPo($inst,$branch,$sup,$wh,$item,10);
        $svc=app(\App\Services\Purchase\GoodsReceiptService::class);
        $owner=$this->user($inst,'institute-owner',$branch->id);
        $receipt=$svc->create(['purchase_order_id'=>$po->id,'warehouse_id'=>$wh->id,'lines'=>[['purchase_order_line_id'=>$po->lines->first()->id,'received_quantity'=>6,'unit_cost'=>50]]], $inst->id,$branch->id,$owner->id);
        $receipt=$svc->confirm($receipt,$owner->id);
        $this->assertEquals('confirmed',$receipt->status);
        $poLine=$po->lines->first()->fresh();
        $this->assertEquals('6.0000',$poLine->received_quantity);
        $this->assertEquals('partially_received',$po->fresh()->status);
        $stockBefore = (float) \App\Models\InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$item->id)->value('quantity');
        $reversed=$svc->reverse($receipt,$owner->id,'Test reversal');
        $this->assertNotNull($reversed->reversed_at);
        $poLineAfter=$po->lines->first()->fresh();
        $this->assertEquals('0.0000',$poLineAfter->received_quantity);
        $this->assertEquals('approved',$po->fresh()->status);
        $stockAfter = (float) \App\Models\InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$item->id)->value('quantity');
        $this->assertEqualsWithDelta($stockBefore-6,$stockAfter,0.01);
        // Cannot reverse again
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->reverse($reversed->fresh(),$owner->id);
    }

    public function test_batch_fields_stored_and_forwarded_to_inventory(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $sup=$this->supplier($inst,$branch->id); $wh=$this->warehouse($inst,$branch->id);
        $medicine=$this->item($inst,$branch->id,'medicine');
        $po=$this->createApprovedPo($inst,$branch,$sup,$wh,$medicine,10);
        $svc=app(\App\Services\Purchase\GoodsReceiptService::class);
        $owner=$this->user($inst,'institute-owner',$branch->id);
        $receipt=$svc->create([
            'purchase_order_id'=>$po->id,
            'warehouse_id'=>$wh->id,
            'lines'=>[[
                'purchase_order_line_id'=>$po->lines->first()->id,
                'inventory_item_id'=>$medicine->id,
                'received_quantity'=>5,
                'unit_cost'=>50,
                'batch_number'=>'BATCH-001',
                'expiry_date'=>now()->addYear()->toDateString(),
                'manufacture_date'=>now()->subMonth()->toDateString(),
                'serial_numbers'=>['SN001','SN002','SN003','SN004','SN005'],
                'received_condition'=>'good',
            ]]
        ], $inst->id,$branch->id,$owner->id);
        $this->assertEquals('BATCH-001',$receipt->items->first()->batch_number);
        $this->assertEquals('good',$receipt->items->first()->received_condition);
        $receipt=$svc->confirm($receipt,$owner->id);
        $this->assertEquals('confirmed',$receipt->status);
        // Check inventory batch created
        $batch=\App\Models\InventoryBatch::where('institute_id',$inst->id)->where('item_id',$medicine->id)->where('batch_number','BATCH-001')->first();
        $this->assertNotNull($batch);
        $this->assertNotNull($batch->expiry_date);
        // Check serials created
        $serials=\App\Models\InventorySerialNumber::where('institute_id',$inst->id)->where('item_id',$medicine->id)->count();
        $this->assertEquals(5,$serials);
    }

    public function test_duplicate_confirm_idempotency(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst);
        $po=$this->createApprovedPo($inst,null,$sup,$wh,$item,10);
        $svc=app(\App\Services\Purchase\GoodsReceiptService::class);
        $owner=$this->user($inst,'institute-owner');
        $receipt=$svc->create(['purchase_order_id'=>$po->id,'warehouse_id'=>$wh->id,'lines'=>[['purchase_order_line_id'=>$po->lines->first()->id,'received_quantity'=>5,'unit_cost'=>50]]], $inst->id,null,$owner->id);
        $svc->confirm($receipt,$owner->id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->confirm($receipt->fresh(),$owner->id);
        // Ensure stock not doubled
        $qty=(float)\App\Models\InventoryStockLevel::where('warehouse_id',$wh->id)->where('item_id',$item->id)->value('quantity');
        $this->assertEqualsWithDelta(5,$qty,0.01);
    }

    public function test_finance_non_duplication(): void
    {
        $inst=$this->institute(); $sup=$this->supplier($inst); $wh=$this->warehouse($inst); $item=$this->item($inst);
        $po=$this->createApprovedPo($inst,null,$sup,$wh,$item,10);
        $svc=app(\App\Services\Purchase\GoodsReceiptService::class);
        $owner=$this->user($inst,'institute-owner');
        $journalsBefore=\App\Models\Journal::where('institute_id',$inst->id)->count();
        $receipt=$svc->create(['purchase_order_id'=>$po->id,'warehouse_id'=>$wh->id,'lines'=>[['purchase_order_line_id'=>$po->lines->first()->id,'received_quantity'=>5,'unit_cost'=>50]]], $inst->id,null,$owner->id);
        $this->assertEquals($journalsBefore, \App\Models\Journal::where('institute_id',$inst->id)->count());
        $svc->confirm($receipt,$owner->id);
        $journalsAfter=\App\Models\Journal::where('institute_id',$inst->id)->count();
        $this->assertEquals(1,$journalsAfter - $journalsBefore); // Only one inventory receipt journal, not duplicate AP
        // Confirming again should not create another journal
        try { $svc->confirm($receipt->fresh(),$owner->id); } catch (\Throwable $e) {}
        $this->assertEquals($journalsAfter, \App\Models\Journal::where('institute_id',$inst->id)->count());
    }

    public function test_web_ui_access(): void
    {
        $inst=$this->institute(); $owner=$this->user($inst,'institute-owner');
        TenantContext::set($inst->id);
        $this->actingAs($owner,'institute_user')->get(route('purchase.receipts.index'))->assertOk()->assertSee('Goods Receipts');
        $this->actingAs($owner,'institute_user')->get(route('purchase.receipts.create'))->assertOk()->assertSee('New Goods Receipt');
    }
}
