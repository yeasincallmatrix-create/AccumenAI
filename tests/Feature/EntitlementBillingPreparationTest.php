<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EntitlementBillingPreparationTest extends TestCase
{
    use DatabaseTransactions;

    private function package(string $slug): SubscriptionPackage
    {
        return SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower($slug)])->firstOrFail();
    }

    private function institute(): Institute
    {
        \App\Support\TenantContext::clear();
        $pkg = $this->package('FREE');
        return Institute::create([
            'name' => 'BillPrep '.uniqid(),
            'slug' => 'bill-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => 'Bangladesh',
        ]);
    }

    private function admin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests(['email'=>'bill-admin-'.uniqid().'@test.local','password_hash'=>bcrypt('secret'),'status'=>'active']);
    }

    // 1. nullable price
    public function test_nullable_price(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();
        $ent = app(ModuleAccessService::class)->grantModule($inst,'hr',['status'=>'active','is_grant'=>true,'monthly_price'=>null,'yearly_price'=>null], $admin->id);
        $this->assertNull($ent->monthly_price);
        $this->assertNull($ent->yearly_price);
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($inst->fresh(),'hr'));
    }

    // 2. monthly pricing
    public function test_monthly_pricing(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();
        $this->actingAs($admin,'platform_admin')->post(route('admin.institutes.entitlements.store',$inst),[
            'module_key'=>'sales','is_grant'=>1,'status'=>'active','monthly_price'=>'29.99','billing_cycle'=>'monthly'
        ])->assertRedirect();
        $this->assertDatabaseHas('institute_module_entitlements',['institute_id'=>$inst->id,'module_key'=>'sales','monthly_price'=>'29.99','billing_cycle'=>'monthly']);
    }

    // 3. yearly pricing
    public function test_yearly_pricing(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();
        $this->actingAs($admin,'platform_admin')->post(route('admin.institutes.entitlements.store',$inst),[
            'module_key'=>'purchase','is_grant'=>1,'status'=>'active','yearly_price'=>'299.00','billing_cycle'=>'yearly'
        ])->assertRedirect();
        $this->assertDatabaseHas('institute_module_entitlements',['institute_id'=>$inst->id,'module_key'=>'purchase','yearly_price'=>'299.00','billing_cycle'=>'yearly']);
    }

    // 4. one-time pricing
    public function test_one_time_pricing(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();
        $this->actingAs($admin,'platform_admin')->post(route('admin.institutes.entitlements.store',$inst),[
            'module_key'=>'inventory','is_grant'=>1,'status'=>'active','monthly_price'=>'99.00','billing_cycle'=>'one_time'
        ])->assertRedirect();
        $this->assertDatabaseHas('institute_module_entitlements',['institute_id'=>$inst->id,'module_key'=>'inventory','billing_cycle'=>'one_time']);
    }

    // 5. discount validation
    public function test_discount_validation(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();
        $this->actingAs($admin,'platform_admin')->post(route('admin.institutes.entitlements.store',$inst),[
            'module_key'=>'hr','is_grant'=>1,'status'=>'active','discount_percent'=>'15.5'
        ])->assertRedirect();
        $this->assertDatabaseHas('institute_module_entitlements',['institute_id'=>$inst->id,'discount_percent'=>'15.50']);
        // discount >100 rejected
        $this->actingAs($admin,'platform_admin')->post(route('admin.institutes.entitlements.store',$inst),[
            'module_key'=>'hr','is_grant'=>1,'status'=>'active','discount_percent'=>'101'
        ])->assertSessionHasErrors('discount_percent');
    }

    // 6. auto_renew
    public function test_auto_renew(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();
        $this->actingAs($admin,'platform_admin')->post(route('admin.institutes.entitlements.store',$inst),[
            'module_key'=>'hr','is_grant'=>1,'status'=>'active','auto_renew'=>1
        ])->assertRedirect();
        $this->assertDatabaseHas('institute_module_entitlements',['institute_id'=>$inst->id,'auto_renew'=>1]);
        $this->actingAs($admin,'platform_admin')->post(route('admin.institutes.entitlements.store',$inst),[
            'module_key'=>'sales','is_grant'=>1,'status'=>'active','auto_renew'=>0
        ])->assertRedirect();
        $this->assertDatabaseHas('institute_module_entitlements',['institute_id'=>$inst->id,'module_key'=>'sales','auto_renew'=>0]);
    }

    // 7. invalid negative price rejected
    public function test_invalid_negative_price_rejected(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();
        $this->actingAs($admin,'platform_admin')->post(route('admin.institutes.entitlements.store',$inst),[
            'module_key'=>'hr','is_grant'=>1,'status'=>'active','monthly_price'=>-5
        ])->assertSessionHasErrors('monthly_price');
        $this->actingAs($admin,'platform_admin')->post(route('admin.institutes.entitlements.store',$inst),[
            'module_key'=>'hr','is_grant'=>1,'status'=>'active','yearly_price'=>-1
        ])->assertSessionHasErrors('yearly_price');
    }

    // 8. invalid billing cycle rejected
    public function test_invalid_billing_cycle_rejected(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();
        $this->actingAs($admin,'platform_admin')->post(route('admin.institutes.entitlements.store',$inst),[
            'module_key'=>'hr','is_grant'=>1,'status'=>'active','billing_cycle'=>'weekly'
        ])->assertSessionHasErrors('billing_cycle');
    }

    // 9. commercial metadata does not affect access incorrectly
    public function test_commercial_metadata_does_not_affect_access(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();
        // High price still grants access
        app(ModuleAccessService::class)->grantModule($inst,'hr',['status'=>'active','is_grant'=>true,'monthly_price'=>9999,'yearly_price'=>99999,'billing_cycle'=>'monthly','discount_percent'=>50], $admin->id);
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($inst->fresh(),'hr'));
        // Null price also grants
        $inst2 = $this->institute();
        app(ModuleAccessService::class)->grantModule($inst2,'sales',['status'=>'active','is_grant'=>true,'monthly_price'=>null], $admin->id);
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($inst2->fresh(),'sales'));
    }

    // 10. FREE + individually granted module still works without payment
    public function test_free_plus_granted_without_payment(): void
    {
        $inst = $this->institute(); // FREE
        $admin = $this->admin();
        // No price at all, still works
        $this->actingAs($admin,'platform_admin')->post(route('admin.institutes.entitlements.store',$inst),[
            'module_key'=>'hr','is_grant'=>1,'status'=>'active'
        ])->assertRedirect();
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($inst->fresh(),'hr'));
        // Simulate institute user access via middleware
        $role = \App\Models\Role::where('slug','institute-owner')->firstOrFail();
        \App\Support\TenantContext::clear();
        $user = \App\Models\InstituteUser::create(['institute_id'=>$inst->id,'role_id'=>$role->id,'first_name'=>'Test','last_name'=>'User','email'=>'billtest-'.uniqid().'@test.local','phone'=>'017'.rand(10000000,99999999),'password_hash'=>bcrypt('secret'),'status'=>'active']);
        $this->actingAs($user,'institute_user')->get(route('hr.reports.index'))->assertOk();
    }

    // 11. package entitlement still works
    public function test_package_entitlement_still_works(): void
    {
        $premium = $this->package('PREMIUM');
        \App\Support\TenantContext::clear();
        $inst = Institute::create(['name'=>'Pkg '.uniqid(),'slug'=>'pkg-'.uniqid(),'status'=>'active','package_id'=>$premium->id,'industry'=>'education','sub_industry'=>'school','country'=>'Bangladesh']);
        // PREMIUM has sales via package, no entitlement needed
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($inst,'sales'));
    }

    // 12. existing package logic remains unchanged
    public function test_existing_package_logic_unchanged(): void
    {
        $free = $this->package('FREE');
        $before = \App\Models\PackageModule::where('package_id',$free->id)->pluck('module_key')->sort()->values()->toArray();
        $inst = $this->institute();
        $admin = $this->admin();
        // Adding entitlement should not touch package_modules
        $this->actingAs($admin,'platform_admin')->post(route('admin.institutes.entitlements.store',$inst),[
            'module_key'=>'hr','is_grant'=>1,'status'=>'active','monthly_price'=>'10'
        ])->assertRedirect();
        $after = \App\Models\PackageModule::where('package_id',$free->id)->pluck('module_key')->sort()->values()->toArray();
        $this->assertEquals($before,$after);
        $this->assertEquals($free->id, $inst->fresh()->package_id);
    }

    // 13. unauthorized user cannot manage pricing metadata
    public function test_unauthorized_cannot_manage_pricing(): void
    {
        $inst = $this->institute();
        $role = \App\Models\Role::where('slug','institute-owner')->firstOrFail();
        \App\Support\TenantContext::clear();
        $user = \App\Models\InstituteUser::create(['institute_id'=>$inst->id,'role_id'=>$role->id,'first_name'=>'Test','last_name'=>'User','email'=>'bill-unauth-'.uniqid().'@test.local','phone'=>'017'.rand(10000000,99999999),'password_hash'=>bcrypt('secret'),'status'=>'active']);
        $before = \App\Models\InstituteModuleEntitlement::count();
        $this->actingAs($user,'institute_user')->post(route('admin.institutes.entitlements.store',$inst),[
            'module_key'=>'hr','is_grant'=>1,'status'=>'active','monthly_price'=>'100','yearly_price'=>'1000','billing_cycle'=>'monthly','discount_percent'=>'10'
        ]);
        $this->assertEquals($before, \App\Models\InstituteModuleEntitlement::count());
        $this->assertDatabaseMissing('institute_module_entitlements',['institute_id'=>$inst->id,'monthly_price'=>'100.00']);
    }
}
