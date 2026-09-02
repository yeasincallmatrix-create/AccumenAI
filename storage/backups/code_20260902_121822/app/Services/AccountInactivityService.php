<?php
namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AccountInactivityService
{
    public const DEFAULT_RETENTION_DAYS = 365; // 1 year
    public const PREMIUM_RETENTION_DAYS = 1095; // 3 years - Phase 5 hook
    public const WARNING_DAYS_BEFORE = 30; // first warning 30 days before deletion
    public const FINAL_WARNING_DAYS_BEFORE = 7; // final warning 7 days before

    // Bootstrap exception for protected business owner (platform admin now yeasinsheikh999@gmail.com is NOT a User, so not listed here)
    public const BOOTSTRAP_EXCEPTIONS = ['admin@mawa.com'];

    public static function isBootstrapException(User $user): bool
    {
        $email = strtolower(trim((string)$user->email));
        return in_array($email, array_map('strtolower', self::BOOTSTRAP_EXCEPTIONS), true);
    }

    public static function getEffectiveLastLoginAt(User $user): ?Carbon
    {
        if ($user->last_login_at) {
            $dt = $user->last_login_at instanceof Carbon ? $user->last_login_at : Carbon::parse($user->last_login_at);
            // Future timestamp safety: if > now+5min (clock skew), treat as now
            if ($dt->isFuture() && $dt->diffInMinutes(now()) > 5) {
                Log::warning('inactivity_future_last_login', ['user_id'=>$user->id, 'last_login_at'=>$dt->toIso8601String()]);
                return now();
            }
            return $dt;
        }
        // Never logged in: use created_at as activation grace reference (pending already handled)
        if ($user->created_at) {
            return $user->created_at instanceof Carbon ? $user->created_at : Carbon::parse($user->created_at);
        }
        return null;
    }

    public static function isPremium(User $user): bool
    {
        // Authoritative: currently active premium = owner of institute with active subscription package slug PREMIUM and end_date >= today
        // Uses withoutGlobalScope to bypass tenant isolation during cleanup (out-of-tenant context)
        try {
            $hasPremium = \App\Models\Membership::where('user_id',$user->id)
                ->whereHas('role', fn($q)=>$q->where('slug','institute-owner'))
                ->whereHas('institution', function($q){
                    $q->whereNull('deleted_at')->whereHas('instituteSubscriptions', function($sq){
                        $sq->withoutGlobalScopes()->where('status','active')
                            ->where('end_date','>=', now()->toDateString())
                            ->whereHas('package', fn($pq)=>$pq->where('slug','PREMIUM')->where('status','active'));
                    });
                })->exists();
            if ($hasPremium) return true;
        } catch (\Throwable $e) {}
        return false;
    }

    public static function retentionDays(User $user): int
    {
        return self::isPremium($user) ? self::PREMIUM_RETENTION_DAYS : self::DEFAULT_RETENTION_DAYS;
    }

    public static function retentionDate(User $user): ?Carbon
    {
        $effective = self::getEffectiveLastLoginAt($user);
        if (!$effective) return null;
        return $effective->copy()->addDays(self::retentionDays($user));
    }

    public static function isEligibleForDeletion(User $user): bool
    {
        if (self::isBootstrapException($user)) return false;
        if ($user->deleted_at !== null) return false;
        // Do not delete suspended/banned/inactive unless explicitly allowed — only active
        if ($user->status !== 'active') return false;
        $effective = self::getEffectiveLastLoginAt($user);
        if (!$effective) return false; // no reference, safe to not delete
        if ($effective->isFuture() && $effective->diffInMinutes(now()) <=5) return false;
        $retention = self::retentionDate($user);
        if (!$retention) return false;
        return now()->greaterThanOrEqualTo($retention);
    }

    public static function isWarningDue(User $user): bool
    {
        if (self::isEligibleForDeletion($user)) return false; // already eligible, warning phase passed
        if (self::isBootstrapException($user) || $user->status !== 'active' || $user->deleted_at) return false;
        $effective = self::getEffectiveLastLoginAt($user);
        if (!$effective) return false;
        $retention = self::retentionDate($user);
        $warningDate = $retention->copy()->subDays(self::WARNING_DAYS_BEFORE);
        if (now()->lt($warningDate)) return false;
        if ($user->inactivity_warning_sent_at && $user->inactivity_warning_sent_at->greaterThan($effective)) {
            // Already warned after last login, idempotent
            return false;
        }
        // If user logged in after warning, warning is reset (inactivity_warning_sent_at < effective)
        return true;
    }

    public static function isFinalWarningDue(User $user): bool
    {
        if (self::isEligibleForDeletion($user)) return false;
        if (self::isBootstrapException($user) || $user->status !== 'active' || $user->deleted_at) return false;
        $effective = self::getEffectiveLastLoginAt($user);
        if (!$effective) return false;
        $retention = self::retentionDate($user);
        $finalDate = $retention->copy()->subDays(self::FINAL_WARNING_DAYS_BEFORE);
        if (now()->lt($finalDate)) return false;
        if ($user->inactivity_final_warning_sent_at && $user->inactivity_final_warning_sent_at->greaterThan($effective)) return false;
        return true;
    }

    public static function markLogin(User $user): void
    {
        // Only update last_login_at on successful authentication boundary — caller ensures success
        $user->forceFill(['last_login_at'=>now(), 'inactivity_warning_sent_at'=>null, 'inactivity_final_warning_sent_at'=>null])->save();
        Log::info('inactivity.login_reset', ['user_id'=>$user->id]);
    }

    // ── Phase 7 — state machine helpers (DB is source of truth) ─────────
    public static function stateFor(User $user): string
    {
        return \App\Services\AccountDeletionGovernance::state($user);
    }

    public static function isRestorable(User $user): bool
    {
        return \App\Services\AccountDeletionGovernance::isRestorable($user);
    }

    public static function isPermanentDeletionEligible(User $user): bool
    {
        return \App\Services\AccountDeletionGovernance::isPermanentDeletionEligible($user);
    }
}
