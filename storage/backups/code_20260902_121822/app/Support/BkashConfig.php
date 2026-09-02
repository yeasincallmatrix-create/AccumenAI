<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Payment/Bkash resolver: E19 DB → env → default
 * Does NOT create another payment engine; only resolves credentials.
 */
class BkashConfig
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $map = [
            'app_key' => 'payment.api_key',
            'app_secret' => 'payment.api_secret',
            'username' => 'payment.username',
            'password' => 'payment.password',
            'base_url' => 'payment.base_url',
            'sandbox' => 'payment.mode',
            'webhook_secret' => 'payment.webhook_secret',
        ];
        $settingKey = $map[$key] ?? "payment.{$key}";
        $db = Setting::get($settingKey);
        if ($db !== null && $db !== '') {
            if ($key === 'sandbox') return $db === 'sandbox' || $db === '1' ? true : false;
            return $db;
        }
        // fallback to config/services.php env
        return config("services.bkash.{$key}", $default);
    }

    public static function isEnabled(): bool
    {
        $v = Setting::get('payment.enabled');
        if ($v !== null && $v !== '') return $v === '1';
        return false;
    }

    public static function isConfigured(): bool
    {
        return filled(self::get('app_key')) && filled(self::get('app_secret'));
    }
}
