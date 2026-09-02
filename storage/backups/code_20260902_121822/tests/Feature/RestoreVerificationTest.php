<?php

namespace Tests\Feature;

use App\Models\SystemBackup;
use App\Services\System\BackupService;
use App\Services\System\RestoreVerificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RestoreVerificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_verify_backup_file(): void
    {
        $backupService = app(BackupService::class);
        $backup = $backupService->create('manual');
        $file = storage_path('app/' . $backup->path);

        $service = app(RestoreVerificationService::class);
        $log = $service->verify($file, $backup->id);

        $this->assertEquals('verified', $log->status);
        $this->assertNotEmpty($log->checksum);
        $this->assertGreaterThan(0, $log->table_count);
        $this->assertDatabaseHas('backup_verification_logs', [
            'id' => $log->id,
            'status' => 'verified',
        ]);
    }

    public function test_verify_checks_tables_exist(): void
    {
        $backup = app(BackupService::class)->create('manual');
        $service = app(RestoreVerificationService::class);
        $report = $service->generateReport(storage_path('app/' . $backup->path));

        $this->assertArrayHasKey('table_count', $report);
        $this->assertGreaterThan(0, $report['table_count']);
        $this->assertTrue($report['verified']);
    }

    public function test_verify_checks_migrations_match(): void
    {
        $backup = app(BackupService::class)->create('manual');
        $service = app(RestoreVerificationService::class);
        $report = $service->generateReport(storage_path('app/' . $backup->path));

        $this->assertArrayHasKey('checks', $report);
        $this->assertArrayHasKey('migrations_match', $report['checks']);
    }

    public function test_verify_checks_seed_data(): void
    {
        $backup = app(BackupService::class)->create('manual');
        $service = app(RestoreVerificationService::class);
        $report = $service->generateReport(storage_path('app/' . $backup->path));

        $this->assertArrayHasKey('seeds_healthy', $report['checks']);
    }

    public function test_verify_row_count_comparison(): void
    {
        $backup = app(BackupService::class)->create('manual');
        $service = app(RestoreVerificationService::class);
        $report = $service->generateReport(storage_path('app/' . $backup->path));

        $this->assertArrayHasKey('row_count', $report);
        $this->assertIsInt($report['row_count']);
    }

    public function test_verify_checksum(): void
    {
        $backup = app(BackupService::class)->create('manual');
        $service = app(RestoreVerificationService::class);
        $report = $service->generateReport(storage_path('app/' . $backup->path));

        $this->assertNotEmpty($report['checksum']);
        $this->assertEquals(64, strlen($report['checksum']));
    }

    public function test_artisan_backup_verify_command(): void
    {
        $backup = app(BackupService::class)->create('manual');
        $file = storage_path('app/' . $backup->path);

        $this->artisan('backup:verify', ['file' => $file])
            ->assertExitCode(0);
    }

    public function test_temporary_database_simulation(): void
    {
        $backup = app(BackupService::class)->create('manual');
        $service = app(RestoreVerificationService::class);
        $report = $service->verifyTemporaryDatabase(storage_path('app/' . $backup->path));

        $this->assertArrayHasKey('verified', $report);
        $this->assertTrue($report['verified']);
    }
}
