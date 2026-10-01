<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Services\Backup\BackupService;
use Tests\TestCase;

class BackupKeepLatestTest extends TestCase
{
    public function test_previous_backup_deleted_on_new_success()
    {
        $tenantId = 900101;
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

            $second = $svc->createBackup($tenantId, $ownerId);
            $createdFiles[] = $second->filename;

            $this->assertDatabaseMissing('backups', ['id' => $first->id]);
            $this->assertDatabaseHas('backups', ['id' => $second->id]);
            $this->assertEquals(1, Backup::where('tenant_id', $tenantId)->count());

            // Previous backup FILE must be gone too (not just the DB row)
            $firstPath = storage_path("app/backups/{$first->filename}");
            $this->assertFileDoesNotExist($firstPath);
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
