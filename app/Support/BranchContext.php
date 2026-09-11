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

    /**
     * Phase 18 (additive) — whether the current actor is restricted to one
     * branch (false = institute-wide: owner/admin, platform, CLI, or no
     * branch assignment).
     */
    public static function isBranchScoped(): bool
    {
        return self::$branchId !== null;
    }

    /**
     * Phase 18 (additive) — branch ids the current actor may access.
     * Returns null for institute-wide actors (no constraint), otherwise
     * the single assigned branch. Single-element by construction: one
     * membership per user+institute carries at most one branch_id.
     *
     * @return int[]|null
     */
    public static function accessibleBranchIds(): ?array
    {
        return self::$branchId !== null ? [(int) self::$branchId] : null;
    }

    /**
     * Phase 18 (additive) — whether a branch id is accessible here.
     * Null branch = legacy/pre-branch record, always passable at this
     * layer (callers decide legacy visibility explicitly).
     */
    public static function allows(?int $branchId): bool
    {
        if ($branchId === null) {
            return true;
        }
        if (self::$branchId === null) {
            return true;
        }

        return (int) $branchId === (int) self::$branchId;
    }
}
