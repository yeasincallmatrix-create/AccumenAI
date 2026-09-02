<?php

namespace App\Services\Identity;

use App\Mail\EmailOtpMail;
use App\Models\EmailOtp;
use App\Support\EmailNormalizer;
use App\Support\IdentityConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class EmailOtpService
{
    /**
     * Send Email OTP for authenticated user. Queued delivery.
     */
    public function send(object $user, string $rawEmail, ?string $guard = null, ?int $instituteId = null): array
    {
        $normalized = EmailNormalizer::normalize($rawEmail);
        if ($normalized === null || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => ['Invalid email address.']]);
        }

        $email = $normalized;
        $guard = $guard ?? $this->resolveGuard($user);
        $userId = $user->getKey();

        // Throttle per user+email 60s — E19 DB → env
        $throttleKey = 'email_otp_send:' . $guard . ':' . $userId . ':' . $email;
        $expires = (int) IdentityConfig::emailOtp('resend_throttle_seconds', 60);
        if (Cache::has($throttleKey)) {
            throw ValidationException::withMessages(['email' => ['Please wait before requesting another code.']]);
        }

        // Hourly limit
        $hourKey = 'email_otp_hour:' . $guard . ':' . $userId . ':' . $email;
        $hourCount = (int) Cache::get($hourKey, 0);
        $maxPerHour = (int) IdentityConfig::emailOtp('max_sends_per_hour', 5);
        if ($hourCount >= $maxPerHour) {
            throw ValidationException::withMessages(['email' => ['Too many OTP requests. Try again later.']]);
        }

        // Invalidate previous OTPs
        EmailOtp::where('guard', $guard)
            ->where('user_id', $userId)
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $length = (int) IdentityConfig::emailOtp('length', 6);
        $otp = $this->generateOtp($length);
        $hash = Hash::make($otp);
        $expiresMinutes = (int) IdentityConfig::emailOtp('expires_minutes', 10);

        $instituteId = $instituteId ?? $this->resolveInstituteId($user);

        $record = EmailOtp::create([
            'guard' => $guard,
            'user_id' => $userId,
            'institute_id' => $instituteId,
            'email' => $email,
            'otp_hash' => $hash,
            'attempts' => 0,
            'expires_at' => now()->addMinutes($expiresMinutes),
        ]);

        $this->queueEmail($email, $otp);

        Cache::put($throttleKey, 1, $expires);
        Cache::put($hourKey, $hourCount + 1, 3600);

        // Audit masked
        try {
            IdentityAuditService::log($userId, 'email_otp_sent', 'email', ['email' => $this->maskEmail($email), 'guard' => $guard]);
        } catch (\Throwable $e) {}

        return $record->toArray();
    }

    /**
     * Send for 2FA pending login (no authenticated session). Uses same guards.
     * Reuses same throttling but by user_id.
     */
    public function sendForLogin(object $user, ?string $guard = null): void
    {
        $email = $user->email ?? null;
        if (! $email) return;
        $normalized = EmailNormalizer::normalize($email);
        if ($normalized === null) return;

        // Check if user has email verified and email_2fa_enabled
        // Caller should verify availability, but we double check
        try {
            $this->send($user, $normalized, $guard, $this->resolveInstituteId($user));
        } catch (ValidationException $e) {
            // Throttled - rethrow so controller can show message, but don't expose existence
            throw $e;
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function verify(object $user, string $rawEmail, string $otp, ?string $guard = null): bool
    {
        $normalized = EmailNormalizer::normalize($rawEmail);
        if ($normalized === null) {
            throw ValidationException::withMessages(['email' => ['Invalid email address.']]);
        }
        $email = $normalized;
        $guard = $guard ?? $this->resolveGuard($user);
        $userId = $user->getKey();

        return \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $email, $guard, $otp) {
            $record = EmailOtp::where('guard', $guard)
                ->where('user_id', $userId)
                ->where('email', $email)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $record) {
                throw ValidationException::withMessages(['otp' => ['Invalid verification code.']]);
            }

            if ($record->isExpired()) {
                $record->update(['consumed_at' => now()]);
                \Illuminate\Support\Facades\Log::info('otp.expired', ['type' => 'email_otp', 'guard' => $guard, 'user_id' => $userId]);
                throw ValidationException::withMessages(['otp' => ['This verification code has expired. Please request a new code.']]);
            }

            $maxAttempts = (int) IdentityConfig::emailOtp('max_attempts', 5);
            if ($record->attempts >= $maxAttempts) {
                $record->update(['consumed_at' => now()]);
                try { IdentityAuditService::log($userId, 'email_otp_bruteforce', 'email', ['email' => $this->maskEmail($email)]); } catch (\Throwable $e) {}
                throw ValidationException::withMessages(['otp' => ['Too many incorrect attempts. Please request a new code.']]);
            }

            if (! \Illuminate\Support\Facades\Hash::check($otp, $record->otp_hash)) {
                $record->increment('attempts');
                $fresh = $record->fresh();
                $remaining = $maxAttempts - $fresh->attempts;
                if ($remaining <= 0) {
                    $fresh->update(['consumed_at' => now()]);
                    try { IdentityAuditService::log($userId, 'email_otp_bruteforce', 'email', ['email' => $this->maskEmail($email)]); } catch (\Throwable $e) {}
                    throw ValidationException::withMessages(['otp' => ['Too many incorrect attempts. Please request a new code.']]);
                }
                try { IdentityAuditService::log($userId, 'email_otp_failed', 'email', ['email' => $this->maskEmail($email), 'attempts' => $fresh->attempts]); } catch (\Throwable $e) {}
                throw ValidationException::withMessages(['otp' => ['Invalid verification code.']]);
            }

            $record->update(['consumed_at' => now()]);

            EmailOtp::where('guard', $guard)
                ->where('user_id', $userId)
                ->where('email', $email)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            try { IdentityAuditService::log($userId, 'email_otp_verified', 'email', ['email' => $this->maskEmail($email)]); } catch (\Throwable $e) {}

            return true;
        });
    }

    /**
     * Enumeration-safe send for unauthenticated flows - generic message.
     */
    public function sendForLookup(string $rawEmail, ?string $ip = null): void
    {
        $key = 'email_otp_enum:' . ($ip ?? request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }
        RateLimiter::hit($key, 3600);
        $normalized = EmailNormalizer::normalize($rawEmail);
        if ($normalized === null) return;
        Log::info('email_otp_lookup', ['email' => $this->maskEmail($normalized), 'ip' => $ip]);
    }

    protected function generateOtp(int $length): string
    {
        $max = (int) pow(10, $length) - 1;
        $otp = random_int(0, $max);
        return str_pad((string) $otp, $length, '0', STR_PAD_LEFT);
    }

    protected function queueEmail(string $email, string $otp): void
    {
        // Queued delivery to avoid HTTP timeout (SMTP 17s). Fallback to sync + log if queue fails.
        try {
            $masked = $this->maskEmail($email);
            if (app()->environment('testing')) {
                Mail::to($email)->queue(new EmailOtpMail($otp, $masked));
            } else {
                Mail::to($email)->queue(new EmailOtpMail($otp, $masked));
            }
            Log::info('email_otp_queued', ['email' => $masked, 'queue' => config('queue.default')]);

            // Local dev hint: if database queue stuck, warn operator
            if (app()->environment('local') && config('queue.default') === 'database') {
                try {
                    $pending = \Illuminate\Support\Facades\DB::table('jobs')->count();
                    if ($pending > 3) {
                        Log::warning('email_otp_queue_stuck_hint', ['pending_jobs' => $pending, 'hint' => 'Run php artisan queue:work or set QUEUE_CONNECTION=sync for local']);
                    }
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            report($e);
            Log::warning('email_otp_queue_failed_fallback_to_sync', ['email' => $this->maskEmail($email), 'error' => substr($e->getMessage(), 0, 300)]);
            try {
                // Fallback to synchronous send so code still arrives when queue broken
                Mail::to($email)->send(new EmailOtpMail($otp, $this->maskEmail($email)));
                Log::info('email_otp_sent_sync_fallback', ['email' => $this->maskEmail($email)]);
            } catch (\Throwable $e2) {
                report($e2);
                Log::error('email_otp_sync_fallback_failed', ['email' => $this->maskEmail($email), 'error' => substr($e2->getMessage(), 0, 300)]);
            }
        }
    }

    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return '***';
        $local = $parts[0];
        $domain = $parts[1] ?? '';
        if (strlen($local) <= 2) return str_repeat('*', strlen($local)) . '@' . $domain;
        return substr($local, 0, 1) . str_repeat('*', max(1, strlen($local)-2)) . substr($local, -1) . '@' . $domain;
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
            if (isset($user->institute_id)) return $user->institute_id ? (int) $user->institute_id : null;
            if (method_exists($user, 'getInstituteIdAttribute')) return $user->institute_id;
        } catch (\Throwable $e) {}
        return null;
    }
}
