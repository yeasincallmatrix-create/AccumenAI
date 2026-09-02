<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Runtime-read AI configuration.
 *
 * Values configured by the Super Admin (stored in the platform `settings`
 * key/value table) win over the static `config/ai.php` / env defaults. API
 * credentials and secrets are never exposed to institute users.
 */
class AiConfig
{
    public static function enabled(bool $platformOnly = false): bool
    {
        try {
            $value = Setting::get('ai.enabled');
        } catch (\Throwable) {
            $value = null;
        }

        return match (true) {
            $value === '1', $value === 'true', $value === true => true,
            $value !== null => false,
            default => (bool) config('ai.enabled'),
        };
    }

    public static function provider(): string
    {
        return (string) (Setting::get('ai.provider') ?? config('ai.provider', 'openai'));
    }

    public static function apiKey(?string $provider = null): string
    {
        $provider = $provider ?? self::provider();
        // First, check active key in ai_api_keys table
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('ai_api_keys')) {
                $active = \App\Models\AiApiKey::forProvider($provider)->active()->first();
                if ($active && filled($active->api_key)) {
                    return (string) $active->api_key;
                }
            }
        } catch (\Throwable $e) {}
        // per-provider setting
        $perKey = Setting::get("ai.api_key_{$provider}");
        if (filled($perKey)) {
            return (string) $perKey;
        }
        // fallback to generic (legacy)
        $generic = Setting::get('ai.api_key');
        if (filled($generic)) {
            return (string) $generic;
        }
        return (string) config('ai.providers.'.$provider.'.api_key', config('ai.providers.openai.api_key', ''));
    }

    public static function baseUrl(?string $provider = null): string
    {
        $provider = $provider ?? self::provider();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('ai_api_keys')) {
                $active = \App\Models\AiApiKey::forProvider($provider)->active()->first();
                if ($active && filled($active->base_url)) {
                    return (string) $active->base_url;
                }
            }
        } catch (\Throwable $e) {}
        $per = Setting::get("ai.base_url_{$provider}");
        if (filled($per)) {
            return (string) $per;
        }
        $generic = Setting::get('ai.base_url');
        if (filled($generic)) {
            return (string) $generic;
        }
        return (string) config('ai.providers.'.$provider.'.base_url', config('ai.providers.openai.base_url', 'https://api.openai.com/v1'));
    }

    public static function model(?string $provider = null): string
    {
        $provider = $provider ?? self::provider();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('ai_api_keys')) {
                $active = \App\Models\AiApiKey::forProvider($provider)->active()->first();
                if ($active && filled($active->model)) {
                    return (string) $active->model;
                }
            }
        } catch (\Throwable $e) {}
        $per = Setting::get("ai.model_{$provider}");
        if (filled($per)) {
            return (string) $per;
        }
        $generic = Setting::get('ai.model');
        if (filled($generic)) {
            return (string) $generic;
        }
        return (string) config('ai.providers.'.$provider.'.model', config('ai.default_model', 'gpt-4o-mini'));
    }

    public static function globalInstructions(): string
    {
        return (string) (Setting::get('ai.global_instructions') ?? config('ai.global_instructions', ''));
    }

    public static function maxTokens(): int
    {
        return (int) (Setting::get('ai.max_tokens') ?? config('ai.max_tokens', 900));
    }

    public static function temperature(): float
    {
        return (float) (Setting::get('ai.temperature') ?? config('ai.temperature', 0.2));
    }

    public static function storePrompts(): bool
    {
        $value = Setting::get('ai.log_prompts');

        return match (true) {
            $value === '1', $value === 'true', $value === true => true,
            $value !== null => false,
            default => (bool) config('ai.log.store_prompts', false),
        };
    }

    public static function timeout(): int
    {
        return (int) (Setting::get('ai.timeout')
            ?? config('ai.providers.'.self::provider().'.timeout', 60));
    }

    public static function responseLanguage(): string
    {
        return (string) (Setting::get('ai.response_language') ?? 'auto');
    }

    public static function dailyLimit(): int
    {
        return (int) (Setting::get('ai.daily_limit') ?? 0);
    }

    public static function monthlyLimit(): int
    {
        return (int) (Setting::get('ai.monthly_limit') ?? 0);
    }

    public static function maxToolRounds(): int
    {
        return (int) config('ai.max_tool_rounds', 5);
    }

    public static function features(): array
    {
        $saved = Setting::get('ai.features');

        if ($saved !== null) {
            $features = is_array($saved) ? $saved : json_decode((string) $saved, true);

            return is_array($features) ? array_values(array_filter($features)) : [];
        }

        return config('ai.features', []);
    }
}
