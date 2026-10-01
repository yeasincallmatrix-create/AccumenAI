<?php

namespace App\Services\Backup;

use App\Models\BackupChunk;
use App\Models\BackupChunkReference;
use App\Models\BackupManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChunkReferenceService
{
    /**
     * Register row-level references when a manifest is saved.
     * Records (manifest_id, chunk_id) + denormalized drive_file_id for GC,
     * and increments the informational reference_count on each chunk row.
     */
    public function registerReferences(
        int $tenantId,
        BackupManifest $manifest,
        array $chunkIds
    ): int {
        $registered = 0;

        DB::transaction(function () use ($tenantId, $manifest, $chunkIds, &$registered) {
            foreach ($chunkIds as $chunkId) {
                $chunk = BackupChunk::find($chunkId);
                if (!$chunk) {
                    continue;
                }

                $ref = BackupChunkReference::firstOrCreate(
                    [
                        'manifest_id' => $manifest->id,
                        'chunk_id'    => $chunkId,
                    ],
                    [
                        'tenant_id'      => $tenantId,
                        'content_sha256' => $chunk->content_sha256,
                        'drive_file_id'  => $chunk->drive_file_id,
                    ]
                );

                if ($ref->wasRecentlyCreated) {
                    $chunk->increment('reference_count');
                    $registered++;
                }
            }
        });

        Log::info('Chunk references registered', [
            'tenant_id'   => $tenantId,
            'manifest_id' => $manifest->id,
            'count'       => $registered,
        ]);

        return $registered;
    }

    /**
     * Deregister references when a manifest is deleted.
     * Decrements row-level refcounts (informational). The actual Drive-file
     * decision is made by getOrphanDriveFileIds() (live-manifest GC rule).
     */
    public function deregisterReferences(BackupManifest $manifest): int
    {
        $deregistered = 0;

        DB::transaction(function () use ($manifest, &$deregistered) {
            $refs = BackupChunkReference::where('manifest_id', $manifest->id)->get();

            foreach ($refs as $ref) {
                $chunk = BackupChunk::find($ref->chunk_id);
                if ($chunk) {
                    $chunk->reference_count = max(0, (int) $chunk->reference_count - 1);
                    $chunk->save();
                }

                $ref->delete();
                $deregistered++;
            }
        });

        Log::info('Chunk references deregistered', [
            'manifest_id' => $manifest->id,
            'count'       => $deregistered,
        ]);

        return $deregistered;
    }

    /**
     * Row-level orphan candidates (reference_count = 0) — informational only.
     * NOT the GC rule: a chunk row with refcount 0 can still back a file
     * shared with another live manifest's row.
     */
    public function getOrphanCandidates(int $tenantId): array
    {
        return BackupChunk::where('tenant_id', $tenantId)
            ->where('reference_count', 0)
            ->pluck('id')
            ->toArray();
    }

    /**
     * ⚠️ THE GC RULE (Phase 2B approved design).
     *
     * A Drive file is orphaned iff NO live manifest has any chunk row with
     * that drive_file_id. Keyed on drive_file_id — NOT on chunk rows —
     * because dedup reuses the same Drive file across per-manifest rows.
     *
     * @return string[] drive_file_ids safe to trash (0 live references)
     */
    public function getOrphanDriveFileIds(int $tenantId): array
    {
        $liveFiles = BackupChunk::where('tenant_id', $tenantId)
            ->whereIn('manifest_id', function ($q) {
                $q->select('id')->from('backup_manifests');
            })
            ->distinct()
            ->pluck('drive_file_id');

        $allFiles = BackupChunk::where('tenant_id', $tenantId)
            ->distinct()
            ->pluck('drive_file_id');

        return $allFiles->diff($liveFiles)->values()->all();
    }

    /**
     * Chunk rows whose manifest no longer exists (dangling after keep-1 /
     * deleteBackup). Safe to delete: they hold no live reference. Callers
     * must have already decided the file fate via getOrphanDriveFileIds().
     */
    public function dropDeadManifestRows(int $tenantId): int
    {
        $dropped = BackupChunk::where('tenant_id', $tenantId)
            ->whereNotIn('manifest_id', function ($q) {
                $q->select('id')->from('backup_manifests');
            })
            ->delete();

        if ($dropped > 0) {
            Log::info('Dead-manifest chunk rows dropped', [
                'tenant_id' => $tenantId,
                'rows'      => $dropped,
            ]);
        }

        return $dropped;
    }

    /**
     * Verify row-level refcount parity with the reference table
     * (informational sanity check — drive_file_id level is covered by GC).
     *
     * @return array<int, array{chunk_id:int, stored:int, actual:int}>
     */
    public function verifyAndReconcile(int $tenantId): array
    {
        $drift = [];

        $chunks = BackupChunk::where('tenant_id', $tenantId)->get();

        foreach ($chunks as $chunk) {
            $actualRefs = BackupChunkReference::where('chunk_id', $chunk->id)->count();

            if ($actualRefs !== (int) $chunk->reference_count) {
                $drift[] = [
                    'chunk_id' => $chunk->id,
                    'stored'   => (int) $chunk->reference_count,
                    'actual'   => $actualRefs,
                ];

                $chunk->update(['reference_count' => $actualRefs]);
            }
        }

        if (!empty($drift)) {
            Log::warning('Refcount drift detected and fixed', [
                'tenant_id' => $tenantId,
                'count'     => count($drift),
                'drift'     => $drift,
            ]);
        }

        return $drift;
    }
}
