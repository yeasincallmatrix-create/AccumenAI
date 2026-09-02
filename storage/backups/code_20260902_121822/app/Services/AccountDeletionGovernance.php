<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 7 — Account Deletion Governance
 *
 * Single authoritative policy layer for the full lifecycle:
 *   ACTIVE -> INACTIVITY_ELIGIBLE -> SOFT_DELETED -> RESTORABLE
 *          -> PERMANENT_DELETION_ELIGIBLE -> FORCE_DELETED
 *
 * DB timestamps are authoritative; scheduler execution time is NOT.
 * Permanent deletion never runs inside the inactivity scheduler.
 */
class AccountDeletionGovernance
{
    // ── State constants ───────────────────────────────────────────────────
    public const STATE_ACTIVE = 'active';
    public const STATE_INACTIVITY_ELIGIBLE = 'inactivity_eligible';
    public const STATE_SOFT_DELETED = 'soft_deleted';
    public const STATE_RESTORABLE = 'restorable';
    public const STATE_PERMANENT_DELETION_ELIGIBLE = 'permanent_deletion_eligible';
    public const STATE_FORCE_DELETED = 'force_deleted';

    /**
     * Authoritative recovery window days (RESTORABLE -> PERMANENT_DELETION_ELIGIBLE).
     * Env-driven, defaults to 30. Centralized here, not scattered magic numbers.
     */
    public static function recoveryDays(): int
    {
        return (int) config('account_deletion.recovery_days', 30);
    }

    public static function permanentAfterDays(): int
    {
        return (int) config('account_deletion.permanent_after_days', 30);
    }

    /**
     * Resolve authoritative deletion timestamp for a soft-deleted user.
     * Priority: inactivity_deleted_at ?? deleted_at.
     * Both are DB-persisted, not scheduler-derived.
     */
    public static function authoritativeDeletionAt(User $user): ?Carbon
    {
        $ts = $user->inactivity_deleted_at ?? $user->deleted_at;
        if ($ts === null) return null;
        return $ts instanceof Carbon ? $ts : Carbon::parse($ts);
    }

    /**
     * Full state machine resolver — DB is source of truth.
     */
    public static function state(User $user): string
    {
        // Force-deleted: row no longer exists — caller must handle via withTrashed check.
        // If user object is provided, it exists, so not force-deleted.
        if ($user->deleted_at === null) {
            // Active row — check inactivity eligibility
            if (AccountInactivityService::isEligibleForDeletion($user)) {
                return self::STATE_INACTIVITY_ELIGIBLE;
            }
            return self::STATE_ACTIVE;
        }

        // Soft-deleted row
        $delAt = self::authoritativeDeletionAt($user);
        if ($delAt === null) {
            // Fallback: considered soft-deleted but restorable (no authoritative ts yet)
            return self::STATE_RESTORABLE;
        }

        $permanentAt = $delAt->copy()->addDays(self::permanentAfterDays());
        if (now()->greaterThanOrEqualTo($permanentAt)) {
            return self::STATE_PERMANENT_DELETION_ELIGIBLE;
        }

        return self::STATE_RESTORABLE;
    }

    public static function isRestorable(User $user): bool
    {
        if ($user->deleted_at === null) return false;
        return self::state($user) === self::STATE_RESTORABLE;
    }

    public static function isPermanentDeletionEligible(User $user): bool
    {
        if ($user->deleted_at === null) return false;
        return self::state($user) === self::STATE_PERMANENT_DELETION_ELIGIBLE;
    }

    public static function permanentEligibleAt(User $user): ?Carbon
    {
        $delAt = self::authoritativeDeletionAt($user);
        if ($delAt === null) return null;
        return $delAt->copy()->addDays(self::permanentAfterDays());
    }

