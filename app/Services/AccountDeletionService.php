<?php

namespace App\Services;

use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Platform Admin exclusive full account cleanup.
 *
 * Rule: ONLY platform_admin guard may delete entire User account.
 * Business (institute) deletion NEVER deletes the global User account
 * (see InstituteAdminController E24); this service is the EXCEPTION
 * that DOES perform full cleanup when platform_admin explicitly deletes
 * a User account.
 *
 * E28 — Complete Global User Account Permanent Deletion Residue Audit & Fix.
 * All user-owned data is cleaned. Business/institution data is NEVER touched.
 */
class AccountDeletionService
{
    /**
     * Soft delete: deactivate account, soft-delete memberships, kill sessions/tokens.
     * DATA SAFETY: Soft delete (ARCHIVE) is the safe alternative to permanent deletion.
     * It preserves business data (institutes) and is reversible via restore.
     * Therefore, even sole owners may be soft-deleted (archived) — the institute
     * remains but owner membership is soft-deleted and can be restored.
     * Only permanent deletion (forceDelete) is blocked for sole owners.
     */
    public static function softDelete(User $user, ?int $actorAdminId = null): void
    {
        // Soft delete is ALLOWED even for sole owners — it's reversible and preserves business.
        // We log a warning for audit but do not block.
        if (self::isOrphanRisk($user)) {
            try {
                PlatformAuditLog::record('users', 'user.'.$user->id, 'account_soft_delete_sole_owner_warning', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'actor_admin_id' => $actorAdminId,
                    'warning' => 'Soft-deleted sole owner — institute will be ownerless until transfer or restore',
                ]);
            } catch (\Throwable $e) {}
        }
        DB::transaction(function () use ($user, $actorAdminId) {
            // Mark inactive before soft delete for auth guards.
            if ($user->status !== 'inactive') {
                $user->update(['status' => 'inactive']);
            }

            // Tenant memberships are tenant-scoped, soft-delete with account.
            // institution_user has SoftDeletes + FK user_id CASCADE on forceDelete,
            // but here we soft-delete only.
            try {
                \App\Models\Membership::where('user_id', $user->id)->delete();
            } catch (\Throwable $e) {}

            // Immediate session/token revocation (full logout).
            self::revokeSessionsAndTokens($user);

            // Soft delete the global account (SoftDeletes).
            if ($user->deleted_at === null) {
                $user->delete();
            }
        });

