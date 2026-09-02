<?php

namespace Tests\Feature;

use App\Models\SystemBackup;
use App\Services\System\BackupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackupServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creates_timestamped_backup_with_metadata(): void
    {
        $service = app(BackupService::class);
        $backup = $service->create('manual', 1, 'user');

        $this->assertDatabaseHas('system_backups', [
            'id' => $backup->id,
            'type' => 'manual',
            'status' => 'completed',
        ]);
        $this->assertNotEmpty($backup->filename);
        $this->assertStringContainsString('monetix_manual_', $backup->filename);
        $this->assertGreaterThan(0, $backup->size_bytes);
        $this->assertNotEmpty($backup->checksum);
        $this->assertFileExists(storage_path('app/' . $backup->path));
    }

    public function test_backup_metadata_includes_migration_version(): void
    {
        $service = app(BackupService::class);
        $backup = $service->create('manual');

        $this->assertGreaterThan(0, $backup->migration_count);
        $this->assertNotEmpty($backup->migration_version);
        $this->assertGreaterThan(0, $backup->table_count);
    }

    public function test_verify_backup(): void
    {
        $service = app(BackupService::class);
        $backup = $service->create('manual');
        $verified = $service->verify($backup);

        $this->assertTrue($verified);
        $this->assertEquals('verified', $backup->fresh()->status);
    }

    public function test_pre_restore_backup_type(): void
    {
        $service = app(BackupService::class);
        $backup = $service->createPreRestoreBackup();

        $this->assertEquals('pre_restore', $backup->type);
        $this->assertDatabaseHas('system_backups', [
            'id' => $backup->id,
            'type' => 'pre_restore',
        ]);
    }

    public function test_backup_creates_audit_log(): void
    {
        $service = app(BackupService::class);
        $service->create('manual', 1, 'user');

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'backup',
            'action' => 'backup_created',
        ]);
    }

    public function test_list_backups(): void
    {
        $service = app(BackupService::class);
        $service->create('manual');
        $service->create('manual');

        $list = $service->listBackups(10);
        $this->assertGreaterThanOrEqual(2, $list->count());
    }
}
