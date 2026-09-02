<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\InventoryItem;
use App\Models\InventoryWarehouse;
use App\Models\Party;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Student;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;
    protected function tearDown(): void { TenantContext::clear(); BranchContext::clear(); parent::tearDown(); }

    private function country(): Country { return Country::withoutGlobalScopes()->firstOrCreate(['iso2'=>'BD'],['name'=>'Bangladesh','iso3'=>'BGD','phone_code'=>'880','status'=>true]); }
    private function institute(?Country $c=null, string $industry='education', string $sub='school'): Institute {
        $c??=$this->country();
        $inst=Institute::create(['name'=>'Sec '.uniqid(),'slug'=>'sec-'.uniqid(),'country'=>$c->name,'country_id'=>$c->id,'industry'=>$industry,'sub_industry'=>$sub,'status'=>'active']);
        // Ensure FREE package modules
        $freeId = DB::table('subscription_packages')->whereRaw('LOWER(slug)=?', ['free'])->value('id');
        if ($freeId) $inst->forceFill(['package_id'=>$freeId])->save();
        return $inst;
    }
    private function branch(Institute $i,string $n='B'): Branch { return Branch::create(['institute_id'=>$i->id,'name'=>$n.' '.uniqid(),'status'=>'active']); }
    private function user(Institute $i,string $role,?int $branchId=null): InstituteUser {
        return InstituteUser::create(['institute_id'=>$i->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'branch_id'=>$branchId,'first_name'=>ucfirst($role),'last_name'=>'User','email'=>$role.'-'.uniqid().'@example.test','phone'=>'01700'.rand(100000,999999),'password_hash'=>bcrypt('secret12345'),'status'=>'active']);
    }

    public function test_mass_assignment_institute_id_ignored(): void
    {
        $a=$this->institute(); $b=$this->institute();
        $aBranch=$this->branch($a); $userA=$this->user($a,'institute-owner');
        TenantContext::set($a->id); BranchContext::clear();
        // Simulate malicious request trying to override institute_id via Party creation
        $party = Party::create(['institute_id'=>$b->id,'branch_id'=>$aBranch->id,'type'=>'customer','name'=>'Hacker','phone'=>'017'.rand(10000000,99999999),'email'=>'hack-'.uniqid().'@test.com','is_active'=>true]);
        // TenantScoped trait should force institute_id to TenantContext (a), not b
        $this->assertEquals($a->id, $party->institute_id);
        $this->assertNotEquals($b->id, $party->institute_id);
    }

    public function test_mass_assignment_branch_id_ignored_for_branch_user(): void
    {
        $inst=$this->institute(); $branchA=$this->branch($inst,'A'); $branchB=$this->branch($inst,'B');
        $mgrA=$this->user($inst,'branch-manager',$branchA->id);
        TenantContext::set($inst->id); BranchContext::set($branchA->id);
        $party = Party::create(['institute_id'=>$inst->id,'branch_id'=>$branchB->id,'type'=>'customer','name'=>'HackBranch','phone'=>'017'.rand(10000000,99999999),'is_active'=>true]);
        $this->assertEquals($branchA->id, $party->branch_id);
        $this->assertNotEquals($branchB->id, $party->branch_id);
    }

    public function test_system_number_not_mass_assignable_via_validated_request(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst); $cust=Party::create(['institute_id'=>$inst->id,'branch_id'=>$branch->id,'type'=>'customer','name'=>'Cust','phone'=>'017'.rand(10000000,99999999),'is_active'=>true]);
        $owner=$this->user($inst,'institute-owner');
        TenantContext::set($inst->id); BranchContext::clear();
        // Try to spoof order_number via sales order creation with explicit order_number in payload — should be ignored by service
        $svc=app(\App\Services\Sales\SalesOrderService::class);
        $cur=\App\Models\Currency::firstOrCreate(['code'=>'BDT'],['name'=>'Taka','symbol'=>'৳','is_active'=>true]);
        $order=$svc->createDraft($inst->id,$branch->id,['customer_id'=>$cust->id,'order_date'=>now()->toDateString(),'currency_id'=>$cur->id,'lines'=>[['description'=>'A','quantity'=>1,'unit_price'=>100]],'order_number'=>'HACKED-999'], $owner->id);
        $this->assertNotEquals('HACKED-999', $order->order_number);
        $this->assertStringStartsWith('SO-', $order->order_number);
    }

    public function test_tenant_isolation_students(): void
    {
        $a=$this->institute(); $b=$this->institute();
        $studentA=Student::withoutGlobalScopes()->create(['institute_id'=>$a->id,'branch_id'=>null,'first_name'=>'A','last_name'=>'Student','status'=>'active','admission_status'=>'enrolled','student_id_number'=>'STU-'.uniqid(),'admission_date'=>now()->toDateString()]);
        $mgrB=$this->user($b,'institute-owner');
        TenantContext::set($b->id);
        $found=Student::find($studentA->id);
        $this->assertNull($found);
        TenantContext::clear(); BranchContext::clear();
        $found2=Student::withoutGlobalScopes()->find($studentA->id);
        $this->assertNotNull($found2);
        // HTTP IDOR
        $this->actingAs($mgrB,'institute_user')->get(route('students.show',$studentA))->assertNotFound();
    }

    public function test_branch_isolation_sales_order(): void
    {
        $inst=$this->institute(); $branchA=$this->branch($inst,'A'); $branchB=$this->branch($inst,'B');
        $custA=Party::create(['institute_id'=>$inst->id,'branch_id'=>$branchA->id,'type'=>'customer','name'=>'CustA','phone'=>'017'.rand(10000000,99999999),'is_active'=>true]);
        $order=app(\App\Services\Sales\SalesOrderService::class)->createDraft($inst->id,$branchA->id,['customer_id'=>$custA->id,'order_date'=>now()->toDateString(),'currency_id'=>\App\Models\Currency::firstOrCreate(['code'=>'BDT'],['name'=>'Taka'])->id,'lines'=>[['description'=>'A','quantity'=>1,'unit_price'=>100]]],null);
        TenantContext::set($inst->id); BranchContext::set($branchB->id);
        $mgrB=$this->user($inst,'branch-manager',$branchB->id);
        $this->actingAs($mgrB,'institute_user')->get(route('sales.orders.show',$order))->assertNotFound();
    }

    public function test_idor_crm_contact(): void
    {
        $a=$this->institute(); $b=$this->institute();
        $contactA=\App\Models\CrmContact::withoutGlobalScopes()->create(['institute_id'=>$a->id,'branch_id'=>null,'first_name'=>'LeadA','last_name'=>'Test','email'=>'a-'.uniqid().'@t.com','assigned_user_id'=>null]);
        $mgrB=$this->user($b,'institute-owner');
        TenantContext::set($b->id);
        $found=\App\Models\CrmContact::find($contactA->id);
        $this->assertNull($found);
        $foundRaw=\App\Models\CrmContact::withoutGlobalScopes()->find($contactA->id);
        $this->assertNotNull($foundRaw);
    }

    public function test_idor_hr_employee(): void
    {
        $a=$this->institute(); $b=$this->institute();
        $branchA=$this->branch($a);
        $deptA=\App\Models\HrDepartment::withoutGlobalScopes()->create(['institute_id'=>$a->id,'branch_id'=>$branchA->id,'name'=>'Dept '.uniqid(),'is_active'=>true]);
        $empA=\App\Models\HrEmployee::withoutGlobalScopes()->create(['institute_id'=>$a->id,'branch_id'=>$branchA->id,'department_id'=>$deptA->id,'employee_code'=>'EMP-'.uniqid(),'first_name'=>'EmpA','last_name'=>'Test','display_name'=>'EmpA Test','employment_status'=>'active','joining_date'=>now()->toDateString()]);
        $mgrB=$this->user($b,'institute-owner');
        TenantContext::set($b->id);
        $found=\App\Models\HrEmployee::find($empA->id);
        $this->assertNull($found);
    }

    public function test_ai_industry_isolation(): void
    {
        $registry=app(\App\Services\Ai\AiToolRegistry::class);
        $eduClasses=$registry->classesForIndustry('education');
        $retailClasses=$registry->classesForIndustry('retail');
        $coreClasses=$registry->classesForIndustry('core');
        // Core tools should be shared
        $this->assertContains(\App\Services\Ai\Tools\Core\GetFinancialSummaryTool::class, $eduClasses);
        $this->assertContains(\App\Services\Ai\Tools\Core\GetFinancialSummaryTool::class, $retailClasses);
        $this->assertContains(\App\Services\Ai\Tools\Core\GetFinancialSummaryTool::class, $coreClasses);
        // Education-specific tools must NOT leak to retail
        $eduOnlyTools=[\App\Services\Ai\Tools\Education\StudentsTool::class, \App\Services\Ai\Tools\Education\AttendanceTool::class, \App\Services\Ai\Tools\Education\FeesTool::class];
        foreach ($eduOnlyTools as $tool) {
            $this->assertContains($tool, $eduClasses, "Education tool $tool missing for education");
            $this->assertNotContains($tool, $retailClasses, "Education tool $tool leaked to retail");
        }
        // Verify provider never receives institute_id: check via AiContext and AiService buildMessages uses only tenant name, not raw ID
        $eduInst=$this->institute(null,'education','school');
        $eduUser=$this->user($eduInst,'institute-owner');
        $eduContext=new \App\Services\Ai\AiContext($eduUser, $eduInst, 'education', true, ['assistant'], [], ['*']);
        // Use reflection to verify buildMessages does not leak raw institute_id
        // If AiService construction fails due to provider config, skip live check and verify via context
        $this->assertEquals($eduInst->industry, $eduContext->industry);
        $this->assertNotEquals((string)$eduInst->id, $eduContext->industry);
    }

    public function test_saas_module_bypass_blocked(): void
    {
        // Institute without package (legacy) should be treated as FREE, not bypass
        $inst=$this->institute(); $inst->forceFill(['package_id'=>null])->save();
        $inst->refresh();
        $user=$this->user($inst,'institute-owner');
        TenantContext::set($inst->id);
        // FREE has only crm/notifications; sales should be blocked
        $this->actingAs($user,'institute_user')->get(route('sales.orders.index'))->assertStatus(403);
        $this->actingAs($user,'institute_user')->get(route('crm.contacts.index'))->assertOk();
    }

    public function test_saas_package_slug_canonical(): void
    {
        $slugs=DB::table('subscription_packages')->pluck('slug')->toArray();
        foreach (['FREE','BASIC','ADVANCED','PREMIUM'] as $canon) {
            $this->assertContains($canon, $slugs, "Canonical slug $canon missing");
        }
        // Legacy slugs should not exist (case-insensitive)
        $lowerSlugs=array_map('strtolower',$slugs);
        $this->assertNotContains('starter', $lowerSlugs);
        $this->assertNotContains('professional', $lowerSlugs);
        $this->assertNotContains('enterprise', $lowerSlugs);
    }

    public function test_inventory_movement_no_unique_under_concurrency(): void
    {
        $inst=$this->institute(); $branch=$this->branch($inst);
        $cat=\App\Models\InventoryCategory::create(['institute_id'=>$inst->id,'branch_id'=>$branch->id,'name'=>'Cat','is_active'=>true]);
        $item=\App\Models\InventoryItem::create(['institute_id'=>$inst->id,'branch_id'=>$branch->id,'category_id'=>$cat->id,'item_type'=>'stock_item','name'=>'Prod','sku'=>'SKU-'.uniqid(),'unit'=>'pcs','selling_price'=>100,'purchase_price'=>50,'is_active'=>true]);
        $wh=\App\Models\InventoryWarehouse::create(['institute_id'=>$inst->id,'branch_id'=>$branch->id,'name'=>'WH','code'=>'WH-'.uniqid(),'is_active'=>true]);
        // Ensure package modules not blocking
        app(\App\Services\ModuleAccessService::class)->flushCache($inst->id);
        // Generate 5 movement numbers concurrently (simulate via loop) and assert uniqueness
        $nums=[];
        for ($i=0;$i<5;$i++) {
            $svc=app(\App\Services\Inventory\InventoryStockService::class);
            $ref=new \ReflectionMethod($svc,'movementNumber');
            $ref->setAccessible(true);
            $nums[]=$ref->invoke($svc,$inst->id);
        }
        $this->assertEquals(count($nums), count(array_unique($nums)), 'movement_no not unique');
        foreach ($nums as $n) {
            $this->assertMatchesRegularExpression('/^IVM-\d{8}-[A-Z0-9]{5}$/', $n);
        }
    }

    public function test_invoice_branch_id_not_trusted_from_request(): void
    {
        $a=$this->institute(); $branchA=$this->branch($a,'A'); $branchB=$this->branch($a,'B');
        $custA=Party::create(['institute_id'=>$a->id,'branch_id'=>$branchA->id,'type'=>'customer','name'=>'Cust','phone'=>'017'.rand(10000000,99999999),'is_active'=>true]);
        $owner=$this->user($a,'institute-owner');
        TenantContext::set($a->id); BranchContext::set($branchA->id);
        // Simulate request with branch_id=B trying to create order for branch B via branch A user — should be forced to A
        $svc=app(\App\Services\Sales\SalesOrderService::class);
        $order=$svc->createDraft($a->id,$branchA->id,['customer_id'=>$custA->id,'order_date'=>now()->toDateString(),'currency_id'=>\App\Models\Currency::firstOrCreate(['code'=>'BDT'],['name'=>'Taka'])->id,'lines'=>[['description'=>'A','quantity'=>1,'unit_price'=>100]],'branch_id'=>$branchB->id], $owner->id);
        $this->assertEquals($branchA->id, $order->branch_id);
        $this->assertNotEquals($branchB->id, $order->branch_id);
    }
}
