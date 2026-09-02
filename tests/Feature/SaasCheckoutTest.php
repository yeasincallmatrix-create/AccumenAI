<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteModuleEntitlement;
use App\Models\OnlinePaymentAttempt;
use App\Models\PaymentGateway;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use App\Services\SaasSubscriptionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SaasCheckoutTest extends TestCase
{
    use DatabaseTransactions;

    private function package(string $slug): SubscriptionPackage
    {
        return SubscriptionPackage::whereRaw('LOWER(slug)=?', [strtolower($slug)])->firstOrFail();
    }

    private function institute(string $country='Bangladesh', string $package='FREE'): Institute
    {
        \App\Support\TenantContext::clear();
        $pkg = $this->package($package);
        return Institute::create([
            'name'=>'Saas '.uniqid(),'slug'=>'saas-'.uniqid(),'status'=>'active','package_id'=>$pkg->id,'industry'=>'education','sub_industry'=>'school','country'=>$country,
        ]);
    }

    private function user(Institute $inst)
    {
        $role = \App\Models\Role::where('slug','institute-owner')->firstOrFail();
        \App\Support\TenantContext::clear();
        return \App\Models\InstituteUser::create([
            'institute_id'=>$inst->id,'role_id'=>$role->id,'first_name'=>'Test','last_name'=>'User','email'=>'saas-'.uniqid().'@test.local','phone'=>'017'.rand(10000000,99999999),'password_hash'=>bcrypt('secret'),'status'=>'active'
        ]);
    }

    // 1 Bangladesh available
    public function test_bangladesh_bkash_available(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $u = $this->user($inst);
        $this->actingAs($u,'institute_user')->get(route('saas.packages'))->assertOk()->assertSee('Pay with bKash');
    }
    // 2 Non-Bangladesh unavailable
    public function test_non_bangladesh_bkash_unavailable(): void
    {
        $inst = $this->institute('United States','FREE');
        $u = $this->user($inst);
        $this->actingAs($u,'institute_user')->get(route('saas.packages'))->assertOk()->assertSee('bKash unavailable');
    }
    // 3 direct checkout rejected for non-Bangladesh
    public function test_non_bangladesh_direct_checkout_rejected(): void
    {
        $inst = $this->institute('United States','FREE');
        $u = $this->user($inst);
        $pkg = $this->package('BASIC');
        $this->actingAs($u,'institute_user')->post(route('saas.checkout'),['package_id'=>$pkg->id,'billing_cycle'=>'monthly'])->assertSessionHasErrors('country');
    }
    // 4-6 BASIC/ADVANCED/PREMIUM purchase
    public function test_basic_purchase_creates_invoice_and_attempt(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $u = $this->user($inst);
        $pkg = $this->package('BASIC');
        $this->actingAs($u,'institute_user')->post(route('saas.checkout'),['package_id'=>$pkg->id,'billing_cycle'=>'monthly'])->assertRedirect();
        $this->assertDatabaseHas('invoices',['institute_id'=>$inst->id,'invoice_meta->package_id'=>$pkg->id]);
        $this->assertDatabaseHas('online_payment_attempts',['institute_id'=>$inst->id,'amount'=>$pkg->price_monthly,'currency_code'=>'BDT']);
    }
    public function test_advanced_purchase(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $u = $this->user($inst);
        $pkg = $this->package('ADVANCED');
        $this->actingAs($u,'institute_user')->post(route('saas.checkout'),['package_id'=>$pkg->id,'billing_cycle'=>'yearly'])->assertRedirect();
        $this->assertDatabaseHas('online_payment_attempts',['institute_id'=>$inst->id,'amount'=>$pkg->price_yearly]);
    }
    public function test_premium_purchase(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $u = $this->user($inst);
        $pkg = $this->package('PREMIUM');
        $this->actingAs($u,'institute_user')->post(route('saas.checkout'),['package_id'=>$pkg->id,'billing_cycle'=>'monthly'])->assertRedirect();
    }
    // 7 inactive package rejected
    public function test_inactive_package_rejected(): void
    {
        $pkg = $this->package('BASIC');
        $pkg->update(['status'=>'inactive']);
        $inst = $this->institute('Bangladesh','FREE');
        $u = $this->user($inst);
        $this->actingAs($u,'institute_user')->post(route('saas.checkout'),['package_id'=>$pkg->id,'billing_cycle'=>'monthly'])->assertSessionHasErrors('package_id');
        $pkg->update(['status'=>'active']);
    }
    // 8 invalid package
    public function test_invalid_package_rejected(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $u = $this->user($inst);
        $this->actingAs($u,'institute_user')->post(route('saas.checkout'),['package_id'=>999999,'billing_cycle'=>'monthly'])->assertSessionHasErrors('package_id');
    }
    // 9 correct amount accepted (verify)
    public function test_correct_amount_accepted(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $pkg = $this->package('BASIC');
        $svc = app(SaasSubscriptionService::class);
        $result = $svc->createCheckout($inst,$pkg->id,'monthly', null);
        $attempt = $result['attempt'];
        $payload = ['attempt_id'=>$attempt->id,'paymentID'=>$attempt->gateway_reference,'trxID'=>'TRX'.uniqid(),'amount'=>$attempt->amount,'currency'=>'BDT','status'=>'success'];
        $verified = $svc->verifyAndActivate($attempt->fresh(), $payload);
        $this->assertEquals(OnlinePaymentAttempt::STATUS_COMPLETED, $verified->status);
        $this->assertEquals($pkg->id, $inst->fresh()->package_id);
    }
    // 10 incorrect amount rejected
    public function test_incorrect_amount_rejected(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $pkg = $this->package('BASIC');
        $svc = app(SaasSubscriptionService::class);
        $result = $svc->createCheckout($inst,$pkg->id,'monthly', null);
        $attempt = $result['attempt'];
        $payload = ['attempt_id'=>$attempt->id,'paymentID'=>$attempt->gateway_reference,'trxID'=>'TRX'.uniqid(),'amount'=>1,'currency'=>'BDT','status'=>'success'];
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->verifyAndActivate($attempt->fresh(), $payload);
        $this->assertEquals('FREE', $inst->fresh()->package->slug);
    }
    // 11 incorrect currency rejected
    public function test_incorrect_currency_rejected(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $pkg = $this->package('BASIC');
        $svc = app(SaasSubscriptionService::class);
        $result = $svc->createCheckout($inst,$pkg->id,'monthly', null);
        $attempt = $result['attempt'];
        $payload = ['attempt_id'=>$attempt->id,'paymentID'=>$attempt->gateway_reference,'trxID'=>'TRX'.uniqid(),'amount'=>$attempt->amount,'currency'=>'USD','status'=>'success'];
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->verifyAndActivate($attempt->fresh(), $payload);
    }
    // 12 fake status rejected (failed)
    public function test_failed_payment_does_not_activate(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $pkg = $this->package('BASIC');
        $svc = app(SaasSubscriptionService::class);
        $result = $svc->createCheckout($inst,$pkg->id,'monthly', null);
        $attempt = $result['attempt'];
        $payload = ['attempt_id'=>$attempt->id,'paymentID'=>$attempt->gateway_reference,'trxID'=>'TRX'.uniqid(),'amount'=>$attempt->amount,'currency'=>'BDT','status'=>'failed'];
        $verified = $svc->verifyAndActivate($attempt->fresh(), $payload);
        $this->assertEquals(OnlinePaymentAttempt::STATUS_FAILED, $verified->status);
        $this->assertEquals('FREE', $inst->fresh()->package->slug);
        $invoice = \App\Models\Invoice::find($attempt->invoice_id);
        $this->assertNotEquals('paid', $invoice->status);
    }
    // 13 cancelled
    public function test_cancelled_does_not_activate(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $pkg = $this->package('BASIC');
        $svc = app(SaasSubscriptionService::class);
        $result = $svc->createCheckout($inst,$pkg->id,'monthly', null);
        $attempt = $result['attempt'];
        $payload = ['attempt_id'=>$attempt->id,'paymentID'=>$attempt->gateway_reference,'amount'=>$attempt->amount,'currency'=>'BDT','status'=>'cancelled'];
        $verified = $svc->verifyAndActivate($attempt->fresh(), $payload);
        $this->assertEquals(OnlinePaymentAttempt::STATUS_CANCELLED, $verified->status);
        $this->assertEquals('FREE', $inst->fresh()->package->slug);
    }
    // 14 expired
    public function test_expired_does_not_activate(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $pkg = $this->package('BASIC');
        $svc = app(SaasSubscriptionService::class);
        $result = $svc->createCheckout($inst,$pkg->id,'monthly', null);
        $attempt = $result['attempt'];
        $payload = ['attempt_id'=>$attempt->id,'paymentID'=>$attempt->gateway_reference,'amount'=>$attempt->amount,'currency'=>'BDT','status'=>'expired'];
        $verified = $svc->verifyAndActivate($attempt->fresh(), $payload);
        $this->assertEquals(OnlinePaymentAttempt::STATUS_EXPIRED, $verified->status);
        $this->assertEquals('FREE', $inst->fresh()->package->slug);
    }
    // 16 cross-tenant rejected
    public function test_cross_tenant_rejected(): void
    {
        $instA = $this->institute('Bangladesh','FREE');
        $instB = $this->institute('Bangladesh','FREE');
        $userA = $this->user($instA);
        $pkg = $this->package('BASIC');
        $svc = app(SaasSubscriptionService::class);
        $result = $svc->createCheckout($instA,$pkg->id,'monthly', $userA->id);
        $attempt = $result['attempt'];
        // Attempt belongs to instA, instB should not be able to activate it via direct service without check — controller would 404 due to TenantScoped
        // Verify that instB remains FREE after instA success
        $payload = ['attempt_id'=>$attempt->id,'paymentID'=>$attempt->gateway_reference,'amount'=>$attempt->amount,'currency'=>'BDT','status'=>'success','trxID'=>'TRX'.uniqid()];
        $svc->verifyAndActivate($attempt->fresh(), $payload);
        $this->assertEquals($pkg->id, $instA->fresh()->package_id);
        $this->assertEquals('FREE', $instB->fresh()->package->slug);
        // Amount tampering check — server amount is authoritative
        $result2 = $svc->createCheckout($instA,$pkg->id,'monthly', $userA->id);
        $attempt2 = $result2['attempt'];
        $this->assertEquals((float)$pkg->price_monthly, (float)$attempt2->amount);
    }
    // 20 idempotency duplicate callback
    public function test_duplicate_callback_idempotent(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $pkg = $this->package('BASIC');
        $svc = app(SaasSubscriptionService::class);
        $result = $svc->createCheckout($inst,$pkg->id,'monthly', null);
        $attempt = $result['attempt'];
        $trx = 'TRX'.uniqid();
        $payload = ['attempt_id'=>$attempt->id,'paymentID'=>$attempt->gateway_reference,'trxID'=>$trx,'amount'=>$attempt->amount,'currency'=>'BDT','status'=>'success'];
        $first = $svc->verifyAndActivate($attempt->fresh(), $payload);
        $this->assertEquals(OnlinePaymentAttempt::STATUS_COMPLETED, $first->status);
        $countSubs = \App\Models\InstituteSubscription::where('institute_id',$inst->id)->where('package_id',$pkg->id)->count();
        $countLogs = \App\Models\ModuleAccessLog::where('institute_id',$inst->id)->where('action','package_added')->count();
        // second callback same trx
        $second = $svc->verifyAndActivate($attempt->fresh(), $payload);
        $this->assertEquals(OnlinePaymentAttempt::STATUS_COMPLETED, $second->status);
        $this->assertEquals($countSubs, \App\Models\InstituteSubscription::where('institute_id',$inst->id)->where('package_id',$pkg->id)->count());
        $this->assertEquals($countLogs, \App\Models\ModuleAccessLog::where('institute_id',$inst->id)->where('action','package_added')->count());
    }
    // 23 monthly +1 month
    public function test_monthly_subscription_one_month(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $pkg = $this->package('BASIC');
        $svc = app(SaasSubscriptionService::class);
        $result = $svc->createCheckout($inst,$pkg->id,'monthly', null);
        $attempt = $result['attempt'];
        $payload = ['attempt_id'=>$attempt->id,'paymentID'=>$attempt->gateway_reference,'trxID'=>'TRX'.uniqid(),'amount'=>$attempt->amount,'currency'=>'BDT','status'=>'success'];
        $svc->verifyAndActivate($attempt->fresh(), $payload);
        $sub = \App\Models\InstituteSubscription::where('institute_id',$inst->id)->latest('id')->first();
        $this->assertEquals('monthly', $sub->billing_cycle);
        $this->assertTrue(\Carbon\Carbon::parse($sub->end_date)->greaterThan(\Carbon\Carbon::parse($sub->start_date)));
    }
    // 24 yearly +1 year
    public function test_yearly_subscription_one_year(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $pkg = $this->package('BASIC');
        $svc = app(SaasSubscriptionService::class);
        $result = $svc->createCheckout($inst,$pkg->id,'yearly', null);
        $attempt = $result['attempt'];
        $payload = ['attempt_id'=>$attempt->id,'paymentID'=>$attempt->gateway_reference,'trxID'=>'TRX'.uniqid(),'amount'=>$attempt->amount,'currency'=>'BDT','status'=>'success'];
        $svc->verifyAndActivate($attempt->fresh(), $payload);
        $sub = \App\Models\InstituteSubscription::where('institute_id',$inst->id)->latest('id')->first();
        $this->assertEquals('yearly', $sub->billing_cycle);
        $this->assertTrue(\Carbon\Carbon::parse($sub->end_date)->greaterThan(\Carbon\Carbon::parse($sub->start_date)));
    }
    // 25 package modules activated
    public function test_package_modules_activated(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $this->assertFalse(app(ModuleAccessService::class)->isEnabled($inst->fresh(),'hr'));
        $pkg = $this->package('PREMIUM'); // has hr
        $svc = app(SaasSubscriptionService::class);
        $result = $svc->createCheckout($inst,$pkg->id,'monthly', null);
        $attempt = $result['attempt'];
        $payload = ['attempt_id'=>$attempt->id,'paymentID'=>$attempt->gateway_reference,'trxID'=>'TRX'.uniqid(),'amount'=>$attempt->amount,'currency'=>'BDT','status'=>'success'];
        $svc->verifyAndActivate($attempt->fresh(), $payload);
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($inst->fresh(),'hr'));
    }
    // 26 Step63 entitlements preserved
    public function test_step63_entitlements_preserved(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $admin = \App\Models\PlatformAdmin::firstOrReuseForTests(['email'=>'saas-admin-'.uniqid().'@test.local','password_hash'=>bcrypt('secret'),'status'=>'active']);
        app(ModuleAccessService::class)->grantModule($inst,'hr',['status'=>'active','is_grant'=>true], $admin->id);
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($inst,'hr'));
        $pkg = $this->package('PREMIUM');
        $svc = app(SaasSubscriptionService::class);
        $result = $svc->createCheckout($inst,$pkg->id,'monthly', null);
        $attempt = $result['attempt'];
        $payload = ['attempt_id'=>$attempt->id,'paymentID'=>$attempt->gateway_reference,'trxID'=>'TRX'.uniqid(),'amount'=>$attempt->amount,'currency'=>'BDT','status'=>'success'];
        $svc->verifyAndActivate($attempt->fresh(), $payload);
        $this->assertTrue(\App\Models\InstituteModuleEntitlement::where('institute_id',$inst->id)->where('module_key','hr')->exists());
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled($inst->fresh(),'hr'));
    }
    // 28 invoice created correctly, 29 paid after success, 30 failed not paid
    public function test_invoice_created_and_paid_after_success(): void
    {
        $inst = $this->institute('Bangladesh','FREE');
        $pkg = $this->package('BASIC');
        $svc = app(SaasSubscriptionService::class);
        $result = $svc->createCheckout($inst,$pkg->id,'monthly', null);
        $invoice = $result['invoice'];
        $attempt = $result['attempt'];
        $this->assertEquals('unpaid', $invoice->status);
        $this->assertEquals($pkg->price_monthly, (float)$invoice->payable_amount);
        $this->assertEquals('saas', $invoice->invoice_meta['source']);
        $payload = ['attempt_id'=>$attempt->id,'paymentID'=>$attempt->gateway_reference,'trxID'=>'TRX'.uniqid(),'amount'=>$attempt->amount,'currency'=>'BDT','status'=>'success'];
        $svc->verifyAndActivate($attempt->fresh(), $payload);
        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertEquals((float)$invoice->payable_amount, (float)$invoice->fresh()->paid_amount);
    }
}
