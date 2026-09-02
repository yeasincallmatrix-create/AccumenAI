<?php

namespace App\Services\Platform;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class SmsConfig
{
    public static function get(string $key, mixed $default = null): mixed
    {
        // Setting::get already decrypts encrypted keys
        return Setting::get('sms.'.$key, $default);
    }

    public static function all(): array
    {
        return [
            'provider' => Setting::get('sms.provider', 'log'),
            'type' => Setting::get('sms.type', 'log'),
            'api_url' => Setting::get('sms.api_url', ''),
            'http_method' => Setting::get('sms.http_method', 'POST'),
            'api_key' => Setting::get('sms.api_key', ''),
            'api_secret' => Setting::get('sms.api_secret', ''),
            'username' => Setting::get('sms.username', ''),
            'password' => Setting::get('sms.password', ''),
            'sender_id' => Setting::get('sms.sender_id', ''),
            'sender_name' => Setting::get('sms.sender_name', ''),
            'auth_type' => Setting::get('sms.auth_type', 'none'),
            'headers' => Setting::get('sms.headers', ''),
            'params' => Setting::get('sms.params', ''),
            'message_param' => Setting::get('sms.message_param', 'message'),
            'phone_param' => Setting::get('sms.phone_param', 'to'),
            'success_condition' => Setting::get('sms.success_condition', ''),
            'enabled' => Setting::get('sms.enabled', '1'),
        ];
    }

    public static function activeProvider(): string
    {
        $enabled = Setting::get('sms.enabled');
        if ($enabled !== null && $enabled !== '' && $enabled !== '1' && $enabled !== 1 && $enabled !== true) {
            return 'log';
        }
        $p = Setting::get('sms.provider', config('notifications.sms.default', 'log'));
        if (! filled($p)) {
            return 'log';
        }
        $registry = array_keys(config('notifications.sms.providers', []));
        if (! in_array($p, $registry, true)) {
            return 'log';
        }
        return $p;
    }

    public static function providerOptions(): array
    {
        return [
            'api_key' => (string) (Setting::get('sms.api_key', '')),
            'api_secret' => (string) (Setting::get('sms.api_secret', '')),
            'from' => (string) (Setting::get('sms.sender_id', '') ?: Setting::get('sms.from', '')),
            'sender_id' => (string) (Setting::get('sms.sender_id', '')),
            'url' => (string) (Setting::get('sms.api_url', config('notifications.sms.http.url', ''))),
        ];
    }
}
