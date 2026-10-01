<?php

namespace App\Services\Backup;

use App\Contracts\DriveStorageInterface;
use App\Models\Backup;
use App\Models\BackupChunk;
use App\Models\BackupManifest;
use App\Models\TenantDriveConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BackupService
{
    public function __construct(
        private EncryptionService $encryption,
        private DriveStorageInterface $drive,
        private BackupChunkService $chunkService,
        private ManifestService $manifestService,
        private ChunkReferenceService $refService
    ) {}

    /**
     * Silent backup — no passphrase, no user prompt.
     *
     * Two paths (config backup.chunk_enabled):
     *  - chunked (default): per-table JSON → SHA256-deduped encrypted chunks
     *    → Drive + self-checksummed manifest. No local .enc is ever written.
     *  - legacy: single tar.gz → encrypt → upload → delete local .enc.
     */
    public function createBackup(int $tenantId, int $userId): Backup
    {
        $backup = $this->createPendingBackup($tenantId, $userId);

        try {
            return $this->executeBackup($backup, $tenantId, $userId);
        } catch (\Throwable $e) {
            $backup->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            @unlink(storage_path("app/backup-work/{$backup->id}.tar.gz"));
            $this->rmrf(storage_path("app/backup-work/{$backup->id}"));
            throw $e;
        }
    }

    /**
     * Phase 2C: pending-row factory (controller pre-flight + dispatch path).
     * Sync callers (createBackup) and async callers (BackupJob) share it so
     * filename/pending state is identical.
     */
    public function createPendingBackup(int $tenantId, int $userId): Backup
    {
        return Backup::create([
            'tenant_id'       => $tenantId,
            'owner_user_id'   => $userId,
            'filename'        => 'backup-' . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.enc',
            'status'          => 'pending',
            'is_chunked'      => (bool) config('backup.chunk_enabled', true),
            'destination'     => 'drive',
            'progress_stage'  => 'queued',
            'progress_message' => 'Queued...',
        ]);
    }

    /**
     * Phase 2C: job entry point — runs an EXISTING pending/failed row with
     * progress tracking. Adaptation 9: createBackup() keeps its sync path.
     */
    public function executeBackup(Backup $backup, int $tenantId, int $userId): Backup
    {
        $progress = app(ProgressService::class);

        try {
            // Guard: disk space — refuse below hard floor, warn below soft floor
            // (lives here so BOTH sync createBackup and async BackupJob get it)
            $freeMb = disk_free_space(storage_path()) / 1024 / 1024;
            $refuseMb = (int) config('backup.refuse_free_space_mb', 512);
            $warnMb = (int) config('backup.min_free_space_mb', 1024);
            if ($freeMb < $refuseMb) {
                throw new \RuntimeException("Insufficient disk space ({$freeMb}MB free, refuse below {$refuseMb}MB)");
            }
            if ($freeMb < $warnMb) {
                Log::warning('backup_low_disk_space', [
                    'free_mb' => round($freeMb, 1),
                    'warn_mb' => $warnMb,
                ]);
            }

            $progress->updateBackup($backup, 2, 'starting', 'Preparing...');
            $backup->update(['status' => 'uploading', 'started_at' => now()]);

            $chunked = (bool) config('backup.chunk_enabled', true);
            $result = $chunked
                ? $this->createChunkedBackup($backup, $tenantId)
                : $this->createLegacyBackup($backup, $tenantId);

            $progress->updateBackup($result, 100, 'completed', 'Backup complete');

            return $result;
        } finally {
            $progress->clear($backup);
        }
    }

    /**
     * Phase 2A: content-addressed chunked backup.
     *
     * F5: tables are STREAMED to per-table JSON files (dumpTable/chunk(500))
     * and the chunk service reads those files sequentially — no whole-table
     * or whole-JSON strings are ever held in memory.
     */
    private function createChunkedBackup(Backup $backup, int $tenantId): Backup
    {
        $conn = TenantDriveConnection::where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->first();

        if (!$conn) {
            throw new \RuntimeException('Google Drive not connected. Please connect Drive first.');
        }

        $folders = $this->drive->ensureTenantFolderStructure($conn);

        // Dedup source: previous manifest (survives keep-1 — only older ones
        // are trashed, the newest always remains until superseded).
        $oldManifest = BackupManifest::where('tenant_id', $tenantId)
            ->where('backup_id', '!=', $backup->id)
            ->orderByDesc('id')
            ->first();

        $workDir = storage_path("app/backup-work/{$backup->id}");
        @mkdir($workDir, 0755, true);

        $maxMb = (int) config('backup.max_backup_size_mb', 500);
        $tableData = [];
        $totalNew = 0;
        $totalReused = 0;

        $progress = app(ProgressService::class);
        $tables = $this->getTenantTables();
        $tableCount = max(1, count($tables));
        $done = 0;
        $uploadedChunks = 0;

        $progress->updateBackup($backup, 10, 'preparing', 'Exporting tables...');

        foreach ($tables as $table => $column) {
            $jsonFile = "{$workDir}/{$table}.json";
            $rowCount = $this->dumpTable($table, $column, $tenantId, $jsonFile);

            if ($rowCount === 0) {
                @unlink($jsonFile);
                $done++;
                continue;
            }

            $sizeMb = filesize($jsonFile) / 1048576;
            if ($sizeMb > $maxMb) {
                @unlink($jsonFile);
                throw new \RuntimeException("Backup exceeds limit ({$table}: {$sizeMb}MB > {$maxMb}MB)");
            }

            // 10–45%: exporting/uploading table by table
            $pct = 10 + (int) round(35 * ($done / $tableCount));
            $progress->updateBackup($backup, $pct, 'uploading', "Table: {$table}");

            $entry = $this->chunkService->processTable(
                $tenantId, $table, $jsonFile, $conn, $folders['chunks'], $oldManifest
            );
            $entry['row_count'] = $rowCount;
            $tableData[$table] = $entry;

            foreach ($entry['chunks'] as $chunk) {
                $chunk['reused'] ? $totalReused++ : $totalNew++;
            }
            $uploadedChunks += count($entry['chunks']);
            $backup->update([
                'uploaded_chunks' => $uploadedChunks,
                'total_chunks'    => $uploadedChunks,
            ]);

            $done++;
            @unlink($jsonFile);
        }

        // Build + save manifest (encrypt → Drive current + checksum + timestamped copy)
        $progress->updateBackup($backup, 90, 'finalizing', 'Saving manifest...');
        $manifest = $this->manifestService->buildManifest($tenantId, $backup->id, $tableData);
        $manifestRecord = $this->manifestService->saveManifest(
            $backup, $conn, $manifest, $this->encryption
        );

        // Persist chunk rows (dedup reuses drive_file_id — rows are per-manifest)
        $chunkRowIds = [];
        foreach ($tableData as $table => $data) {
            foreach ($data['chunks'] as $chunk) {
                $chunkRowIds[] = BackupChunk::create([
                    'manifest_id'    => $manifestRecord->id,
                    'tenant_id'      => $tenantId,
                    'content_sha256' => $chunk['hash'],
                    'file_hmac'      => $chunk['file_hmac'],
                    'drive_file_id'  => $chunk['drive_file_id'],
                    'source_table'   => $table,
                    'chunk_index'    => $chunk['index'],
                    'total_chunks'   => $data['total_chunks'],
                    'size_bytes'     => $chunk['size'],
                ])->id;
            }
        }

        // Phase 2B: register row-level references (manifest × chunk + file_id)
        $this->refService->registerReferences($tenantId, $manifestRecord, $chunkRowIds);

        $backup->update([
            'status'          => 'completed',
            'completed_at'    => now(),
            'size_bytes'      => $manifestRecord->total_size_bytes,
            'destination'     => 'drive',
            'drive_file_id'   => $manifestRecord->drive_file_id, // manifest = restore entry point
            'drive_folder_id' => $conn->app_folder_id,
            'is_chunked'      => true,
        ]);

        $conn->update(['last_sync_at' => now()]);

        // F8: local workdir must be gone after success (Drive = source of truth)
        $this->verifyAndCleanupLocal(null, $workDir);

        if (config('backup.delete_previous_on_success', true)) {
            $this->cleanupPreviousBackups($tenantId, $backup->id);
        }

        Log::info('Chunked backup completed', [
            'backup_id'     => $backup->id,
            'tenant_id'     => $tenantId,
            'tables'        => count($tableData),
            'chunks_new'    => $totalNew,
            'chunks_reused' => $totalReused,
            'size_bytes'    => $manifestRecord->total_size_bytes,
        ]);

        return $backup;
    }

    /**
     * Legacy single-file path (BACKUP_CHUNK_ENABLED=false escape hatch).
     * Drive upload failure deliberately KEEPS the local .enc (only copy).
     */
    private function createLegacyBackup(Backup $backup, int $tenantId): Backup
    {
        $progress = app(ProgressService::class);
        $progress->updateBackup($backup, 30, 'uploading', 'Exporting tables...');

        $workDir = storage_path("app/backup-work/{$backup->id}");
        $tarPath = $this->exportTenantData($tenantId, $workDir);

        // Guard: backup size
        $sizeMb = filesize($tarPath) / 1024 / 1024;
        $maxMb = (int) config('backup.max_backup_size_mb', 500);
        if ($sizeMb > $maxMb) {
            throw new \RuntimeException("Backup exceeds limit ({$sizeMb}MB > {$maxMb}MB)");
        }

        $encPath = storage_path("app/backups/{$backup->filename}");
        @mkdir(dirname($encPath), 0755, true);

        $hmac = $this->encryption->encryptFile($tarPath, $encPath, $tenantId);

        $backup->update([
            'size_bytes' => filesize($encPath),
            'file_hmac'  => $hmac,
        ]);

        // Integrity check BEFORE any previous backup is removed.
        if (!$this->encryption->verifyFileHmac($encPath, $hmac)) {
            @unlink($encPath);
            throw new \RuntimeException('Backup integrity verification failed');
        }

        $driveConn = TenantDriveConnection::where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->first();

        if (!$driveConn) {
            throw new \RuntimeException('Google Drive not connected. Please connect Drive first.');
        }

        app(ProgressService::class)->updateBackup($backup, 70, 'uploading', 'Uploading to Drive...');

        try {
            $driveFileId = $this->drive->uploadBackup($driveConn, $encPath, $backup->filename);

            $backup->update([
                'destination'   => 'drive',
                'drive_file_id' => $driveFileId,
                'status'        => 'completed',
                'completed_at'  => now(),
            ]);

            $driveConn->update(['last_sync_at' => now()]);

            // F8: local .enc + workdir removed ONLY after Drive success,
            // verified gone (orphans are logged for review).
            $this->verifyAndCleanupLocal($encPath, $workDir);
        } catch (\Throwable $e) {
            // Local .enc deliberately kept: it is the only copy right now.
            @unlink($tarPath);
            throw new \RuntimeException('Drive upload failed: ' . $e->getMessage());
        }

        @unlink($tarPath);

        if (config('backup.delete_previous_on_success', true)) {
            $this->cleanupPreviousBackups($tenantId, $backup->id);
        }

        return $backup;
    }

    /**
     * F8: delete local artifacts after Drive success and VERIFY they are gone.
     * $encPath = null for chunked backups (no local .enc is ever created).
     * Failure to delete is logged as error but never fails the backup —
     * Drive is already the source of truth at this point.
     * Scoped orphan scan: only backup-*.enc (never system monetix_*.sql).
     */
    public function verifyAndCleanupLocal(?string $encPath, string $workDir): void
    {
        if ($encPath !== null && file_exists($encPath)) {
            @unlink($encPath);
            if (file_exists($encPath)) {
                Log::error('backup_local_cleanup_failed', ['file' => basename($encPath)]);
            }
        }

        $this->rmrf($workDir);
        if (is_dir($workDir)) {
            Log::error('backup_workdir_cleanup_failed', ['dir' => basename($workDir)]);
        }

        // Orphan scan (warning only): leftover backup-*.enc from earlier failures.
        $orphans = array_diff(glob(storage_path('app/backups/backup-*.enc')) ?: [], [$encPath]);
        if ($orphans) {
            Log::warning('backup_orphan_local_files', [
                'files' => array_map('basename', array_values($orphans)),
            ]);
        }
    }

    private function exportTenantData(int $tenantId, string $workDir): string
    {
        @mkdir($workDir, 0755, true);

        $tables = $this->getTenantTables();
        $counts = [];

        foreach ($tables as $table => $column) {
            $counts[$table] = $this->dumpTable($table, $column, $tenantId, "{$workDir}/{$table}.json");
        }

        file_put_contents("{$workDir}/_metadata.json", json_encode([
            'tenant_id'   => $tenantId,
            'exported_at' => now()->toIso8601String(),
            'version'     => '1.0',
            'tables'      => array_keys($tables),
            'row_counts'  => $counts,
        ]));

        $tarPath = "{$workDir}.tar.gz";
        $tarRaw = substr($tarPath, 0, -3); // strip .gz -> .tar
        @unlink($tarRaw);
        @unlink($tarPath);

        $phar = new \PharData($tarRaw);
        $phar->buildFromDirectory($workDir);
        $phar->compress(\Phar::GZ);
        unset($phar);
        @unlink($tarRaw);

        return $tarPath;
    }

    /**
     * Stream one tenant-scoped table to JSON (chunked — never loads the
     * whole table into memory). Returns the exported row count.
     */
    private function dumpTable(string $table, string $column, int $tenantId, string $outFile): int
    {
        $fh = fopen($outFile, 'w');
        if ($fh === false) {
            throw new \RuntimeException("Cannot write export file: {$outFile}");
        }

        fwrite($fh, '[');
        $count = 0;

        $query = DB::table($table)->where($column, $tenantId);
        $keys = $this->primaryKeyColumns($table);
        foreach ($keys as $key) {
            $query->orderBy($key);
        }

        $query->chunk(500, function ($rows) use ($fh, &$count) {
            foreach ($rows as $row) {
                fwrite($fh, ($count > 0 ? ',' : '') . json_encode((array) $row));
                $count++;
            }
        });

        fwrite($fh, ']');
        fclose($fh);

        return $count;
    }

    /**
     * Every tenant-scoped table (has institute_id), excluding backup
     * bookkeeping tables so a restore never re-imports backup metadata.
     * ADAPT: single information_schema query — no hard-coded table list.
     * Public: RestoreService builds rollback snapshots from the same list.
     */
    public function getTenantTables(): array
    {
        $skip = [
            'backups', 'tenant_backup_keys', 'restore_tokens', 'restore_logs',
            'system_backups', 'backup_verification_logs',
            'tenant_recovery_archives', 'tenant_deletion_requests',
            'backup_manifests', 'backup_chunks', 'backup_chunk_trash',
        ];

        $rows = DB::select(
            "SELECT TABLE_NAME AS t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'institute_id'
             ORDER BY TABLE_NAME"
        );

        $tables = [];
        foreach ($rows as $row) {
            if (!in_array($row->t, $skip, true)) {
                $tables[$row->t] = 'institute_id';
            }
        }

        return $tables;
    }

    public function primaryKeyColumns(string $table): array
    {
        $rows = DB::select(
            "SELECT COLUMN_NAME AS c FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'
             ORDER BY ORDINAL_POSITION",
            [$table]
        );

        $keys = array_map(fn ($r) => $r->c, $rows);

        // No PK: fall back to total order over all columns (keeps chunk() valid/stable)
        return $keys ?: Schema::getColumnListing($table);
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

    /**
     * Keep-1: delete ALL other backup records for this tenant (local files,
     * legacy Drive single files, chunked manifests → trash).
     * Called ONLY after the new backup succeeded + verified.
     *
     * Chunked backups: the old manifest row is deleted and its Drive file
     * moves to backup_chunk_trash (7-day grace, purged by BackupCleanupCommand).
     * Chunk DB rows / Drive files are NOT deleted here — chunks may be shared
     * with the LIVE manifest (dedup reuse); reference counting = Phase 2B GC.
     */
    private function cleanupPreviousBackups(int $tenantId, int $keepBackupId): void
    {
        $previous = Backup::where('tenant_id', $tenantId)
            ->where('id', '!=', $keepBackupId)
            ->get();

        if ($previous->isEmpty()) {
            return;
        }

        $hasDriveFiles = $previous->contains(
            fn ($b) => $b->destination === 'drive' && $b->drive_file_id
        );
        $conn = $hasDriveFiles
            ? TenantDriveConnection::where('tenant_id', $tenantId)->whereNull('revoked_at')->first()
            : null;

        $deleted = 0;
        $freedBytes = 0;

        foreach ($previous as $old) {
            // Local single-file copy (legacy path + failed-path leftovers)
            $path = storage_path("app/backups/{$old->filename}");
            if (file_exists($path)) {
                $freedBytes += (int) filesize($path);
                @unlink($path);
            }

            $oldManifest = BackupManifest::where('backup_id', $old->id)->first();

            if ($oldManifest) {
                // Phase 2B: row-level refcount-- BEFORE the manifest row dies.
                // Drive-file fate decided later by GC (0 live refs rule).
                $this->refService->deregisterReferences($oldManifest);

                // Chunked: manifest file → trash (grace period, GC purges later)
                if ($oldManifest->drive_file_id && $conn && $conn->trash_folder_id) {
                    DB::table('backup_chunk_trash')->insert([
                        'tenant_id'      => $tenantId,
                        'drive_file_id'  => $oldManifest->drive_file_id,
                        'content_sha256' => $oldManifest->manifest_sha256,
                        'size_bytes'     => strlen($oldManifest->manifest_json),
                        'trashed_at'     => now(),
                        'expires_at'     => now()->addDays((int) config('backup.trash_grace_days', 7)),
                    ]);
                } elseif ($oldManifest->drive_file_id && $conn) {
                    // No trash folder (pre-structure): delete immediately
                    $this->drive->deleteFile($conn, $oldManifest->drive_file_id);
                }
                $oldManifest->delete();
            } elseif ($old->destination === 'drive' && $old->drive_file_id) {
                // Legacy single-file Drive copy must go too (G5: no orphans)
                if ($conn) {
                    try {
                        $this->drive->deleteFile($conn, $old->drive_file_id);
                    } catch (\Throwable $e) {
                        Log::warning('Drive orphan cleanup failed', [
                            'backup_id' => $old->id,
                            'file_id'   => $old->drive_file_id,
                            'error'     => $e->getMessage(),
                        ]);
                    }
                } else {
                    Log::warning('Drive orphan cleanup skipped: no active connection', [
                        'backup_id' => $old->id,
                        'file_id'   => $old->drive_file_id,
                    ]);
                }
            }

            $old->delete();
            $deleted++;
        }

        Log::info('Previous backups deleted after successful new backup', [
            'tenant_id'     => $tenantId,
            'kept_id'       => $keepBackupId,
            'deleted_count' => $deleted,
            'freed_bytes'   => $freedBytes,
        ]);
    }
}
