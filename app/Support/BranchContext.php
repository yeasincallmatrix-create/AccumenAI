<?php

namespace App\Support;

/**
 * Holds the branch the currently authenticated user is restricted to.
 *
 * Mirrors TenantContext: a middleware (SetTenantContext) sets the branch from
 * the authenticated institute user's `branch_id`. While no context is set the
 * branch scope is inactive, so platform-level queries, CLI commands and users
 * without an assigned branch see all rows. A user with `branch_id = null`
 * (owner / institute admin) is NOT restricted to any branch.
 */
final class BranchContext
{
    private static ?int $branchId = null;

    public static function set(?int $branchId): void
    {
        self::$branchId = $branchId;
    }

    public static function id(): ?int
    {
        return self::$branchId;
    }

    public static function enabled(): bool
    {
        return self::$branchId !== null;
    }

    public static function clear(): void
    {
        self::$branchId = null;
    }
}
