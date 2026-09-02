<?php

namespace App\Services\Platform;

use App\Models\PlatformAuditLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class PlatformSettingsService
{
    public const SECRET_KEYS = [
        'smtp.password',
        'sms.api_key',
        'sms.api_secret',
        'sms.password',
        'payment.api_key',
        'payment.api_secret',
        'payment.webhook_secret',
        'maps.api_key',
        'storage.s3.secret',
        'webhook.secret',
        'ai.api_key',
        'ai.openai_api_key',
        'ai.custom_api_key',
        'bkash.app_key',
        'bkash.app_secret',
        'bkash.password',
        'bkash.webhook_secret',
    ];

    public const GENERAL_DEFAULTS = [
        'app.name' => 'Accumen AI',
        'app.short_name' => 'AccumenAI',
        'app.url' => 'http://localhost',
        'app.timezone' => 'Asia/Dhaka',
        'app.country' => 'BD',
        'app.currency' => 'BDT',
        'app.language' => 'en',
        'app.date_format' => 'd M Y',
        'app.time_format' => 'H:i',
        'app.pagination' => '15',
        'app.contact_email' => '',
        'app.support_phone' => '',
        'app.support_url' => '',
        'app.maintenance' => '0',
        'app.maintenance_message' => '',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $db = Setting::get($key);
        if ($db !== null && $db !== '') {
            return $db;
        }
        if (array_key_exists($key, self::GENERAL_DEFAULTS)) {
            return self::GENERAL_DEFAULTS[$key];
        }
        // fallback to env/config for legacy keys
        return $default ?? self::envFallback($key);
    }

    private static function envFallback(string $key): mixed
    {
        return match ($key) {
            'smtp.host' => config('mail.mailers.smtp.host'),
            'smtp.port' => config('mail.mailers.smtp.port'),
            'smtp.encryption' => config('mail.mailers.smtp.encryption'),
            'smtp.username' => config('mail.mailers.smtp.username'),
            'smtp.password' => config('mail.mailers.smtp.password'),
            default => null,
        };
    }

    public static function set(string $key, mixed $value, string $section = 'general'): void
    {
        $isSecret = in_array($key, self::SECRET_KEYS, true);
        // Masked placeholder should not overwrite
        if ($isSecret && $value === '••••••••') {
            return;
        }
        Setting::set($key, $value);
        $auditValue = $isSecret ? 'credential_changed' : (string) $value;
        // Never log secret plaintext
        if ($isSecret) {
            PlatformAuditLog::record($section, $key, 'credential_changed');
        } else {
            PlatformAuditLog::record($section, $key, 'updated', ['value' => substr($auditValue, 0, 200)]);
        }
    }

    public static function masked(string $key): string
    {
        $val = Setting::get($key);
        return filled($val) ? 'Configured ••••••••' : 'NOT CONFIGURED';
    }

    public static function otpSettings(): array
    {
        return [
            'email_otp.enabled' => self::get('email_otp.enabled', '1'),
            'email_otp.length' => self::get('email_otp.length', '6'),
            'email_otp.expiry' => self::get('email_otp.expiry', '10'),
            'email_otp.max_attempts' => self::get('email_otp.max_attempts', '5'),
            'email_otp.resend_cooldown' => self::get('email_otp.resend_cooldown', '60'),
            'email_otp.max_resend' => self::get('email_otp.max_resend', '5'),
            'email_otp.queue' => self::get('email_otp.queue', '1'),
            'sms_otp.enabled' => self::get('sms_otp.enabled', '1'),
            'sms_otp.length' => self::get('sms_otp.length', '6'),
            'sms_otp.expiry' => self::get('sms_otp.expiry', '10'),
            'sms_otp.max_attempts' => self::get('sms_otp.max_attempts', '5'),
            'sms_otp.resend_cooldown' => self::get('sms_otp.resend_cooldown', '60'),
            'sms_otp.max_resend' => self::get('sms_otp.max_resend', '5'),
            'sms_otp.queue' => self::get('sms_otp.queue', '1'),
            'phone_verification.required_registration' => self::get('phone_verification.required_registration', '0'),
            'phone_verification.required_2fa' => self::get('phone_verification.required_2fa', '1'),
            'phone_verification.resend_cooldown' => self::get('phone_verification.resend_cooldown', '60'),
            'phone_verification.expiry' => self::get('phone_verification.expiry', '10'),
        ];
    }

    public static function twoFactorSettings(): array
    {
        return [
            '2fa.allow_totp' => self::get('2fa.allow_totp', '1'),
            '2fa.allow_email' => self::get('2fa.allow_email', '1'),
            '2fa.allow_sms' => self::get('2fa.allow_sms', '1'),
            '2fa.preferred' => self::get('2fa.preferred', 'totp'),
            '2fa.allow_user_change' => self::get('2fa.allow_user_change', '1'),
            '2fa.allow_backup' => self::get('2fa.allow_backup', '1'),
            '2fa.require_verified_email' => self::get('2fa.require_verified_email', '1'),
            '2fa.require_verified_phone' => self::get('2fa.require_verified_phone', '1'),
            '2fa.max_failed' => self::get('2fa.max_failed', '5'),
            '2fa.challenge_expiry' => self::get('2fa.challenge_expiry', '10'),
        ];
    }

    public static function loginSecuritySettings(): array
    {
        return [
            'login.max_attempts' => self::get('login.max_attempts', '10'),
            'login.lockout_duration' => self::get('login.lockout_duration', '15'),
            'login.session_lifetime' => self::get('login.session_lifetime', '120'),
            'login.remember_me' => self::get('login.remember_me', '1'),
            'login.2fa_challenge_lifetime' => self::get('login.2fa_challenge_lifetime', '10'),
            'password.min_length' => self::get('password.min_length', '8'),
        ];
    }

    /**
     * Effective login threshold for guard — minimum 10 for all except platform_admin (super user).
     * Super user stays strict (5) when no explicit setting; regular users floor at 10.
     */
    public static function effectiveLoginThreshold(string $guardName): int
    {
        $rawVal = \App\Models\Setting::get('login.max_attempts');
        if ($rawVal === null || $rawVal === '') {
            return $guardName === 'platform_admin' ? 5 : 10;
        }
        $raw = (int) $rawVal;
        if ($guardName === 'platform_admin') {
            return max(5, min(20, $raw ?: 5));
        }
        return max(10, min(20, $raw ?: 10));
    }

    public static function effectiveLockoutMinutes(string $guardName): int
    {
        $raw = (int) (self::get('login.lockout_duration', '15') ?? 15);
        if ($guardName === 'platform_admin') {
            return max(5, min(60, $raw ?: 15));
        }
        // Regular users: shorter lockout for smoother UX (5 min default if misconfigured)
        return max(5, min(60, $raw ?: 15));
    }
}
