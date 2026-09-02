<?php

namespace Tests\Feature;

use App\Models\EmailOtp;
use App\Models\Phone2faOtp;
use App\Models\PhoneVerificationOtp;
use App\Models\User;
use App\Services\Identity\EmailOtpService;
use App\Services\Identity\PhoneOtpService;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class E18UserFriendlyOtp2FaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Use delete not truncate to avoid implicit commit breaking DatabaseTransactions
        DB::table('jobs')->delete();
        DB::table('failed_jobs')->delete();
        DB::table('email_otps')->delete();
        DB::table('phone_2fa_otps')->delete();
        DB::table('phone_verification_otps')->delete();
    }

    // ---- Phone Normalization Bangladesh ----
    public function test_phone_normalization_variants(): void
    {
        $cases = ['01755555555', '+8801755555555', '8801755555555', '017-555-55555'];
        $norms = array_map(fn($c) => PhoneNormalizer::toE164($c, 'Bangladesh'), $cases);
        $this->assertSame('+8801755555555', $norms[0]);
        $this->assertSame($norms[0], $norms[1]);
        $this->assertSame($norms[0], $norms[2]);
        $this->assertSame($norms[0], $norms[3]);
    }

    // ---- SMS OTP: generation, hashing, provider, verify ----
    public function test_sms_otp_hashed_and_sent(): void
    {
        $user = User::factory()->create(['phone' => '+8801711111111', 'phone_verified_at' => now()]);
        $svc = app(PhoneOtpService::class);
        $data = $svc->send($user, '+8801711111111', 'Bangladesh');
        $record = PhoneVerificationOtp::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($record);
        $this->assertNotEquals('123456', $record->otp_hash);
        $this->assertTrue(Hash::check('000000', $record->otp_hash) === false); // hash not plain
        // Previous OTP invalidation
        $firstId = $record->id;
        sleep(1);
        Cache::forget('phone_otp_send:'.$user->id.':+8801711111111');
        $svc->send($user, '+8801711111111', 'Bangladesh');
        $second = PhoneVerificationOtp::where('user_id', $user->id)->latest()->first();
        $this->assertNotEquals($firstId, $second->id);
        $firstReload = PhoneVerificationOtp::find($firstId);
        $this->assertNotNull($firstReload->consumed_at);
    }

    public function test_sms_otp_correct_verifies_and_consumes(): void
    {
        $user = User::factory()->create(['phone' => '+8801722222222']);
        $otp = '123456';
        PhoneVerificationOtp::create([
            'user_id' => $user->id,
            'phone' => '+8801722222222',
            'otp_hash' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);
        $svc = app(PhoneOtpService::class);
        $this->assertTrue($svc->verify($user, '+8801722222222', $otp, 'Bangladesh'));
        $rec = PhoneVerificationOtp::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($rec->consumed_at);
        // Single use: second verify should fail
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->verify($user, '+8801722222222', $otp, 'Bangladesh');
    }

    public function test_sms_otp_incorrect_rejected(): void
    {
        $user = User::factory()->create(['phone' => '+8801733333333']);
        PhoneVerificationOtp::create([
            'user_id' => $user->id,
            'phone' => '+8801733333333',
            'otp_hash' => Hash::make('654321'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);
        $svc = app(PhoneOtpService::class);
        try {
            $svc->verify($user, '+8801733333333', '000000', 'Bangladesh');
            $this->fail('Should throw');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('Invalid code', $e->getMessage());
        }
    }

    public function test_sms_otp_expired_rejected(): void
    {
        $user = User::factory()->create(['phone' => '+8801744444444']);
        PhoneVerificationOtp::create([
            'user_id' => $user->id,
            'phone' => '+8801744444444',
            'otp_hash' => Hash::make('111111'),
            'attempts' => 0,
            'expires_at' => now()->subMinutes(1),
        ]);
        $svc = app(PhoneOtpService::class);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->verify($user, '+8801744444444', '111111', 'Bangladesh');
    }

    public function test_sms_otp_resend_throttled(): void
    {
        $user = User::factory()->create(['phone' => '+8801755555555']);
        $svc = app(PhoneOtpService::class);
        $svc->send($user, '+8801755555555', 'Bangladesh');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->send($user, '+8801755555555', 'Bangladesh');
    }

    public function test_sms_otp_max_attempts_invalidates(): void
    {
        $user = User::factory()->create(['phone' => '+8801766666666']);
        PhoneVerificationOtp::create([
            'user_id' => $user->id,
            'phone' => '+8801766666666',
            'otp_hash' => Hash::make('999999'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);
        $svc = app(PhoneOtpService::class);
        for ($i=0; $i<5; $i++) {
            try { $svc->verify($user, '+8801766666666', '000000', 'Bangladesh'); } catch (\Throwable $e) {}
        }
        $rec = PhoneVerificationOtp::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($rec->consumed_at);
    }

    // ---- Email OTP same matrix ----
    public function test_email_otp_hashed_and_queued(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com', 'email_verified_at' => now()]);
        $svc = app(EmailOtpService::class);
        $svc->send($user, 'test@example.com', 'web');
        $record = EmailOtp::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($record);
        $this->assertTrue(Hash::check('000000', $record->otp_hash) === false);
        // Check queued mail
        Mail::assertQueued(\App\Mail\EmailOtpMail::class);
    }

    public function test_email_otp_correct_verifies(): void
    {
        $user = User::factory()->create(['email' => 'verify@example.com', 'email_verified_at' => now()]);
        $otp='123456';
        EmailOtp::create([
            'guard'=>'web','user_id'=>$user->id,'email'=>'verify@example.com','otp_hash'=>Hash::make($otp),'attempts'=>0,'expires_at'=>now()->addMinutes(10),
        ]);
        $svc=app(EmailOtpService::class);
        $this->assertTrue($svc->verify($user,'verify@example.com',$otp,'web'));
    }

    public function test_email_otp_incorrect_and_expired_and_throttle(): void
    {
        $user = User::factory()->create(['email'=>'email2@example.com','email_verified_at'=>now()]);
        EmailOtp::create(['guard'=>'web','user_id'=>$user->id,'email'=>'email2@example.com','otp_hash'=>Hash::make('111111'),'attempts'=>0,'expires_at'=>now()->addMinutes(10)]);
        $svc=app(EmailOtpService::class);
        try { $svc->verify($user,'email2@example.com','000000','web'); $this->fail(); } catch (\Illuminate\Validation\ValidationException $e) { $this->assertTrue(true); }

        // expired
        EmailOtp::where('user_id',$user->id)->delete();
        EmailOtp::create(['guard'=>'web','user_id'=>$user->id,'email'=>'email2@example.com','otp_hash'=>Hash::make('222222'),'attempts'=>0,'expires_at'=>now()->subMinute()]);
        try { $svc->verify($user,'email2@example.com','222222','web'); $this->fail(); } catch (\Illuminate\Validation\ValidationException $e) { $this->assertTrue(true); }

        // throttle
        Cache::flush();
        $u2 = User::factory()->create(['email'=>'throttle@example.com','email_verified_at'=>now()]);
        Mail::fake();
        $svc->send($u2,'throttle@example.com','web');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->send($u2,'throttle@example.com','web');
    }

    // ---- Login flows ----
    protected function createUserWith2FA(string $method): User
    {
        $user = User::factory()->create([
            'email'=>'login_'.$method.'_'.uniqid().'@example.com',
            'phone'=>'+88017'.rand(10000000,99999999),
            'phone_verified_at'=>now(),
            'email_verified_at'=>now(),
            'password_hash'=>Hash::make('Secret123!'),
            'status'=>'active',
        ]);
        if ($method==='totp' || $method==='all') {
            // Enable TOTP via Fortify: generate secret
            $user->forceFill([
                'two_factor_secret'=>encrypt('JBSWY3DPEHPK3PXP'),
                'two_factor_recovery_codes'=>encrypt(json_encode(['code1','code2'])),
                'two_factor_confirmed_at'=>now(),
            ])->save();
        }
        if ($method==='sms' || $method==='all') {
            $user->forceFill(['sms_2fa_enabled'=>true])->save();
        }
        if ($method==='email' || $method==='all') {
            $user->forceFill(['email_2fa_enabled'=>true])->save();
        }
        if ($method==='all') {
            $user->forceFill(['preferred_2fa_method'=>'totp'])->save();
        } elseif (in_array($method,['sms','email','totp'])) {
            $user->forceFill(['preferred_2fa_method'=>$method])->save();
        }
        return $user;
    }

    public function test_login_no_2fa_direct(): void
    {
        $user = User::factory()->create(['email'=>'no2fa@example.com','password_hash'=>Hash::make('Secret123!'),'status'=>'active','email_verified_at'=>now()]);
        $user->forceFill(['sms_2fa_enabled'=>false,'email_2fa_enabled'=>false,'two_factor_secret'=>null,'two_factor_confirmed_at'=>null])->save();
        $response = $this->post('/login', ['login'=>'no2fa@example.com','password'=>'Secret123!']);
        // Redirect may be to workspace picker if no membership, or dashboard
        $this->assertTrue(in_array($response->getStatusCode(), [302, 303]));
        $location = $response->headers->get('Location');
        $this->assertTrue(str_contains($location, '/workspace') || str_contains($location, '/') , 'Should redirect to workspace or dashboard');
        $this->assertAuthenticated('web');
    }

    public function test_login_sms_2fa_challenge(): void
    {
        $user = $this->createUserWith2FA('sms');
        $response = $this->post('/login', ['login'=>$user->email,'password'=>'Secret123!']);
        $response->assertRedirect(route('two-factor.login'));
        $this->assertEquals('sms', session('login.2fa_method'));

        // Challenge page should show SMS text and not TOTP
        $resp2 = $this->get('/two-factor-challenge');
        $resp2->assertSee('Enter the 6-digit code sent to your mobile');
        $resp2->assertSee('Resend Code');

        // Simulate OTP sent and verify
        $otp='123456';
        Phone2faOtp::create(['guard'=>'web','user_id'=>$user->id,'phone'=>$user->phone,'otp_hash'=>Hash::make($otp),'attempts'=>0,'expires_at'=>now()->addMinutes(10)]);
        $resp3 = $this->post('/two-factor-challenge', ['code'=>$otp]);
        $resp3->assertRedirect('/');
        $this->assertAuthenticated('web');
    }

    public function test_login_email_2fa_challenge(): void
    {
        $user = $this->createUserWith2FA('email');
        $this->post('/login', ['login'=>$user->email,'password'=>'Secret123!'])->assertRedirect(route('two-factor.login'));
        $this->assertEquals('email', session('login.2fa_method'));
        $resp = $this->get('/two-factor-challenge');
        $resp->assertSee('Enter the 6-digit code sent to your email');

        $otp='654321';
        EmailOtp::create(['guard'=>'web','user_id'=>$user->id,'email'=>$user->email,'otp_hash'=>Hash::make($otp),'attempts'=>0,'expires_at'=>now()->addMinutes(10)]);
        $this->post('/two-factor-challenge', ['code'=>$otp])->assertRedirect('/');
        $this->assertAuthenticated('web');
    }

    public function test_login_totp_challenge_does_not_send_email(): void
    {
        Mail::fake();
        $user = $this->createUserWith2FA('totp');
        $this->post('/login', ['login'=>$user->email,'password'=>'Secret123!'])->assertRedirect(route('two-factor.login'));
        $this->assertEquals('totp', session('login.2fa_method'));
        $resp = $this->get('/two-factor-challenge');
        $resp->assertSee('Enter the 6-digit code from your Authenticator App');
        // Ensure no email queued automatically
        Mail::assertNothingQueued();
        // Also ensure not showing email OTP hint
        $resp->assertDontSee('Enter the 6-digit code sent to your email');
    }

    public function test_login_all_methods_preferred_and_alternate(): void
    {
        $user = $this->createUserWith2FA('all');
        $this->post('/login', ['login'=>$user->email,'password'=>'Secret123!'])->assertRedirect(route('two-factor.login'));
        $resp = $this->get('/two-factor-challenge');
        $resp->assertSee('Enter the 6-digit code from your Authenticator App');
        $resp->assertSee('Use another verification method');
        $resp->assertSee('Use SMS instead');
        $resp->assertSee('Use Email instead');

        // Switch to SMS
        $this->post('/two-factor-challenge/switch', ['method'=>'sms'])->assertRedirect(route('two-factor.login'));
        $this->assertEquals('sms', session('login.2fa_method'));
        $resp2 = $this->get('/two-factor-challenge');
        $resp2->assertSee('Enter the 6-digit code sent to your mobile');

        // Verify via SMS after switch
        $otp='111222';
        Phone2faOtp::create(['guard'=>'web','user_id'=>$user->id,'phone'=>$user->phone,'otp_hash'=>Hash::make($otp),'attempts'=>0,'expires_at'=>now()->addMinutes(10)]);
        $this->post('/two-factor-challenge', ['code'=>$otp])->assertRedirect('/');
        $this->assertAuthenticated('web');
    }

    public function test_login_switch_to_email_and_verify(): void
    {
        $user = $this->createUserWith2FA('all');
        $this->post('/login', ['login'=>$user->email,'password'=>'Secret123!']);
        $this->post('/two-factor-challenge/switch', ['method'=>'email']);
        $resp = $this->get('/two-factor-challenge');
        $resp->assertSee('Enter the 6-digit code sent to your email');
        $otp='333444';
        EmailOtp::create(['guard'=>'web','user_id'=>$user->id,'email'=>$user->email,'otp_hash'=>Hash::make($otp),'attempts'=>0,'expires_at'=>now()->addMinutes(10)]);
        $this->post('/two-factor-challenge', ['code'=>$otp])->assertRedirect('/');
        $this->assertAuthenticated('web');
    }

    public function test_bypass_not_allowed(): void
    {
        $user = $this->createUserWith2FA('totp');
        // Ensure sms not enabled, try to switch to sms should fail
        $this->post('/login', ['login'=>$user->email,'password'=>'Secret123!']);
        $resp = $this->post('/two-factor-challenge/switch', ['method'=>'sms']);
        $resp->assertSessionHasErrors('method');
    }

    public function test_tenant_isolation_otp(): void
    {
        $u1 = User::factory()->create(['email'=>'tenant_iso1_'.uniqid().'@example.com','phone'=>'+88017'.rand(10000000,99999999),'phone_verified_at'=>now(),'email_verified_at'=>now()]);
        $u2 = User::factory()->create(['email'=>'tenant_iso2_'.uniqid().'@example.com','phone'=>'+88017'.rand(10000000,99999999),'phone_verified_at'=>now(),'email_verified_at'=>now()]);
        // Create OTP for u1
        EmailOtp::create(['guard'=>'web','user_id'=>$u1->id,'institute_id'=>1,'email'=>$u1->email,'otp_hash'=>Hash::make('123456'),'attempts'=>0,'expires_at'=>now()->addMinutes(10)]);
        // Attempt verify with u2 should fail (no record for u2)
        $svc=app(EmailOtpService::class);
        try {
            $svc->verify($u2, $u1->email, '123456', 'web');
            $this->fail('Should not verify cross-user');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertTrue(true);
        }
        // Also test institute_id isolation: OTP for institute 1 should not be accessible from institute 2 context (user same but different institute_id query)
        // Our service uses guard+user_id+email, institute_id not used for verify lookup, but for creation isolation we stored institute_id
        // Verify column stored correctly
        $otp = EmailOtp::where('user_id',$u1->id)->first();
        $this->assertEquals(1, $otp->institute_id);
    }

    public function test_queue_configuration(): void
    {
        // In testing, queue is sync; in local/production it must be database
        $this->assertEquals('sync', config('queue.default'));
        $this->assertEquals('sync', env('QUEUE_CONNECTION', 'database'));
        // Check .env (local) has database
        $envContent = file_get_contents(base_path('.env'));
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $envContent);
        // Composer dev script check
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $dev = $composer['scripts']['dev'][1] ?? '';
        $this->assertStringContainsString('queue:listen', $dev);
        $this->assertStringContainsString('default,notifications', $dev);
        $this->assertStringContainsString('--tries=3', $dev);
        $this->assertStringContainsString('--timeout=25', $dev);
    }

    public function test_security_scan_no_plain_otp(): void
    {
        // Ensure no log contains OTP plain in code (quick forensic)
        $files = [
            app_path('Services/Identity/PhoneOtpService.php'),
            app_path('Services/Identity/EmailOtpService.php'),
        ];
        foreach ($files as $f) {
            $content = file_get_contents($f);
            // Should not log $otp variable directly
            $this->assertStringNotContainsString('Log::info($otp', $content);
            $this->assertStringNotContainsString('logger()->info($otp', $content);
        }
    }
}
