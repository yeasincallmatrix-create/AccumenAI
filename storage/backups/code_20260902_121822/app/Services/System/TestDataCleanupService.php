<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * TEST DATA CLEANUP — Isolated to explicitly marked test/demo records only.
 *
 * Safety rules:
 *  - Only operates on records where is_test = true
 *  - UNKNOWN (NULL) or false => PROTECTED => DO NOT DELETE
 *  - Requires environment safety (APP_ENV=testing)
 *  - Requires database connection safety (monetix_test)
 *  - Requires backup before destructive execution
 *  - Email/name/phone patterns NEVER authorize deletion
 */
class TestDataCleanupService
{
    public function __construct(private readonly BackupService $backups = new BackupService()) {}

    /**
     * Preview what would be deleted — only explicit test records.
     */
    public function preview(): array
    {
        DataSafetyGuard::assertEnvironmentSafeForTestCleanup('test_cleanup_preview');
        DataSafetyGuard::assertDatabaseSafeForDestructive('test_cleanup_preview');

        return $this->collectTestRecords();
    }

    /**
     * Execute deletion of explicitly marked test records.
     * $dryRun = true => preview only; false => destructive with backup.
     */
    public function execute(bool $dryRun = true): array
    {
        DataSafetyGuard::assertEnvironmentSafeForTestCleanup('test_cleanup');
        DataSafetyGuard::assertDatabaseSafeForDestructive('test_cleanup');

        $preview = $this->collectTestRecords();

        if ($dryRun) {
            return array_merge($preview, ['dry_run' => true, 'backup' => null, 'deleted' => []]);
        }

        // Backup before destructive (uses 'manual' type to satisfy enum)
        $backup = DataSafetyGuard::requireBackupBeforeDestructive('test_cleanup', 'manual');
        $verified = $this->backups->verify($backup);
        if (! $verified || $backup->status !== 'verified') {
            throw new \RuntimeException('Backup verification failed — aborting test cleanup');
        }

        $deleted = [];
        DB::beginTransaction();
        try {
            // Delete in dependency-safe order: memberships first, then institutes, then users
            // Only where is_test = true

            // 1. memberships (institution_user) where is_test = true
            if (Schema::hasTable('institution_user') && Schema::hasColumn('institution_user', 'is_test')) {
                $deleted['institution_user'] = DB::table('institution_user')->where('is_test', true)->delete();
            } else {
                $deleted['institution_user'] = 0;
            }

            // 2. students where is_test = true
            if (Schema::hasTable('students') && Schema::hasColumn('students', 'is_test')) {
                $deleted['students'] = DB::table('students')->where('is_test', true)->delete();
            } else {
                $deleted['students'] = 0;
            }

            // 3. courses where is_test = true
            if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'is_test')) {
                $deleted['courses'] = DB::table('courses')->where('is_test', true)->delete();
            } else {
                $deleted['courses'] = 0;
            }

            // 4. batches where is_test = true
            if (Schema::hasTable('batches') && Schema::hasColumn('batches', 'is_test')) {
                $deleted['batches'] = DB::table('batches')->where('is_test', true)->delete();
            } else {
                $deleted['batches'] = 0;
            }

            // 5. institutes where is_test = true
            if (Schema::hasTable('institutes') && Schema::hasColumn('institutes', 'is_test')) {
                $deleted['institutes'] = DB::table('institutes')->where('is_test', true)->delete();
            } else {
                $deleted['institutes'] = 0;
            }

            // 6. users where is_test = true — LAST, after dependents
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_test')) {
                $deleted['users'] = DB::table('users')->where('is_test', true)->delete();
            } else {
                $deleted['users'] = 0;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::critical('test_cleanup.failed', ['error' => $e->getMessage(), 'preview' => $preview]);
            throw $e;
        }

        // Audit
        DataSafetyGuard::auditDestructive([
            'actor_id' => auth()->id() ?? 0,
            'actor_type' => 'system',
            'target_type' => 'test_data',
            'target_id' => 'batch',
            'action' => 'test_cleanup_executed',
            'reason' => 'Explicit is_test=true cleanup',
            'environment' => app()->environment(),
            'database' => config('database.connections.mysql.database'),
            'timestamp' => now()->toIso8601String(),
            'backup_reference' => $backup->filename,
            'before_state' => $preview,
            'after_state' => $deleted,
        ]);

        Log::info('test_cleanup.completed', ['deleted' => $deleted, 'backup' => $backup->filename]);

        return array_merge($preview, [
            'dry_run' => false,
            'backup' => $backup,
            'deleted' => $deleted,
        ]);
    }

    private function collectTestRecords(): array
    {
        $counts = [];
        $samples = [];

        $tables = ['users', 'institutes', 'institution_user', 'students', 'courses', 'batches'];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'is_test')) {
                $counts[$table] = null; // column missing => not applicable yet
                continue;
            }
            $counts[$table] = DB::table($table)->where('is_test', true)->count();
            // Also count protected vs unknown for audit
            $counts[$table.'_protected'] = DB::table($table)->where(function ($q) {
                $q->where('is_test', false)->orWhereNull('is_test');
            })->count();
            $counts[$table.'_unknown_null'] = DB::table($table)->whereNull('is_test')->count();
        }

        // Demonstrate that email-pattern counts are NOT used for deletion
        // We collect them for audit but never delete based on them
        $emailPatternCounts = [];
        try {
            if (Schema::hasTable('users')) {
                $emailPatternCounts['users_email_like_test'] = DB::table('users')->where('email', 'like', '%test%')->count();
                $emailPatternCounts['users_email_like_example'] = DB::table('users')->where('email', 'like', '%@example%')->count();
            }
        } catch (\Throwable $e) {}

        return [
            'counts' => $counts,
            'email_pattern_counts_blocked' => $emailPatternCounts,
            'note' => 'Only is_test=true records are eligible for deletion. Email patterns are BLOCKED.',
        ];
    }
}
