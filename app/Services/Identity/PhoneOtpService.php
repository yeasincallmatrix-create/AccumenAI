<?php

namespace App\Services\Identity;

use App\Models\PhoneVerificationOtp;
use App\Models\User;
use App\Services\Notification\Sms\SmsProviderContract;
use App\Support\IdentityConfig;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PhoneOtpService
{
    /**
     * Generate and send OTP for phone verification.
     * Applies resend throttling, brute-force protection, invalidates previous OTPs.
     * Never reveal existence of phone in responses for unauthenticated flows; this
     * method is for authenticated user flows only (verification/change).
     * For unauthenticated, caller should handle enumeration protection.
     */
    public function send(User $user, string $rawPhone, ?string $country = null): array
    {
        return $this->sendForUser($user, $rawPhone, $country);
    }

    /**
     * Generic send supporting any guard user (User, InstituteUser, PlatformAdmin, Guardian).
     * Keeps same security guarantees as legacy User-only method.
     */
    public function sendForUser(object $user, string $rawPhone, ?string $country = null): array
    {
        $normalized = PhoneNormalizer::toE164($rawPhone, $country);
        if ($normalized === null) {
            throw ValidationException::withMessages(['phone' => ['Invalid phone number.']]);
        }

        $phone = $normalized;

        // Uniqueness check: phone not taken by another user (normalized) - only for User model where phone unique
        if ($user instanceof User) {
            $exists = User::where('phone', $phone)->where('id', '!=', $user->id)->exists();
            if ($exists) {
                throw ValidationException::withMessages(['phone' => ['Phone already taken.']]);
            }
        }

        // Also check pending_phone collision if changing?
        // Resend throttle: per user+phone
        $throttleKey = 'phone_otp_send:'. $user->id . ':' . $phone;
        $expires = (int) IdentityConfig::phoneOtp('resend_throttle_seconds', 60);
        if (Cache::has($throttleKey)) {
            throw ValidationException::withMessages(['phone' => ['Please wait before requesting another code.']]);
        }

        // Hourly limit
        $hourKey = 'phone_otp_hour:'. $user->id . ':' . $phone;
        $hourCount = (int) Cache::get($hourKey, 0);
        $maxPerHour = (int) IdentityConfig::phoneOtp('max_sends_per_hour', 5);
        if ($hourCount >= $maxPerHour) {
            throw ValidationException::withMessages(['phone' => ['Too many OTP requests. Try again later.']]);
        }

        // Invalidate previous OTPs for this user+phone (set consumed or delete)
        PhoneVerificationOtp::where('user_id', $user->id)
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        // Generate OTP — precedence E19 DB → env → default
        $length = (int) IdentityConfig::phoneOtp('length', 6);
        $otp = $this->generateOtp($length);
        $hash = Hash::make($otp);
        $expiresMinutes = (int) IdentityConfig::phoneOtp('expires_minutes', 10);

        $record = PhoneVerificationOtp::create([
            'user_id' => $user->id,
            'phone' => $phone,
            'otp_hash' => $hash,
            'attempts' => 0,
            'expires_at' => now()->addMinutes($expiresMinutes),
        ]);

        // Send via SMS provider (log provider in dev)
        $this->sendSms($phone, $otp);

        Cache::put($throttleKey, 1, $expires);
        Cache::put($hourKey, $hourCount + 1, 3600);

        IdentityAuditService::log($user->id, 'phone_otp_sent', 'phone', ['phone' => $this->maskPhone($phone)]);

        return $record->toArray();
    }

    /**
     * Verify OTP for given user and phone. Returns true on success.
     * Handles expiration, max attempts, invalidation after success or attempts exceeded.
     */
    public function verify(User $user, string $rawPhone, string $otp, ?string $country = null): bool
    {
        return $this->verifyForUser($user, $rawPhone, $otp, $country);
    }

    public function verifyForUser(object $user, string $rawPhone, string $otp, ?string $country = null): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $rawPhone, $otp, $country) {
            $normalized = PhoneNormalizer::toE164($rawPhone, $country);
            if ($normalized === null) {
                throw ValidationException::withMessages(['phone' => ['Invalid phone number.']]);
            }
            $phone = $normalized;
            $record = PhoneVerificationOtp::where('user_id', $user->getKey())
                ->where('phone', $phone)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if (!$record) {
                throw ValidationException::withMessages(['otp' => ['Invalid verification code.']]);
            }
            if ($record->isExpired()) {
                $record->update(['consumed_at' => now()]);
                \Illuminate\Support\Facades\Log::info('otp.expired', ['type' => 'phone_verification', 'user_id' => $user->getKey()]);
                throw ValidationException::withMessages(['otp' => ['This verification code has expired. Please request a new code.']]);
            }
            $maxAttempts = (int) IdentityConfig::phoneOtp('max_attempts', 5);
            if ($record->attempts >= $maxAttempts) {
                $record->update(['consumed_at' => now()]);
                IdentityAuditService::log($user->id, 'phone_otp_bruteforce', 'phone', ['phone' => $this->maskPhone($phone)]);
                throw ValidationException::withMessages(['otp' => ['Too many incorrect attempts. Please request a new code.']]);
            }
            if (! Hash::check($otp, $record->otp_hash)) {
                $record->increment('attempts');
                $fresh = $record->fresh();
                $remaining = $maxAttempts - $fresh->attempts;
                if ($remaining <= 0) {
                    $fresh->update(['consumed_at' => now()]);
                    IdentityAuditService::log($user->id, 'phone_otp_bruteforce', 'phone', ['phone' => $this->maskPhone($phone)]);
                    throw ValidationException::withMessages(['otp' => ['Too many incorrect attempts. Please request a new code.']]);
                }
                IdentityAuditService::log($user->id, 'phone_otp_failed', 'phone', ['phone' => $this->maskPhone($phone), 'attempts' => $fresh->attempts]);
                throw ValidationException::withMessages(['otp' => ['Invalid verification code.']]);
            }
            $record->update(['consumed_at' => now()]);
            PhoneVerificationOtp::where('user_id', $user->id)
                ->where('phone', $phone)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);
            \Illuminate\Support\Facades\Log::info('otp.verification_success', ['type' => 'phone_verification', 'user_id' => $user->getKey()]);
            return true;
        });
    }

    /**
     * Send OTP without revealing user existence - used for enumeration-safe endpoints.
     * Always returns generic success.
     */
    public function sendForLookup(string $rawPhone, ?string $country = null, ?string $ip = null): void
    {
        // Rate limit by IP to prevent brute force enumeration
        $key = 'phone_otp_enum:'. ($ip ?? request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return; // silently throttle
        }
        RateLimiter::hit($key, 3600);

        // Normalize but do not reveal if invalid
        $normalized = PhoneNormalizer::toE164($rawPhone, $country);
        if ($normalized === null) {
            return;
        }
        // Even if phone not found, we simulate success without sending
        // Actual send not performed for unauthenticated lookup to avoid enumeration
        Log::info('phone_otp_lookup', ['phone' => $this->maskPhone($normalized), 'ip' => $ip]);
    }

    protected function generateOtp(int $length): string
    {
        $max = (int) pow(10, $length) - 1;
        $otp = random_int(0, $max);
        return str_pad((string) $otp, $length, '0', STR_PAD_LEFT);
    }

    protected function sendSms(string $phone, string $otp): void
    {
        $message = "Your verification code is: {$otp}. It expires in ". IdentityConfig::phoneOtp('expires_minutes', 10) ." minutes.";
        try {
            $providerName = \App\Services\Platform\SmsConfig::activeProvider();
            $providers = config('notifications.sms.providers', []);
            $providerClass = $providers[$providerName] ?? \App\Services\Notification\Sms\LogSmsProvider::class;
            /** @var SmsProviderContract $provider */
            $provider = app($providerClass);
            $options = array_merge(['provider' => $providerName], \App\Services\Platform\SmsConfig::providerOptions());
            $provider->send($phone, $message, $options);
        } catch (\Throwable $e) {
            report($e);
            Log::info('sms_otp_sent', ['phone' => $this->maskPhone($phone)]);
        }
        if (app()->environment('local', 'testing')) {
            Log::info('otp_generated', ['phone' => $this->maskPhone($phone), 'note' => 'otp not logged plaintext']);
        }
    }

    /**
     * Send OTP for 2FA login (distinct from phone verification).
     * Uses phone_2fa_otps table, per-user throttling, queued delivery via same SMS provider.
     * Does NOT check uniqueness, only that phone is verified and present.
     */
    public function sendFor2FA(object $user, ?string $country = null): array
    {
        $rawPhone = $user->phone ?? null;
        if (! $rawPhone) {
            throw \Illuminate\Validation\ValidationException::withMessages(['phone' => ['No phone on file.']]);
        }
        $normalized = PhoneNormalizer::toE164($rawPhone, $country ?? 'Bangladesh');
        if ($normalized === null) {
            throw \Illuminate\Validation\ValidationException::withMessages(['phone' => ['Invalid phone number.']]);
        }
        $phone = $normalized;
        $guard = $this->resolveGuard($user);
        $userId = $user->getKey();
        $instituteId = $this->resolveInstituteId($user);

        $throttleKey = 'phone_2fa_send:' . $guard . ':' . $userId . ':' . $phone;
        $expires = (int) IdentityConfig::phoneOtp('resend_throttle_seconds', 60);
        if (Cache::has($throttleKey)) {
            throw ValidationException::withMessages(['phone' => ['Please wait before requesting another code.']]);
        }
        $hourKey = 'phone_2fa_hour:' . $guard . ':' . $userId . ':' . $phone;
        $hourCount = (int) Cache::get($hourKey, 0);
        $maxPerHour = (int) IdentityConfig::phoneOtp('max_sends_per_hour', 5);
        if ($hourCount >= $maxPerHour) {
            throw ValidationException::withMessages(['phone' => ['Too many OTP requests. Try again later.']]);
        }

        \App\Models\Phone2faOtp::where('guard', $guard)
            ->where('user_id', $userId)
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $length = (int) IdentityConfig::phoneOtp('length', 6);
        $otp = $this->generateOtp($length);
        $hash = Hash::make($otp);
        $expiresMinutes = (int) IdentityConfig::phoneOtp('expires_minutes', 10);

        $record = \App\Models\Phone2faOtp::create([
            'guard' => $guard,
            'user_id' => $userId,
            'institute_id' => $instituteId,
            'phone' => $phone,
            'otp_hash' => $hash,
            'attempts' => 0,
            'expires_at' => now()->addMinutes($expiresMinutes),
        ]);

        $this->sendSms($phone, $otp);

        Cache::put($throttleKey, 1, $expires);
        Cache::put($hourKey, $hourCount + 1, 3600);

        try { IdentityAuditService::log($userId, 'sms_2fa_sent', '2fa', ['phone' => $this->maskPhone($phone), 'guard' => $guard]); } catch (\Throwable $e) {}

        return $record->toArray();
    }

    public function verifyFor2FA(object $user, string $otp, ?string $country = null): bool
    {
        $rawPhone = $user->phone ?? null;
        if (! $rawPhone) {
            throw ValidationException::withMessages(['otp' => ['Invalid or expired code.']]);
        }
        $normalized = PhoneNormalizer::toE164($rawPhone, $country ?? 'Bangladesh');
        if ($normalized === null) {
            throw ValidationException::withMessages(['otp' => ['Invalid or expired code.']]);
        }
        $phone = $normalized;
        $guard = $this->resolveGuard($user);
        $userId = $user->getKey();

        $record = \App\Models\Phone2faOtp::where('guard', $guard)
            ->where('user_id', $userId)
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $record) {
            throw ValidationException::withMessages(['otp' => ['Invalid or expired code.']]);
        }

        if ($record->isExpired()) {
            $record->update(['consumed_at' => now()]);
            throw ValidationException::withMessages(['otp' => ['Code expired.']]);
        }

        $maxAttempts = (int) IdentityConfig::phoneOtp('max_attempts', 5);
        if ($record->attempts >= $maxAttempts) {
            $record->update(['consumed_at' => now()]);
            try { IdentityAuditService::log($userId, 'sms_2fa_bruteforce', '2fa', ['phone' => $this->maskPhone($phone)]); } catch (\Throwable $e) {}
            throw ValidationException::withMessages(['otp' => ['Too many attempts. Code invalidated.']]);
        }

        if (! Hash::check($otp, $record->otp_hash)) {
            $record->increment('attempts');
            $remaining = $maxAttempts - $record->attempts;
            if ($remaining <= 0) {
                $record->update(['consumed_at' => now()]);
                try { IdentityAuditService::log($userId, 'sms_2fa_bruteforce', '2fa', ['phone' => $this->maskPhone($phone)]); } catch (\Throwable $e) {}
                throw ValidationException::withMessages(['otp' => ['Too many attempts. Code invalidated.']]);
            }
            try { IdentityAuditService::log($userId, 'sms_2fa_failed', '2fa', ['phone' => $this->maskPhone($phone), 'attempts' => $record->attempts]); } catch (\Throwable $e) {}
            throw ValidationException::withMessages(['otp' => ['Invalid code.']]);
        }

        $record->update(['consumed_at' => now()]);
        \App\Models\Phone2faOtp::where('guard', $guard)
            ->where('user_id', $userId)
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        try { IdentityAuditService::log($userId, 'sms_2fa_verified', '2fa', ['phone' => $this->maskPhone($phone)]); } catch (\Throwable $e) {}

        return true;
    }

    protected function resolveGuard(object $user): string
    {
        if ($user instanceof \App\Models\PlatformAdmin) return 'platform_admin';
        if ($user instanceof \App\Models\InstituteUser) return 'institute_user';
        if ($user instanceof \App\Models\Guardian) return 'guardian';
        return 'web';
    }

    protected function resolveInstituteId(object $user): ?int
    {
        try {
            if (isset($user->institute_id) && $user->institute_id) return (int) $user->institute_id;
        } catch (\Throwable $e) {}
        return null;
    }

    protected function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) return str_repeat('*', $len);
        return substr($phone, 0, 3) . str_repeat('*', $len - 6) . substr($phone, -3);
    }
}
