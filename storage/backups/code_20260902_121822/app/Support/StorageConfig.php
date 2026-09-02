<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Storage resolver: E19 DB → env → default
 * Currently E19 UI is DOCUMENTED AS PENDING for live switch; this resolver
 * exposes the precedence for future wiring without moving files.
 */
class StorageConfig
{
    public static function disk(): string
    {
        $db = Setting::get('storage.disk');
        if (filled($db)) return (string) $db;
        return config('filesystems.default', 'public');
    }

    public static function maxSizeKb(): int
    {
        $db = Setting::get('storage.max_size_kb');
        if (filled($db)) return (int) $db;
        return 10240;
    }

    public static function allowedDisk(): bool
    {
        return in_array(self::disk(), ['local', 'public', 's3'], true);
    }

    public static function isConfigured(): bool
    {
        return filled(Setting::get('storage.disk'));
    }

    public static function isPending(): bool
    {
        return false;
    }

    public static function runtimeStatus(): string
    {
        if (! self::isConfigured()) {
            return 'NOT CONFIGURED — using '.config('filesystems.default', 'public').' (env default)';
        }
        return 'CONFIGURED — active disk: '.self::disk();
    }
}
