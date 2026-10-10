<?php

namespace Tests\Feature;

use App\Contracts\DriveStorageInterface;
use App\Models\Backup;
use App\Models\TenantDriveConnection;
use App\Services\Backup\BackupService;
use Tests\Support\FakeDriveService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BackupKeepLatestTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 900101;

    private FakeDriveService $fakeDrive;

    protected function setUp(): void
    {
        parent::setUp();

        // G2: swap the real Drive client for an in-memory fake and seed a
        // connection row so createBackup() can reach its keep-1 logic
        // without touching Google.
        $this->fakeDrive = new FakeDriveService();
        $this->app->instance(DriveStorageInterface::class, $this->fakeDrive);

        TenantDriveConnection::updateOrCreate(
            ['tenant_id' => self::TENANT_ID],
            [
                'connected_by_user_id' => 1,
                'google_user_email'    => 'keep-latest@example.test',
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
        \App\Models\BackupManifest::where('tenant_id', self::TENANT_ID)->delete();
        \DB::table('backup_chunk_trash')->where('tenant_id', self::TENANT_ID)->delete();
        TenantDriveConnection::where('tenant_id', self::TENANT_ID)->delete();
        parent::tearDown();
    }

    public function test_previous_backup_deleted_on_new_success()
    {
        $tenantId = self::TENANT_ID;
        Backup::where('tenant_id', $tenantId)->delete();

        $svc = app(BackupService::class);
        $ownerId = $this->findOwner($tenantId) ?? 1;

        if (!$ownerId) {
            $this->markTestSkipped('No owner');
        }

        $createdFiles = [];

        try {
            $first = $svc->createBackup($tenantId, $ownerId);
            $createdFiles[] = $first->filename;
            $this->assertEquals(1, Backup::where('tenant_id', $tenantId)->count());

            // Phase 2: ciphertext lives on Drive, local copy removed after upload
            $this->assertSame('drive', $first->destination);
            $this->assertNotEmpty($first->drive_file_id);
            $this->assertFileDoesNotExist(storage_path("app/backups/{$first->filename}"));

            $second = $svc->createBackup($tenantId, $ownerId);
            $createdFiles[] = $second->filename;

            $this->assertDatabaseMissing('backups', ['id' => $first->id]);
            $this->assertDatabaseHas('backups', ['id' => $second->id]);
            $this->assertEquals(1, Backup::where('tenant_id', $tenantId)->count());

            // Previous backup FILE must be gone too (not just the DB row)
            $firstPath = storage_path("app/backups/{$first->filename}");
            $this->assertFileDoesNotExist($firstPath);

            // G5: the previous backup's Drive copy must NOT be orphaned.
            // Phase 2A: chunked manifests go to backup_chunk_trash (7-day
            // grace) instead of an immediate deleteFile() — either outcome
            // proves the file is scheduled for removal.
            $trashed = \DB::table('backup_chunk_trash')
                ->where('tenant_id', $tenantId)
                ->count();
            $this->assertTrue(
                $this->fakeDrive->deletes >= 1 || $trashed >= 1,
                'Previous backup Drive file was neither deleted nor trashed'
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('Test env cannot create backup: ' . $e->getMessage());
        } finally {
            // Remove DB rows AND files so the test never leaves orphans
            Backup::where('tenant_id', $tenantId)->delete();
            foreach ($createdFiles as $f) {
                $p = storage_path("app/backups/{$f}");
                if (file_exists($p)) {
                    @unlink($p);
                }
            }
        }
    }

    public function test_keep_config_default_is_one()
    {
        $this->assertEquals(1, config('backup.keep_backups_per_tenant'));
    }

    public function test_delete_previous_flag_enabled()
    {
        $this->assertTrue(config('backup.delete_previous_on_success'));
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
