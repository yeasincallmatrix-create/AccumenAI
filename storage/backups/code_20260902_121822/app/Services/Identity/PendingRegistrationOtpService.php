<?php

namespace App\Services\Identity;

use App\Mail\EmailOtpMail;
use App\Models\PendingRegistration;
use App\Support\IdentityConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PendingRegistrationOtpService
{
    public function send(PendingRegistration $pending): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($pending) {
            $locked = PendingRegistration::whereKey($pending->id)->lockForUpdate()->firstOrFail();
            $email = $locked->email;
            $throttleKey = 'pending_otp_send:' . $locked->id . ':' . $email;
            $expires = (int) IdentityConfig::emailOtp('resend_throttle_seconds', 60);
            if (Cache::has($throttleKey)) {
                throw ValidationException::withMessages(['otp' => ['Please wait before requesting another code.']]);
            }
            $hourKey = 'pending_otp_hour:' . $locked->id . ':' . $email;
            $hourCount = (int) Cache::get($hourKey, 0);
            $maxPerHour = (int) IdentityConfig::emailOtp('max_sends_per_hour', 5);
            if ($hourCount >= $maxPerHour) {
                throw ValidationException::withMessages(['otp' => ['Too many OTP requests. Try again later.']]);
            }

            $length = (int) IdentityConfig::emailOtp('length', 6);
            $otp = $this->generateOtp($length);
            $hash = Hash::make($otp);
            $expiresMinutes = (int) IdentityConfig::emailOtp('expires_minutes', 10);

            $locked->update([
                'otp_hash' => $hash,
                'otp_expires_at' => now()->addMinutes($expiresMinutes),
                'attempts' => 0,
                'last_sent_at' => now(),
                'resend_count' => $locked->resend_count + 1,
            ]);

            $this->queueEmail($email, $otp);

            Cache::put($throttleKey, 1, $expires);
            Cache::put($hourKey, $hourCount + 1, 3600);

            Log::info('otp.generated', ['type' => 'pending_registration', 'pending_id' => $locked->id, 'email' => $this->maskEmail($email)]);
            Log::info('pending_registration_otp_sent', ['pending_id' => $locked->id, 'email' => $this->maskEmail($email)]);
        });
    }

    public function verify(PendingRegistration $pending, string $otp): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($pending, $otp) {
            $locked = PendingRegistration::whereKey($pending->id)->lockForUpdate()->firstOrFail();
            if ($locked->verified_at !== null) {
                throw ValidationException::withMessages(['otp' => ['This verification code has already been used. Please request a new code.']]);
            }
            if ($locked->otp_hash === null || $locked->otp_expires_at === null) {
                throw ValidationException::withMessages(['otp' => ['Invalid or expired code.']]);
            }
            if ($locked->otp_expires_at->isPast()) {
                Log::info('otp.expired', ['type' => 'pending_registration', 'pending_id' => $locked->id]);
                throw ValidationException::withMessages(['otp' => ['This verification code has expired. Please request a new code.']]);
            }
            $maxAttempts = (int) IdentityConfig::emailOtp('max_attempts', 5);
            if ($locked->attempts >= $maxAttempts) {
                Log::info('otp.locked', ['type' => 'pending_registration', 'pending_id' => $locked->id]);
                throw ValidationException::withMessages(['otp' => ['Too many incorrect attempts. Please request a new code.']]);
            }
            if (! Hash::check($otp, $locked->otp_hash)) {
                $locked->increment('attempts');
                $attempts = $locked->fresh()->attempts;
                if ($attempts >= $maxAttempts) {
                    Log::info('otp.locked', ['type' => 'pending_registration', 'pending_id' => $locked->id, 'attempts' => $attempts]);
                    throw ValidationException::withMessages(['otp' => ['Too many incorrect attempts. Please request a new code.']]);
                }
                Log::info('otp.verification_failed', ['type' => 'pending_registration', 'pending_id' => $locked->id, 'attempts' => $attempts]);
                throw ValidationException::withMessages(['otp' => ['Invalid verification code.']]);
            }
            $locked->update(['verified_at' => now(), 'expires_at' => now()->addHours(48)]);
            Log::info('otp.verification_success', ['type' => 'pending_registration', 'pending_id' => $locked->id, 'expires_at' => (string) $locked->fresh()->expires_at]);
            return true;
        });
    }

    protected function generateOtp(int $length): string
    {
        $max = (int) pow(10, $length) - 1;
        $otp = random_int(0, $max);
        return str_pad((string) $otp, $length, '0', STR_PAD_LEFT);
    }

    protected function queueEmail(string $email, string $otp): void
    {
        try {
            $masked = $this->maskEmail($email);
            Mail::to($email)->queue(new EmailOtpMail($otp, $masked));
            Log::info('pending_otp_queued', ['email' => $masked]);
        } catch (\Throwable $e) {
            report($e);
            Log::warning('pending_otp_queue_failed_fallback', ['email' => $this->maskEmail($email), 'error' => substr($e->getMessage(), 0, 300)]);
            try {
                Mail::to($email)->send(new EmailOtpMail($otp, $this->maskEmail($email)));
            } catch (\Throwable $e2) {
                report($e2);
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
}
