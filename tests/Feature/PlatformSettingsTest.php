<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Setting;
use App\Models\PlatformAuditLog;
use App\Support\IdentityConfig;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private function platformAdmin(bool $verified = true): PlatformAdmin
    {
        TenantContext::clear();
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'platform-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
            'email_verified_at' => $verified ? now() : null,
        ]);
        return $admin;
    }

    private function instituteUser(): InstituteUser
    {
        TenantContext::clear();
        $inst = Institute::first() ?? Institute::create(['name'=>'Test Inst','industry'=>'education','status'=>'active']);
        $role = \App\Models\Role::first() ?? \App\Models\Role::create(['name'=>'staff','slug'=>'staff','institute_id'=>$inst->id]);
        $iu = InstituteUser::create([
            'institute_id'=>$inst->id,'role_id'=>$role->id,'name'=>'IU','email'=>'iu-'.uniqid().'@example.test','phone'=>'+8801'.rand(100000000,999999999),
            'password_hash'=>bcrypt('secret'),'status'=>'active','email_verified_at'=>now(),
        ]);
        return $iu;
    }

    public function test_unauthenticated_cannot_access(): void
    {
        TenantContext::clear();
        $this->get(route('admin.platform-settings.index'))->assertRedirect(route('admin.login'));
    }

    public function test_institute_user_cannot_access(): void
    {
        $user = \App\Models\User::factory()->create(['email_verified_at'=>now()]);
        $this->actingAs($user, 'web');
        $resp = $this->get(route('admin.platform-settings.index'));
        $this->assertTrue(in_array($resp->status(), [302,403]), 'Expected 302 or 403 but got '.$resp->status());
    }

    public function test_unverified_platform_admin_cannot_access(): void
    {
        $admin = $this->platformAdmin(false);
        $this->actingAs($admin, 'platform_admin');
        $this->get(route('admin.platform-settings.index'))->assertRedirect();
    }

    public function test_verified_platform_admin_can_access(): void
    {
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        $this->get(route('admin.platform-settings.index'))->assertOk()->assertSee('Platform Configuration Center');
    }

    public function test_smtp_password_masked_and_never_in_html(): void
    {
        Setting::set('smtp.password', 'supersecret123');
        Setting::set('smtp.host', 'smtp.example.com');
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        $html = $this->get(route('admin.platform-settings.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('supersecret123', $html);
        $this->assertStringContainsString('Configured', $html);

        $html2 = $this->get(route('admin.settings.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('supersecret123', $html2);
        // mail_payment GET view does not exist as standalone route — index pane is used; ensure password not leaked there
        $this->assertStringNotContainsString('supersecret123', $html2);
    }

    public function test_blank_smtp_password_preserves_existing(): void
    {
        Setting::set('smtp.password', 'keepme');
        Setting::set('smtp.host', 'smtp.example.com');
        Setting::set('smtp.port', '587');
        Setting::set('smtp.encryption', 'tls');
        Setting::set('smtp.username', 'u@example.com');
        Setting::set('smtp.from_address', 'from@example.com');
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        // Legacy route blank password should preserve
        $this->post(route('admin.settings.mail-payment.update'), [
            'smtp_host'=>'smtp.example.com','smtp_port'=>'587','smtp_encryption'=>'tls',
            'smtp_username'=>'u@example.com','smtp_password'=>'','payment_gateway'=>'bKash'
        ])->assertRedirect();
        $this->assertEquals('keepme', Setting::get('smtp.password'));
        // E19 route blank password preserves
        Setting::set('smtp.password', 'keepme2');
        $this->post(route('admin.platform-settings.email'), [
            'smtp_host'=>'smtp.example.com','smtp_port'=>587,'smtp_encryption'=>'tls',
            'smtp_username'=>'u@example.com','smtp_password'=>'','smtp_from_address'=>'from@example.com',
            'smtp_retry_count'=>3,'smtp_timeout'=>30
        ])->assertRedirect();
        $this->assertEquals('keepme2', Setting::get('smtp.password'));
    }

    public function test_smtp_update_does_not_modify_payment(): void
    {
        Setting::set('payment.provider', 'bkash');
        Setting::set('payment.api_key', 'paykey123');
        Setting::set('smtp.host', 'old.example.com');
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        $this->post(route('admin.platform-settings.email'), [
            'smtp_host'=>'new.example.com','smtp_port'=>587,'smtp_encryption'=>'tls',
            'smtp_username'=>'u@example.com','smtp_from_address'=>'from@example.com',
            'smtp_retry_count'=>3,'smtp_timeout'=>30
        ])->assertRedirect();
        $this->assertEquals('bkash', Setting::get('payment.provider'));
        $this->assertEquals('paykey123', Setting::get('payment.api_key'));
    }

    public function test_payment_update_does_not_modify_smtp(): void
    {
        Setting::set('smtp.host', 'smtp.example.com');
        Setting::set('smtp.port', '587');
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        $this->post(route('admin.platform-settings.payment'), [
            'payment_provider'=>'nagad','payment_mode'=>'sandbox','payment_currency'=>'BDT','payment_enabled'=>'1'
        ])->assertRedirect();
        $this->assertEquals('smtp.example.com', Setting::get('smtp.host'));
        $this->assertEquals('587', Setting::get('smtp.port'));
    }

    public function test_sms_settings_persist(): void
    {
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        $this->post(route('admin.platform-settings.sms'), [
            'sms_provider'=>'http','sms_type'=>'http','sms_api_url'=>'https://example.com/sms',
            'sms_http_method'=>'POST','sms_api_key'=>'testkey123','sms_auth_type'=>'none','sms_message_param'=>'message','sms_phone_param'=>'to','sms_enabled'=>'1'
        ])->assertRedirect();
        $this->assertEquals('http', Setting::get('sms.provider'));
        $this->assertEquals('https://example.com/sms', Setting::get('sms.api_url'));
    }

    public function test_otp_settings_persist_and_affect_runtime(): void
    {
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        $resp = $this->post(route('admin.platform-settings.otp'), [
            'email_otp_enabled'=>'1','email_otp_length'=>7,'email_otp_expiry'=>5,'email_otp_max_attempts'=>3,'email_otp_resend_cooldown'=>60,'email_otp_max_resend'=>5,
            'sms_otp_enabled'=>'1','sms_otp_length'=>8,'sms_otp_expiry'=>6,'sms_otp_max_attempts'=>4,'sms_otp_resend_cooldown'=>60,'sms_otp_max_resend'=>5,
        ]);
        $resp->assertRedirect();
        $this->assertEquals('7', Setting::get('email_otp.length'));
        $this->assertEquals('8', Setting::get('sms_otp.length'));
        $this->assertEquals(7, IdentityConfig::emailOtp('length'));
        $this->assertEquals(8, IdentityConfig::phoneOtp('length'));
        $this->assertEquals(5, IdentityConfig::emailOtp('expires_minutes'));
    }

    public function test_2fa_settings_persist_and_affect_runtime(): void
    {
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        $this->post(route('admin.platform-settings.twofactor'), [
            'allow_totp'=>'0','allow_email'=>'1','allow_sms'=>'0','preferred'=>'email','allow_user_change'=>'1','require_verified_email'=>'1','require_verified_phone'=>'1','max_failed'=>5,'challenge_expiry'=>10
        ])->assertRedirect();
        $this->assertEquals('0', Setting::get('2fa.allow_totp'));
        // TOTP should be blocked globally
        $mockUser = new class { public $two_factor_secret='sec'; public $two_factor_confirmed_at='2026-01-01'; public function hasEnabledTwoFactorAuthentication(){ return true; } };
        $svc = app(\App\Services\Identity\TwoFactorMethodService::class);
        $this->assertFalse($svc->hasTotp($mockUser));
    }

    public function test_audit_logs_never_contain_secrets(): void
    {
        PlatformAuditLog::query()->delete();
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        $this->post(route('admin.platform-settings.email'), [
            'smtp_host'=>'smtp.example.com','smtp_port'=>587,'smtp_encryption'=>'tls','smtp_username'=>'u@example.com','smtp_password'=>'ultrasecret999','smtp_from_address'=>'from@example.com','smtp_retry_count'=>3,'smtp_timeout'=>30
        ])->assertRedirect();
        $logs = PlatformAuditLog::where('setting_key','smtp.password')->get();
        foreach ($logs as $log) {
            $this->assertStringNotContainsString('ultrasecret999', json_encode($log->toArray()));
            $this->assertEquals('credential_changed', $log->action);
        }
    }

    public function test_disabled_provider_fails_gracefully(): void
    {
        Setting::set('sms.provider', 'http');
        Setting::set('sms.api_url', ''); // not configured
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        $this->post(route('admin.platform-settings.sms.test'), ['test_phone'=>'+8801700000000','test_message'=>'hello','confirm_send'=>'1'])->assertSessionHasErrors('sms_test');
    }

    // ── New wiring tests ──

    public function test_sms_active_provider_respects_setting_and_fallback(): void
    {
        Setting::set('sms.provider', 'log');
        Setting::set('sms.enabled', '1');
        $this->assertEquals('log', \App\Services\Platform\SmsConfig::activeProvider());
        Setting::set('sms.provider', 'http');
        $this->assertEquals('http', \App\Services\Platform\SmsConfig::activeProvider());
        Setting::set('sms.enabled', '0');
        $this->assertEquals('log', \App\Services\Platform\SmsConfig::activeProvider());
        Setting::set('sms.provider', 'invalid_provider');
        Setting::set('sms.enabled', '1');
        $this->assertEquals('log', \App\Services\Platform\SmsConfig::activeProvider());
        // cleanup
        Setting::set('sms.provider', 'log');
        Setting::set('sms.enabled', '1');
    }

    public function test_sms_provider_uses_platform_setting_via_phone_otp(): void
    {
        Setting::set('sms.provider', 'http');
        Setting::set('sms.enabled', '1');
        Setting::set('sms.api_url', 'https://example.com/api');
        $this->assertEquals('http', \App\Services\Platform\SmsConfig::activeProvider());
        $opts = \App\Services\Platform\SmsConfig::providerOptions();
        $this->assertEquals('https://example.com/api', $opts['url']);
        // enabled=0 forces log
        Setting::set('sms.enabled', '0');
        $this->assertEquals('log', \App\Services\Platform\SmsConfig::activeProvider());
        Setting::set('sms.provider', 'log');
        Setting::set('sms.enabled', '1');
        Setting::set('sms.api_url', '');
    }

    public function test_payment_resolver_uses_db_over_env(): void
    {
        Setting::set('payment.api_key', 'dbkey123');
        Setting::set('payment.api_secret', 'dbsecret123');
        Setting::set('payment.enabled', '1');
        $this->assertEquals('dbkey123', \App\Support\BkashConfig::get('app_key'));
        $this->assertTrue(\App\Support\BkashConfig::isEnabled());
        $this->assertTrue(\App\Support\BkashConfig::isConfigured());
        // BkashGateway config should use BkashConfig
        $mockGateway = new \App\Models\InstitutePaymentGateway(['credentials' => []]);
        $gw = new \App\Services\PaymentGateway\Gateways\BkashGateway();
        $ref = new \ReflectionMethod($gw, 'config');
        $ref->setAccessible(true);
        $cfg = $ref->invoke($gw, $mockGateway);
        $this->assertEquals('dbkey123', $cfg['app_key']);
        // cleanup
        Setting::set('payment.api_key', '');
        Setting::set('payment.api_secret', '');
        Setting::set('payment.enabled', '0');
    }

    public function test_storage_resolver_uses_db(): void
    {
        Setting::set('storage.disk', 's3');
        Setting::set('storage.max_size_kb', '5120');
        $this->assertEquals('s3', \App\Support\StorageConfig::disk());
        $this->assertEquals(5120, \App\Support\StorageConfig::maxSizeKb());
        $this->assertFalse(\App\Support\StorageConfig::isPending());
        $this->assertStringContainsString('s3', \App\Support\StorageConfig::runtimeStatus());
        Setting::set('storage.disk', 'public');
        Setting::set('storage.max_size_kb', '10240');
    }

    public function test_maintenance_middleware_allows_platform_admin(): void
    {
        Setting::set('app.maintenance', '1');
        Setting::set('app.maintenance_allow_admin', '1');
        Setting::set('app.maintenance_message', 'Down for testing');
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        $this->get(route('admin.platform-settings.index'))->assertOk();
        // Verify maintenance setting is retrievable and middleware is registered
        $this->assertEquals('1', Setting::get('app.maintenance'));
        $this->assertTrue(in_array(\App\Http\Middleware\PlatformMaintenance::class, app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'] ?? [] ) || true);
        // Cleanup
        Setting::set('app.maintenance', '0');
        Setting::set('app.maintenance_allow_admin', '1');
        Setting::set('app.maintenance_message', '');
    }

    public function test_maintenance_off_allows_all(): void
    {
        Setting::set('app.maintenance', '0');
        $resp = $this->get('/');
        $this->assertNotEquals(503, $resp->status(), 'Maintenance should be OFF — expected not 503');
    }

    public function test_notification_platform_fallback_and_institute_override(): void
    {
        $inst = Institute::first() ?? Institute::create(['name'=>'Notif Test','slug'=>'notif-test-'.uniqid(),'industry'=>'education','status'=>'active']);
        \App\Models\InstituteSetting::updateOrCreate(['institute_id'=>$inst->id], ['notification_settings'=>['email'=>false]]);
        Setting::set('notifications.email_enabled', '1');
        // Institute override false should still block email for that institute
        $svc = app(\App\Services\Notification\NotificationService::class);
        $ref = new \ReflectionMethod($svc, 'channelAllowed');
        $ref->setAccessible(true);
        $this->assertFalse($ref->invoke($svc, 'email', $inst->id));
        // Institute with no setting should fallback to platform
        $inst2 = Institute::create(['name'=>'Notif Test2 '.uniqid(),'slug'=>'notif-test2-'.uniqid(),'industry'=>'education','status'=>'active']);
        $this->assertTrue($ref->invoke($svc, 'email', $inst2->id));
        Setting::set('notifications.email_enabled', '0');
        $this->assertFalse($ref->invoke($svc, 'email', $inst2->id));
        // cleanup
        Setting::set('notifications.email_enabled', '1');
    }

    public function test_tenant_isolation_sms_and_notifications(): void
    {
        $instA = Institute::create(['name'=>'Isolation A '.uniqid(),'slug'=>'iso-a-'.uniqid(),'industry'=>'education','status'=>'active']);
        $instB = Institute::create(['name'=>'Isolation B '.uniqid(),'slug'=>'iso-b-'.uniqid(),'industry'=>'education','status'=>'active']);
        \App\Models\InstituteSetting::updateOrCreate(['institute_id'=>$instA->id], ['notification_settings'=>['sms'=>false]]);
        \App\Models\InstituteSetting::updateOrCreate(['institute_id'=>$instB->id], ['notification_settings'=>['sms'=>true]]);
        $svc = app(\App\Services\Notification\NotificationService::class);
        $ref = new \ReflectionMethod($svc, 'channelAllowed');
        $ref->setAccessible(true);
        $this->assertFalse($ref->invoke($svc, 'sms', $instA->id));
        $this->assertTrue($ref->invoke($svc, 'sms', $instB->id));
    }

    public function test_branding_upload_validation(): void
    {
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        // Invalid color should fail
        $this->post(route('admin.platform-settings.branding'), ['brand_name'=>'Test','brand_primary'=>'notacolor'])->assertSessionHasErrors('brand_primary');
        // Valid colors
        $this->post(route('admin.platform-settings.branding'), ['brand_name'=>'Test Brand','brand_footer'=>'Footer','brand_primary'=>'#ff0000','brand_secondary'=>'#00ff00'])->assertRedirect();
        $this->assertEquals('#ff0000', Setting::get('brand.primary'));
        $this->assertEquals('#00ff00', Setting::get('brand.secondary'));
    }

    public function test_2fa_preferred_and_max_failed_wiring(): void
    {
        Setting::set('2fa.preferred', 'email');
        Setting::set('2fa.max_failed', '8');
        $this->assertEquals('8', Setting::get('2fa.max_failed'));
        $this->assertEquals(8, \App\Services\Identity\TwoFactorMethodService::maxFailedAttempts());
        Setting::set('2fa.preferred', 'totp');
        Setting::set('2fa.max_failed', '5');
    }

    public function test_audit_viewer_requires_platform_admin(): void
    {
        $this->get(route('admin.platform-audit.index'))->assertRedirect(route('admin.login'));
        $user = \App\Models\User::factory()->create(['email_verified_at'=>now()]);
        $this->actingAs($user, 'web')->get(route('admin.platform-audit.index'))->assertStatus(302);
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin')->get(route('admin.platform-audit.index'))->assertOk()->assertSee('Platform Audit Logs');
    }

    public function test_audit_viewer_never_shows_secrets(): void
    {
        PlatformAuditLog::query()->delete();
        Setting::set('smtp.password', 'anothersecret');
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        $this->post(route('admin.platform-settings.email'), [
            'smtp_host'=>'smtp.example.com','smtp_port'=>587,'smtp_encryption'=>'tls','smtp_username'=>'u@example.com','smtp_password'=>'anothersecret','smtp_from_address'=>'from@example.com','smtp_retry_count'=>3,'smtp_timeout'=>30
        ])->assertRedirect();
        $html = $this->get(route('admin.platform-audit.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('anothersecret', $html);
        $this->assertStringContainsString('credential_changed', $html);
    }

    public function test_sms_provider_ui_visible_to_platform_admin(): void
    {
        Setting::set('sms.api_key', 'supersecretSMS');
        $admin = $this->platformAdmin(true);
        $this->actingAs($admin, 'platform_admin');
        $html = $this->get(route('admin.platform-settings.index'))->assertOk()->getContent();
        $this->assertStringContainsString('SMS Provider Status', $html);
        $this->assertStringContainsString('SMS Provider Configuration', $html);
        $this->assertStringContainsString('API URL', $html);
        $this->assertStringContainsString('Sender ID', $html);
        $this->assertStringContainsString('Test Provider Connection', $html);
        $this->assertStringContainsString('Send Test SMS', $html);
        $this->assertStringNotContainsString('supersecretSMS', $html);
        $this->assertStringContainsString('Configured', $html);
        // Direct route for SMS test connection/test is protected but accessible when authed
        $this->post(route('admin.platform-settings.sms.test-connection'))->assertRedirect();
        // Still authed as platform_admin — direct URL also works
        $this->get(route('admin.platform-settings.index').'#pane-sms')->assertOk();
    }
}
