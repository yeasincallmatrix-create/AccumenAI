<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Account Deletion Governance — Phase 7 (Production Safety)
    |--------------------------------------------------------------------------
    |
    | Centralized, env-driven policy for the full lifecycle:
    |   ACTIVE -> INACTIVITY_ELIGIBLE -> SOFT_DELETED -> RESTORABLE
    |          -> PERMANENT_DELETION_ELIGIBLE -> FORCE_DELETED
    |
    | No scheduler execution time is authoritative — DB timestamps are.
    | Permanent deletion never runs inside the inactivity scheduler.
    |
    */

    // ── Soft-delete → restorable window ─────────────────────────────────
    //   While now < inactivity_deleted_at + recovery_days  → RESTORABLE
    //   When  now >= inactivity_deleted_at + recovery_days → PERMANENT_DELETION_ELIGIBLE
    //   Admin soft-delete (inactivity_deleted_at may be NULL) falls back to deleted_at.
    'recovery_days' => (int) env('ACCOUNT_RECOVERY_DAYS', 30),

    // ── Permanent deletion retention (alias of recovery_days, explicit) ───
    //   Kept as separate key for audit clarity. Defaults to same as recovery_days.
    'permanent_after_days' => (int) env('ACCOUNT_PERMANENT_DELETION_DAYS', 30),

    // ── Inactivity retention (authoritative, already in AccountInactivityService) ──
    //   Kept here for single source of truth documentation.
    'inactivity_default_days' => 365,
    'inactivity_premium_days' => 1095,

    // ── Warning windows ───────────────────────────────────────────────────
    'warning_days_before' => 30,
    'final_warning_days_before' => 7,

    // ── Restore policy ────────────────────────────────────────────────────
    //   Who may restore: only platform_admin (guard platform_admin).
    //   Deleted user cannot self-restore; institute owner/admin cannot restore cross-tenant.
    //   Require explicit Super Admin audit, no silent login after restore.
    'restore_allowed_guard' => 'platform_admin',

    // After restore: do not auto-authenticate. Require fresh login + re-verify if needed.
    // revoke sessions/tokens/OTP, clear stale 2FA recovery, preserve audit.
    'restore_revokes_sessions' => true,
    'restore_revokes_tokens' => true,
    'restore_clears_otps' => true,

    // Email reuse: ACTIVE and SOFT_DELETED block reuse (unique constraint includes soft-deleted).
    // Only FORCE_DELETED frees the email.
    'allow_email_reuse_only_after_force_delete' => true,

    // ── Permanent deletion boundary ───────────────────────────────────────
    //   Tenant/business/financial/audit data is NEVER deleted with the user.
    //   Only user-owned identity + notification + session/token/OTP state is purged.
    'preserve_audit_logs' => true,
    'preserve_activity_logs' => true,
    'preserve_institute_data' => true,
];
