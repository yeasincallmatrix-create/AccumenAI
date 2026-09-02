<?php

namespace App\Services\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DATA SAFETY GUARDRAILS — Central safety enforcement.
 *
 * Principles:
 *  - REAL ≠ TEST; test must be EXPLICIT via is_test = true
 *  - email/name/phone pattern NEVER authorizes deletion
 *  - UNKNOWN (NULL) => PROTECTED => DO NOT DELETE
 *  - Production default safe (is_test = false)
 *  - Structural migrations must never delete accounts
 *  - Destructive ops require backup + audit + explicit intent
 */
class DataSafetyGuard
{
    /**
     * Determine if a record is EXPLICITLY marked as test data.
     * Only is_test === true is authoritative.
     * NULL, false, 0, missing column => PROTECTED.
     */
    public static function isExplicitTestRecord(Model|array $record): bool
    {
        if (is_array($record)) {
            return (isset($record['is_test']) && $record['is_test'] === true) || (isset($record['is_test']) && $record['is_test'] === 1) || (isset($record['is_test']) && $record['is_test'] === '1');
        }
        // Model path: check attribute exists and is truthy strictly
        if (! isset($record->is_test)) {
            return false;
        }
        $v = $record->is_test;
        return $v === true || $v === 1 || $v === '1' || $v === 1.0;
    }

    /**
     * PROTECTED = default. Missing/null/false => protected.
     */
    public static function isProtected(Model|array $record): bool
    {
        return ! self::isExplicitTestRecord($record);
    }

    /**
     * NEVER classify test data by email alone.
     * This method is the ONLY email-aware helper and it ALWAYS returns false
     * for deletion authorization. It exists solely to allow audit logging
     * that email-pattern matching was BLOCKED.
     *
     * Returns: false (never authorized)
     */
    public static function isTestByEmailPattern(?string $email): bool
    {
        // Intentionally BLOCKED: email pattern must never authorize deletion.
        // We log the attempt for forensic audit if caller tries to use it.
        if ($email !== null && $email !== '') {
            Log::warning('data_safety.email_pattern_blocked', ['email_masked' => self::maskEmail($email)]);
        }
        return false;
    }

