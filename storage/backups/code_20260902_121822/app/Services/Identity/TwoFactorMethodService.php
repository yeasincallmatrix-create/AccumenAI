<?php

namespace App\Services\Identity;

use App\Models\Guardian;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Setting;
use App\Models\User;

/**
 * Determines available 2FA methods and preferred method.
 * Keeps Phone Verification distinct from 2FA.
 */
class TwoFactorMethodService
{
    /**
     * Get available 2FA methods for user.
     * Returns array of method strings: totp, sms, email
     */
    public function availableMethods(object $user): array
    {
        $methods = [];

        // TOTP: has secret and confirmed
        if ($this->hasTotp($user)) {
            $methods[] = 'totp';
        }

        // SMS 2FA: enabled + phone verified
        if ($this->hasSms2FA($user)) {
            $methods[] = 'sms';
        }

        // Email 2FA: enabled + email verified
        if ($this->hasEmail2FA($user)) {
            $methods[] = 'email';
        }

        return $methods;
    }

    public function hasTotp(object $user): bool
    {
        // Platform toggle: 2fa.allow_totp → if 0, TOTP is disabled globally
        $platform = Setting::get('2fa.allow_totp');
        if ($platform !== null && $platform !== '' && $platform !== '1' && $platform !== true) return false;
        // TwoFactorAuthenticatable trait provides hasEnabledTwoFactorAuthentication + two_factor_confirmed_at
        if (! method_exists($user, 'hasEnabledTwoFactorAuthentication')) {
            return false;
        }
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return false;
        }
        // Fortify confirm => two_factor_confirmed_at must be not null
        return ! empty($user->two_factor_confirmed_at);
    }

    public function hasSms2FA(object $user): bool
    {
        $platform = Setting::get('2fa.allow_sms');
        if ($platform !== null && $platform !== '' && $platform !== '1' && $platform !== true) return false;
        $enabled = (bool) ($user->sms_2fa_enabled ?? false);
        if (! $enabled) return false;
        // Requires verified phone
        if (empty($user->phone)) return false;
        if (property_exists($user, 'phone_verified_at') || isset($user->phone_verified_at)) {
            return ! empty($user->phone_verified_at);
        }
        // For institute_users etc, if no phone_verified_at column, consider phone presence as verified
        // But require phone_verified_at if column exists
        return true;
    }

    public function hasEmail2FA(object $user): bool
    {
        $platform = Setting::get('2fa.allow_email');
        if ($platform !== null && $platform !== '' && $platform !== '1' && $platform !== true) return false;
        $enabled = (bool) ($user->email_2fa_enabled ?? false);
        if (! $enabled) return false;
        if (empty($user->email)) return false;
        return ! empty($user->email_verified_at);
    }

    public function preferredMethod(object $user): ?string
    {
        $available = $this->availableMethods($user);
        if (empty($available)) return null;

        $pref = $user->preferred_2fa_method ?? null;
        if ($pref && in_array($pref, $available, true)) {
            return $pref;
        }

        // Platform preferred fallback
        $platformPref = Setting::get('2fa.preferred');
        if ($platformPref && in_array($platformPref, $available, true)) {
            return $platformPref;
        }

        // Fallback priority: totp > sms > email
        foreach (['totp', 'sms', 'email'] as $m) {
            if (in_array($m, $available, true)) return $m;
        }

        return $available[0];
    }

    public static function maxFailedAttempts(): int
    {
        $v = Setting::get('2fa.max_failed');
        if ($v !== null && $v !== '') return max(1, min(20, (int) $v));
        return 10;
    }

    public static function challengeExpiryMinutes(): int
    {
        $v = Setting::get('2fa.challenge_expiry');
        if ($v !== null && $v !== '') return max(1, min(60, (int) $v));
        return 10;
    }

    public function is2FAEnabled(object $user): bool
    {
        return ! empty($this->availableMethods($user));
    }

    /**
     * Mask phone for UI display
     */
    public function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) return str_repeat('*', $len);
        return substr($phone, 0, 3) . str_repeat('*', $len - 6) . substr($phone, -3);
    }

    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return '***';
        $local = $parts[0];
        $domain = $parts[1];
        if (strlen($local) <= 2) return str_repeat('*', strlen($local)) . '@' . $domain;
        return substr($local,0,1) . str_repeat('*', max(1, strlen($local)-2)) . substr($local,-1) . '@' . $domain;
    }

    /**
     * Normalize alternative methods excluding current
     */
    public function alternateMethods(object $user, string $current): array
    {
        return array_values(array_filter($this->availableMethods($user), fn($m) => $m !== $current));
    }

    /**
     * Check if method is valid for user (verified and enabled)
     */
    public function isMethodAvailable(object $user, string $method): bool
    {
        return in_array($method, $this->availableMethods($user), true);
    }
}
