<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class Setting extends Model
{
    protected $table = 'settings';

    public $timestamps = true;

    protected $guarded = [];

    /**
     * Keys that are secrets and must never be returned to the browser or
     * written in plain text. They are encrypted at rest with the app key.
     */
    protected static array $encrypted = [
        'ai.api_key',
        'ai.api_key_openai',
        'ai.api_key_anthropic',
        'ai.api_key_gemini',
        'ai.api_key_groq',
        'ai.api_key_custom',
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
        'ai.openai_api_key',
        'ai.custom_api_key',
        'bkash.app_key',
        'bkash.app_secret',
        'bkash.password',
        'bkash.webhook_secret',
    ];

    public static function masked(string $key): string
    {
        $val = static::get($key);
        if (! filled($val)) {
            return 'NOT CONFIGURED';
        }
        return 'Configured ••••••••';
    }

    public static function isConfigured(string $key): bool
    {
        return filled(static::get($key));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // Request-level in-memory cache + short cache to avoid N queries per request
        // (View composer + boot both call this 3-4 times; without cache = 100+ queries).
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key] ?? $default;
        }

        // Try 60s cache first to collapse duplicate concurrent lookups
        $cacheKey = 'setting:'.$key;
        try {
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                $val = \Illuminate\Support\Facades\Cache::get($cacheKey);
                $cache[$key] = $val;
                return $val ?? $default;
            }
        } catch (Throwable) {}

        try {
            $row = static::query()->where('key', $key)->first();
        } catch (Throwable $e) {
            // Tablespace missing / DB down — fail open to $default instead of 500 (see 194 error on settings)
            try { \Illuminate\Support\Facades\Log::warning('Setting::get fallback', ['key' => $key, 'error' => $e->getMessage()]); } catch (Throwable) {}
            return $default;
        }

        if (! $row) {
            $cache[$key] = null;
            try { \Illuminate\Support\Facades\Cache::put($cacheKey, null, 60); } catch (Throwable) {}
            return $default;
        }

        if (in_array($key, static::$encrypted, true) && $row->value !== null) {
            try {
                $val = Crypt::decryptString($row->value);
            } catch (Throwable) {
                // Legacy plaintext value written before encryption was enabled.
                $val = $row->value;
            }
            $cache[$key] = $val;
            try { \Illuminate\Support\Facades\Cache::put($cacheKey, $val, 60); } catch (Throwable) {}
            return $val;
        }

        $cache[$key] = $row->value;
        try { \Illuminate\Support\Facades\Cache::put($cacheKey, $row->value, 60); } catch (Throwable) {}
        return $row->value;
    }

    public static function set(string $key, mixed $value): void
    {
        if (in_array($key, static::$encrypted, true) && $value !== null && $value !== '') {
            $value = Crypt::encryptString((string) $value);
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value]
        );

        // Invalidate caches on write
        try { \Illuminate\Support\Facades\Cache::forget('setting:'.$key); } catch (Throwable) {}
    }
}
