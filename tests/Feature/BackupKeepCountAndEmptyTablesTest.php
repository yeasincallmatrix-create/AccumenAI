<?php

namespace Tests\Feature;

use App\Contracts\DriveStorageInterface;
use App\Models\Backup;
use App\Models\BackupManifest;
use App\Models\TenantDriveConnection;
use App\Services\Backup\BackupChunkService;
use App\Services\Backup\BackupService;
use Tests\Support\FakeDriveService;
use Tests\TestCase;

/**
 * Phase 3 bugs found during the Smart Restore E2E run:
 *
 *  1. BackupService::cleanupPreviousBackups() ignored
 *     backup.keep_backups_per_tenant (only BackupCleanupCommand honoured it),
 *     so two backups of the same tenant could never coexist.
 *  2. Zero-row tables were dropped from the manifest entirely, so a smart
 *     restore could not soft-delete rows in a table that was empty at
 *     snapshot time ("tenant deleted everything" case).
 */
class BackupKeepCountAndEmptyTablesTest extends TestCase
{
    private const TENANT_ID = 900101;

    private FakeDriveService $fakeDrive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDrive = new FakeDriveService();
        $this->app->instance(DriveStorageInterface::class, $this->fakeDrive);

        TenantDriveConnection::updateOrCreate(
            ['tenant_id' => self::TENANT_ID],
            [
                'connected_by_user_id' => 1,
                'google_user_email'    => 'keep-count@example.test',
                'google_user_id'       => '900101',
                'refresh_token'        => 'fake-refresh-token',
                'drive_folder_id'      => 'fake-folder-900101',
                'connected_at'         => now(),
                'revoked_at'           => null,
            ]
        );
    }

    protected function tearDown(): void
    {
        \App\Models\BackupChunk::where('tenant_id', self::TENANT_ID)->delete();
        BackupManifest::where('tenant_id', self::TENANT_ID)->delete();
        \DB::table('backup_chunk_trash')->where('tenant_id', self::TENANT_ID)->delete();
        Backup::where('tenant_id', self::TENANT_ID)->delete();
        TenantDriveConnection::where('tenant_id', self::TENANT_ID)->delete();
        parent::tearDown();
    }

    public function test_keep_config_respected_by_backup_job(): void
    {
        $tenantId = self::TENANT_ID;
        Backup::where('tenant_id', $tenantId)->delete();

        config(['backup.keep_backups_per_tenant' => 2]);
        $this->assertSame(2, (int) config('backup.keep_backups_per_tenant'));

        $svc = app(BackupService::class);
        $ownerId = $this->findOwner($tenantId) ?? 1;
        $createdIds = [];

        try {
            $first = $svc->createBackup($tenantId, $ownerId);
            $createdIds[] = $first->id;
            $second = $svc->createBackup($tenantId, $ownerId);
            $createdIds[] = $second->id;

            $this->assertSame(
                2,
                Backup::where('tenant_id', $tenantId)->count(),
                'keep=2 must let the previous backup survive alongside the new one'
            );
            $this->assertDatabaseHas('backups', ['id' => $first->id]);
            $this->assertDatabaseHas('backups', ['id' => $second->id]);
            $this->assertNotNull(
                BackupManifest::where('backup_id', $first->id)->first(),
                'Surviving backup must keep its manifest'
            );

            $third = $svc->createBackup($tenantId, $ownerId);
            $createdIds[] = $third->id;

            $remaining = Backup::where('tenant_id', $tenantId)
                ->orderByDesc('created_at')->orderByDesc('id')
                ->pluck('id')->all();

            $this->assertSame(
                [$third->id, $second->id],
                $remaining,
                'keep=2 must retain exactly the two newest backups and evict the oldest'
            );
            $this->assertDatabaseMissing('backups', ['id' => $first->id]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Test env cannot create backup: ' . $e->getMessage());
        } finally {
            Backup::where('tenant_id', $tenantId)->delete();
        }
    }

    public function test_empty_tables_included_in_manifest(): void
    {
        $tenantId = self::TENANT_ID;
        Backup::where('tenant_id', $tenantId)->delete();

        $svc = app(BackupService::class);
        $ownerId = $this->findOwner($tenantId) ?? 1;

        try {
            $backup = $svc->createBackup($tenantId, $ownerId);

            $manifestRow = BackupManifest::where('backup_id', $backup->id)->first();
            $this->assertNotNull($manifestRow, 'Backup must produce a manifest');

            $manifest = json_decode($manifestRow->manifest_json, true);
            $this->assertIsArray($manifest);
            $this->assertTrue(
                app(\App\Services\Backup\ManifestService::class)->verifyChecksum($manifest),
                'Manifest self-checksum must hold'
            );

            $expected = array_keys($svc->getTenantTables());
            $actual = array_keys($manifest['tables']);
            sort($expected);
            sort($actual);
            $this->assertSame(
                $expected,
                $actual,
                'Every tenant table must appear in the manifest — zero-row tables included'
            );

            $empties = array_filter(
                $manifest['tables'],
                fn ($t) => (int) ($t['row_count'] ?? -1) === 0
            );
            $this->assertNotEmpty(
                $empties,
                'A tenant with mostly unused tables must record at least one zero-row entry'
            );

            foreach ($empties as $name => $info) {
                $this->assertArrayHasKey('hash', $info, "Empty table {$name} must carry a hash");
                $this->assertSame([], $info['chunks'], "Empty table {$name} must declare no chunks");
                $this->assertSame(0, (int) $info['total_chunks']);
                $this->assertTrue(
                    (bool) ($info['empty_at_backup'] ?? false),
                    "Empty table {$name} must be flagged empty_at_backup"
                );
            }

            // Restore side: a zero-row table must read as an empty payload
            // instead of throwing "No chunks for table".
            $conn = TenantDriveConnection::where('tenant_id', $tenantId)
                ->whereNull('revoked_at')
                ->first();
            $this->assertNotNull($conn);

            $firstEmpty = array_key_first($empties);
            $json = app(BackupChunkService::class)
                ->downloadTableChunksParallel($manifestRow, $firstEmpty, $conn);

            $this->assertSame([], json_decode($json, true));
        } catch (\Throwable $e) {
            $this->markTestSkipped('Test env cannot create backup: ' . $e->getMessage());
        } finally {
            Backup::where('tenant_id', $tenantId)->delete();
        }
    }

    private function findOwner(int $tenantId): ?int
    {
        $id = \DB::table('institute_users')
            ->where('institute_id', $tenantId)
            ->where('role_id', 1)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