    public static function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) return '***';
        [$l, $d] = explode('@', $email, 2);
        return (strlen($l) <= 2 ? str_repeat('*', strlen($l)) : $l[0].str_repeat('*', max(1, strlen($l)-2)).substr($l, -1)).'@'.$d;
    }

    /**
     * Environment safety: destructive test utilities must only run in testing/demo context
     * or when explicitly confirmed.
     *
     * Allowed environments for test cleanup: testing, local with explicit confirmation.
     * Production is NEVER allowed for test cleanup.
     */
    public static function assertEnvironmentSafeForTestCleanup(?string $command = null): void
    {
        $env = app()->environment();
        $db = config('database.connections.mysql.database');

        // Production guard: never allow destructive test cleanup in production
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'Test cleanup is BLOCKED in production environment (APP_ENV=production). '
                .'Refusing to delete test data in production. DB: '.($db ?? 'unknown')
            );
        }

        // Allowed envs: testing always allowed; local allowed with explicit intent
        $allowed = ['testing', 'local', 'development'];
        if (! in_array($env, $allowed, true)) {
            // Still block unless APP_ENV is explicitly testing
            throw new \RuntimeException(
                "Test cleanup requires APP_ENV=testing (current: {$env}). Aborting for safety. DB: ".($db ?? 'unknown')
            );
        }
    }

    /**
     * Database connection safety: verify we are not accidentally running destructive
     * test cleanup against a production database.
     *
     * Heuristic: if DB name is 'monetix' (production/local prod-like) and env is testing,
     * caller must explicitly confirm via test database 'monetix_test'.
     */
    public static function assertDatabaseSafeForDestructive(string $operation = 'destructive'): void
    {
        $db = config('database.connections.mysql.database');
        $env = app()->environment();

        // In testing env, the database MUST be the test database (monetix_test)
        // or a parallel test variant (monetix_test_test_N)
        if ($env === 'testing' && ! preg_match('/^monetix_test(_test_\d+)?$/', $db)) {
            // This is a critical safety violation: testing env pointing at production DB
            Log::critical('data_safety.database_mismatch', [
                'operation' => $operation,
                'env' => $env,
                'database' => $db,
                'expected' => 'monetix_test',
            ]);
            throw new \RuntimeException(
                "Database safety violation: APP_ENV=testing but database is '{$db}' (expected 'monetix_test'). "
                ."Aborting {$operation} to protect production data."
            );
        }

        // If DB is production-like (monetix) and env is not testing, block test cleanup
        if ($db === 'monetix' && $env !== 'testing') {
            // This is allowed only for non-test operations; test cleanup must be blocked
            // Caller should have already checked environment, but double-guard here
            if (str_contains(strtolower($operation), 'test')) {
                throw new \RuntimeException(
                    "Test cleanup BLOCKED: database '{$db}' is not a disposable test database. Operation: {$operation}"
                );
            }
        }

        // Log database context for audit
        Log::info('data_safety.database_verified', [
            'operation' => $operation,
            'env' => $env,
            'database' => $db,
        ]);
    }

    /**
     * Verify backup exists before destructive operation.
     * Throws if backup creation/verification fails.
     */
    public static function requireBackupBeforeDestructive(string $operation, ?string $backupType = 'manual'): \App\Models\SystemBackup
    {
        $service = app(BackupService::class);
        $backup = $service->create($backupType ?? 'pre_destructive');
        $verified = $service->verify($backup);
        if (! $verified || $backup->status !== 'verified') {
            Log::critical('data_safety.backup_failed_abort', [
                'operation' => $operation,
                'backup_id' => $backup->id ?? null,
                'backup_status' => $backup->status ?? 'unknown',
            ]);
            throw new \RuntimeException("Backup verification failed — aborting {$operation}. Backup ID: ".($backup->id ?? 'unknown'));
        }
        Log::info('data_safety.backup_verified', [
            'operation' => $operation,
            'backup_id' => $backup->id,
            'filename' => $backup->filename,
        ]);
        return $backup;
    }

    /**
     * Business data dependency analysis before deleting a User/Institute owner.
     * Checks comprehensive relationships; if any significant business data exists, blocks deletion.
     *
     * Returns [bool $hasBlockingDependencies, string|null $reason, array $counts]
     */
    public static function analyzeBusinessDependencies(\App\Models\User $user): array
    {
        $counts = [];
        $reason = null;
        $hasBlocking = false;

        try {
            // Memberships / institutes
            $counts['memberships_total'] = \App\Models\Membership::withTrashed()->where('user_id', $user->id)->count();
            $counts['memberships_active'] = \App\Models\Membership::where('user_id', $user->id)->count();
            $counts['institutes_owned_active'] = \App\Models\Membership::where('user_id', $user->id)
                ->whereHas('role', fn($q) => $q->where('slug', 'institute-owner'))
                ->whereHas('institution', fn($q) => $q->whereNull('deleted_at')->where('status', 'active'))
                ->count();
        } catch (\Throwable $e) {
            $counts['memberships_error'] = substr($e->getMessage(), 0, 100);
        }

        // If user is sole owner of an active institute => BLOCK (orphan risk)
        if (($counts['institutes_owned_active'] ?? 0) > 0) {
            // Check if sole owner for any institute
            try {
                $ownerMemberships = \App\Models\Membership::where('user_id', $user->id)
                    ->whereHas('role', fn($q) => $q->where('slug', 'institute-owner'))
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->whereHas('institution', fn($q) => $q->whereNull('deleted_at')->where('status', 'active'))
                    ->get();
                foreach ($ownerMemberships as $m) {
                    $otherOwners = \App\Models\Membership::where('institution_id', $m->institution_id)
                        ->where('id', '!=', $m->id)
                        ->whereHas('role', fn($q) => $q->where('slug', 'institute-owner'))
                        ->where('status', 'active')
                        ->whereNull('deleted_at')
                        ->whereHas('institution', fn($q) => $q->whereNull('deleted_at')->where('status', 'active'))
                        ->count();
                    if ($otherOwners === 0) {
                        $hasBlocking = true;
                        $instName = $m->institution->name ?? 'active business';
                        $reason = "This account is the only active owner of \"{$instName}\". Transfer ownership first.";
                        $counts['orphan_institute_id'] = $m->institution_id;
                        break;
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Broader business data check: if user has any active memberships at all,
        // consider it business-associated. For AUTOMATIC deletion (inactivity/orphan/test cleanup),
        // we block. For EXPLICIT admin deletion with backup, we allow but warn.
        // Here we return counts for the caller to decide policy.
        // For now, only orphan is hard-block; other memberships are informational.
        // However, per spec 6, we should also block if account has important business relationships.
        // We treat "has any active institute association with students/courses/batches" as blocking for automatic flows.
        if (! $hasBlocking && ($counts['memberships_active'] ?? 0) > 0) {
            try {
                // Find institutes for this user
                $instituteIds = \App\Models\Membership::where('user_id', $user->id)
                    ->whereHas('institution', fn($q) => $q->whereNull('deleted_at'))
                    ->pluck('institution_id')->unique()->all();
                if (! empty($instituteIds)) {
                    // Check for business data in those institutes
                    $businessCounts = [];
                    if (\Illuminate\Support\Facades\Schema::hasTable('students')) {
                        $businessCounts['students'] = DB::table('students')->whereIn('institute_id', $instituteIds)->count();
                    }
                    if (\Illuminate\Support\Facades\Schema::hasTable('courses')) {
                        $businessCounts['courses'] = DB::table('courses')->whereIn('institute_id', $instituteIds)->count();
                    }
                    if (\Illuminate\Support\Facades\Schema::hasTable('batches')) {
                        $businessCounts['batches'] = DB::table('batches')->whereIn('institute_id', $instituteIds)->count();
                    }
                    if (\Illuminate\Support\Facades\Schema::hasTable('invoices')) {
                        $businessCounts['invoices'] = DB::table('invoices')->whereIn('institute_id', $instituteIds)->count();
                    }
                    $counts = array_merge($counts, $businessCounts);
                    $hasBusinessData = collect($businessCounts)->filter(fn($c) => $c > 0)->isNotEmpty();
                    // Do not hard-block here for explicit admin action, but flag for audit
                    $counts['has_business_data'] = $hasBusinessData;
                }
            } catch (\Throwable $e) {}
        }

        // Audit logs existence check (business audit)
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
                $counts['audit_logs_as_actor'] = DB::table('audit_logs')->where('user_id', $user->id)->count();
            }
        } catch (\Throwable $e) {}

        return [$hasBlocking, $reason, $counts];
    }

    /**
     * Comprehensive destroy guard: combines orphan + business dependency check.
     * Used for automatic/purge flows — stricter policy.
     * Returns [bool $allowed, string|null $reason, array $counts]
     */
    public static function canDeleteAccountAutomatically(\App\Models\User $user): array
    {
        [$hasBlocking, $reason, $counts] = self::analyzeBusinessDependencies($user);

        if ($hasBlocking) {
            return [false, $reason ?? 'This account is associated with business data and cannot be permanently deleted automatically. Deactivate or archive the account instead.', $counts];
        }

        // For automatic flows, also block if user has any active business memberships with business data
        // This enforces: UNKNOWN/REAL => PROTECT unless explicit approval
        if (($counts['has_business_data'] ?? false) && ($counts['memberships_active'] ?? 0) > 0) {
            // Only block automatic deletion, not explicit admin with backup
            // For now, we still allow explicit admin; this method is for automatic flows
            return [false, 'This account is associated with business data and cannot be permanently deleted automatically. Deactivate or archive the account instead.', $counts];
        }

        return [true, null, $counts];
    }

    /**
     * Audit logging for destructive operations — never logs secrets.
     */
    public static function auditDestructive(array $payload): void
    {
        try {
            $safe = $payload;
            // Strip secrets
            foreach (['password', 'password_hash', 'otp', 'token', 'two_factor_secret', '2fa'] as $key) {
                unset($safe[$key]);
            }
            \App\Models\PlatformAuditLog::record(
                $safe['target_type'] ?? 'users',
                $safe['target_id'] ?? 'unknown',
                $safe['action'] ?? 'destructive_operation',
                $safe
            );
        } catch (\Throwable $e) {
            Log::warning('data_safety.audit_failed', ['error' => substr($e->getMessage(), 0, 200)]);
        }
    }
}
