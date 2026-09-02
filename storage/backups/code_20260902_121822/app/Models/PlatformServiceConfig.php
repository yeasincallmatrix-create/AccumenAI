<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * platform_service_configs — Classification: B. FUTURE/PLANNED SOURCE
 *
 * Currently EMPTY/UNUSED. Active truth for platform settings is `settings` K/V
 * via App\Models\Setting + App\Services\Platform\PlatformSettingsService.
 * This table is RESERVED for future normalized provider configs (e.g., multiple
 * SMS gateways, per-provider structured params) when K/V becomes insufficient.
 * Do NOT drop without explicit approval — migration shipped [54] and rollback
 * compatibility requires keeping table. If populated, wire a resolver similar
 * to PlatformSettingsService. Search: grep -R PlatformServiceConfig — no
 * runtime read yet (verified).
 */
class PlatformServiceConfig extends Model
{
    protected $table = 'platform_service_configs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }

    public static function getValue(string $service, string $key, ?string $provider = null, mixed $default = null): mixed
    {
        $row = static::query()
            ->where('service', $service)
            ->where('key', $key)
            ->when($provider !== null, fn ($q) => $q->where('provider', $provider))
            ->first();

        if (! $row) {
            return $default;
        }

        if ($row->is_encrypted && $row->value !== null) {
            try {
                return Crypt::decryptString($row->value);
            } catch (Throwable) {
                return $row->value;
            }
        }

        return $row->value;
    }

    public static function setValue(string $service, string $key, mixed $value, ?string $provider = null, bool $encrypted = false): void
    {
        $store = $value;
        if ($encrypted && $value !== null && $value !== '') {
            $store = Crypt::encryptString((string) $value);
        }

        static::query()->updateOrCreate(
            ['service' => $service, 'provider' => $provider, 'key' => $key],
            ['value' => $store === null ? null : (string) $store, 'is_encrypted' => $encrypted]
        );
    }

    public static function isConfigured(string $service, string $key, ?string $provider = null): bool
    {
        return filled(static::getValue($service, $key, $provider));
    }
}