    /**
     * Restore authorization — ONLY platform_admin.
     * Returns [bool $allowed, string|null $reason, string|null $code].
     */
    public static function canRestore(User $user, mixed $actor = null, string $actorGuard = 'platform_admin'): array
    {
        // User must be soft-deleted
        if ($user->deleted_at === null) {
            return [false, 'Account is not deleted.', 'not_deleted'];
        }

        // Only platform_admin guard may restore (Super Admin). Cross-tenant admin blocked.
        if ($actor !== null) {
            // Actor must be platform_admin guard — caller ensures guard, but double-check
            // If actor is provided via web/institute_user guard, block.
            if ($actorGuard !== 'platform_admin' && $actorGuard !== 'platform_admin_guard') {
                return [false, 'Only Super Admin can restore accounts.', 'not_super_admin'];
            }
        }

        // Email collision — if an active account now occupies this email, block.
        // Unique constraint includes soft-deleted rows, but active row with same email would
        // cause restore to violate uniqueness (email is now taken).
        $email = strtolower(trim((string) $user->email));
        if ($email !== '') {
            $collision = User::whereRaw('LOWER(email) = ?', [$email])
                ->whereNull('deleted_at')
                ->where('id', '!=', $user->id)
                ->exists();
            if ($collision) {
                return [false, 'Cannot restore: email is now occupied by another active account.', 'email_collision'];
            }
            // Also check phone collision if phone present
            $phone = $user->phone;
            if ($phone) {
                $phoneCollision = User::where('phone', $phone)
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $user->id)
                    ->exists();
                if ($phoneCollision) {
                    return [false, 'Cannot restore: phone is now occupied by another active account.', 'phone_collision'];
                }
            }
        }

        // If not restorable (permanent window passed), still allow? Policy: allow restore even after
        // permanent eligibility until forceDelete actually happens. But log warning.
        // Do NOT block solely on permanent eligibility — forceDelete is separate step.

        return [true, null, null];
    }

    /**
     * Permanent deletion authorization — only if SOFT_DELETED and beyond recovery window.
     * Also enforces orphan protection via AccountDeletionService.
     * Returns [bool $allowed, string|null $reason, string|null $code]
     */
    public static function canForceDelete(User $user): array
    {
        if ($user->deleted_at === null) {
            return [false, 'Account must be in the recycle bin before permanent deletion.', 'not_deleted'];
        }

        if (! self::isPermanentDeletionEligible($user)) {
            $eligibleAt = self::permanentEligibleAt($user);
            $msg = $eligibleAt
                ? 'Permanent deletion not yet eligible. Eligible after '.$eligibleAt->toDateString().'.'
                : 'Permanent deletion not yet eligible.';
            return [false, $msg, 'not_yet_eligible'];
        }

        // Orphan guard still applies even at permanent stage
        [$allowed, $reason] = AccountDeletionService::canForceDelete($user);
        if (! $allowed) {
            return [false, $reason, 'orphan_risk'];
        }

        return [true, null, null];
    }

    /**
     * Detailed governance snapshot for UI / audit.
     */
    public static function snapshot(User $user): array
    {
        $state = self::state($user);
        $delAt = self::authoritativeDeletionAt($user);
        $eligibleAt = self::permanentEligibleAt($user);

        return [
            'user_id' => $user->id,
            'state' => $state,
            'is_restorable' => self::isRestorable($user),
            'is_permanent_eligible' => self::isPermanentDeletionEligible($user),
            'deleted_at' => $user->deleted_at?->toIso8601String(),
            'inactivity_deleted_at' => $user->inactivity_deleted_at?->toIso8601String(),
            'authoritative_deletion_at' => $delAt?->toIso8601String(),
            'permanent_eligible_at' => $eligibleAt?->toIso8601String(),
            'recovery_days' => self::recoveryDays(),
        ];
    }

    /**
     * Idempotent masked audit helper — never logs secrets.
     */
    public static function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) return '***';
        [$l, $d] = explode('@', $email, 2);
        return (strlen($l) <= 2 ? str_repeat('*', strlen($l)) : $l[0].str_repeat('*', max(1, strlen($l)-2)).substr($l, -1)).'@'.$d;
    }
}
