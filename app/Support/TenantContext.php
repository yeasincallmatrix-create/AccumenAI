<?php

namespace App\Support;

/**
 * Holds the currently authenticated institute context.
 *
 * A middleware (set in a later phase) calls TenantContext::set($instituteId)
 * for institute-user requests. While no context is set the tenant scope is
 * inactive, so platform-level queries and CLI commands see all rows.
 */
final class TenantContext
{
    private static ?int $instituteId = null;

    public static function set(?int $instituteId): void
    {
        self::$instituteId = $instituteId;
    }

    public static function id(): ?int
    {
        return self::$instituteId;
    }

    public static function enabled(): bool
    {
        return self::$instituteId !== null;
    }

    public static function clear(): void
    {
        self::$instituteId = null;
    }
}
