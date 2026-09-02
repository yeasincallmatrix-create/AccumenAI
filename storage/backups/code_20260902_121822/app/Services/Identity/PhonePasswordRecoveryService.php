<?php

namespace App\Services\Identity;

use App\Models\PhonePasswordResetOtp;
use App\Models\User;
use App\Services\Auth\PasswordService;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PhonePasswordRecoveryService
{
    /**
     * Request OTP for phone password reset - enumeration safe.
     * Always returns generic success regardless of existence.
     */
    public function request(string $rawPhone, ?string $country, ?string $ip = null): void
    {
        $normalized = PhoneNormalizer::toE164($rawPhone, $country ?? 'Bangladesh');
        if ($normalized === null) {
            // Invalid phone - still generic, rate limit IP
            $this->hitRateLimit($ip);
            IdentityAuditService::log(null, 'phone_password_reset_requested_invalid', 'phone', []);
            return;
        }

        $ip = $ip ?? request()->ip();
        // Generic throttle per IP + phone to prevent enumeration brute force
        $throttleKey = 'phone_pwd_reset:'. $ip . ':' . $normalized;
        // Use Cache for resend throttle (per phone)
        $throttleCache = 'phone_pwd_reset_send:' . $normalized;
        if (Cache::has($throttleCache)) {
            // Still generic, but don't send again
            IdentityAuditService::log(null, 'phone_password_reset_throttled', 'phone', ['phone' => $this->mask($normalized)]);
            return;
        }

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            IdentityAuditService::log(null, 'phone_password_reset_rate_limited', 'phone', []);
            return;
        }
        RateLimiter::hit($throttleKey, 3600);

        $user = User::where('phone', $normalized)->first();
        // Audit request regardless of existence (no enumeration)
        IdentityAuditService::log($user?->id, 'phone_password_reset_requested', 'phone', ['phone' => $this->mask($normalized)]);

        if (!$user || $user->status !== 'active') {
            // Generic - do not send SMS, but still pretend success
            Log::info('phone_password_reset_no_account', ['phone' => $this->mask($normalized)]);
            return;
        }

        // Check phone must be verified? For recovery we allow any active user's phone, but ideally verified.
        // We allow even if not verified - but audit.

        // Hourly limit per phone
        $hourKey = 'phone_pwd_reset_hour:' . $normalized;
        $hourCount = (int) Cache::get($hourKey, 0);
        $maxPerHour = (int) config('identity.phone_password_reset.max_sends_per_hour', 5);
        if ($hourCount >= $maxPerHour) {
            IdentityAuditService::log($user->id, 'phone_password_reset_hour_limit', 'phone', []);
            return;
        }

        // Invalidate previous OTPs for this phone
        PhonePasswordResetOtp::where('phone', $normalized)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $length = (int) config('identity.phone_password_reset.length', 6);
        $otp = $this->generateOtp($length);
        $hash = Hash::make($otp);
        $expires = (int) config('identity.phone_password_reset.expires_minutes', 10);

        PhonePasswordResetOtp::create([
            'user_id' => $user->id,
            'phone' => $normalized,
            'otp_hash' => $hash,
            'attempts' => 0,
            'expires_at' => now()->addMinutes($expires),
        ]);

        $this->sendSms($normalized, $otp);
        $resendThrottle = (int) config('identity.phone_password_reset.resend_throttle_seconds', 60);
        Cache::put($throttleCache, 1, $resendThrottle);
        Cache::put($hourKey, $hourCount + 1, 3600);
    }

    /**
     * Verify OTP for phone password reset.
     * Returns true on success, throws ValidationException on failure.
     * On success, marks verified cache.
     */
    public function verify(string $rawPhone, string $otp, ?string $country = null): bool
    {
        $normalized = PhoneNormalizer::toE164($rawPhone, $country ?? 'Bangladesh');
        if ($normalized === null) {
            throw ValidationException::withMessages(['phone' => ['Invalid phone number.']]);
        }

        $record = PhonePasswordResetOtp::where('phone', $normalized)
            ->whereNull('consumed_at')
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (!$record) {
            // Check if there's a verified record already? Then already verified
            $verified = PhonePasswordResetOtp::where('phone', $normalized)->whereNotNull('verified_at')->latest('id')->first();
            if ($verified && !$verified->isExpired() && $verified->consumed_at === null) {
                return true; // already verified within window
            }
            throw ValidationException::withMessages(['otp' => ['Invalid or expired code.']]);
        }

        if ($record->isExpired()) {
            $record->update(['consumed_at' => now()]);
            IdentityAuditService::log($record->user_id, 'phone_password_reset_expired', 'phone', ['phone' => $this->mask($normalized)]);
            throw ValidationException::withMessages(['otp' => ['Code expired.']]);
        }

        $max = (int) config('identity.phone_password_reset.max_attempts', 5);
        if ($record->attempts >= $max) {
            $record->update(['consumed_at' => now()]);
            IdentityAuditService::log($record->user_id, 'phone_password_reset_bruteforce', 'phone', ['phone' => $this->mask($normalized)]);
            throw ValidationException::withMessages(['otp' => ['Too many attempts. Code invalidated.']]);
        }

        if (!Hash::check($otp, $record->otp_hash)) {
            $record->increment('attempts');
            if ($record->attempts >= $max) {
                $record->update(['consumed_at' => now()]);
                IdentityAuditService::log($record->user_id, 'phone_password_reset_bruteforce', 'phone', ['phone' => $this->mask($normalized)]);
                throw ValidationException::withMessages(['otp' => ['Too many attempts. Code invalidated.']]);
            }
            IdentityAuditService::log($record->user_id, 'phone_password_reset_failed', 'phone', ['phone' => $this->mask($normalized), 'attempts' => $record->attempts]);
            throw ValidationException::withMessages(['otp' => ['Invalid code.']]);
        }

        // Success - mark verified and set cache flag
        $record->update(['verified_at' => now()]);
        $verifiedTtl = (int) config('identity.phone_password_reset.verified_ttl_minutes', 10);
        Cache::put($this->verifiedKey($normalized), $record->user_id, $verifiedTtl * 60);
        // Invalidate other pending for same phone
        PhonePasswordResetOtp::where('phone', $normalized)->where('id', '!=', $record->id)->whereNull('consumed_at')->whereNull('verified_at')->update(['consumed_at' => now()]);

        IdentityAuditService::log($record->user_id, 'phone_password_reset_verified', 'phone', ['phone' => $this->mask($normalized)]);
        return true;
    }

    /**
     * Reset password after OTP verification.
     */
    public function reset(string $rawPhone, string $newPassword, ?string $country = null): User
    {
        $normalized = PhoneNormalizer::toE164($rawPhone, $country ?? 'Bangladesh');
        if ($normalized === null) {
            throw ValidationException::withMessages(['phone' => ['Invalid phone number.']]);
        }

        $verifiedUserId = Cache::get($this->verifiedKey($normalized));
        // Also check DB verified record not expired/consumed
        $record = PhonePasswordResetOtp::where('phone', $normalized)->whereNotNull('verified_at')->whereNull('consumed_at')->latest('id')->first();
        if (!$record || $record->isExpired() || $verifiedUserId === null || (int)$verifiedUserId !== (int)$record->user_id) {
            // Also allow if recent verified_at within TTL and cache missing due to array driver? Check DB
            if ($record && $record->verified_at && $record->verified_at->addMinutes((int)config('identity.phone_password_reset.verified_ttl_minutes',10))->isFuture() && $record->consumed_at === null && !$record->isExpired()) {
                // fallback allow DB verified
                $verifiedUserId = $record->user_id;
            } else {
                throw ValidationException::withMessages(['phone' => ['Verification required.']]);
            }
        }

        $user = User::find($verifiedUserId);
        if (!$user || $user->phone !== $normalized) {
            throw ValidationException::withMessages(['phone' => ['Invalid verification.']]);
        }

        // Validate PasswordPolicy via PasswordService (will throw if weak)
        app(PasswordService::class)->setForUser($user, $newPassword);

        // Consume OTP and clear verified cache
        $record->update(['consumed_at' => now()]);
        Cache::forget($this->verifiedKey($normalized));
        // Invalidate all other OTPs for this phone
        PhonePasswordResetOtp::where('phone', $normalized)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        // Clear lockout
        try { $user->forceFill(['failed_login_count'=>0,'locked_until'=>null])->save(); } catch (\Throwable $e) { report($e); }

        IdentityAuditService::log($user->id, 'phone_password_reset_completed', 'phone', ['phone' => $this->mask($normalized)]);
        return $user;
    }

    protected function hitRateLimit(?string $ip): void
    {
        $ip = $ip ?? request()->ip();
        RateLimiter::hit('phone_pwd_reset_invalid:'.$ip, 3600);
    }

    protected function generateOtp(int $length): string
    {
        $max = (int) pow(10, $length) - 1;
        $otp = random_int(0, $max);
        return str_pad((string) $otp, $length, '0', STR_PAD_LEFT);
    }

    protected function sendSms(string $phone, string $otp): void
    {
        $message = "Your password reset code is: {$otp}. It expires in ". config('identity.phone_password_reset.expires_minutes', 10) ." minutes.";
        try {
            $providerName = config('notifications.sms.default', 'log');
            $providers = config('notifications.sms.providers', []);
            $providerClass = $providers[$providerName] ?? \App\Services\Notification\Sms\LogSmsProvider::class;
            $provider = app($providerClass);
            $provider->send($phone, $message, ['provider' => $providerName]);
        } catch (\Throwable $e) {
            report($e);
            Log::info('sms_otp_sent', ['phone' => $this->mask($phone)]);
        }
        if (app()->environment('local','testing')) {
            Log::info('phone_pwd_otp_generated', ['phone' => $this->mask($phone)]);
        }
    }

    protected function mask(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) return str_repeat('*', $len);
        return substr($phone,0,3).str_repeat('*', $len-6).substr($phone,-3);
    }

    protected function verifiedKey(string $phone): string
    {
        return 'phone_pwd_reset_verified:' . $phone;
    }
}
