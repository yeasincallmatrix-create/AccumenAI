<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Models\BackupChunk;
use App\Models\BackupChunkReference;
use App\Models\BackupManifest;
use App\Services\Backup\ChunkReferenceService;
use App\Services\Backup\OrphanCleanupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupCleanupCommand extends Command
{
    protected $signature = 'backup:cleanup
                            {--dry-run : Show what would be deleted}
                            {--tenant= : Clean specific tenant only}';
    protected $description = 'Retention + keep-count + orphan .enc + chunk GC (drive_file_id rule) + trash purge + refcount reconcile';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $retentionDays = (int) config('backup.retention_days', 365);
        $keepCount = (int) config('backup.keep_backups_per_tenant', 1);

        $this->info('Cleanup start' . ($dryRun ? ' [DRY-RUN]' : '')
            . " | retention = {$retentionDays} days | keep = {$keepCount}/tenant");
        $this->newLine();

        // 1. Age-based deletion
        $this->cleanupAgeBased($tenantId, $retentionDays, $dryRun);

        // 2. Keep-count enforcement
        $this->cleanupKeepCount($tenantId, $keepCount, $dryRun);

        // 3. Phase 2B: orphan .enc local files (>24h, no DB record)
        $orphanSvc = app(OrphanCleanupService::class);
        $local = $orphanSvc->cleanupLocalOrphans($dryRun);
        $this->info("Orphan .enc files: deleted={$local['deleted']} freed={$local['freed_mb']}MB");

        // 4. Phase 2B: chunk-temp (>1h)
        $temp = $orphanSvc->cleanupChunkTemp($dryRun);
        if ($temp['deleted'] > 0) {
            $this->info("Chunk temp: deleted={$temp['deleted']}");
        }

        // 5. 2A extras: orphan temp dirs (24h)
        $orphans = $this->cleanupDir(storage_path('app/backup-work'), now()->subHours(24)->timestamp, $dryRun);
        $orphans += $this->cleanupDir(storage_path('app/restore-work'), now()->subHours(24)->timestamp, $dryRun);
        $orphans += $this->cleanupDir(config('backup.rollback_dir', storage_path('app/rollback-snapshots')), now()->subHours(24)->timestamp, $dryRun);

        // 6. Phase 2B: chunk GC — drive_file_id rule (0 live refs → trash)
        foreach ($this->targetTenants($tenantId) as $tid) {
            $gc = $orphanSvc->cleanupOrphanChunks((int) $tid, $dryRun);
            if (($gc['trashed'] ?? 0) > 0 || ($gc['rows_dropped'] ?? 0) > 0 || $dryRun) {
                $this->line("  Tenant {$tid}: orphan files trashed={$gc['trashed']} dead rows dropped={$gc['rows_dropped']}");
            }
        }

        // 7. Phase 2B: purge expired trash (7-day grace; failure = retry next run)
        $trash = $orphanSvc->purgeExpiredTrash($dryRun, $tenantId);
        $this->info("Trash purged: {$trash['purged']} (failed/retry: {$trash['failed']}, no-conn: {$trash['skipped']})");

        // 8. System SQL dumps (age-based; NEVER *.enc)
        $sysDeleted = $this->cleanupSystemDumps($dryRun);

        // 9. Phase 2B: refcount reconciliation (row-level parity; skip in dry-run)
        if (!$dryRun) {
            $refService = app(ChunkReferenceService::class);
            foreach ($this->targetTenants($tenantId) as $tid) {
                $drift = $refService->verifyAndReconcile((int) $tid);
                if (!empty($drift)) {
                    $this->warn("Tenant {$tid}: refcount drift fixed (" . count($drift) . ' entries)');
                }
            }
        }

        // 10. 2A extra: orphan temp dirs report + free-space warning
        $this->info("Temp orphans: {$orphans} | System dumps: {$sysDeleted}");

        $freeMb = round(disk_free_space(storage_path()) / 1048576, 1);
        $warnMb = (int) config('backup.min_free_space_mb', 1024);
        $this->info("Disk free: {$freeMb} MB (warn < {$warnMb} MB)");
        if ($freeMb < $warnMb) {
            $this->warn('! Disk space is LOW.');
        }

        $this->newLine();
        $this->info('Cleanup complete' . ($dryRun ? ' [DRY-RUN]' : ''));

        return self::SUCCESS;
    }

    /**
     * Tenants with something to GC: backup rows OR chunk rows.
     */
    private function targetTenants(?int $tenantId): array
    {
        if ($tenantId) {
            return [$tenantId];
        }

        $fromBackups = Backup::select('tenant_id')->distinct()->pluck('tenant_id');
        $fromChunks = BackupChunk::select('tenant_id')->distinct()->pluck('tenant_id');

        return $fromBackups->union($fromChunks)->values()->all();
    }

    private function cleanupAgeBased(?int $tenantId, int $retentionDays, bool $dryRun): void
    {
        $cutoff = now()->subDays($retentionDays);
        $query = Backup::where('created_at', '<', $cutoff);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $old = $query->get();

        $this->info("Age-based: {$old->count()} backups older than {$retentionDays} days");

        foreach ($old as $b) {
            if ($dryRun) {
                $this->line("  [DRY] Would delete: {$b->filename}");
            } else {
                $this->deleteBackup($b);
            }
        }
    }

    private function cleanupKeepCount(?int $tenantId, int $keepCount, bool $dryRun): void
    {
        foreach ($this->targetTenants($tenantId) as $tid) {
            // 2A parity: ALL statuses count toward the slot (failed rows pile
            // up otherwise) — but new backups only created for completed.
            $backups = Backup::where('tenant_id', $tid)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            if ($backups->count() <= $keepCount) {
                continue;
            }

            $toDelete = $backups->slice($keepCount);
            foreach ($toDelete as $b) {
                if ($dryRun) {
                    $this->line("  [DRY] Tenant {$tid}: would delete {$b->filename}");
                } else {
                    $this->deleteBackup($b);
                }
            }
            $this->line("  Tenant {$tid}: deleted {$toDelete->count()} (keep-count exceeded)");
        }
    }

    private function cleanupSystemDumps(bool $dryRun): int
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            return 0;
        }

        $days = (int) config('backup.system_dump_retention_days', 30);
        $cutoff = now()->subDays($days)->timestamp;
        $count = 0;

        // Guard: *.sql ONLY — never *.enc (Phase 2A rule)
        foreach (glob($dir . '/*.sql') ?: [] as $file) {
            if (filemtime($file) >= $cutoff) {
                continue;
            }

            if ($dryRun) {
                $this->line('  [DRY] System dump: ' . basename($file));
            } else {
                @unlink($file);
            }
            $count++;
        }

        return $count;
    }

    /**
     * Delete one backup: deregister refs → trash manifest file (2A grace)
     * → drop manifest row + chunk refs → local file → backup row.
     * Chunk rows are removed by the GC step (drive_file_id rule) — a file
     * shared with a live manifest is NEVER deleted here.
     */
    private function deleteBackup(Backup $backup): void
    {
        if ($this->option('dry-run')) {
            $this->line("  [DRY] Would delete: {$backup->filename}");
            return;
        }

        $manifest = BackupManifest::where('backup_id', $backup->id)->first();
        if ($manifest) {
            // Phase 2B: refcount-- before the manifest row dies
            app(ChunkReferenceService::class)->deregisterReferences($manifest);

            // Phase 2A: manifest Drive file → trash (grace, reversible)
            $this->trashManifestFile($manifest);

            BackupChunkReference::where('manifest_id', $manifest->id)->delete();
            $manifest->delete();
        }

        $path = storage_path("app/backups/{$backup->filename}");
        if (file_exists($path)) {
            @unlink($path);
        }

        $backup->delete();
        $this->line("  Deleted: {$backup->filename}");
    }

    /**
     * Move a manifest's Drive file into backup_chunk_trash (deleted after
     * trash_grace_days). Falls back to skip when no active connection.
     */
    private function trashManifestFile(BackupManifest $manifest): void
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
        } else {
            // No trash folder (pre-structure): delete immediately (2A behavior)
            try {
                app(\App\Contracts\DriveStorageInterface::class)
                    ->deleteFile($conn, $manifest->drive_file_id);
            } catch (\Throwable $e) {
                $this->warn("  Manifest file delete failed ({$manifest->drive_file_id}): {$e->getMessage()}");
            }
        }
    }

    private function cleanupDir(string $dir, int $cutoff, bool $dryRun): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $count = 0;
        foreach (glob($dir . '/*') ?: [] as $item) {
            if (filemtime($item) >= $cutoff) {
                continue;
            }

            if ($dryRun) {
                $this->line('  [DRY] Orphan: ' . basename($item));
            } else {
                is_dir($item) ? $this->rmrf($item) : @unlink($item);
                $this->line('  Orphan removed: ' . basename($item));
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
