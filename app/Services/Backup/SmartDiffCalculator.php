<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Time-aware diff between a backup snapshot and the live tenant data.
 *
 * Key rule (never touch new data):
 *   db_row.created_at > backup.created_at  ->  KEEP
 *
 * A row is soft-deleted only when ALL hold:
 *   - absent from the backup (deleted after the snapshot)
 *   - created_at <= backup timestamp (existed at snapshot time)
 *   - not already soft-deleted
 *   - the table HAS a deleted_at column (otherwise it is counted as kept)
 */
class SmartDiffCalculator
{
    private const IGNORED_COLUMNS = [
        'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'deleted_reason',
    ];

    private const BLOCKED_TABLES = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'worker_heartbeats', 'backups', 'restore_logs',
        'restore_tokens', 'restore_previews', 'restore_rollbacks', 'restore_audits',
        'password_reset_tokens', 'personal_access_tokens', 'permission_tables',
    ];

    private const BLOCKED_PATTERNS = [
        'backup%', 'cache%', '%_log', '%_logs', '%_audit', '%_audits', 'activity_%',
    ];

    private const SAMPLE_LIMIT = 10;

    /**
     * Counts + samples — used by RestorePreviewService.
     *
     * @param int    $tenantId
     * @param array  $backupTables  [table => ['decrypted_rows' => [...], ...]]
     * @param string $backupTimestamp ISO8601 from manifest['created_at']
     * @return array<table, array{insert:int,update:int,delete:int,kept:int,
     *                            delete_sample:array,kept_sample:array}>
     */
    public function computeDiff(int $tenantId, array $backupTables, string $backupTimestamp): array
    {
        $diff = [];

        foreach ($this->eligibleTables($backupTables) as $table => $info) {
            $analysis = $this->analyze(
                $tenantId,
                $table,
                $info['decrypted_rows'],
                \Carbon\Carbon::parse($backupTimestamp)
            );

            $diff[$table] = [
                'insert'        => count($analysis['insert']),
                'update'        => count($analysis['update']),
                'delete'        => count($analysis['soft_delete']),
                'kept'          => $analysis['kept'],
                'delete_sample' => $analysis['delete_sample'],
                'kept_sample'   => $analysis['kept_sample'],
            ];
        }

        return $diff;
    }

    /**
     * PK lists — used by RestoreService to snapshot rows before touching them.
     *
     * @return array{insert:int[],update:int[],soft_delete:int[],kept:int}
     */
    public function targetPks(int $tenantId, string $table, array $backupRows, \Carbon\Carbon $backupTime): array
    {
        if (!$this->isSafeTable($table) || !Schema::hasTable($table)) {
            return ['insert' => [], 'update' => [], 'soft_delete' => [], 'kept' => 0];
        }

        $analysis = $this->analyze($tenantId, $table, $backupRows, $backupTime);

        return [
            'insert'      => $analysis['insert'],
            'update'      => $analysis['update'],
            'soft_delete' => $analysis['soft_delete'],
            'kept'        => $analysis['kept'],
        ];
    }

    /**
     * Single source of truth: one pass over backup rows + one over DB rows.
     *
     * @return array{insert:int[],update:int[],soft_delete:int[],kept:int,
     *               delete_sample:array,kept_sample:array}
     */
    private function analyze(int $tenantId, string $table, array $backupRows, \Carbon\Carbon $backupTime): array
    {
        $backupByPk = [];
        foreach ($backupRows as $row) {
            $row = (array) $row;
            if (isset($row['id'])) {
                $backupByPk[$row['id']] = $row;
            }
        }

        $dbRows = DB::table($table)
            ->where('institute_id', $tenantId)
            ->get()
            ->keyBy('id');

        $hasSoftDelete = Schema::hasColumn($table, 'deleted_at');

        $insert = [];
        $update = [];
        $softDelete = [];
        $kept = 0;
        $deleteSample = [];
        $keptSample = [];

        // In backup -> recover (INSERT) or overwrite (UPDATE)
        foreach ($backupByPk as $pk => $backupRow) {
            if (!isset($dbRows[$pk])) {
                $insert[] = $pk;
                continue;
            }

            if ($this->rowsDiffer($backupRow, (array) $dbRows[$pk])) {
                $update[] = $pk;
            }
        }

        // In DB but not in backup -> KEEP or SOFT DELETE
        foreach ($dbRows as $pk => $dbRow) {
            if (isset($backupByPk[$pk])) {
                continue;
            }

            $dbRowArr = (array) $dbRow;
            $dbCreatedAt = $dbRowArr['created_at'] ?? null;

            if (!empty($dbRowArr['deleted_at'])) {
                $kept++;   // already tombstoned
                continue;
            }

            if ($dbCreatedAt !== null && \Carbon\Carbon::parse($dbCreatedAt)->gt($backupTime)) {
                $kept++;   // created after the snapshot -> never touch
                if (count($keptSample) < self::SAMPLE_LIMIT) {
                    $keptSample[] = [
                        'id'         => $pk,
                        'label'      => $this->rowLabel($dbRowArr),
                        'created_at' => $dbCreatedAt,
                        'reason'     => 'created_after_backup',
                    ];
                }
                continue;
            }

            if (!$hasSoftDelete) {
                $kept++;   // nothing reversible available for this table
                if (count($keptSample) < self::SAMPLE_LIMIT) {
                    $keptSample[] = [
                        'id'         => $pk,
                        'label'      => $this->rowLabel($dbRowArr),
                        'created_at' => $dbCreatedAt,
                        'reason'     => 'no_deleted_at_column',
                    ];
                }
                continue;
            }

            $softDelete[] = $pk;
            if (count($deleteSample) < self::SAMPLE_LIMIT) {
                $deleteSample[] = [
                    'id'         => $pk,
                    'label'      => $this->rowLabel($dbRowArr),
                    'created_at' => $dbCreatedAt,
                ];
            }
        }

        return [
            'insert'        => $insert,
            'update'        => $update,
            'soft_delete'   => $softDelete,
            'kept'          => $kept,
            'delete_sample' => $deleteSample,
            'kept_sample'   => $keptSample,
        ];
    }

    /**
     * @return array<table, array{decrypted_rows: array}>
     */
    private function eligibleTables(array $backupTables): array
    {
        $eligible = [];

        foreach ($backupTables as $table => $info) {
            if (!$this->isSafeTable($table) || !Schema::hasTable($table)) {
                continue;
            }
            if (!Schema::hasColumn($table, 'created_at') || !Schema::hasColumn($table, 'institute_id')) {
                Log::warning("SmartDiff: {$table} missing created_at/institute_id — skipped");
                continue;
            }

            $rows = $info['decrypted_rows'] ?? [];
            if (!is_array($rows)) {
                continue;
            }

            $eligible[$table] = ['decrypted_rows' => $rows];
        }

        return $eligible;
    }

    private function rowsDiffer(array $backupRow, array $dbRow): bool
    {
        $keys = array_unique(array_merge(array_keys($backupRow), array_keys($dbRow)));

        foreach ($keys as $key) {
            if (in_array($key, self::IGNORED_COLUMNS, true)) {
                continue;
            }

            $left = $backupRow[$key] ?? null;
            $right = $dbRow[$key] ?? null;

            if ($left === null && $right === null) {
                continue;
            }
            if ($left === null || $right === null) {
                if ((string) ($left ?? '') === (string) ($right ?? '')) {
                    continue;
                }

                return true;
            }
            if ((string) $left !== (string) $right) {
                return true;
            }
        }

        return false;
    }

    private function rowLabel(array $row): string
    {
        foreach (['name', 'title', 'invoice_number', 'code', 'email', 'reference'] as $field) {
            if (!empty($row[$field])) {
                return (string) $row[$field];
            }
        }

        return '#' . ($row['id'] ?? '?');
    }

    public function isSafeTable(string $table): bool
    {
        if (in_array($table, self::BLOCKED_TABLES, true)) {
            return false;
        }

        foreach (self::BLOCKED_PATTERNS as $pattern) {
            if (fnmatch($pattern, $table)) {
                return false;
            }
        }

        return true;
    }
}