        try {
            $depCounts = [];
            try { [, , $depCounts] = \App\Services\System\DataSafetyGuard::analyzeBusinessDependencies($user); } catch (\Throwable $e) {}
            PlatformAuditLog::record('users', 'user.'.$user->id, 'account_soft_deleted', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'account_type' => $user->account_type,
                'action' => 'soft_delete',
                'reason' => $actorAdminId ? 'admin_initiated' : 'inactivity_or_admin',
                'environment' => app()->environment(),
                'database' => config('database.connections.mysql.database'),
                'timestamp' => now()->toIso8601String(),
                'dependency_counts' => $depCounts,
                'before_state' => ['status' => $user->status, 'is_test' => $user->is_test ?? false],
            ]);
        } catch (\Throwable $e) {}
    }

    /**
     * Restore soft-deleted account — Phase 7 hardened.
     *
     * Governance:
     *  - Only Super Admin (platform_admin guard) may restore — caller must verify guard.
     *  - Email/phone collision blocks restore (deterministic unique constraint).
     *  - Concurrency: row-level lockForUpdate + fresh re-evaluation → one valid final state.
     *  - Idempotent: if not deleted → RuntimeException.
     *  - After restore: revoke stale sessions/tokens/OTP, require fresh login,
     *    preserve audit, create explicit audit event, do NOT auto-authenticate.
     *  - Memberships: restore soft-deleted memberships; if institute deleted/suspended,
     *    membership restored but institute remains non-active (no auto-transfer).
     *    If role no longer valid, membership kept but audit notes it.
     *  - No automatic ownership transfer.
     */
    public static function restore(User $user, ?int $actorAdminId = null): void
    {
        // Fresh lock + re-evaluation inside transaction — authoritative DB state.
        DB::transaction(function () use ($user, $actorAdminId) {
            $locked = User::withTrashed()->whereKey($user->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException('Account not found.');
            }
            if ($locked->deleted_at === null) {
                throw new \RuntimeException('Account is not in the recycle bin.');
            }

            // Email collision — active account occupies same email
            $email = strtolower(trim((string) $locked->email));
            if ($email !== '') {
                $collision = User::whereRaw('LOWER(email) = ?', [$email])
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $locked->id)
                    ->exists();
                if ($collision) {
                    try {
                        PlatformAuditLog::record('users', 'user.'.$locked->id, 'account_restore_rejected', [
                            'user_id' => $locked->id,
                            'user_email' => $locked->email,
                            'reason' => 'email_collision',
                        ]);
                    } catch (\Throwable $e) {}
                    throw new \RuntimeException('Cannot restore: email is now occupied by another active account.');
                }
                // Phone collision check
                if ($locked->phone) {
                    $phoneCollision = User::where('phone', $locked->phone)
                        ->whereNull('deleted_at')
                        ->where('id', '!=', $locked->id)
                        ->exists();
                    if ($phoneCollision) {
                        try {
                            PlatformAuditLog::record('users', 'user.'.$locked->id, 'account_restore_rejected', [
                                'user_id' => $locked->id,
                                'user_email' => $locked->email,
                                'reason' => 'phone_collision',
                            ]);
                        } catch (\Throwable $e) {}
                        throw new \RuntimeException('Cannot restore: phone is now occupied by another active account.');
                    }
                }
            }

            // Ownership edge capture before restore (for audit)
            $ownershipNote = null;
            try {
                $membershipsToRestore = \App\Models\Membership::withTrashed()->where('user_id', $locked->id)->get();
                foreach ($membershipsToRestore as $m) {
                    if ($m->role_id) {
                        $role = \App\Models\Role::find($m->role_id);
                        if (! $role) {
                            $ownershipNote = 'role_missing_for_membership_'.$m->id;
                        }
                    }
                    if ($m->institution_id) {
                        $inst = \App\Models\Institute::withTrashed()->find($m->institution_id);
                        if ($inst && $inst->deleted_at) {
                            $ownershipNote = ($ownershipNote ? $ownershipNote.';' : '').'institute_deleted_'.$inst->id;
                        } elseif ($inst && $inst->status !== 'active') {
                            $ownershipNote = ($ownershipNote ? $ownershipNote.';' : '').'institute_suspended_'.$inst->id;
                        }
                    }
                }
            } catch (\Throwable $e) {}

            // Restore user row
            $locked->restore();
            $locked->update([
                'status' => 'active',
                'deleted_at' => null,
                // Reset inactivity markers so warnings can re-fire if user becomes inactive again.
                // Keep inactivity_deleted_at for audit trail (do not null it immediately — clear after audit).
                'inactivity_warning_sent_at' => null,
                'inactivity_final_warning_sent_at' => null,
                // Do NOT clear inactivity_deleted_at yet — will be cleared after successful restore audit
            ]);

            // Restore memberships soft-deleted with account.
            // Only restore memberships that were soft-deleted alongside the user.
            // Preserve branch/role/branch assignments as they were (no re-assignment).
            try {
                \App\Models\Membership::withTrashed()->where('user_id', $locked->id)->restore();
                // Reactivate memberships that were marked inactive by softDelete (status was active before)
                // But only if their institute is still active — if institute deleted/suspended, keep as is
                // For active institutes, ensure status active
                $restored = \App\Models\Membership::where('user_id', $locked->id)->get();
                foreach ($restored as $rm) {
                    if ($rm->status === 'inactive') {
                        // Check if institute is active before re-activating
                        $inst = \App\Models\Institute::withTrashed()->find($rm->institution_id);
                        if ($inst && $inst->deleted_at === null && $inst->status === 'active') {
                            $rm->update(['status' => 'active']);
                        }
                    }
                }
            } catch (\Throwable $e) {}

            // SECURITY: revoke stale sessions/tokens/OTP state after restore.
            // The old sessions/tokens must NOT remain valid; require fresh login.
            self::revokeSessionsAndTokens($locked);
            // Also clear any pending 2FA recovery state but preserve TOTP secret
            // (user must re-authenticate with existing 2FA, not bypass it).
            // OTP tables already cleared by revokeSessionsAndTokens.

            // Clear inactivity_deleted_at now that restore succeeded (audit preserved)
            try {
                DB::table('users')->where('id', $locked->id)->update(['inactivity_deleted_at' => null]);
            } catch (\Throwable $e) {}

            // Explicit restore audit — masked, no secrets
            try {
                PlatformAuditLog::record('users', 'user.'.$locked->id, 'account_restore_completed', [
                    'user_id' => $locked->id,
                    'user_email' => $locked->email,
                    'user_name' => $locked->name,
                    'restored_by' => $actorAdminId,
                    'ownership_note' => $ownershipNote,
                    'note' => 'stale sessions/tokens/OTP revoked; fresh login required',
                ]);
            } catch (\Throwable $e) {}

            // Restore notice (idempotent, privacy-safe, no secrets) — best effort
            try {
                if ($locked->email) {
                    \Illuminate\Support\Facades\Mail::raw(
                        'Your account has been restored. Please login with your credentials. If you did not expect this, contact support.',
                        function ($m) use ($locked) { $m->to($locked->email)->subject('Account restored'); }
                    );
                }
            } catch (\Throwable $e) {
                // Mail queue failure must not rollback restore transaction — already committed
                \Illuminate\Support\Facades\Log::warning('restore_notice_failed', ['user_id' => $locked->id]);
            }
        });
    }

    /**
     * Permanent full cleanup — ONLY callable by platform_admin.
     * Deletes every user-owned row then forceDeletes the User.
     * Caller must verify platform_admin password + guard beforehand.
     *
     * DATA SAFETY: Requires backup before destructive operation when possible.
     * E28: Comprehensive residue cleanup covering ALL user-owned data.
     * Business/institution data is NEVER silently deleted.
     */
    public static function forceDelete(User $user): void
    {
        if (self::isOrphanRisk($user)) {
            try {
                PlatformAuditLog::record('users', 'user.'.$user->id, 'account_force_delete_blocked_orphan', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);
            } catch (\Throwable $e) {}
            throw new \RuntimeException('Cannot force delete: user is the only active owner of an active institute. Transfer ownership first.');
        }

        // DATA SAFETY: Comprehensive dependency analysis — block if important business data exists via guard
        // For explicit admin forceDelete, we allow but record dependency counts for audit/recovery
        try {
            [$hasBlocking, $reason, $counts] = \App\Services\System\DataSafetyGuard::analyzeBusinessDependencies($user);
            // hasBlocking already checked via orphan above; here we preserve counts for audit
            if ($hasBlocking) {
                throw new \RuntimeException($reason);
            }
        } catch (\RuntimeException $re) {
            throw $re;
        } catch (\Throwable $e) {}
        // Also enforce permanent eligibility if called via governance purge — allow direct admin forceDelete
        // bypasses permanent window? Admin permanent delete via UI should still check governance via
        // controller; service-level allows direct but governance layer will block purge window.
        // No hard block here for admin manual forceDelete before window — but log it.
        try {
            if (! \App\Services\AccountDeletionGovernance::isPermanentDeletionEligible($user)) {
                \Illuminate\Support\Facades\Log::info('force_delete_before_eligible', [
                    'user_id' => $user->id,
                    'deleted_at' => $user->deleted_at?->toIso8601String(),
                    'inactivity_deleted_at' => $user->inactivity_deleted_at?->toIso8601String(),
                ]);
            }
        } catch (\Throwable $e) {}
        $userId = $user->id;
        $userEmail = $user->email;
        $userName = $user->name;

        // DATA SAFETY: Backup before destructive — best-effort, abort if backup fails in non-testing env
        $backupReference = null;
        try {
            if (! app()->environment('testing')) {
                $backup = \App\Services\System\DataSafetyGuard::requireBackupBeforeDestructive('account_force_delete', 'manual');
                $backupReference = $backup->filename ?? 'backup_'.$backup->id;
            }
        } catch (\Throwable $e) {
            // In testing, allow missing backup; in local/production require backup
            if (! app()->environment('testing')) {
                throw new \RuntimeException('Backup creation/verification failed — aborting permanent deletion: '.$e->getMessage());
            }
            $backupReference = 'testing_no_backup_'.now()->format('YmdHis');
        }

        // Comprehensive destructive audit BEFORE deletion
        $dependencyCounts = [];
        try {
            [, , $dependencyCounts] = \App\Services\System\DataSafetyGuard::analyzeBusinessDependencies($user);
        } catch (\Throwable $e) {}

        // Audit BEFORE deletion (outside transaction — audit must persist even on rollback).
        try {
            PlatformAuditLog::record('users', 'user.'.$userId, 'account_force_deleted', [
                'user_id' => $userId,
                'user_email' => $userEmail,
                'user_name' => $userName,
                'action' => 'force_delete',
                'reason' => 'explicit_admin_confirmation',
                'environment' => app()->environment(),
                'database' => config('database.connections.mysql.database'),
                'timestamp' => now()->toIso8601String(),
                'before_state' => ['status' => $user->status, 'deleted_at' => $user->deleted_at?->toIso8601String(), 'is_test' => $user->is_test ?? false],
                'dependency_counts' => $dependencyCounts,
                'backup_reference' => $backupReference,
            ]);
        } catch (\Throwable $e) {}

        DB::transaction(function () use ($user, $userId, $userEmail) {
            // ──────────────────────────────────────────────────────────────────
            // 1. MEMBERSHIPS (institution_user) — delete including soft-deleted
            // ──────────────────────────────────────────────────────────────────
            try {
                \App\Models\Membership::withTrashed()->where('user_id', $userId)->forceDelete();
            } catch (\Throwable $e) {}
            // Also raw delete as fallback (handles edge cases)
            try {
                DB::table('institution_user')->where('user_id', $userId)->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 2. SESSIONS — all database sessions for this user
            // ──────────────────────────────────────────────────────────────────
            try {
                DB::table('sessions')->where('user_id', $userId)->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 3. API / ACCESS TOKENS — personal access tokens (Sanctum)
            // ──────────────────────────────────────────────────────────────────
            try {
                DB::table('personal_access_tokens')
                    ->where('tokenable_id', $userId)
                    ->whereIn('tokenable_type', ['App\\Models\\User', 'App\Models\User'])
                    ->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 4. PASSWORD RESET TOKENS — keyed by email
            // ──────────────────────────────────────────────────────────────────
            if ($userEmail) {
                try {
                    DB::table('password_reset_tokens')->where('email', $userEmail)->delete();
                } catch (\Throwable $e) {}
            }

            // ──────────────────────────────────────────────────────────────────
            // 5. EMAIL OTP — all email OTP records for this user
            // ──────────────────────────────────────────────────────────────────
            try {
                DB::table('email_otps')->where('user_id', $userId)->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 6. PHONE OTP — phone verification OTPs
            // ──────────────────────────────────────────────────────────────────
            try {
                DB::table('phone_verification_otps')->where('user_id', $userId)->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 7. PHONE 2FA OTP — phone-based two-factor OTPs
            // ──────────────────────────────────────────────────────────────────
            try {
                DB::table('phone_2fa_otps')->where('user_id', $userId)->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 8. PHONE PASSWORD RESET OTP — phone-based password recovery
            // ──────────────────────────────────────────────────────────────────
            try {
                DB::table('phone_password_reset_otps')->where('user_id', $userId)->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 9. TOTP / 2FA SECRET — cleared on the user row itself
            //    (two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at)
            //    These are columns on the users table, cleared before forceDelete.
            // ──────────────────────────────────────────────────────────────────
            try {
                DB::table('users')->where('id', $userId)->update([
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                    'remember_token' => null,
                    'pending_email' => null,
                    'pending_email_token_hash' => null,
                    'pending_email_expires_at' => null,
                    'pending_phone' => null,
                ]);
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 10. EMAIL VERIFICATION — pending email state cleared above
            // ──────────────────────────────────────────────────────────────────
            // Already handled by the users table update in step 9.

            // ──────────────────────────────────────────────────────────────────
            // 11. PHONE VERIFICATION — pending phone cleared above
            // ──────────────────────────────────────────────────────────────────
            // Already handled by the users table update in step 9.

            // ──────────────────────────────────────────────────────────────────
            // 12. USER NOTIFICATIONS — notifications targeting this user
            // ──────────────────────────────────────────────────────────────────
            // Custom notifications table: target_user_type + target_user_id
            // The User model maps to 'institute_user' type in the notification system.
            // Delete notifications where this user is the target.
            try {
                DB::table('notifications')
                    ->where('target_user_type', 'institute_user')
                    ->where('target_user_id', $userId)
                    ->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 13. NOTIFICATION READS — user's read tracking
            // ──────────────────────────────────────────────────────────────────
            try {
                DB::table('notification_reads')
                    ->where('user_id', $userId)
                    ->delete();
            } catch (\Throwable $e) {}
            // Also clean by user_type for the new User model
            try {
                DB::table('notification_reads')
                    ->where('user_type', 'user')
                    ->where('user_id', $userId)
                    ->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 14. NOTIFICATION PREFERENCES — user-specific notification settings
            // ──────────────────────────────────────────────────────────────────
            // recipient_type enum: 'institute_user', 'platform_admin', 'student', etc.
            // For User model, preferences may be stored under 'institute_user' type.
            try {
                DB::table('notification_preferences')
                    ->where('recipient_type', 'institute_user')
                    ->where('recipient_id', $userId)
                    ->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 15. NOTIFICATION LOGS — delivery logs targeting this user
            // ──────────────────────────────────────────────────────────────────
            try {
                DB::table('notification_logs')
                    ->where('recipient_type', 'institute_user')
                    ->where('recipient_id', $userId)
                    ->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 16. LOGIN ATTEMPTS — user's login history by email
            // ──────────────────────────────────────────────────────────────────
            if ($userEmail) {
                try {
                    DB::table('login_attempts')
                        ->where('email', $userEmail)
                        ->delete();
                } catch (\Throwable $e) {}
            }

            // ──────────────────────────────────────────────────────────────────
            // 17. IDENTITY AUDIT LOGS — identity change history
            // ──────────────────────────────────────────────────────────────────
            try {
                DB::table('identity_audit_logs')->where('user_id', $userId)->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 18. CALENDAR EVENT REMINDERS — user-specific reminders
            // ──────────────────────────────────────────────────────────────────
            try {
                DB::table('calendar_event_reminders')->where('user_id', $userId)->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 19. USER MODULE ACCESS — per-user module entitlements
            // ──────────────────────────────────────────────────────────────────
            try {
                DB::table('user_module_access')->where('user_id', $userId)->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 20. AI LOGS — user's AI interaction history
            // ──────────────────────────────────────────────────────────────────
            try {
                DB::table('ai_logs')->where('user_id', $userId)->delete();
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 21. AUDIT LOGS — INTENTIONALLY PRESERVED
            // ──────────────────────────────────────────────────────────────────
            // audit_logs.user_id references the user who performed the action.
            // These are INSTITUTE-SCOPED business audit records (they carry
            // institute_id) that belong to the business, not the user.
            // Deleting them would destroy the institute's compliance audit trail.
            // The user was merely the actor — the record belongs to the institute.
            // DO NOT DELETE audit_logs here.

            // ──────────────────────────────────────────────────────────────────
            // 22. ACTIVITY LOGS — INTENTIONALLY PRESERVED
            // ──────────────────────────────────────────────────────────────────
            // Same rationale as audit_logs — these are institute-scoped business
            // records. The user was the actor, not the owner of the record.
            // DO NOT DELETE activity_logs here.

            // ──────────────────────────────────────────────────────────────────
            // 23. PERSONAL FILES — profile photo cleanup
            // ──────────────────────────────────────────────────────────────────
            // Only delete if the photo is exclusively user-owned (not shared with business).
            // Profile photos are stored on the users.photo column and are exclusively user-owned.
            try {
                $photoPath = DB::table('users')->where('id', $userId)->value('photo');
                if ($photoPath && Storage::disk('public')->exists($photoPath)) {
                    Storage::disk('public')->delete($photoPath);
                }
            } catch (\Throwable $e) {}

            // ──────────────────────────────────────────────────────────────────
            // 24. USER PREFERENCES — stored in users.preferences JSON column
            //     (handled by forceDelete of the user row itself)
            // ──────────────────────────────────────────────────────────────────
            // The preferences column is on the users table — handled by forceDelete below.

            // ──────────────────────────────────────────────────────────────────
            // 25. USER ROW — final hard delete
            //     Clears all sensitive columns before deletion.
            //     FK CASCADE on institution_user handles any remaining membership rows.
            // ──────────────────────────────────────────────────────────────────
            // Clear sensitive data on the row before hard delete (defense in depth)
            try {
                DB::table('users')->where('id', $userId)->update([
                    'password_hash' => null,
                    'remember_token' => null,
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'photo' => null,
                    'preferences' => null,
                    'pending_email' => null,
                    'pending_email_token_hash' => null,
                    'pending_phone' => null,
                ]);
            } catch (\Throwable $e) {}

            $user->forceDelete();
        });
    }

    /**
     * Authoritative orphan check: active institute must not become ownerless.
     * Active owner = Membership where role institute-owner, status active, deleted_at null, institution deleted_at null AND status active.
     * Allowed only if for every active institute owned by user there remains at least one other active owner.
     */
    public static function isOrphanRisk(User $user): bool
    {
        try {
            $ownerMemberships = \App\Models\Membership::where('user_id', $user->id)
                ->whereHas('role', fn ($q) => $q->where('slug', 'institute-owner'))
                ->where('status','active')
                ->whereNull('deleted_at')
                ->whereHas('institution', fn ($q) => $q->whereNull('deleted_at')->where('status','active'))
                ->with('institution')
                ->get();
            foreach ($ownerMemberships as $m) {
                $otherOwners = \App\Models\Membership::where('institution_id', $m->institution_id)
                    ->where('id','!=',$m->id)
                    ->whereHas('role', fn ($q) => $q->where('slug','institute-owner'))
                    ->where('status','active')
                    ->whereNull('deleted_at')
                    ->whereHas('institution', fn ($q) => $q->whereNull('deleted_at')->where('status','active'))
                    ->count();
                if ($otherOwners === 0) return true;
            }
        } catch (\Throwable $e) {}
        return false;
    }

    public static function canDeleteWithoutOrphaningInstitute(User $user): array
    {
        if (self::isOrphanRisk($user)) {
            try {
                $orphans = \App\Models\Membership::where('user_id',$user->id)->whereHas('role',fn($q)=>$q->where('slug','institute-owner'))->where('status','active')->whereNull('deleted_at')->whereHas('institution',fn($q)=>$q->whereNull('deleted_at')->where('status','active'))->with('institution')->get();
                $orphanCount = 0;
                $firstName = null;
                foreach ($orphans as $m) {
                    $otherOwners = \App\Models\Membership::where('institution_id', $m->institution_id)->where('id','!=',$m->id)->whereHas('role', fn($q)=>$q->where('slug','institute-owner'))->where('status','active')->whereNull('deleted_at')->whereHas('institution', fn($q)=>$q->whereNull('deleted_at')->where('status','active'))->count();
                    if ($otherOwners === 0) {
                        $orphanCount++;
                        if ($firstName === null) $firstName = $m->institution->name ?? 'active business';
                    }
                }
                if ($orphanCount > 1) {
                    return [false, 'This account is the only active owner of '.$orphanCount.' active businesses. Transfer ownership first. This account is the only active owner of "'.$firstName.'". Transfer ownership first.'];
                }
                if ($orphanCount === 1) {
                    return [false, 'This account is the only active owner of 1 active business "'.$firstName.'". Transfer ownership first.'];
                }
            } catch (\Throwable $e) {}
            return [false, 'This account is the only active owner of an active business. Transfer ownership first.'];
        }
        return [true, null];
    }

    /**
     * Check if account can be permanently deleted.
     * Block if deleting would orphan an active institute OR has business data in automatic flows.
     * Returns [bool $allowed, string|null $reason]
     */
    public static function canForceDelete(User $user): array
    {
        // Primary: orphan protection
        [$allowed, $reason] = self::canDeleteWithoutOrphaningInstitute($user);
        if (! $allowed) return [$allowed, $reason];

        // Secondary: DATA SAFETY dependency check for PRODUCTION data
        // Explicit test records (is_test=true) are allowed for test cleanup flows
        try {
            if (isset($user->is_test) && $user->is_test === true) {
                return [true, null];
            }
        } catch (\Throwable $e) {}

        // For real production data, if hasBlocking via DataSafetyGuard, block automatic deletion
        // Explicit admin deletion with backup is handled in forceDelete; this check is for UI/purge guards
        try {
            [$hasBlocking, $blockReason] = \App\Services\System\DataSafetyGuard::analyzeBusinessDependencies($user);
            if ($hasBlocking) {
                return [false, $blockReason];
            }
        } catch (\Throwable $e) {}

        return [true, null];
    }

    /**
     * Explicit guard for business data dependency — returns detailed counts.
     * Used by DataSafetyGuard::canDeleteAccountAutomatically for stricter automatic blocking.
     */
    public static function hasBusinessDataDependencies(User $user): array
    {
        return \App\Services\System\DataSafetyGuard::analyzeBusinessDependencies($user);
    }

    /**
     * Get detailed deletion pre-check data for UI display.
     * Returns array with business counts, membership info, etc.
     */
    public static function getDeletionCheckData(User $user): array
    {
        $data = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'account_type' => $user->account_type,
            'total_memberships' => 0,
            'active_memberships' => 0,
            'deleted_memberships' => 0,
            'owned_active' => 0,
            'businesses' => [],
            'roles' => [],
        ];

        try {
            $data['total_memberships'] = \App\Models\Membership::withTrashed()->where('user_id', $user->id)->count();
            $data['active_memberships'] = \App\Models\Membership::where('user_id', $user->id)
                ->whereHas('institution', fn ($q) => $q->whereNull('deleted_at'))->count();
            $data['deleted_memberships'] = \App\Models\Membership::withTrashed()->where('user_id', $user->id)
                ->where(function ($q) {
                    $q->whereNotNull('deleted_at')
                        ->orWhereHas('institution', fn ($iq) => $iq->onlyTrashed());
                })->count();
            $data['owned_active'] = \App\Models\Membership::where('user_id', $user->id)
                ->whereHas('role', fn ($q) => $q->where('slug', 'institute-owner'))
                ->whereHas('institution', fn ($q) => $q->whereNull('deleted_at'))
                ->count();
            $data['roles'] = \App\Models\Membership::withTrashed()->where('user_id', $user->id)
                ->with('role')->get()->pluck('role.slug')->unique()->filter()->values()->toArray();
        } catch (\Throwable $e) {}

        [$allowed, $reason] = self::canForceDelete($user);
        $data['can_delete'] = $allowed;
        $data['block_reason'] = $reason;

        return $data;
    }

    protected static function revokeSessionsAndTokens(User $user): void
    {
        try {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        } catch (\Throwable $e) {}
        try {
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $user->id)
                ->whereIn('tokenable_type', ['App\\Models\\User', 'App\Models\User'])
                ->delete();
        } catch (\Throwable $e) {}
        // Invalidate OTPs (including password reset tokens keyed by email)
        foreach (['email_otps', 'phone_verification_otps', 'phone_2fa_otps', 'phone_password_reset_otps'] as $t) {
            try { DB::table($t)->where('user_id', $user->id)->delete(); } catch (\Throwable $e) {}
        }
        if ($user->email) {
            try { DB::table('password_reset_tokens')->where('email', $user->email)->delete(); } catch (\Throwable $e) {}
        }
        // Clear stale pending identity tokens on users row (do not null password_hash)
        try {
            DB::table('users')->where('id', $user->id)->update([
                'pending_email_token_hash' => null,
                'pending_email_expires_at' => null,
                'remember_token' => null,
            ]);
        } catch (\Throwable $e) {}
    }

    /**
     * Explicit restore audit for cross-tenant attempt — caller should invoke when
     * authorization fails due to tenant boundary.
     */
    public static function auditCrossTenantAttempt(int $actorId, int $targetUserId): void
    {
        try {
            PlatformAuditLog::record('users', 'user.'.$targetUserId, 'restore_cross_tenant_attempt', [
                'actor_id' => $actorId,
                'target_user_id' => $targetUserId,
            ]);
        } catch (\Throwable $e) {}
    }
}
