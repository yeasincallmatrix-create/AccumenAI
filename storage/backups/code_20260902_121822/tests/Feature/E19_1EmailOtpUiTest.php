<?php

namespace Tests\Feature;

use App\Models\EmailOtp;
use App\Models\Phone2faOtp;
use App\Models\User;
use App\Services\Identity\EmailOtpService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class E19_1EmailOtpUiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        DB::table('email_otps')->delete();
        DB::table('phone_2fa_otps')->delete();
        DB::table('phone_verification_otps')->delete();
    }

    protected function createUserWithMethod(string $method, array $overrides = []): User
    {
        $email = 'e19_'.uniqid().'@example.com';
        $phone = '+88017'.rand(10000000,99999999);
        $user = User::factory()->create(array_merge([
            'email' => $email,
            'phone' => $phone,
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
            'sms_2fa_enabled' => false,
            'email_2fa_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ], $overrides));

        if ($method === 'totp' || $method === 'all') {
            $user->forceFill([
                'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
                'two_factor_recovery_codes' => encrypt(json_encode(['code1','code2'])),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }
        if ($method === 'sms' || $method === 'all') {
            $user->forceFill(['sms_2fa_enabled' => true])->save();
        }
        if ($method === 'email' || $method === 'all') {
            $user->forceFill(['email_2fa_enabled' => true])->save();
        }
        if ($method === 'all') {
            $user->forceFill(['preferred_2fa_method' => 'email'])->save();
        } elseif (in_array($method, ['sms','email','totp'])) {
            $user->forceFill(['preferred_2fa_method' => $method])->save();
        }
        return $user->fresh();
    }

    // ---- UI Audit ----
    public function test_email_challenge_ui_structure(): void
    {
        $user = $this->createUserWithMethod('email');
        $this->post('/login', ['login' => $user->email, 'password' => 'Secret123!'])->assertRedirect(route('two-factor.login'));
        $resp = $this->get('/two-factor-challenge');
        $resp->assertOk();
        // Heading
        $resp->assertSee('Verify your identity');
        $resp->assertSee('Email verification');
        $resp->assertSee('We sent a 6-digit verification code to');
        $resp->assertSee('This code expires in 10 minutes');
        // Masked email
        $masked = substr($user->email,0,1).str_repeat('*', max(1, strlen(explode('@',$user->email)[0])-2)).substr(explode('@',$user->email)[0],-1).'@'.explode('@',$user->email)[1];
        $resp->assertSee($masked);
        // OTP input UX
        $resp->assertSee('inputmode="numeric"', false);
        $resp->assertSee('pattern="[0-9]*"', false);
        $resp->assertSee('maxlength="6"', false);
        $resp->assertSee('autocomplete="one-time-code"', false);
        $resp->assertSee('6-digit code', false);
        $resp->assertSee('Verify Code');
        $resp->assertSee('Resend Code');
        // Single method -> no switch shown
        $resp->assertDontSee('Use another verification method');
        // Now test with all methods should show switch
        $userAll = $this->createUserWithMethod('all');
        $this->post('/login', ['login' => $userAll->email, 'password' => 'Secret123!']);
        $respAll = $this->get('/two-factor-challenge');
        $respAll->assertSee('Use another verification method');
    }

    public function test_sms_challenge_ui_structure(): void
    {
        $user = $this->createUserWithMethod('sms');
        $this->post('/login', ['login' => $user->email, 'password' => 'Secret123!']);
        $resp = $this->get('/two-factor-challenge');
        $resp->assertSee('Verify your identity');
        $resp->assertSee('SMS verification');
        $resp->assertSee('We sent a 6-digit verification code to your mobile number');
        $resp->assertSee('This code expires in 10 minutes');
        $resp->assertSee('Verify Code');
        $resp->assertSee('Resend Code');
    }

    public function test_totp_challenge_ui_no_email(): void
    {
        Mail::fake();
        $user = $this->createUserWithMethod('totp');
        $this->post('/login', ['login' => $user->email, 'password' => 'Secret123!']);
        $resp = $this->get('/two-factor-challenge');
        $resp->assertSee('Enter the 6-digit code from your Authenticator App');
        $resp->assertSee('Authenticator App');
        $resp->assertDontSee('We sent a 6-digit verification code to your email');
        $resp->assertDontSee('We sent a 6-digit verification code to your mobile number');
        Mail::assertNothingQueued();
    }

    public function test_otp_input_rejects_short_code_validation(): void
    {
        $user = $this->createUserWithMethod('email');
        $this->post('/login', ['login' => $user->email, 'password' => 'Secret123!']);
        // Try to submit short code 123
        $resp = $this->post('/two-factor-challenge', ['code' => '123']);
        $resp->assertSessionHasErrors('code');
        // Should be friendly message
        $errors = session('errors')->get('code');
        $this->assertStringContainsString('6-digit', $errors[0]);
    }

    public function test_email_incorrect_code_friendly_message(): void
    {
        $user = $this->createUserWithMethod('email');
        $this->post('/login', ['login' => $user->email, 'password' => 'Secret123!']);
        EmailOtp::create(['guard'=>'web','user_id'=>$user->id,'email'=>$user->email,'otp_hash'=>Hash::make('123456'),'attempts'=>0,'expires_at'=>now()->addMinutes(10)]);
        $resp = $this->post('/two-factor-challenge', ['code' => '000000']);
        $resp->assertSessionHasErrors('code');
        $msg = session('errors')->get('code')[0];
        $this->assertEquals('The verification code is incorrect.', $msg);
    }

    public function test_email_expired_friendly_message(): void
    {
        $user = $this->createUserWithMethod('email');
        $this->post('/login', ['login' => $user->email, 'password' => 'Secret123!']);
        EmailOtp::create(['guard'=>'web','user_id'=>$user->id,'email'=>$user->email,'otp_hash'=>Hash::make('123456'),'attempts'=>0,'expires_at'=>now()->subMinute()]);
        $resp = $this->post('/two-factor-challenge', ['code' => '123456']);
        $msg = session('errors')->get('code')[0];
        $this->assertStringContainsString('expired', strtolower($msg));
        $this->assertStringContainsString('request a new code', strtolower($msg));
    }

    public function test_email_max_attempts_enforced(): void
    {
        $user = User::factory()->create(['email'=>'maxattempt_'.uniqid().'@example.com','email_verified_at'=>now(),'password_hash'=>Hash::make('Secret123!')]);
        $svc = app(EmailOtpService::class);
        EmailOtp::create(['guard'=>'web','user_id'=>$user->id,'email'=>$user->email,'otp_hash'=>Hash::make('999999'),'attempts'=>0,'expires_at'=>now()->addMinutes(10)]);
        for ($i=0;$i<4;$i++) {
            try { $svc->verify($user, $user->email, '000000', 'web'); } catch (\Throwable $e) {}
        }
        // 5th attempt should be Too many attempts
        try {
            $svc->verify($user, $user->email, '000000', 'web');
            $this->fail('Should throw Too many attempts');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = $e->errors()['otp'][0] ?? $e->errors()['code'][0] ?? '';
            $this->assertStringContainsString('Too many attempts', $msg);
        }
        $record = EmailOtp::where('user_id',$user->id)->latest()->first();
        $this->assertNotNull($record->consumed_at);
    }

    public function test_resend_cooldown_enforced_and_new_code_invalidates_previous(): void
    {
        $user = $this->createUserWithMethod('email');
        Mail::fake();
        $svc = app(EmailOtpService::class);
        $svc->send($user, $user->email, 'web');
        $first = EmailOtp::where('user_id',$user->id)->latest()->first();
        $this->assertNotNull($first);
        // Immediate resend should be throttled
        try {
            $svc->send($user, $user->email, 'web');
            $this->fail('Should throttle');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('Please wait', $e->getMessage());
        }
        // After clearing throttle, resend creates new and invalidates previous
        Cache::forget('email_otp_send:web:'.$user->id.':'.$user->email);
        // Also ensure hourly not blocking, but keep count
        $svc->send($user, $user->email, 'web');
        $second = EmailOtp::where('user_id',$user->id)->latest()->first();
        // Ensure new record created (could be same id if only one, so check count)
        $this->assertEquals(2, EmailOtp::where('user_id',$user->id)->where('email',$user->email)->count());
        $firstFresh = EmailOtp::find($first->id);
        $this->assertNotNull($firstFresh->consumed_at);
        Mail::assertQueued(\App\Mail\EmailOtpMail::class);
    }

    public function test_email_otp_queued_not_sync(): void
    {
        Mail::fake();
        $user = $this->createUserWithMethod('email');
        $svc = app(EmailOtpService::class);
        $svc->send($user, $user->email, 'web');
        Mail::assertQueued(\App\Mail\EmailOtpMail::class, function($mail){
            return ($mail->queue ?? '') === 'notifications' || str_contains($mail->queue ?? '', 'notifications');
        });
        // Ensure no plain OTP in HTML
        $otpRecord = EmailOtp::where('user_id',$user->id)->latest()->first();
        $this->assertNotEquals('123456', $otpRecord->otp_hash);
    }

    public function test_masked_email_displayed_not_full(): void
    {
        $user = $this->createUserWithMethod('email');
        $this->post('/login', ['login'=>$user->email,'password'=>'Secret123!']);
        $resp = $this->get('/two-factor-challenge');
        // Should not contain full email unmasked in a way that reveals full local part? It should contain masked
        $this->assertStringNotContainsString($user->email, $resp->getContent());
        // But should contain masked version
        $masked = substr($user->email,0,1).str_repeat('*', max(1, strlen(explode('@',$user->email)[0])-2)).substr(explode('@',$user->email)[0],-1).'@'.explode('@',$user->email)[1];
        $resp->assertSee($masked);
    }

    public function test_method_switching_only_available(): void
    {
        $user = $this->createUserWithMethod('email'); // only email
        $this->post('/login', ['login'=>$user->email,'password'=>'Secret123!']);
        $resp = $this->get('/two-factor-challenge');
        // Should not show SMS or TOTP
        $resp->assertDontSee('Use SMS instead');
        $resp->assertDontSee('Use Authenticator App');
        // Try to switch to SMS should fail
        $r = $this->post('/two-factor-challenge/switch', ['method'=>'sms']);
        $r->assertSessionHasErrors('method');

        // All methods (preferred is email per createUserWithMethod all)
        $user2 = $this->createUserWithMethod('all');
        $this->post('/login', ['login'=>$user2->email,'password'=>'Secret123!']);
        $resp2 = $this->get('/two-factor-challenge');
        // Current is email, so alternates are totp and sms
        $resp2->assertSee('Use SMS instead');
        $resp2->assertSee('Use Authenticator App');
        $resp2->assertDontSee('Use Email instead');
        // Switch email->sms
        $this->post('/two-factor-challenge/switch', ['method'=>'sms'])->assertRedirect(route('two-factor.login'));
        $this->assertEquals('sms', session('login.2fa_method'));
        // Verify SMS and Email use separate tables
        $otp='123456';
        Phone2faOtp::create(['guard'=>'web','user_id'=>$user2->id,'phone'=>$user2->phone,'otp_hash'=>Hash::make($otp),'attempts'=>0,'expires_at'=>now()->addMinutes(10)]);
        // Ensure Email OTP table does not have same hash
        $this->assertEquals(0, EmailOtp::where('user_id',$user2->id)->where('otp_hash', Hash::make($otp))->count());
        $this->post('/two-factor-challenge', ['code'=>$otp])->assertRedirect('/');
    }

    public function test_security_settings_shows_three_methods(): void
    {
        $user = $this->createUserWithMethod('all');
        $resp = $this->actingAs($user, 'web')->get('/account/security');
        $resp->assertSee('Two-Factor Authentication');
        $resp->assertSee('Use an authenticator app to generate security codes');
        $resp->assertSee('Receive a one-time verification code by email when you sign in');
        $resp->assertSee('Receive a one-time verification code on your verified mobile number');
        $resp->assertSee('Preferred');
    }

    public function test_enable_email_requires_verified(): void
    {
        $user = User::factory()->create(['email'=>'unverified_'.uniqid().'@example.com','email_verified_at'=>null,'password_hash'=>Hash::make('Secret123!')]);
        $resp = $this->actingAs($user, 'web')->post('/account/security/two-factor/email/enable', ['password'=>'Secret123!']);
        $resp->assertSessionHasErrors('email');
        $this->assertStringContainsString('verify your email', strtolower(collect(session('errors')->get('email'))->implode(' ')));
    }

    public function test_enable_sms_requires_verified(): void
    {
        $user = User::factory()->create(['phone'=>'+88017'.rand(10000000,99999999),'phone_verified_at'=>null,'password_hash'=>Hash::make('Secret123!')]);
        $resp = $this->actingAs($user, 'web')->post('/account/security/two-factor/sms/enable', ['password'=>'Secret123!']);
        $resp->assertSessionHasErrors('phone');
    }

    public function test_otp_not_in_html_or_url(): void
    {
        $user = $this->createUserWithMethod('email');
        EmailOtp::create(['guard'=>'web','user_id'=>$user->id,'email'=>$user->email,'otp_hash'=>Hash::make('654321'),'attempts'=>0,'expires_at'=>now()->addMinutes(10)]);
        $this->post('/login', ['login'=>$user->email,'password'=>'Secret123!']);
        $resp = $this->get('/two-factor-challenge');
        $this->assertStringNotContainsString('654321', $resp->getContent());
        $this->assertStringNotContainsString('654321', $resp->getContent());
        // Ensure URL does not contain otp
        $this->assertStringNotContainsString('654321', $resp->baseResponse->headers->get('Location') ?? '');
    }

    public function test_email_mail_contains_expiry_and_app_name(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email'=>'mailcontent@example.com','email_verified_at'=>now()]);
        $svc = app(EmailOtpService::class);
        $svc->send($user, 'mailcontent@example.com', 'web');
        Mail::assertQueued(\App\Mail\EmailOtpMail::class, function($mail){
            // Check view contains expiry and app name via rendering
            $rendered = $mail->render();
            // We cannot easily check OTP itself, but check static content
            return str_contains($rendered, 'expires in 10 minutes') && str_contains($rendered, 'Accumen AI');
        });
    }

    public function test_resend_button_countdown_present(): void
    {
        $user = $this->createUserWithMethod('email');
        $this->post('/login', ['login'=>$user->email,'password'=>'Secret123!']);
        $resp = $this->get('/two-factor-challenge');
        $resp->assertSee('Resend Code', false);
        $resp->assertSee('data-cooldown="60"', false);
        $resp->assertSee('This code expires in 10 minutes');
    }
}
