<?php

namespace App\Console\Commands;

use App\Models\Backup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupCleanupCommand extends Command
{
    protected $signature = 'backup:cleanup
                            {--dry-run : Show what would be deleted}
                            {--tenant= : Clean specific tenant only}';
    protected $description = 'Delete backups older than retention + enforce keep-count per tenant + orphans';

    public function handle(): int
    {
        $retentionDays = (int) config('backup.retention_days', 365);
        $keepCount = (int) config('backup.keep_backups_per_tenant', 1);
        $cutoff = now()->subDays($retentionDays);

        $this->info("Cleanup: retention = {$retentionDays} days | keep={$keepCount}/tenant");

        // 1. Age-based deletion
        $ageQuery = Backup::where('created_at', '<', $cutoff);
        if ($this->option('tenant')) {
            $ageQuery->where('tenant_id', $this->option('tenant'));
        }
        $oldByAge = $ageQuery->get();
        $this->info("Old backups (> {$retentionDays} days): {$oldByAge->count()}");

        foreach ($oldByAge as $b) {
            $this->deleteBackup($b);
        }

        // 2. Keep-count enforcement (keep newest N per tenant)
        $tenantIds = DB::table('backups')
            ->select('tenant_id')
            ->distinct()
            ->pluck('tenant_id');

        if ($this->option('tenant')) {
            $tenantIds = collect([(int) $this->option('tenant')]);
        }

        $deletedByKeep = 0;
        foreach ($tenantIds as $tenantId) {
            $backups = Backup::where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            if ($backups->count() > $keepCount) {
                $toDelete = $backups->slice($keepCount);
                foreach ($toDelete as $b) {
                    $this->deleteBackup($b);
                    $deletedByKeep++;
                }
                $this->line("  Tenant {$tenantId}: deleted {$toDelete->count()} (keep-count exceeded)");
            }
        }
        $this->info("Deleted by keep-count rule: {$deletedByKeep}");

        // 3. Orphan temp files (24h)
        $orphans = $this->cleanupDir(storage_path('app/backup-work'), now()->subHours(24)->timestamp);
        $orphans += $this->cleanupDir(storage_path('app/restore-work'), now()->subHours(24)->timestamp);
        $orphans += $this->cleanupDir(config('backup.rollback_dir', storage_path('app/rollback-snapshots')), now()->subHours(24)->timestamp);
        $orphans += $this->cleanupDir(config('backup.chunk_temp_dir', storage_path('app/chunk-temp')), now()->subHours(24)->timestamp);

        // 4. System SQL dumps (> system_dump_retention_days, never *.enc)
        $sysDeleted = $this->cleanupSystemDumps();

        // 5. Phase 2A: purge expired chunk/manifest trash (7-day grace)
        $trashed = $this->purgeExpiredTrash();

        $this->info("Orphans: {$orphans} | System dumps: {$sysDeleted} | Trash purged: {$trashed}");

        $freeMb = round(disk_free_space(storage_path()) / 1048576, 1);
        $warnMb = (int) config('backup.min_free_space_mb', 1024);
        $refuseMb = (int) config('backup.refuse_free_space_mb', 512);
        $this->info("Disk free: {$freeMb} MB (warn < {$warnMb} MB, refuse < {$refuseMb} MB)");
        if ($freeMb < $warnMb) {
            $this->warn('! Disk space is LOW.');
        }

        return self::SUCCESS;
    }

    private function deleteBackup(Backup $backup): void
    {
        if ($this->option('dry-run')) {
            $this->line("  [DRY] Would delete: {$backup->filename}");
            return;
        }

        $path = storage_path("app/backups/{$backup->filename}");
        if (file_exists($path)) {
            @unlink($path);
        }

        // Phase 2A: remove manifest row; its Drive file goes to trash (grace).
        $manifest = \App\Models\BackupManifest::where('backup_id', $backup->id)->first();
        if ($manifest) {
            $this->trashManifestFile($manifest);
            \App\Models\BackupChunk::where('manifest_id', $manifest->id)->delete();
            $manifest->delete();
        }

        $backup->delete();
        $this->line("  Deleted: {$backup->filename}");
    }

    /**
     * Move a manifest's Drive file into backup_chunk_trash (deleted after
     * trash_grace_days). Falls back to immediate delete when no connection.
     */
    private function trashManifestFile(\App\Models\BackupManifest $manifest): void
    {
        if (!$manifest->drive_file_id) {
            return;
        }

        $conn = \App\Models\TenantDriveConnection::where('tenant_id', $manifest->tenant_id)
            ->whereNull('revoked_at')
            ->first();

        if (!$conn) {
            return;
        }

        if ($conn->trash_folder_id) {
            DB::table('backup_chunk_trash')->insertOrIgnore([
                'tenant_id'      => $manifest->tenant_id,
                'drive_file_id'  => $manifest->drive_file_id,
                'content_sha256' => $manifest->manifest_sha256,
                'size_bytes'     => strlen($manifest->manifest_json),
                'trashed_at'     => now(),
                'expires_at'     => now()->addDays((int) config('backup.trash_grace_days', 7)),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    /**
     * Phase 2A GC: delete Drive files whose trash grace period expired,
     * then drop the trash rows. dry-run only reports.
     */
    private function purgeExpiredTrash(): int
    {
        $expired = \App\Models\BackupChunkTrash::where('expires_at', '<', now())
            ->when($this->option('tenant'), fn ($q) => $q->where('tenant_id', $this->option('tenant')))
            ->get();

        $count = 0;
        foreach ($expired as $item) {
            if ($this->option('dry-run')) {
                $this->line("  [DRY] Would purge trash: {$item->drive_file_id}");
                $count++;
                continue;
            }

            $conn = \App\Models\TenantDriveConnection::where('tenant_id', $item->tenant_id)
                ->whereNull('revoked_at')
                ->first();

            if ($conn) {
                try {
                    app(\App\Contracts\DriveStorageInterface::class)
                        ->deleteFile($conn, $item->drive_file_id);
                } catch (\Throwable $e) {
                    $this->warn("  Trash delete failed ({$item->drive_file_id}): {$e->getMessage()}");
                    continue; // keep row — retry next run
                }
            }

            $item->delete();
            $count++;
        }

        return $count;
    }

    private function cleanupDir(string $dir, int $cutoff): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $count = 0;
        foreach (glob($dir . '/*') ?: [] as $item) {
            if (filemtime($item) >= $cutoff) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line('  [DRY] Orphan: ' . basename($item));
            } else {
                is_dir($item) ? $this->rmrf($item) : @unlink($item);
                $this->line('  Orphan removed: ' . basename($item));
            }
            $count++;
        }

        return $count;
    }

    private function cleanupSystemDumps(): int
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            return 0;
        }

        $days = (int) config('backup.system_dump_retention_days', 30);
        $cutoff = now()->subDays($days)->timestamp;
        $count = 0;

        foreach (glob($dir . '/*.sql') ?: [] as $file) {
            if (filemtime($file) >= $cutoff) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line('  [DRY] System dump: ' . basename($file));
            } else {
                @unlink($file);
            }
            $count++;
        }

        return $count;
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
        }
        @rmdir($dir);
    }
}
