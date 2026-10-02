<?php

namespace App\Services\Backup;

use App\Models\RestoreRollback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Smart-mode rollback: captures a pre-image of every row the restore is about
 * to touch (UPDATE targets + SOFT DELETE targets) and can restore it later.
 *
 * Note (Gate D): merge mode keeps using RestoreService::createRollbackSnapshot()
 * (restore_logs.rollback_path). Smart mode uses this table only — no double
 * snapshot.
 */
class RestoreRollbackService
{
    public const RETENTION_DAYS = 30;

    /**
     * Read the current rows for the given PKs — MUST be called BEFORE the
     * restore mutates them.
     *
     * @return array<int|string, object> rows keyed by primary key
     */
    public function captureRows(int $tenantId, string $table, array $pks): array
    {
        if (empty($pks) || !Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->where('institute_id', $tenantId)
            ->whereIn('id', $pks)
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * Persist the accumulated pre-images as one rollback record.
     *
     * @param array<string, array> $tablesWithRows [table => [pk => row, ...]]
     */
    public function snapshotForRollback(
        int $tenantId,
        int $restoreLogId,
        array $tablesWithRows
    ): ?RestoreRollback {
        $snapshot = [];
        $totalRows = 0;

        foreach ($tablesWithRows as $table => $rows) {
            if (empty($rows) || !is_array($rows)) {
                continue;
            }
            if (!Schema::hasTable($table)) {
                continue;
            }

            $snapshot[$table] = array_values($rows);
            $totalRows += count($rows);
        }

        if ($totalRows === 0) {
            return null;
        }

        return RestoreRollback::create([
            'tenant_id'      => $tenantId,
            'restore_log_id' => $restoreLogId,
            'rollback_token' => RestoreRollback::generateToken(),
            'snapshot_data'  => $snapshot,
            'total_rows'     => $totalRows,
            'expires_at'     => now()->addDays(self::RETENTION_DAYS),
        ]);
    }

    /**
     * Undo a smart restore: put every captured row back exactly as it was
     * (re-inserts rows that no longer exist, clears/rewrites deleted_at).
     */
    public function rollback(RestoreRollback $rollback): array
    {
        if (!$rollback->isUsable()) {
            throw new \RuntimeException('Rollback expired or already used');
        }

        $restored = 0;
        $failed = 0;

        DB::transaction(function () use ($rollback, &$restored, &$failed) {
            foreach ($rollback->snapshot_data as $table => $rows) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                $columns = Schema::getColumnListing($table);

                foreach ($rows as $row) {
                    $row = (array) $row;
                    if (!isset($row['id'])) {
                        continue;
                    }

                    // Only columns that still exist (schema may have changed)
                    $payload = array_intersect_key($row, array_flip($columns));

                    try {
                        $affected = DB::table($table)
                            ->where('id', $row['id'])
                            ->update($payload);

                        if ($affected === 0) {
                            DB::table($table)->insert($payload);
                        }

                        $restored++;
                    } catch (\Throwable $e) {
                        $failed++;
                    }
                }
            }

            $rollback->update(['used_at' => now()]);
        });

        return ['restored' => $restored, 'failed' => $failed];
    }
}
