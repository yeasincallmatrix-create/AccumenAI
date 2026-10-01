<?php

namespace App\Services\Backup;

use App\Contracts\DriveStorageInterface;
use App\Models\Backup;
use App\Models\BackupChunk;
use App\Models\BackupChunkReference;
use App\Models\BackupChunkTrash;
use App\Models\TenantDriveConnection;
use Illuminate\Support\Facades\Log;

class OrphanCleanupService
{
    public function __construct(private DriveStorageInterface $drive) {}

    /**
     * Find + delete local orphan .enc files.
     * Orphan = .enc in storage/app/backups/ with no DB record AND older than 24h
     * (grace protects in-progress / just-failed backups).
     */
    public function cleanupLocalOrphans(bool $dryRun = false): array
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            return ['deleted' => 0, 'freed_bytes' => 0, 'freed_mb' => 0, 'dry_run' => $dryRun];
        }

        $cutoff = now()->subHours(24)->timestamp;
        $deleted = 0;
        $freedBytes = 0;

        foreach (glob($dir . '/*.enc') ?: [] as $file) {
            $filename = basename($file);

            // Skip recent files (may be in-progress or a fresh failure)
            if (filemtime($file) > $cutoff) {
                continue;
            }

            // Tracked by a backup record? (filename is globally unique)
            $inDb = Backup::where('filename', $filename)->exists();
            if ($inDb) {
                continue;
            }

            if ($dryRun) {
                Log::info('[DRY] Would delete orphan local file', ['file' => $filename]);
                continue;
            }

            $size = (int) filesize($file);
            @unlink($file);
            $freedBytes += $size;
            $deleted++;

            Log::info('Orphan local .enc deleted', ['file' => $filename, 'size' => $size]);
        }

        return [
            'deleted'     => $deleted,
            'freed_bytes' => $freedBytes,
            'freed_mb'    => round($freedBytes / 1048576, 2),
            'dry_run'     => $dryRun,
        ];
    }

    /**
     * Clean chunk-temp directory (older than 1h — backup runs are minutes).
     */
    public function cleanupChunkTemp(bool $dryRun = false): array
    {
        $dir = config('backup.chunk_temp_dir', storage_path('app/chunk-temp'));
        if (!is_dir($dir)) {
            return ['deleted' => 0, 'freed_bytes' => 0, 'dry_run' => $dryRun];
        }

        $cutoff = now()->subHours(1)->timestamp;
        $deleted = 0;
        $freedBytes = 0;

        foreach (glob($dir . '/*') ?: [] as $item) {
            if (filemtime($item) > $cutoff) {
                continue;
            }

            if ($dryRun) {
                Log::info('[DRY] Would delete temp', ['item' => basename($item)]);
                continue;
            }

            if (is_dir($item)) {
                $this->rmrf($item);
            } else {
                $freedBytes += (int) filesize($item);
                @unlink($item);
            }
            $deleted++;
        }

        return ['deleted' => $deleted, 'freed_bytes' => $freedBytes, 'dry_run' => $dryRun];
    }

    /**
     * ⚠️ GC keyed on drive_file_id (Phase 2B approved rule):
     *   file is orphaned iff 0 LIVE manifests reference it.
     *
     * For each orphan Drive file:
     *   - row(s) → backup_chunk_trash (7-day grace, purgeExpiredTrash finalizes)
     *   - ALL chunk rows with that drive_file_id are removed (they are all
     *     dead-manifest rows by construction of the orphan rule)
     * Dead-manifest rows whose file IS still live are dropped silently
     * (file kept — a live manifest needs it).
     */
    public function cleanupOrphanChunks(int $tenantId, bool $dryRun = false): array
    {
        $refService = app(ChunkReferenceService::class);

        // ORDER MATTERS: resolve orphan files + read representative rows
        // BEFORE dropping dead-manifest rows (dead rows may be the only
        // remaining source of hash/size for a fully-dead file).
        $orphanFiles = $refService->getOrphanDriveFileIds($tenantId);

        if (empty($orphanFiles)) {
            $droppedDeadRows = $refService->dropDeadManifestRows($tenantId);
            return ['trashed' => 0, 'rows_dropped' => $droppedDeadRows, 'dry_run' => $dryRun];
        }

        $graceDays = (int) config('backup.trash_grace_days', 7);
        $trashed = 0;
        $rowsDeleted = 0;
        $keptLive = 0;

        foreach ($orphanFiles as $fileId) {
            // Representative row (rows sharing a file share plaintext content,
            // so the hash is identical across them)
            $representative = BackupChunk::where('tenant_id', $tenantId)
                ->where('drive_file_id', $fileId)
                ->first();

            if (!$representative) {
                continue; // already handled
            }

            // Safety re-check: never trash a file a live manifest still needs
            $liveRefs = BackupChunk::where('tenant_id', $tenantId)
                ->where('drive_file_id', $fileId)
                ->whereIn('manifest_id', function ($q) {
                    $q->select('id')->from('backup_manifests');
                })
                ->count();

            if ($liveRefs > 0) {
                // File regained a live reference (concurrent backup) — keep it
                $keptLive++;
                Log::warning('GC safety abort: file has live reference', [
                    'drive_file_id' => $fileId,
                    'live_refs'     => $liveRefs,
                ]);
                continue;
            }

            if ($dryRun) {
                Log::info('[DRY] Would trash orphan Drive file', [
                    'drive_file_id' => $fileId,
                    'hash'          => substr($representative->content_sha256, 0, 16),
                ]);
                continue;
            }

            // Idempotency: a file already awaiting purge is done
            $alreadyTrashed = BackupChunkTrash::where('tenant_id', $tenantId)
                ->where('drive_file_id', $fileId)
                ->exists();

            if (!$alreadyTrashed) {
                BackupChunkTrash::create([
                    'tenant_id'      => $tenantId,
                    'drive_file_id'  => $fileId,
                    'content_sha256' => $representative->content_sha256,
                    'size_bytes'     => $representative->size_bytes,
                    'trashed_at'     => now(),
                    'expires_at'     => now()->addDays($graceDays),
                ]);
            }
            $trashed++;

            // Remove ALL rows for this file + their reference rows
            $chunkIds = BackupChunk::where('tenant_id', $tenantId)
                ->where('drive_file_id', $fileId)
                ->pluck('id');
            BackupChunkReference::whereIn('chunk_id', $chunkIds)->delete();
            $rowsDeleted += BackupChunk::whereIn('id', $chunkIds)->delete();

            Log::info('Orphan Drive file moved to trash', [
                'tenant_id'     => $tenantId,
                'drive_file_id' => $fileId,
                'expires_at'    => now()->addDays($graceDays)->toDateString(),
            ]);
        }

        // Remaining dead-manifest rows whose file IS live: drop silently
        $droppedDeadRows = $refService->dropDeadManifestRows($tenantId);

        return [
            'trashed'      => $trashed,
            'rows_dropped' => $droppedDeadRows,
            'rows_deleted' => $rowsDeleted,
            'kept_live'    => $keptLive,
            'dry_run'      => $dryRun,
        ];
    }

    /**
     * Purge expired trash (permanent delete from Drive + DB).
     * Adaptation 3: Drive delete FAILURE keeps the row for retry next run —
     * never a silent permanent orphan. Logs drive_file_id + tenant_id.
     */
    public function purgeExpiredTrash(bool $dryRun = false, ?int $tenantId = null): array
    {
        $expired = BackupChunkTrash::where('expires_at', '<', now())
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        $purged = 0;
        $failed = 0;
        $skipped = 0;
        $freedBytes = 0;

        foreach ($expired as $item) {
            if ($dryRun) {
                Log::info('[DRY] Would purge trash', [
                    'drive_file_id' => $item->drive_file_id,
                    'tenant_id'     => $item->tenant_id,
                ]);
                continue;
            }

            $conn = TenantDriveConnection::where('tenant_id', $item->tenant_id)
                ->whereNull('revoked_at')
                ->first();

            if ($conn) {
                try {
                    $this->drive->deleteFile($conn, $item->drive_file_id);
                    $purged++;
                    $freedBytes += (int) $item->size_bytes;
                } catch (\Throwable $e) {
                    $failed++;
                    // Adaptation 3: keep row → retried on next cleanup run
                    Log::warning('Trash purge failed — row kept for retry', [
                        'drive_file_id' => $item->drive_file_id,
                        'tenant_id'     => $item->tenant_id,
                        'error'         => $e->getMessage(),
                    ]);
                    continue;
                }
            } else {
                // No active connection: cannot reach the file; drop the row
                // (same as Phase 2A behavior — revoked tenants don't leak rows)
                $skipped++;
                Log::info('Trash row dropped (no active Drive connection)', [
                    'drive_file_id' => $item->drive_file_id,
                    'tenant_id'     => $item->tenant_id,
                ]);
            }

            $item->delete();
        }

        return [
            'purged'      => $purged,
            'failed'      => $failed,
            'skipped'     => $skipped,
            'freed_bytes' => $freedBytes,
            'dry_run'     => $dryRun,
        ];
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
