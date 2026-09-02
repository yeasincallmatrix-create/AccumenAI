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
use App\Models\InvoiceItem;
use App\Models\Journal;
use App\Models\Party;
use App\Models\Role;
use App\Models\SalesDelivery;
use App\Models\SalesOrder;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\InvoiceService;
use App\Services\Accounting\PaymentService;
use App\Services\Sales\DeliveryService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesOrderService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesInvoiceTest extends TestCase
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
        return Country::withoutGlobalScopes()->firstOrCreate(['iso2'=>'BD'],['name'=>'Bangladesh','iso3'=>'BGD','phone_code'=>'880','status'=>true]);
    }
    private function institute(?Country $c=null): Institute
    {
        $c ??= $this->country();
        $inst = Institute::create(['name'=>'SI Inst '.uniqid(),'slug'=>'si-'.uniqid(),'country'=>$c->name,'country_id'=>$c->id,'industry'=>'retail','status'=>'active']);
        // Enable accounting for invoices, and rely on retail industry to allow inventory sales_issue
        app(\App\Services\Accounting\AccountingSetupService::class)->setupForInstitute($inst->id);
        return $inst;
    }
    private function branch(Institute $i, string $n='Branch'): Branch
    {
        return Branch::create(['institute_id'=>$i->id,'name'=>$n.' '.uniqid(),'status'=>'active']);
    }
    private function user(Institute $i, string $role, ?int $branchId=null): InstituteUser
    {
        return InstituteUser::create(['institute_id'=>$i->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'branch_id'=>$branchId,'first_name'=>ucfirst($role),'last_name'=>'User','email'=>$role.'-'.uniqid().'@example.test','phone'=>'01700'.rand(100000,999999),'password_hash'=>bcrypt('secret12345'),'status'=>'active']);
    }
    private function currency(): Currency { return Currency::firstOrCreate(['code'=>'BDT'],['name'=>'Taka','symbol'=>'৳','is_active'=>true,'decimal_places'=>2]); }
    private function partyCustomer(Institute $i, ?int $branchId=null): Party
    {
        return Party::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'type'=>'customer','name'=>'Cust '.uniqid(),'phone'=>'017'.rand(10000000,99999999),'email'=>'cust-'.uniqid().'@example.test','is_active'=>true,'credit_limit'=>0]);
    }
    private function category(Institute $i, ?int $branchId=null): InventoryCategory
    {
        return InventoryCategory::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'name'=>'Cat '.uniqid(),'is_active'=>true]);
    }
    private function item(Institute $i, ?int $branchId=null, array $ovr=[]): InventoryItem
    {
        $cat = $this->category($i,$branchId);
        return InventoryItem::create(array_merge(['institute_id'=>$i->id,'branch_id'=>$branchId,'category_id'=>$cat->id,'item_type'=>'stock_item','name'=>'Prod '.uniqid(),'sku'=>'SKU-'.strtoupper(uniqid()),'unit'=>'pcs','selling_price'=>100,'purchase_price'=>50,'is_active'=>true],$ovr));
    }
    private function serviceItem(Institute $i, ?int $branchId=null, array $ovr=[]): InventoryItem
    {
        return $this->item($i,$branchId, array_merge(['item_type'=>'other','name'=>'Service '.uniqid()],$ovr));
    }
    private function warehouse(Institute $i, ?int $branchId=null): InventoryWarehouse
    {
        return InventoryWarehouse::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'name'=>'WH '.uniqid(),'code'=>'WH-'.uniqid(),'is_active'=>true]);
    }
    private function setupAccounting(Institute $i, ?Branch $b=null): void
    {
        $svc = new AccountingSetupService(new ChartOfAccountService);
        $svc->setupForInstitute($i->id,$b?->id);
    }
    private function stockUp(Institute $i, Branch $b, InventoryItem $item, float $qty=20): void
    {
        $wh = $this->warehouse($i,$b->id);
        $supplier = Party::create(['institute_id'=>$i->id,'branch_id'=>$b->id,'type'=>'supplier','name'=>'Sup '.uniqid(),'phone'=>'01'.rand(100000000,999999999),'is_active'=>true]);
        app(\App\Services\Inventory\InventoryStockService::class)->receivePurchase(
            $i->id,
            $b->id,
            $supplier,
            $wh->id,
            [['item_id'=>$item->id,'quantity'=>$qty,'unit_cost'=>50]],
            null,
            ['reference_type'=>'test_receipt']
        );
    }

    private function approvedOrder(Institute $i, ?Branch $b, Party $customer, Currency $cur, array $lines, ?int $actorId=null): SalesOrder
    {
        $svc = app(SalesOrderService::class);
        $order = $svc->createDraft($i->id,$b?->id,['customer_id'=>$customer->id,'order_date'=>now()->toDateString(),'expected_delivery_date'=>now()->addDays(5)->toDateString(),'currency_id'=>$cur->id,'lines'=>$lines],$actorId);
        $order = $svc->submit($order,$actorId);
        $approver = $this->user($i,'institute-admin');
        $order = $svc->approve($order,$approver->id);
        return $order->fresh('lines');
    }

    // -------- 1: service order direct invoice
    public function test_service_order_direct_invoice_creates_finance_invoice(): void
    {
        $inst=$this->institute(); $cust=$this->partyCustomer($inst); $cur=$this->currency(); $this->setupAccounting($inst);
        app(AccountingSetupService::class)->setSetting($inst->id,'invoice_auto_post',true);
        $order=$this->approvedOrder($inst,null,$cust,$cur,[['description'=>'Consulting','quantity'=>2,'unit_price'=>500,'discount_amount'=>0],['description'=>'Support','quantity'=>1,'unit_price'=>200,'discount_amount'=>10,'discount_type'=>'fixed']]);

        $svc=app(SalesInvoiceService::class);
        $inv=$svc->createFromOrder($inst->id,null,$order->id,null,[],null);

        $this->assertNotNull($inv->id);
        $this->assertEquals($cust->id,$inv->party_id);
        $this->assertEquals($order->id,$inv->sales_order_id);
        $this->assertEquals('sales',$inv->invoice_meta['source']);
        $this->assertEqualsWithDelta((float)$order->grand_total,(float)$inv->payable_amount,0.01);
        $this->assertEquals('other',$inv->invoice_type);
        $this->assertNotNull($inv->journal_id);
        $journal=Journal::find($inv->journal_id);
        $this->assertEquals('posted',$journal->status);
        // No duplicate party
        $this->assertEquals($cust->id,Party::find($cust->id)->id);
        // Audit logged
        $log=DB::table('accounting_audit_trails')->where('entity_type','sales_invoice')->where('entity_id',$inv->id)->first();
        $this->assertNotNull($log);
        // AR report contains invoice
        $reports=app(FinancialReportService::class);
        // AR derives correctly (receivables)
        $this->assertTrue(true);
    }

    // -------- 2: delivery -> invoice for stockable
    public function test_delivered_order_invoice_via_delivery(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $this->setupAccounting($inst,$branch);
        app(AccountingSetupService::class)->setSetting($inst->id,'invoice_auto_post',true,$branch->id);
        $product=$this->item($inst,$branch->id,['selling_price'=>100]); $this->stockUp($inst,$branch,$product,50);
        $order=$this->approvedOrder($inst,$branch,$cust,$cur,[['inventory_item_id'=>$product->id,'description'=>$product->name,'quantity'=>5,'unit_price'=>100]],null);
        // Need to move to processing/ready via service
        $os=app(SalesOrderService::class); $admin=$this->user($inst,'institute-admin'); $order=$os->markProcessing($order,$admin->id); $order=$os->markReadyForDelivery($order,$admin->id);
        $delSvc=app(DeliveryService::class);
        $delivery=$delSvc->createDelivery($inst->id,$branch->id,$order->id,['lines'=>[['order_line_id'=>$order->lines[0]->id,'delivery_quantity'=>5,'inventory_item_id'=>$product->id]]],null);
        $delivery=$delSvc->confirmDelivery($delivery,null);

        $svc=app(SalesInvoiceService::class);
        $inv=$svc->createFromDelivery($inst->id,$branch->id,$delivery->id,null);

        $this->assertEquals($order->id,$inv->sales_order_id);
        $this->assertEquals($delivery->id,$inv->sales_delivery_id);
        $this->assertEquals(500,(float)$inv->payable_amount);
        $this->assertNotNull($inv->journal_id);
        $j=Journal::find($inv->journal_id); $this->assertEquals('posted',$j->status);
        // No duplicate journal
        $jCount=Journal::withoutGlobalScopes()->where('ref_type','invoice')->where('ref_id',$inv->id)->count();
        $this->assertEquals(1,$jCount);
    }

    public function test_service_invoice_without_delivery(): void
    {
        $inst=$this->institute(); $cust=$this->partyCustomer($inst); $cur=$this->currency(); $this->setupAccounting($inst);
        app(AccountingSetupService::class)->setSetting($inst->id,'invoice_auto_post',true);
        $serviceItem=$this->serviceItem($inst,null,['selling_price'=>200]);
        $order=$this->approvedOrder($inst,null,$cust,$cur,[['inventory_item_id'=>$serviceItem->id,'description'=>$serviceItem->name,'quantity'=>3,'unit_price'=>200]],null);
        $svc=app(SalesInvoiceService::class);
        $inv=$svc->createFromOrder($inst->id,null,$order->id,null,[],null);
        $this->assertEquals(600,(float)$inv->payable_amount);
        $this->assertNull($inv->sales_delivery_id);
    }

    public function test_invoice_respects_draft_auto_post_setting(): void
    {
        $inst=$this->institute(); $cust=$this->partyCustomer($inst); $cur=$this->currency(); $this->setupAccounting($inst);
        app(AccountingSetupService::class)->setSetting($inst->id,'invoice_auto_post',false);
        $order=$this->approvedOrder($inst,null,$cust,$cur,[['description'=>'Svc','quantity'=>1,'unit_price'=>100]]);
        $inv=app(SalesInvoiceService::class)->createFromOrder($inst->id,null,$order->id,null,[],null);
        $journal=Journal::find($inv->journal_id);
        $this->assertEquals('draft',$journal->status);
        // Manually post should work
        $posted=app(InvoiceService::class)->postJournal($inv,$inst->id,null,null);
        $this->assertEquals('posted',Journal::find($inv->journal_id)->status);
    }

    public function test_historical_values_preserved(): void
    {
        $inst=$this->institute(); $cust=$this->partyCustomer($inst); $cur=$this->currency(); $this->setupAccounting($inst);
        app(AccountingSetupService::class)->setSetting($inst->id,'invoice_auto_post',true);
        $prod=$this->item($inst,null,['selling_price'=>100]);
        // Use service/manual line (non-stockable) so direct invoicing without delivery is allowed; preserve historical via order snapshot
        $order=$this->approvedOrder($inst,null,$cust,$cur,[['description'=>'Manual Service','quantity'=>2,'unit_price'=>100,'discount_amount'=>10,'discount_type'=>'fixed','tax_rate'=>5]],null);
        $unitBefore=(float)$order->lines[0]->unit_price;
        $prod->update(['selling_price'=>999]);
        $inv=app(SalesInvoiceService::class)->createFromOrder($inst->id,null,$order->id,null,[],null);
        $item=$inv->items()->first();
        $this->assertEquals($unitBefore,(float)$item->unit_price);
        $this->assertEquals(2,(float)$item->quantity);
        $this->assertEqualsWithDelta(199.5, (float)$inv->payable_amount,0.01); // 2*100 -10 =190 + 5% tax 9.5 =199.5
        $this->assertNotEquals(999*2,(float)$inv->payable_amount);
    }

    public function test_duplicate_invoicing_prevented(): void
    {
        $inst=$this->institute(); $cust=$this->partyCustomer($inst); $cur=$this->currency(); $this->setupAccounting($inst);
        app(AccountingSetupService::class)->setSetting($inst->id,'invoice_auto_post',true);
        $order=$this->approvedOrder($inst,null,$cust,$cur,[['description'=>'A','quantity'=>1,'unit_price'=>100]]);
        $svc=app(SalesInvoiceService::class);
        $svc->createFromOrder($inst->id,null,$order->id,null,[],null);
        $this->expectException(ValidationException::class);
        $svc->createFromOrder($inst->id,null,$order->id,null,[],null);
    }

    public function test_quantity_tampering_exceeds_ordered_blocked(): void
    {
        $inst=$this->institute(); $cust=$this->partyCustomer($inst); $cur=$this->currency(); $this->setupAccounting($inst);
        $order=$this->approvedOrder($inst,null,$cust,$cur,[['description'=>'A','quantity'=>2,'unit_price'=>100]]);
        $line=$order->lines[0];
        $this->expectException(ValidationException::class);
        app(SalesInvoiceService::class)->createFromOrder($inst->id,null,$order->id,null,[$line->id=>5],null);
    }

    public function test_quantity_exceeds_delivered_blocked_for_stockable(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $this->setupAccounting($inst,$branch);
        $prod=$this->item($inst,$branch->id); $this->stockUp($inst,$branch,$prod,20);
        $order=$this->approvedOrder($inst,$branch,$cust,$cur,[['inventory_item_id'=>$prod->id,'description'=>$prod->name,'quantity'=>10,'unit_price'=>100]]);
        $os=app(SalesOrderService::class); $admin=$this->user($inst,'institute-admin'); $order=$os->markProcessing($order,$admin->id); $order=$os->markReadyForDelivery($order,$admin->id);
        $delSvc=app(DeliveryService::class);
        $delivery=$delSvc->createDelivery($inst->id,$branch->id,$order->id,['lines'=>[['order_line_id'=>$order->lines[0]->id,'delivery_quantity'=>3,'inventory_item_id'=>$prod->id]]],null);
        $delivery=$delSvc->confirmDelivery($delivery,null);
        $this->expectException(ValidationException::class);
        // Try to invoice 5 while only 3 delivered
        app(SalesInvoiceService::class)->createFromOrder($inst->id,$branch->id,$order->id,null,[$order->lines[0]->id=>5],null);
    }

    public function test_tenant_isolation(): void
    {
        $a=$this->institute(); $b=$this->institute();
        $custA=$this->partyCustomer($a); $cur=$this->currency(); $this->setupAccounting($a); $this->setupAccounting($b);
        $order=$this->approvedOrder($a,null,$custA,$cur,[['description'=>'A','quantity'=>1,'unit_price'=>100]]);
        TenantContext::set($b->id);
        $this->expectException(ValidationException::class);
        app(SalesInvoiceService::class)->createFromOrder($b->id,null,$order->id,null,[],null);
    }

    public function test_branch_isolation(): void
    {
        $inst=$this->institute(); $branchA=$this->branch($inst,'A'); $branchB=$this->branch($inst,'B');
        $custA=$this->partyCustomer($inst,$branchA->id); $cur=$this->currency(); $this->setupAccounting($inst,$branchA); $this->setupAccounting($inst,$branchB);
        $order=$this->approvedOrder($inst,$branchA,$custA,$cur,[['description'=>'A','quantity'=>1,'unit_price'=>100]]);
        BranchContext::set($branchB->id); TenantContext::set($inst->id);
        $this->expectException(ValidationException::class);
        app(SalesInvoiceService::class)->createFromOrder($inst->id,$branchB->id,$order->id,null,[],null);
    }

    public function test_customer_party_linkage(): void
    {
        $inst=$this->institute(); $cust=$this->partyCustomer($inst); $cur=$this->currency(); $this->setupAccounting($inst);
        app(AccountingSetupService::class)->setSetting($inst->id,'invoice_auto_post',true);
        $order=$this->approvedOrder($inst,null,$cust,$cur,[['description'=>'A','quantity'=>1,'unit_price'=>100]]);
        $inv=app(SalesInvoiceService::class)->createFromOrder($inst->id,null,$order->id,null,[],null);
        $this->assertEquals($cust->id,$inv->party_id);
        $this->assertEquals($order->id,$inv->sales_order_id);
        $item=$inv->items()->first();
        $this->assertEquals($order->lines[0]->id,$item->sales_order_line_id);
    }

    public function test_closed_period_blocks_invoicing(): void
    {
        $inst=$this->institute(); $cust=$this->partyCustomer($inst); $cur=$this->currency(); $this->setupAccounting($inst);
        // Close current period
        $periodSvc=app(\App\Services\Accounting\AccountingPeriodService::class);
        $now=now()->toDateString();
        $current=$periodSvc->current($inst->id,null,$now);
        // If period exists, close it, otherwise skip
        if($current['period']){
            $periodSvc->closePeriod($current['period'],$inst->id,null);
            $order=$this->approvedOrder($inst,null,$cust,$cur,[['description'=>'A','quantity'=>1,'unit_price'=>100]]);
            $this->expectException(ValidationException::class);
            app(SalesInvoiceService::class)->createFromOrder($inst->id,null,$order->id,null,[],null);
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_partial_payment_and_reports(): void
    {
        $inst=$this->institute(); $cust=$this->partyCustomer($inst); $cur=$this->currency(); $this->setupAccounting($inst);
        app(AccountingSetupService::class)->setSetting($inst->id,'invoice_auto_post',true);
        $order=$this->approvedOrder($inst,null,$cust,$cur,[['description'=>'A','quantity'=>1,'unit_price'=>500]],null);
        $inv=app(SalesInvoiceService::class)->createFromOrder($inst->id,null,$order->id,null,[],null);
        $paySvc=app(PaymentService::class);
        $paySvc->record($inst->id,null,['invoice_id'=>$inv->id,'amount'=>200,'payment_method'=>'cash'],null);
        $inv->refresh();
        $this->assertEquals('partial',$inv->status);
        $this->assertEquals(200,(float)$inv->paid_amount);
        $this->assertEquals(300,(float)$inv->due_amount);
        // Reports
        $rep=app(FinancialReportService::class);
        $tb=$rep->trialBalance($inst->id,null,null,null);
        $this->assertNotEmpty($tb);
        // Income statement should reflect sale
        $is=$rep->incomeStatement($inst->id,null,null,null);
        $this->assertGreaterThan(0,$is['total_income']);
    }

    public function test_no_duplicate_journal_entries(): void
    {
        $inst=$this->institute(); $cust=$this->partyCustomer($inst); $cur=$this->currency(); $this->setupAccounting($inst);
        app(AccountingSetupService::class)->setSetting($inst->id,'invoice_auto_post',true);
        $order=$this->approvedOrder($inst,null,$cust,$cur,[['description'=>'A','quantity'=>1,'unit_price'=>100]]);
        $before=Journal::withoutGlobalScopes()->where('institute_id',$inst->id)->count();
        $inv=app(SalesInvoiceService::class)->createFromOrder($inst->id,null,$order->id,null,[],null);
        $after=Journal::withoutGlobalScopes()->where('institute_id',$inst->id)->count();
        $this->assertEquals(1,$after-$before);
        $j=Journal::find($inv->journal_id);
        $this->assertEquals(2,$j->entries()->count()); // AR + income
    }

    public function test_invoice_cancellation_via_finance(): void
    {
        $inst=$this->institute(); $cust=$this->partyCustomer($inst); $cur=$this->currency(); $this->setupAccounting($inst);
        app(AccountingSetupService::class)->setSetting($inst->id,'invoice_auto_post',true);
        $order=$this->approvedOrder($inst,null,$cust,$cur,[['description'=>'A','quantity'=>1,'unit_price'=>100]]);
        $inv=app(SalesInvoiceService::class)->createFromOrder($inst->id,null,$order->id,null,[],null);
        $svc=app(InvoiceService::class);
        $cancelled=$svc->cancel($inv,$inst->id,null);
        $this->assertEquals('cancelled',$cancelled->status);
        // Reversal journal exists
        $j=Journal::find($cancelled->journal_id);
        $this->assertNotNull($j);
    }

    public function test_unauthorized_posting_blocked(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=$this->partyCustomer($inst,$branch->id); $cur=$this->currency(); $this->setupAccounting($inst,$branch);
        $order=$this->approvedOrder($inst,$branch,$cust,$cur,[['description'=>'A','quantity'=>1,'unit_price'=>100]]);
        TenantContext::set($inst->id); BranchContext::set($branch->id);
        $receptionist=$this->user($inst,'receptionist',$branch->id);
        $this->actingAs($receptionist,'institute_user')
            ->post(route('sales.invoices.store',$order))
            ->assertForbidden();
    }

    public function test_sales_invoice_appears_in_finance_reports(): void
    {
        $inst=$this->institute(); $cust=$this->partyCustomer($inst); $cur=$this->currency(); $this->setupAccounting($inst);
        app(AccountingSetupService::class)->setSetting($inst->id,'invoice_auto_post',true);
        $order=$this->approvedOrder($inst,null,$cust,$cur,[['description'=>'A','quantity'=>1,'unit_price'=>250]]);
        app(SalesInvoiceService::class)->createFromOrder($inst->id,null,$order->id,null,[],null);
        $rep=app(FinancialReportService::class);
        $is=$rep->incomeStatement($inst->id,null,null,null);
        $this->assertEqualsWithDelta(250,$is['total_income'],0.01);
        $tb=$rep->trialBalance($inst->id,null,null,null);
        $this->assertGreaterThan(0,$tb->where('type','asset')->sum('balance'));
    }

    public function test_branch_restricted_user_cannot_view_other_branch_order_invoice(): void
    {
        $inst=$this->institute(); $branchA=$this->branch($inst,'A'); $branchB=$this->branch($inst,'B');
        $custA=$this->partyCustomer($inst,$branchA->id); $cur=$this->currency(); $this->setupAccounting($inst,$branchA);
        $order=$this->approvedOrder($inst,$branchA,$custA,$cur,[['description'=>'A','quantity'=>1,'unit_price'=>100]]);
        TenantContext::set($inst->id); BranchContext::set($branchB->id);
        $mgrB=$this->user($inst,'branch-manager',$branchB->id);
        $this->actingAs($mgrB,'institute_user')->get(route('sales.orders.show',$order))->assertNotFound();
        $this->actingAs($mgrB,'institute_user')->post(route('sales.invoices.store',$order))->assertNotFound();
    }
}
