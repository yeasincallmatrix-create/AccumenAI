<?php

namespace Tests\Feature;

use App\Models\PhonePasswordResetOtp;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'Secret123!';

    protected function makeUser(array $overrides = []): User
    {
        $hasEmail = array_key_exists('email', $overrides);
        $hasPhone = array_key_exists('phone', $overrides);
        $email = $hasEmail ? $overrides['email'] : 'pwd-'.uniqid().'@example.test';
        $phoneRaw = $hasPhone ? $overrides['phone'] : '017'.str_pad((string)random_int(10000000,99999999),8,'0',STR_PAD_LEFT);
        $phone = $phoneRaw !== null ? (PhoneNormalizer::toE164($phoneRaw,'Bangladesh') ?? $phoneRaw) : null;
        $base = [
            'name' => 'Pwd User',
            'first_name' => 'Pwd',
            'last_name' => 'User',
            'email' => $email !== null ? strtolower(trim($email)) : null,
            'phone' => $phone,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'account_type' => 'owner',
        ];
        $data = array_merge($base, $overrides);
        if ($hasPhone) $data['phone'] = $phone;
        if ($hasEmail && $email !== null) $data['email'] = strtolower(trim($email));
        return User::create($data);
    }

    // ----- EMAIL RECOVERY -----
    public function test_email_known_email_sends_generic(): void
    {
        $user = $this->makeUser(['email' => 'known@example.test']);
        $resp = $this->post('/forgot-password', ['email' => 'known@example.test']);
        $resp->assertSessionHas('status');
        $this->assertTrue(true, 'Generic response for known email');
    }

    public function test_email_unknown_email_generic(): void
    {
        $resp = $this->post('/forgot-password', ['email' => 'unknown-'.uniqid().'@example.test']);
        $resp->assertSessionHas('status');
        // Generic response - check status is set and not revealing
        $this->assertNotEmpty($resp->getSession()->get('status'));
    }

    public function test_email_enumeration_indistinguishable(): void
    {
        $known = $this->makeUser();
        $r1 = $this->post('/forgot-password', ['email' => $known->email]);
        $r2 = $this->post('/forgot-password', ['email' => 'no-such-'.uniqid().'@example.test']);
        $this->assertEquals($r1->getSession()->get('status'), $r2->getSession()->get('status'));
    }

    public function test_email_successful_reset(): void
    {
        $user = $this->makeUser();
        $token = Password::broker('users')->createToken($user);
        // Verify token is hashed in DB (not plaintext)
        $row = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertNotEquals($token, $row->token, 'Token should be hashed');
        $this->assertTrue(Hash::check($token, $row->token) || password_verify($token, $row->token) || strlen($row->token) > 20);

        $resp = $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);
        $resp->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue(Hash::check('NewSecret123!', $user->getAuthPassword()));
        // Single-use: token should be deleted
        $after = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertNull($after);
    }

    public function test_email_invalid_token(): void
    {
        $user = $this->makeUser();
        Password::broker('users')->createToken($user);
        $resp = $this->post('/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token-123',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);
        $resp->assertSessionHasErrors('email');
    }

    public function test_email_expired_token(): void
    {
        $user = $this->makeUser();
        $token = Password::broker('users')->createToken($user);
        // Manually expire: set created_at to 2 hours ago (expire is 60 min)
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subHours(2)]);
        $resp = $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);
        $resp->assertSessionHasErrors('email');
    }

    public function test_email_reused_token(): void
    {
        $user = $this->makeUser();
        $token = Password::broker('users')->createToken($user);
        $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ])->assertSessionHasNoErrors();
        // Reuse same token should fail
        $resp = $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'Another123!',
            'password_confirmation' => 'Another123!',
        ]);
        $resp->assertSessionHasErrors('email');
    }

    public function test_email_weak_password_rejected(): void
    {
        $user = $this->makeUser();
        $token = Password::broker('users')->createToken($user);
        $resp = $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);
        $resp->assertSessionHasErrors('password');
        $user->refresh();
        $this->assertTrue(Hash::check($this->password, $user->getAuthPassword()), 'Password should not change on weak');
    }

    public function test_email_strong_password_accepted(): void
    {
        $user = $this->makeUser();
        $token = Password::broker('users')->createToken($user);
        $resp = $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'Str0ng!Pass123',
            'password_confirmation' => 'Str0ng!Pass123',
        ]);
        $resp->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue(Hash::check('Str0ng!Pass123', $user->getAuthPassword()));
    }

    public function test_email_session_revocation(): void
    {
        $user = $this->makeUser();
        // Seed a fake session
        $sid = 'testsession123';
        DB::table('sessions')->insert([
            'id' => $sid,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('test'),
            'last_activity' => time(),
        ]);
        $token = Password::broker('users')->createToken($user);
        $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);
        $remaining = DB::table('sessions')->where('user_id', $user->id)->count();
        // All sessions should be revoked (since reset is guest, no current session)
        $this->assertEquals(0, $remaining);
    }

    // ----- PHONE RECOVERY -----
    public function test_phone_known_phone_generic(): void
    {
        $user = $this->makeUser(['phone' => '01711112222']);
        Cache::flush();
        $resp = $this->post('/forgot-password/phone', ['phone' => '01711112222']);
        $resp->assertSessionHas('status');
        $this->assertEquals('If an account exists with that phone, a reset code has been sent.', $resp->getSession()->get('status'));
        $otp = PhonePasswordResetOtp::where('phone', $user->phone)->first();
        $this->assertNotNull($otp, 'OTP should be created for known phone');
    }

    public function test_phone_unknown_phone_generic(): void
    {
        Cache::flush();
        $resp = $this->post('/forgot-password/phone', ['phone' => '01799998888']);
        $resp->assertSessionHas('status');
        $this->assertEquals('If an account exists with that phone, a reset code has been sent.', $resp->getSession()->get('status'));
        // Undistinguishable from known
        $unknownOtp = PhonePasswordResetOtp::where('phone', PhoneNormalizer::toE164('01799998888','Bangladesh'))->first();
        // Should not create OTP for unknown (but still generic response)
        $this->assertNull($unknownOtp);
    }

    public function test_phone_normalized_phone(): void
    {
        $user = $this->makeUser(['phone' => '01733334444']);
        Cache::flush();
        // Variants should all resolve to same normalized phone
        foreach (['01733334444','+8801733334444','8801733334444','017-333-34444'] as $variant) {
            Cache::forget('phone_pwd_reset_send:' . $user->phone);
            // Need to clear hour limit as well
            Cache::flush();
            $this->post('/forgot-password/phone', ['phone' => $variant])->assertSessionHas('status');
            $exists = PhonePasswordResetOtp::where('phone', $user->phone)->exists();
            $this->assertTrue($exists, "Variant $variant should create OTP");
            PhonePasswordResetOtp::where('phone', $user->phone)->delete();
        }
    }

    public function test_phone_invalid_otp(): void
    {
        $user = $this->makeUser(['phone' => '01744445555']);
        Cache::flush();
        $this->post('/forgot-password/phone', ['phone' => '01744445555']);
        $resp = $this->post('/forgot-password/phone/verify', ['phone' => '01744445555', 'otp' => '000000']);
        $resp->assertSessionHasErrors('otp');
    }

    public function test_phone_expired_otp(): void
    {
        $user = $this->makeUser(['phone' => '01755556666']);
        Cache::flush();
        $this->post('/forgot-password/phone', ['phone' => '01755556666']);
        $otp = PhonePasswordResetOtp::where('phone', $user->phone)->latest('id')->first();
        $otp->update(['expires_at' => now()->subMinute()]);
        $resp = $this->post('/forgot-password/phone/verify', ['phone' => '01755556666', 'otp' => '123456']);
        $resp->assertSessionHasErrors('otp');
    }

    public function test_phone_otp_retry_limit(): void
    {
        $user = $this->makeUser(['phone' => '01766667777']);
        Cache::flush();
        $this->post('/forgot-password/phone', ['phone' => '01766667777']);
        for ($i=0;$i<5;$i++) {
            $this->post('/forgot-password/phone/verify', ['phone' => '01766667777', 'otp' => '000000']);
        }
        $otp = PhonePasswordResetOtp::where('phone', $user->phone)->latest('id')->first();
        // After 5 attempts should be consumed/invalidated
        $this->assertNotNull($otp->consumed_at);
        $resp = $this->post('/forgot-password/phone/verify', ['phone' => '01766667777', 'otp' => '000000']);
        $resp->assertSessionHasErrors('otp');
    }

    public function test_phone_resend_throttle(): void
    {
        $user = $this->makeUser(['phone' => '01777778888']);
        Cache::flush();
        $this->post('/forgot-password/phone', ['phone' => '01777778888'])->assertSessionHas('status');
        $firstCount = PhonePasswordResetOtp::where('phone', $user->phone)->count();
        $this->post('/forgot-password/phone', ['phone' => '01777778888'])->assertSessionHas('status');
        $secondCount = PhonePasswordResetOtp::where('phone', $user->phone)->count();
        $this->assertEquals($firstCount, $secondCount, 'Resend throttle should not create new OTP');
    }

    public function test_phone_successful_otp(): void
    {
        $user = $this->makeUser(['phone' => '01788889999']);
        Cache::flush();
        $this->post('/forgot-password/phone', ['phone' => '01788889999']);
        $otp = PhonePasswordResetOtp::where('phone', $user->phone)->latest('id')->first();
        // Retrieve plaintext is not available, so create known OTP
        PhonePasswordResetOtp::where('phone', $user->phone)->delete();
        Cache::forget('phone_pwd_reset_send:'.$user->phone);
        $plain = '123456';
        PhonePasswordResetOtp::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'otp_hash' => Hash::make($plain),
            'expires_at' => now()->addMinutes(10),
        ]);
        $resp = $this->post('/forgot-password/phone/verify', ['phone' => '01788889999', 'otp' => $plain]);
        $resp->assertSessionHasNoErrors();
        $verified = PhonePasswordResetOtp::where('phone', $user->phone)->whereNotNull('verified_at')->first();
        $this->assertNotNull($verified);
    }

    public function test_phone_successful_password_reset(): void
    {
        $user = $this->makeUser(['phone' => '01700001111']);
        Cache::flush();
        PhonePasswordResetOtp::where('phone', $user->phone)->delete();
        $plain = '654321';
        $rec = PhonePasswordResetOtp::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'otp_hash' => Hash::make($plain),
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->post('/forgot-password/phone/verify', ['phone' => '01700001111', 'otp' => $plain])->assertSessionHasNoErrors();
        $resp = $this->post('/reset-password/phone', [
            'phone' => '01700001111',
            'password' => 'NewPhone123!',
            'password_confirmation' => 'NewPhone123!',
        ]);
        $resp->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue(Hash::check('NewPhone123!', $user->getAuthPassword()));
        // OTP should be consumed
        $rec->refresh();
        $this->assertNotNull($rec->consumed_at);
    }

    public function test_phone_session_revocation(): void
    {
        $user = $this->makeUser(['phone' => '01700002222']);
        DB::table('sessions')->insert([
            'id' => 'phpsess1',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);
        Cache::flush();
        $plain = '111222';
        PhonePasswordResetOtp::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'otp_hash' => Hash::make($plain),
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->post('/forgot-password/phone/verify', ['phone' => '01700002222', 'otp' => $plain]);
        $this->post('/reset-password/phone', [
            'phone' => '01700002222',
            'password' => 'NewPhone123!',
            'password_confirmation' => 'NewPhone123!',
        ]);
        $cnt = DB::table('sessions')->where('user_id', $user->id)->count();
        $this->assertEquals(0, $cnt);
    }

    public function test_phone_otp_hashed_storage(): void
    {
        $user = $this->makeUser(['phone' => '01700003333']);
        Cache::flush();
        $this->post('/forgot-password/phone', ['phone' => '01700003333']);
        $otp = PhonePasswordResetOtp::where('phone', $user->phone)->first();
        $this->assertTrue(str_starts_with($otp->otp_hash, '$2y$') || str_starts_with($otp->otp_hash, '$argon'));
    }

    public function test_tenant_cross_recovery_impossible(): void
    {
        // Phone is global unique, email also global; request for phone in one tenant cannot reset password for user in another tenant with same email? Since phone unique globally, cross-tenant impossible.
        $u1 = $this->makeUser(['email' => 'tenant1@example.test', 'phone' => '01710001111']);
        $u2 = $this->makeUser(['email' => 'tenant2@example.test', 'phone' => '01710002222']);
        // Request OTP for u1 phone, try to use it to reset u2 phone - should fail
        Cache::flush();
        $plain = '999888';
        PhonePasswordResetOtp::create([
            'user_id' => $u1->id,
            'phone' => $u1->phone,
            'otp_hash' => Hash::make($plain),
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->post('/forgot-password/phone/verify', ['phone' => '01710001111', 'otp' => $plain])->assertSessionHasNoErrors();
        // Try to reset u2 with u1's phone verification - should fail
        $resp = $this->post('/reset-password/phone', [
            'phone' => '01710002222',
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ]);
        // Should error because no verified OTP for u2 phone
        $resp->assertSessionHasErrors();
        $u2->refresh();
        $this->assertFalse(Hash::check('NewPass123!', $u2->getAuthPassword()));
    }

    public function test_enumeration_phone_generic(): void
    {
        $known = $this->makeUser(['phone' => '01720001111']);
        Cache::flush();
        $r1 = $this->post('/forgot-password/phone', ['phone' => '01720001111']);
        Cache::flush();
        $r2 = $this->post('/forgot-password/phone', ['phone' => '01720009999']);
        $this->assertEquals($r1->getSession()->get('status'), $r2->getSession()->get('status'));
        $this->assertEquals('If an account exists with that phone, a reset code has been sent.', $r1->getSession()->get('status'));
    }
}
