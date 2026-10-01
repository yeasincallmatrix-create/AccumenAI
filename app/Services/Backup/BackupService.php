<?php

namespace App\Services\Backup;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackupService
{
    public function __construct(private EncryptionService $encryption) {}

    /**
     * Silent backup — no passphrase, no user prompt.
     */
    public function createBackup(int $tenantId, int $userId): Backup
    {
        // Guard: disk space — refuse below hard floor, warn below soft floor
        $freeMb = disk_free_space(storage_path()) / 1024 / 1024;
        $refuseMb = (int) config('backup.refuse_free_space_mb', 512);
        $warnMb = (int) config('backup.min_free_space_mb', 1024);
        if ($freeMb < $refuseMb) {
            throw new \RuntimeException("Insufficient disk space ({$freeMb}MB free, refuse below {$refuseMb}MB)");
        }
        if ($freeMb < $warnMb) {
            \Illuminate\Support\Facades\Log::warning('backup_low_disk_space', [
                'free_mb' => round($freeMb, 1),
                'warn_mb' => $warnMb,
            ]);
        }

        $backup = Backup::create([
            'tenant_id'     => $tenantId,
            'owner_user_id' => $userId,
            'filename'      => 'backup-' . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.enc',
            'status'        => 'pending',
        ]);

        try {
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
                'size_bytes'   => filesize($encPath),
                'file_hmac'    => $hmac,
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            // Integrity check BEFORE any previous backup is removed.
            if (!$this->encryption->verifyFileHmac($encPath, $hmac)) {
                @unlink($encPath);
                $backup->update([
                    'status'        => 'failed',
                    'error_message' => 'Post-write HMAC verification failed',
                ]);
                throw new \RuntimeException('Backup integrity verification failed');
            }

            // Delete previous backups ONLY after new backup verified.
            if (config('backup.delete_previous_on_success', true)) {
                $this->deletePreviousBackups($tenantId, $backup->id);
            }

            // Cleanup
            @unlink($tarPath);
            $this->rmrf($workDir);

        } catch (\Throwable $e) {
            $backup->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            @unlink(storage_path("app/backup-work/{$backup->id}.tar.gz"));
            $this->rmrf(storage_path("app/backup-work/{$backup->id}"));
            throw $e;
        }

        return $backup;
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
     * Delete all OTHER backups for this tenant (keep only the specified one).
     * Called ONLY after the new backup succeeded + HMAC verified.
     */
    private function deletePreviousBackups(int $tenantId, int $keepBackupId): void
    {
        $previous = Backup::where('tenant_id', $tenantId)
            ->where('id', '!=', $keepBackupId)
            ->get();

        if ($previous->isEmpty()) {
            return;
        }

        $deleted = 0;
        $freedBytes = 0;

        foreach ($previous as $old) {
            $path = storage_path("app/backups/{$old->filename}");
            if (file_exists($path)) {
                $freedBytes += (int) filesize($path);
                @unlink($path);
            }
            $old->delete();
            $deleted++;
        }

        \Illuminate\Support\Facades\Log::info('Previous backups deleted after successful new backup', [
            'tenant_id'     => $tenantId,
            'kept_id'       => $keepBackupId,
            'deleted_count' => $deleted,
            'freed_bytes'   => $freedBytes,
        ]);
    }
}
