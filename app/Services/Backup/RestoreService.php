<?php

namespace App\Services\Backup;

use App\Mail\EmailOtpMail;
use App\Models\Backup;
use App\Models\BackupManifest;
use App\Models\RestoreAudit;
use App\Models\RestoreLog;
use App\Models\RestoreToken;
use App\Models\TenantDriveConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class RestoreService
{
    public const MODE_MERGE = 'merge';
    public const MODE_SMART = 'smart';

    public function __construct(
        private EncryptionService $encryption,
        private \App\Contracts\DriveStorageInterface $drive,
        private BackupService $backup,
        private BackupChunkService $chunkService,
        private ManifestService $manifestService,
        private TenantLockService $lockService,
        private SmartDiffCalculator $diffCalculator,
        private RestoreRollbackService $rollbackService
    ) {}

    /**
     * Generate OTP and send to owner email.
     */
    public function requestOtp(int $tenantId, int $backupId, int $userId): void
    {
        // Rate limit: 3 per 15 min
        $recent = RestoreToken::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('created_at', '>', now()->subMinutes(15))
            ->count();

        if ($recent >= 3) {
            throw new \RuntimeException('Too many restore requests. Try again in 15 minutes.');
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        RestoreToken::create([
            'tenant_id'  => $tenantId,
            'backup_id'  => $backupId,
            'user_id'    => $userId,
            'token_hash' => hash('sha256', $otp),
            'expires_at' => now()->addMinutes(10),
        ]);

        // Deliver via the existing queued OTP mail (same path as login OTP).
        $user = auth()->user();
        if ($user && !empty($user->email)) {
            try {
                Mail::to($user->email)->queue(new EmailOtpMail($otp, $this->maskEmail($user->email)));
            } catch (\Throwable $e) {
                Log::warning('restore_otp_mail_failed', ['error' => $e->getMessage()]);
            }
        }

        Log::info('Restore OTP generated', [
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'otp_length' => strlen($otp),
        ]);
    }

    /**
     * Phase 2C: OTP gate ONLY (sync — controller verifies before dispatching
     * RestoreJob). Marks the token used exactly once.
     */
    public function verifyOtpToken(int $tenantId, int $backupId, int $userId, string $otp): RestoreToken
    {
        $token = RestoreToken::where('tenant_id', $tenantId)
            ->where('backup_id', $backupId)
            ->where('user_id', $userId)
            ->active()
            ->latest()
            ->first();

        if (!$token) {
            throw new \RuntimeException('No active restore token. Request a new OTP.');
        }

        if (!hash_equals($token->token_hash, hash('sha256', $otp))) {
            throw new \RuntimeException('Invalid OTP');
        }

        $token->update(['used_at' => now()]);

        return $token;
    }

    /**
     * Verify OTP and perform restore (SYNC path — Adaptation 9: kept intact
     * for service-level callers/tests).
     */
    public function verifyAndRestore(int $tenantId, int $backupId, int $userId, string $otp): RestoreLog
    {
        $this->verifyOtpToken($tenantId, $backupId, $userId, $otp);

        $backup = Backup::where('tenant_id', $tenantId)->findOrFail($backupId);

        $log = RestoreLog::create([
            'tenant_id'  => $tenantId,
            'backup_id'  => $backupId,
            'user_id'    => $userId,
            'mode'       => 'merge',
            'status'     => 'pending',
        ]);

        return $this->runRestore($log, $backup, $tenantId);
    }

    /**
     * Phase 2C: job entry point — log row already created by the controller
     * after the OTP gate. Runs the restore body with progress tracking.
     *
     * Phase 2D: acquires the per-tenant lock (shared key with backup + GC).
     * Fail-fast: a concurrent operation is rejected immediately.
     * (verifyAndRestore() sync path stays lock-free for existing tests.)
     */
    public function executeRestore(
        RestoreLog $log,
        int $backupId,
        int $tenantId,
        int $userId,
        string $mode = self::MODE_MERGE
    ): RestoreLog {
        $mode = $mode === self::MODE_SMART ? self::MODE_SMART : self::MODE_MERGE;

        $lock = $this->lockService->acquire($tenantId, 'restore');

        if (!$lock) {
            $log->update([
                'status'           => 'failed',
                'error_message'    => 'Another operation is running for this tenant. Please wait and retry.',
                'progress_stage'   => 'locked',
                'progress_message' => 'Blocked by concurrent operation',
            ]);

            throw new \RuntimeException('Tenant is locked');
        }

        try {
            $backup = Backup::where('tenant_id', $tenantId)->findOrFail($backupId);

            return $this->runRestore($log, $backup, $tenantId, $userId, $mode);
        } finally {
            $this->lockService->release($lock, $tenantId, 'restore');
        }
    }

    /**
     * Shared restore body (manifest vs legacy) + progress + failure marking.
     */
    private function runRestore(
        RestoreLog $log,
        Backup $backup,
        int $tenantId,
        int $userId = 0,
        string $mode = self::MODE_MERGE
    ): RestoreLog {
        $progress = app(ProgressService::class);

        try {
            $progress->updateRestore($log, 5, 'starting', 'Preparing restore...');

            $log->update(['mode' => $mode]);

            $manifest = BackupManifest::where('backup_id', $backup->id)->first();

            $affected = $manifest
                ? $this->restoreFromManifest($backup, $manifest, $log, $tenantId, $userId, $mode)
                : $this->restoreLegacy($backup, $log, $tenantId);

            $log->update([
                'status'              => 'completed',
                'records_affected'    => $affected,
                'rollback_expires_at' => now()->addHours(24),
                'completed_at'        => now(),
            ]);

            $progress->updateRestore($log, 100, 'completed', 'Restore complete');

            $this->writeAudit($tenantId, $log, $userId, $mode, $affected);

            return $log;
        } catch (\Throwable $e) {
            $log->update([
                'status'           => 'failed',
                'records_affected' => ['error' => $e->getMessage()],
                'error_message'    => $e->getMessage(),
                'progress_stage'   => 'failed',
                'progress_message' => 'Restore failed: ' . $e->getMessage(),
            ]);
            throw $e;
        } finally {
            $progress->clear($log);
        }
    }

    /**
     * Phase 2A: manifest-driven restore.
     * 1. Verify manifest self-checksum (tamper gate)
     * 2. Rollback snapshot BEFORE restore
     * 3. Per table: download chunks → decrypt → verify SHA256 → merge
     */
    private function restoreFromManifest(
        Backup $backup,
        BackupManifest $manifest,
        RestoreLog $log,
        int $tenantId,
        int $userId = 0,
        string $mode = self::MODE_MERGE
    ): array {
        $manifestArr = json_decode($manifest->manifest_json, true);

        if (!is_array($manifestArr) || !$this->manifestService->verifyChecksum($manifestArr)) {
            throw new \RuntimeException('Manifest checksum invalid — refusing restore');
        }

        $conn = TenantDriveConnection::where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->first();

        if (!$conn) {
            throw new \RuntimeException(
                'Google Drive connection not found for this backup. Reconnect Drive first.'
            );
        }

        $smart = $mode === self::MODE_SMART;
        $backupTime = null;
        $rollbackRows = [];

        if ($smart) {
            if (empty($manifestArr['created_at'])) {
                throw new \RuntimeException('Manifest missing created_at — smart restore unavailable');
            }
            $backupTime = \Carbon\Carbon::parse($manifestArr['created_at']);
        } else {
            // Gate D: merge mode keeps the legacy file snapshot; smart mode
            // uses restore_rollbacks exclusively (no double snapshot).
            $progress = app(ProgressService::class);
            $progress->updateRestore($log, 15, 'snapshot', 'Creating rollback snapshot...');

            $rollbackPath = $this->createRollbackSnapshot($tenantId);
            $log->update(['rollback_path' => $rollbackPath]);
        }

        $affected = [];
        $tables = $manifestArr['tables'] ?? [];
        $tableCount = max(1, count($tables));
        $done = 0;

        // Total chunks for this manifest (progress denominator)
        $totalChunks = \App\Models\BackupChunk::where('manifest_id', $manifest->id)->count();
        $log->update(['total_chunks' => $totalChunks, 'downloaded_chunks' => 0]);

        foreach ($tables as $table => $info) {
            $pct = 20 + (int) round(70 * ($done / $tableCount));
            $progress = app(ProgressService::class);
            $progress->updateRestore($log, $pct, 'downloading', "Table: {$table}");

            // Phase 2C: parallel download (10 concurrent) — falls back to
            // sequential automatically for non-Google drives (tests).
            $json = $this->chunkService->downloadTableChunksParallel($manifest, $table, $conn);
            $rows = json_decode($json, true);

            if (!is_array($rows)) {
                $affected[$table] = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
                $done++;
                continue;
            }

            if ($smart && $this->diffCalculator->isSafeTable($table)) {
                // Pre-image BEFORE mutation (rollback safety net)
                $targets = $this->diffCalculator->targetPks(
                    $tenantId,
                    $table,
                    $rows,
                    $backupTime
                );

                $touchPks = array_merge($targets['update'], $targets['soft_delete']);
                if (!empty($touchPks)) {
                    $captured = $this->rollbackService->captureRows($tenantId, $table, $touchPks);
                    if (!empty($captured)) {
                        $rollbackRows[$table] = ($rollbackRows[$table] ?? []) + $captured;
                    }
                }

                $progress->updateRestore($log, $pct, 'restoring', "Table: {$table}");
                $affected[$table] = $this->applySmartMode($tenantId, $table, $rows, $backupTime, $userId);
            } else {
                $progress->updateRestore($log, $pct, 'merging', "Table: {$table}");
                $affected[$table] = $this->mergeTable($table, $rows, $tenantId);
            }

            // Chunks for this table downloaded + merged
            $tableChunks = \App\Models\BackupChunk::where('manifest_id', $manifest->id)
                ->where('source_table', $table)->count();
            $log->update([
                'downloaded_chunks' => (int) $log->downloaded_chunks + $tableChunks,
            ]);

            $done++;
        }

        if ($smart) {
            $rollback = $this->rollbackService->snapshotForRollback($tenantId, $log->id, $rollbackRows);
            if ($rollback) {
                $log->update(['rollback_token' => $rollback->rollback_token]);
            }
        }

        $progress = app(ProgressService::class);
        $progress->updateRestore($log, 95, 'finalizing', 'Restore finalize...');

        Log::info('Manifest restore completed', [
            'tenant_id' => $tenantId,
            'backup_id' => $backup->id,
            'mode'      => $mode,
            'tables'    => count($affected),
        ]);

        return $affected;
    }

    /**
     * SMART restore — time-aware, reversible.
     *
     * 1. Backup rows: INSERT if missing, UPDATE if present (backup wins)
     * 2. DB rows absent from the backup:
     *      created after backup  -> KEEP (never touch new data)
     *      already soft-deleted  -> KEEP
     *      existed at backup time -> SOFT DELETE (30-day reversible)
     *
     * Every row touched here must already be captured by
     * RestoreRollbackService::captureRows() (caller responsibility).
     */
    private function applySmartMode(
        int $tenantId,
        string $table,
        array $backupRows,
        \Carbon\Carbon $backupTime,
        int $userId = 0
    ): array {
        if (!Schema::hasTable($table)) {
            return ['inserted' => 0, 'updated' => 0, 'soft_deleted' => 0, 'kept' => 0];
        }

        $inserted = 0;
        $updated = 0;
        $softDeleted = 0;
        $kept = 0;

        $columns = Schema::getColumnListing($table);
        $hasSoftDelete = in_array('deleted_at', $columns, true);
        $hasDeletedBy = in_array('deleted_by', $columns, true);
        $hasDeletedReason = in_array('deleted_reason', $columns, true);
        // Parity with SmartDiffCalculator::analyze() — without a time anchor
        // the preview reports KEEP for this table, so restore must not delete.
        $hasCreatedAt = in_array('created_at', $columns, true);

        $backupByPk = [];
        foreach ($backupRows as $row) {
            $row = (array) $row;
            if (isset($row['id'])) {
                $backupByPk[$row['id']] = $row;
            }
        }

        DB::transaction(function () use (
            $tenantId, $table, $backupByPk, $backupTime, $userId,
            $columns, $hasSoftDelete, $hasDeletedBy, $hasDeletedReason,
            $hasCreatedAt,
            &$inserted, &$updated, &$softDeleted, &$kept
        ) {
            $dbRows = DB::table($table)
                ->where('institute_id', $tenantId)
                ->get()
                ->keyBy('id');

            foreach ($backupByPk as $pk => $backupRow) {
                $filtered = array_intersect_key($backupRow, array_flip($columns));
                $filtered['institute_id'] = $tenantId;

                if ($hasSoftDelete && !array_key_exists('deleted_at', $filtered)) {
                    $filtered['deleted_at'] = null;
                    if ($hasDeletedBy) {
                        $filtered['deleted_by'] = null;
                    }
                    if ($hasDeletedReason) {
                        $filtered['deleted_reason'] = null;
                    }
                }

                try {
                    if (isset($dbRows[$pk])) {
                        DB::table($table)
                            ->where('id', $pk)
                            ->where('institute_id', $tenantId)
                            ->update($filtered);
                        $updated++;
                    } else {
                        DB::table($table)->insert($filtered);
                        $inserted++;
                    }
                } catch (\Throwable $e) {
                    Log::warning("Smart restore row failed: {$table}.id={$pk}", [
                        'error' => $e->getMessage(),
                    ]);
                    $kept++;
                }
            }

            if (!$hasSoftDelete || !$hasCreatedAt) {
                return;
            }

            foreach ($dbRows as $pk => $dbRow) {
                if (isset($backupByPk[$pk])) {
                    continue;
                }

                $dbRowArr = (array) $dbRow;

                if (!empty($dbRowArr['deleted_at'])) {
                    $kept++;
                    continue;
                }

                $createdAt = $dbRowArr['created_at'] ?? null;

                // ⭐ NEVER delete data created after the backup snapshot
                if ($createdAt !== null && \Carbon\Carbon::parse($createdAt)->gt($backupTime)) {
                    $kept++;
                    continue;
                }

                $payload = ['deleted_at' => now()];
                if ($hasDeletedBy) {
                    $payload['deleted_by'] = $userId;
                }
                if ($hasDeletedReason) {
                    $payload['deleted_reason'] = 'Smart restore — deleted after backup';
                }

                DB::table($table)->where('id', $pk)->where('institute_id', $tenantId)->update($payload);
                $softDeleted++;
            }
        });

        return [
            'inserted'     => $inserted,
            'updated'      => $updated,
            'soft_deleted' => $softDeleted,
            'kept'         => $kept,
        ];
    }

    /**
     * Full audit trail for every restore (merge + smart).
     */
    private function writeAudit(
        int $tenantId,
        RestoreLog $log,
        int $userId,
        string $mode,
        array $affected
    ): void {
        try {
            $request = app()->bound('request') ? request() : null;

            RestoreAudit::create([
                'tenant_id'       => $tenantId,
                'restore_log_id'  => $log->id,
                'user_id'         => $userId ?: (int) $log->user_id,
                'mode'            => $mode,
                'affected_tables' => $affected,
                'total_affected'  => (int) collect($affected)->flatten()->filter()->sum(),
                'ip_address'      => $request?->ip(),
                'user_agent'      => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Restore audit write failed', [
                'restore_log_id' => $log->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * F4 legacy fallback: single-file .enc restore (pre-2A backups).
     * Drive is source of truth when destination === 'drive'.
     */
    private function restoreLegacy(Backup $backup, RestoreLog $log, int $tenantId): array
    {
        app(ProgressService::class)->updateRestore($log, 20, 'downloading', 'Downloading backup file...');

        $workDir = storage_path("app/restore-work/{$log->id}");
        $tarPath = "{$workDir}.tar.gz";
        $downloadedEnc = null;

        try {
            // Resolve the ciphertext — Drive is the source of truth
            // once destination === 'drive' (local .enc was deleted after upload).
            if ($backup->destination === 'drive' && $backup->drive_file_id) {
                $driveConn = TenantDriveConnection::where('tenant_id', $tenantId)
                    ->whereNull('revoked_at')
                    ->first();

                if (!$driveConn) {
                    throw new \RuntimeException(
                        'Google Drive connection not found for this backup. Reconnect Drive first.'
                    );
                }

                $encPath = storage_path("app/restore-work/{$log->id}.enc");
                @mkdir(dirname($encPath), 0755, true);

                $this->drive->downloadBackup($driveConn, $backup->drive_file_id, $encPath);
                $downloadedEnc = $encPath;
            } else {
                $encPath = storage_path("app/backups/{$backup->filename}");
                if (!file_exists($encPath)) {
                    throw new \RuntimeException('Backup file not found locally or on Drive');
                }
            }

            // Rollback snapshot BEFORE restore
            $rollbackPath = $this->createRollbackSnapshot($tenantId);
            $log->update(['rollback_path' => $rollbackPath]);

            // Decrypt with tamper detection
            @mkdir($workDir, 0755, true);

            $this->encryption->decryptFile($encPath, $tarPath, $tenantId, $backup->file_hmac);

            // Extract + restore with MERGE semantics
            $affected = $this->restoreFromTar($tarPath, $tenantId);

            @unlink($tarPath);
            if ($downloadedEnc !== null) {
                @unlink($downloadedEnc);
            }
            $this->rmrf($workDir);

            return $affected;
        } catch (\Throwable $e) {
            @unlink($tarPath);
            if ($downloadedEnc !== null) {
                @unlink($downloadedEnc);
            }
            $this->rmrf($workDir);
            throw $e;
        }
    }

    /**
     * Snapshot all tenant data BEFORE a restore so it can be rolled back
     * (rollback application = Phase 2; file expires after 24h).
     * Streams per-table JSON into a single .json.gz (no full-table loads).
     */
    private function createRollbackSnapshot(int $tenantId): string
    {
        $dir = storage_path('app/rollback-snapshots');
        @mkdir($dir, 0755, true);

        $filename = "rollback-tenant-{$tenantId}-" . now()->format('Ymd-His') . '.json.gz';
        $path = "{$dir}/{$filename}";

        $tables = $this->backup->getTenantTables(); // [table => tenant column]
        $tableCount = 0;

        $final = gzopen($path, 'wb');
        if ($final === false) {
            throw new \RuntimeException("Cannot create rollback snapshot: {$path}");
        }
        gzwrite($final, json_encode([
            'meta' => [
                'tenant_id'   => $tenantId,
                'created_at'  => now()->toIso8601String(),
                'table_count' => count($tables),
            ],
        ]));

        foreach ($tables as $table => $column) {
            // Stream write per table to avoid memory issues
            $tempFile = "{$dir}/tmp-{$table}.json";
            $fp = fopen($tempFile, 'wb');
            if ($fp === false) {
                continue;
            }
            fwrite($fp, '[');

            $first = true;
            $query = DB::table($table)->where($column, $tenantId);
            // PK-aware ordering (shared with backup export) — chunk() requires orderBy
            foreach ($this->backup->primaryKeyColumns($table) as $key) {
                $query->orderBy($key);
            }
            $query->chunk(500, function ($rows) use ($fp, &$first) {
                foreach ($rows as $row) {
                    if (!$first) fwrite($fp, ',');
                    fwrite($fp, json_encode((array) $row));
                    $first = false;
                }
            });

            fwrite($fp, ']');
            fclose($fp);

            gzwrite($final, "\n---TABLE:{$table}---\n");
            gzwrite($final, file_get_contents($tempFile));
            @unlink($tempFile);
            $tableCount++;
        }

        gzclose($final);

        if ($tableCount === 0) {
            @unlink($path);
            throw new \RuntimeException('Rollback snapshot failed: no tenant tables exported');
        }

        return $path;
    }

    /**
     * Extract backup and apply rows with MERGE semantics:
     * INSERT new + UPDATE existing — never DELETE.
     * Per-row error handling (log + skip) so one bad row can't abort.
     */
    private function restoreFromTar(string $tarPath, int $tenantId): array
    {
        $workDir = storage_path('app/restore-work/' . uniqid('r', true));
        @mkdir($workDir, 0755, true);

        $affected = [];

        try {
            // Extract tar.gz
            $phar = new \PharData($tarPath);
            $phar->extractTo($workDir, null, true);

            // Process each table JSON
            foreach (glob("{$workDir}/*.json") ?: [] as $jsonFile) {
                $table = basename($jsonFile, '.json');
                if ($table === '_metadata') continue;

                $rows = json_decode(file_get_contents($jsonFile), true);
                if (!is_array($rows)) {
                    $affected[$table] = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
                    continue;
                }

                $affected[$table] = $this->mergeTable($table, $rows, $tenantId);
            }
        } finally {
            // Cleanup extraction dir (success or failure)
            $this->rmrf($workDir);
        }

        return $affected;
    }

    /**
     * MERGE one table's rows: INSERT new + UPDATE existing — never DELETE.
     * Shared by legacy tar restore and manifest chunk restore.
     */
    private function mergeTable(string $table, array $rows, int $tenantId): array
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($table, $rows, $tenantId, &$inserted, &$updated, &$skipped) {
            foreach ($rows as $row) {
                $rowArr = (array) $row;

                // Skip if no primary key
                if (!isset($rowArr['id'])) {
                    $skipped++;
                    continue;
                }

                // Ensure tenant scoping
                if (isset($rowArr['institute_id'])) {
                    $rowArr['institute_id'] = $tenantId;
                }

                $exists = DB::table($table)->where('id', $rowArr['id'])->exists();

                try {
                    if ($exists) {
                        // UPDATE existing
                        DB::table($table)->where('id', $rowArr['id'])->update($rowArr);
                        $updated++;
                    } else {
                        // INSERT new
                        DB::table($table)->insert($rowArr);
                        $inserted++;
                    }
                } catch (\Throwable $e) {
                    // Log + skip individual row (don't abort entire restore)
                    Log::warning("Restore row failed: {$table}.id={$rowArr['id']}", [
                        'error' => $e->getMessage(),
                    ]);
                    $skipped++;
                }
            }
        });

        return [
            'inserted' => $inserted,
            'updated'  => $updated,
            'skipped'  => $skipped,
        ];
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $masked = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 1));

        return $masked . '@' . $domain;
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
        }
        rmdir($dir);
    }
}
