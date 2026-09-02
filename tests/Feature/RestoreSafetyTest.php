<?php

namespace Tests\Feature;

use App\Services\System\BackupService;
use App\Services\System\RestoreSafetyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RestoreSafetyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_prepare_for_restore_creates_pre_restore_backup(): void
    {
        $service = app(RestoreSafetyService::class);
        $result = $service->prepareForRestore(null);

        $this->assertArrayHasKey('pre_restore_backup', $result);
        $this->assertEquals('pre_restore', $result['pre_restore_backup']->type);
        $this->assertDatabaseHas('system_backups', [
            'id' => $result['pre_restore_backup']->id,
            'type' => 'pre_restore',
        ]);
    }

    public function test_schema_compatibility_check(): void
    {
        $service = app(RestoreSafetyService::class);
        $check = $service->verifySchemaCompatibility(null);

        $this->assertTrue($check['compatible']);
        $this->assertArrayHasKey('details', $check);
    }

    public function test_migration_version_check(): void
    {
        $service = app(RestoreSafetyService::class);
        $check = $service->verifyMigrationVersion(null);

        $this->assertTrue($check['compatible']);
        $this->assertArrayHasKey('current_count', $check);
        $this->assertGreaterThan(0, $check['current_count']);
    }

    public function test_restore_dry_run_verifies_backup(): void
    {
        $backupService = app(BackupService::class);
        $restoreService = app(RestoreSafetyService::class);

        $backup = $backupService->create('manual');
        $result = $restoreService->restoreFromBackup($backup, dryRun: true);

        $this->assertTrue($result['verified']);
        $this->assertTrue($result['dry_run']);
        $this->assertStringContainsString('Dry run', $result['message']);
    }

    public function test_prepare_returns_can_restore_flag(): void
    {
        $service = app(RestoreSafetyService::class);
        $result = $service->prepareForRestore(null);

        $this->assertArrayHasKey('can_restore', $result);
        $this->assertIsBool($result['can_restore']);
    }
}
