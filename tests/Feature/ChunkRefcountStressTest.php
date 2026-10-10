<?php

namespace Tests\Feature;

use App\Models\BackupChunk;
use App\Models\BackupChunkReference;
use App\Models\BackupChunkTrash;
use App\Models\BackupManifest;
use App\Services\Backup\ChunkReferenceService;
use App\Services\Backup\OrphanCleanupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ChunkRefcountStressTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANTS = [999801, 999802, 999803, 999804, 999805];

    protected function tearDown(): void
    {
        BackupChunkReference::whereIn('tenant_id', self::TENANTS)->delete();
        BackupChunk::whereIn('tenant_id', self::TENANTS)->delete();
        BackupManifest::whereIn('tenant_id', self::TENANTS)->delete();
        BackupChunkTrash::whereIn('tenant_id', self::TENANTS)->delete();
        parent::tearDown();
    }

    public function test_reference_registration()
    {
        $tenantId = 999801;
        $manifest = BackupManifest::create([
            'backup_id'       => 999999,
            'tenant_id'       => $tenantId,
            'manifest_json'   => '{}',
            'manifest_sha256' => str_repeat('a', 64),
        ]);

        $chunks = [];
        for ($i = 0; $i < 5; $i++) {
            $chunks[] = BackupChunk::create([
                'manifest_id'     => $manifest->id,
                'tenant_id'       => $tenantId,
                'content_sha256'  => hash('sha256', "chunk-{$i}-" . uniqid()),
                'drive_file_id'   => "drive-{$i}",
                'size_bytes'      => 1024,
                'reference_count' => 0,
            ]);
        }

        $svc = app(ChunkReferenceService::class);
        $count = $svc->registerReferences($tenantId, $manifest, array_column($chunks, 'id'));

        $this->assertEquals(5, $count);

        foreach ($chunks as $c) {
            $this->assertEquals(1, $c->fresh()->reference_count);
        }

        // Reference rows carry the denormalized drive_file_id for GC queries
        $this->assertEquals(
            5,
            BackupChunkReference::where('manifest_id', $manifest->id)
                ->whereNotNull('drive_file_id')
                ->count()
        );
    }

    public function test_shared_chunk_across_manifests()
    {
        $tenantId = 999802;
        $sharedHash = hash('sha256', 'shared-content-' . uniqid());

        // Manifest 1 + chunk
        $m1 = BackupManifest::create([
            'backup_id'       => 999991,
            'tenant_id'       => $tenantId,
            'manifest_json'   => '{}',
            'manifest_sha256' => str_repeat('b', 64),
        ]);
        $c1 = BackupChunk::create([
            'manifest_id'     => $m1->id,
            'tenant_id'       => $tenantId,
            'content_sha256'  => $sharedHash,
            'drive_file_id'   => 'd1',
            'size_bytes'      => 1024,
            'reference_count' => 0,
        ]);

        // Manifest 2 + same content row (per-manifest row, same Drive file)
        $m2 = BackupManifest::create([
            'backup_id'       => 999992,
            'tenant_id'       => $tenantId,
            'manifest_json'   => '{}',
            'manifest_sha256' => str_repeat('c', 64),
        ]);

        $svc = app(ChunkReferenceService::class);
        $svc->registerReferences($tenantId, $m1, [$c1->id]);
        $svc->registerReferences($tenantId, $m2, [$c1->id]); // row-level: 2 refs

        $this->assertEquals(2, $c1->fresh()->reference_count);

        // Delete manifest 1 — row refcount becomes 1
        $svc->deregisterReferences($m1);
        $this->assertEquals(1, $c1->fresh()->reference_count);
        $this->assertDatabaseHas('backup_chunks', ['id' => $c1->id]);

        // Delete manifest 2 — refcount 0, chunk becomes orphan candidate
        $svc->deregisterReferences($m2);
        $this->assertEquals(0, $c1->fresh()->reference_count);
    }

    public function test_refcount_drift_reconciliation()
    {
        $tenantId = 999803;
        $manifest = BackupManifest::create([
            'backup_id'       => 999993,
            'tenant_id'       => $tenantId,
            'manifest_json'   => '{}',
            'manifest_sha256' => str_repeat('d', 64),
        ]);

        // Chunk with WRONG refcount (5, should be 0 — no reference rows)
        $chunk = BackupChunk::create([
            'manifest_id'     => $manifest->id,
            'tenant_id'       => $tenantId,
            'content_sha256'  => hash('sha256', 'drift-' . uniqid()),
            'drive_file_id'   => 'd-drift',
            'size_bytes'      => 512,
            'reference_count' => 5,
        ]);

        $svc = app(ChunkReferenceService::class);
        $drift = $svc->verifyAndReconcile($tenantId);

        $this->assertGreaterThanOrEqual(1, count($drift));
        $this->assertEquals(0, $chunk->fresh()->reference_count);
    }

    public function test_orphan_detection()
    {
        $tenantId = 999804;

        $manifest = BackupManifest::create([
            'backup_id'       => 999994,
            'tenant_id'       => $tenantId,
            'manifest_json'   => '{}',
            'manifest_sha256' => str_repeat('e', 64),
        ]);

        // Orphan candidate (refcount=0, no reference rows)
        $orphan = BackupChunk::create([
            'manifest_id'     => $manifest->id,
            'tenant_id'       => $tenantId,
            'content_sha256'  => hash('sha256', 'orphan-' . uniqid()),
            'drive_file_id'   => 'orphan-file',
            'size_bytes'      => 100,
            'reference_count' => 0,
        ]);

        // Non-orphan (refcount=1)
        $active = BackupChunk::create([
            'manifest_id'     => $manifest->id,
            'tenant_id'       => $tenantId,
            'content_sha256'  => hash('sha256', 'active-' . uniqid()),
            'drive_file_id'   => 'active-file',
            'size_bytes'      => 100,
            'reference_count' => 1,
        ]);

        $svc = app(ChunkReferenceService::class);
        $orphanIds = $svc->getOrphanCandidates($tenantId);

        $this->assertContains($orphan->id, $orphanIds);
        $this->assertNotContains($active->id, $orphanIds);
    }

    /**
     * ⚠️ CRITICAL (Phase 2B approved GC rule):
     * Two manifests sharing ONE Drive file via dedup. The file must survive
     * until the LAST live manifest that references it is gone.
     */
    public function test_two_manifests_share_drive_file_id()
    {
        $tenantId = 999805;
        $fileX = 'shared-drive-file-x';
        $hashX = hash('sha256', 'shared-x-content-' . uniqid());

        $mA = BackupManifest::create([
            'backup_id'       => 999995,
            'tenant_id'       => $tenantId,
            'manifest_json'   => '{}',
            'manifest_sha256' => str_repeat('f', 64),
        ]);
        $mB = BackupManifest::create([
            'backup_id'       => 999996,
            'tenant_id'       => $tenantId,
            'manifest_json'   => '{}',
            'manifest_sha256' => str_repeat('g', 64),
        ]);

        // Row A and row B point to the SAME Drive file (dedup reuse)
        $rowA = BackupChunk::create([
            'manifest_id'     => $mA->id,
            'tenant_id'       => $tenantId,
            'content_sha256'  => $hashX,
            'drive_file_id'   => $fileX,
            'size_bytes'      => 2048,
            'reference_count' => 0,
        ]);
        $rowB = BackupChunk::create([
            'manifest_id'     => $mB->id,
            'tenant_id'       => $tenantId,
            'content_sha256'  => $hashX,
            'drive_file_id'   => $fileX,
            'size_bytes'      => 2048,
            'reference_count' => 0,
        ]);

        $refSvc = app(ChunkReferenceService::class);
        $refSvc->registerReferences($tenantId, $mA, [$rowA->id]);
        $refSvc->registerReferences($tenantId, $mB, [$rowB->id]);

        // Both live → file X is NOT an orphan
        $this->assertNotContains($fileX, $refSvc->getOrphanDriveFileIds($tenantId));

        // ── Phase 1: delete manifest A (keep-1 style) ────────────────
        $refSvc->deregisterReferences($mA);
        $mA->delete();

        $orphanSvc = app(OrphanCleanupService::class);
        $gc1 = $orphanSvc->cleanupOrphanChunks($tenantId);

        // Dead row A dropped, row B alive, X NOT trashed (B still needs it)
        $this->assertDatabaseMissing('backup_chunks', ['id' => $rowA->id]);
        $this->assertDatabaseHas('backup_chunks', ['id' => $rowB->id]);
        $this->assertDatabaseMissing('backup_chunk_trash', ['drive_file_id' => $fileX]);
        $this->assertEquals(0, $gc1['trashed']);

        // ── Phase 2: delete manifest B (0 live refs) ─────────────────
        $refSvc->deregisterReferences($mB);
        $mB->delete();

        $this->assertContains($fileX, $refSvc->getOrphanDriveFileIds($tenantId));

        $gc2 = $orphanSvc->cleanupOrphanChunks($tenantId);

        // X trashed (7-day grace), all its rows gone
        $this->assertEquals(1, $gc2['trashed']);
        $this->assertDatabaseHas('backup_chunk_trash', [
            'drive_file_id' => $fileX,
            'tenant_id'     => $tenantId,
        ]);
        $this->assertDatabaseMissing('backup_chunks', ['drive_file_id' => $fileX]);

        // Idempotency: second run finds nothing new
        $gc3 = $orphanSvc->cleanupOrphanChunks($tenantId);
        $this->assertEquals(0, $gc3['trashed']);
        $this->assertEquals(1, BackupChunkTrash::where('drive_file_id', $fileX)->count());
    }
}
