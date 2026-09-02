<?php

namespace App\Support;

use App\Models\Setting;

/**
 * E19 platform DB overrides for OTP/2FA with env fallback.
 * Precedence: Setting (sms_otp.*, email_otp.*, phone_verification.*, 2fa.*, login.*) → config(identity.*) → default
 * Reuses Setting K/V without creating another engine.
 */
class IdentityConfig
{
    public static function phoneOtp(string $key, mixed $default = null): mixed
    {
        $map = [
            'length' => 'sms_otp.length',
            'expires_minutes' => 'sms_otp.expiry',
            'max_attempts' => 'sms_otp.max_attempts',
            'resend_throttle_seconds' => 'sms_otp.resend_cooldown',
            'max_sends_per_hour' => 'sms_otp.max_resend',
        ];
        $settingKey = $map[$key] ?? "sms_otp.{$key}";
        $db = Setting::get($settingKey);
        if ($db !== null && $db !== '') return is_numeric($db) ? (int) $db : $db;
        // also check phone_otp.* legacy if sms not set? fallback to identity
        return config("identity.phone_otp.{$key}", $default);
    }

    public static function emailOtp(string $key, mixed $default = null): mixed
    {
        $map = [
            'length' => 'email_otp.length',
            'expires_minutes' => 'email_otp.expiry',
            'max_attempts' => 'email_otp.max_attempts',
            'resend_throttle_seconds' => 'email_otp.resend_cooldown',
            'max_sends_per_hour' => 'email_otp.max_resend',
        ];
        $settingKey = $map[$key] ?? "email_otp.{$key}";
        $db = Setting::get($settingKey);
        if ($db !== null && $db !== '') return is_numeric($db) ? (int) $db : $db;
        return config("identity.email_otp.{$key}", $default);
    }

    public static function phoneVerification(string $key, mixed $default = null): mixed
    {
        $db = Setting::get("phone_verification.{$key}");
        if ($db !== null && $db !== '') return $db;
        return $default;
    }

    public static function twoFactor(string $key, mixed $default = null): mixed
    {
        $db = Setting::get("2fa.{$key}");
        if ($db !== null && $db !== '') return $db;
        // fallback to identity.two_factor
        $fallbackMap = [
            'allow_totp' => null,
            'allow_sms' => null,
            'allow_email' => null,
            'preferred' => config('identity.two_factor.preferred_methods.0', 'totp'),
        ];
        return $fallbackMap[$key] ?? $default;
    }

    public static function login(string $key, mixed $default = null): mixed
    {
        $db = Setting::get("login.{$key}");
        if ($db !== null && $db !== '') return $db;
        return $default;
    }

    // Helpers for direct use
    public static function isEmailOtpEnabled(): bool
    {
        $v = Setting::get('email_otp.enabled');
        if ($v !== null && $v !== '') return $v === '1' || $v === 'true';
        return true;
    }

    public static function isSmsOtpEnabled(): bool
    {
        $v = Setting::get('sms_otp.enabled');
        if ($v !== null && $v !== '') return $v === '1' || $v === 'true';
        return true;
    }
}
