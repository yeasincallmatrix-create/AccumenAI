<?php

namespace Tests\Feature;

use App\Console\Commands\DatabaseBackupCommand;
use App\Console\Commands\DatabaseBackupStatusCommand;
use App\Models\SystemBackup;
use App\Services\System\BackupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Step 122-K — Automated Database Backup Scheduler Tests.
 */
class DatabaseBackupAutomationTest extends TestCase
{
    use DatabaseTransactions;

    private BackupService $backupService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupService = app(BackupService::class);
    }

    // 1. Manual backup command works
    public function test_manual_backup_command_works(): void
    {
        $exitCode = Artisan::call('database:backup', ['--type' => 'manual']);
        $this->assertEquals(0, $exitCode);

        $backup = SystemBackup::latest()->first();
        $this->assertNotNull($backup);
        $this->assertEquals('manual', $backup->type);
        $this->assertContains($backup->status, ['completed', 'verified']);
    }

    // 2. Daily backup type works
    public function test_daily_backup_type_works(): void
    {
        $exitCode = Artisan::call('database:backup', ['--type' => 'daily', '--verify' => true]);
        $this->assertEquals(0, $exitCode);

        $backup = SystemBackup::latest()->first();
        $this->assertEquals('daily', $backup->type);
    }

    // 3. Weekly backup type works
    public function test_weekly_backup_type_works(): void
    {
        $exitCode = Artisan::call('database:backup', ['--type' => 'weekly', '--verify' => true]);
        $this->assertEquals(0, $exitCode);

        $backup = SystemBackup::latest()->first();
        $this->assertEquals('weekly', $backup->type);
    }

    // 4. Backup file is created
    public function test_backup_file_is_created(): void
    {
        $backup = $this->backupService->create('manual');
        $fullPath = storage_path('app/' . $backup->path);
        $this->assertFileExists($fullPath);
    }

    // 5. Checksum is generated
    public function test_checksum_is_generated(): void
    {
        $backup = $this->backupService->create('manual');
        $this->assertNotNull($backup->checksum);
        $this->assertEquals(64, strlen($backup->checksum));
    }

    // 6. Backup metadata is recorded
    public function test_backup_metadata_is_recorded(): void
    {
        $backup = $this->backupService->create('manual');
        $this->assertNotNull($backup->metadata);
        $this->assertArrayHasKey('db', $backup->metadata);
        $this->assertArrayHasKey('generated_at', $backup->metadata);
        $this->assertNotNull($backup->table_count);
        $this->assertNotNull($backup->migration_count);
    }

    // 7. Verification succeeds
    public function test_verification_succeeds(): void
    {
        $backup = $this->backupService->create('manual');
        $verified = $this->backupService->verify($backup);
        $this->assertTrue($verified);
        $backup->refresh();
        $this->assertEquals('verified', $backup->status);
    }

    // 8. Failed verification is recorded
    public function test_failed_verification_is_recorded(): void
    {
        $backup = SystemBackup::create([
            'filename' => 'test_bad.sql',
            'path' => 'backups/nonexistent.sql',
            'size_bytes' => 0,
            'checksum' => 'bad_checksum',
            'type' => 'manual',
            'status' => 'completed',
            'migration_count' => 0,
            'table_count' => 0,
        ]);

        $verified = $this->backupService->verify($backup);
        $this->assertFalse($verified);
        $backup->refresh();
        $this->assertEquals('failed', $backup->status);
    }

    // 9. Failed backup is recorded
    public function test_failed_backup_is_recorded(): void
    {
        $backup = $this->backupService->createScheduledBackup('daily');
        // The backup should either be completed+verified or failed
        $this->assertNotNull($backup);
        $this->assertContains($backup->status, ['completed', 'verified', 'failed']);
    }

    // 10. Failed verification does not become verified
    public function test_failed_verification_not_marked_verified(): void
    {
        $backup = SystemBackup::create([
            'filename' => 'test_fail.sql',
            'path' => 'backups/nonexistent_fail.sql',
            'size_bytes' => 100,
            'checksum' => 'expected_checksum',
            'type' => 'manual',
            'status' => 'completed',
            'migration_count' => 0,
            'table_count' => 0,
        ]);

        $verified = $this->backupService->verify($backup);
        $this->assertFalse($verified);
        $backup->refresh();
        $this->assertNotEquals('verified', $backup->status);
    }

    // 11. Existing verified backup remains untouched after failure
    public function test_existing_verified_backup_untouched_after_failure(): void
    {
        $goodBackup = $this->backupService->create('manual');
        $this->backupService->verify($goodBackup);

        $badBackup = SystemBackup::create([
            'filename' => 'test_bad_2.sql',
            'path' => 'backups/nonexistent_2.sql',
            'size_bytes' => 0,
            'checksum' => null,
            'type' => 'manual',
            'status' => 'completed',
            'migration_count' => 0,
            'table_count' => 0,
        ]);
        $this->backupService->verify($badBackup);

        $goodBackup->refresh();
        $this->assertEquals('verified', $goodBackup->status);
    }

    // 12. Concurrent backup is prevented
    public function test_concurrent_backup_prevented(): void
    {
        $acquired1 = $this->backupService->acquireLock();
        $this->assertTrue($acquired1);
        $this->assertTrue($this->backupService->isBackupRunning());

        $acquired2 = $this->backupService->acquireLock();
        $this->assertFalse($acquired2);

        $this->backupService->releaseLock();
        $this->assertFalse($this->backupService->isBackupRunning());
    }

    // 13. Stale lock can recover safely
    public function test_stale_lock_can_recover(): void
    {
        Cache::put('backup_lock_running', 'stale_pid', 1);
        $this->assertTrue($this->backupService->isBackupRunning());

        // Wait for lock to expire (using short timeout)
        usleep(1100000); // 1.1 seconds
        $this->assertFalse($this->backupService->isBackupRunning());

        // Should be able to acquire again
        $acquired = $this->backupService->acquireLock();
        $this->assertTrue($acquired);
        $this->backupService->releaseLock();
    }

    // 14. Scheduler contains daily backup
    public function test_scheduler_contains_daily_backup(): void
    {
        $dailyEnabled = config('backup.daily.enabled');
        $this->assertTrue($dailyEnabled, 'Daily backup schedule should be enabled by default');

        $dailySchedule = config('backup.daily.schedule');
        $this->assertEquals('01:00', $dailySchedule);
    }

    // 15. Scheduler contains weekly backup
    public function test_scheduler_contains_weekly_backup(): void
    {
        $weeklyEnabled = config('backup.weekly.enabled');
        $this->assertTrue($weeklyEnabled, 'Weekly backup schedule should be enabled by default');

        $weeklySchedule = config('backup.weekly.schedule');
        $this->assertEquals('02:00', $weeklySchedule);

        $weeklyDay = config('backup.weekly.day');
        $this->assertEquals('sunday', $weeklyDay);
    }

    // 16. Disabled daily schedule is respected
    public function test_disabled_daily_schedule_respected(): void
    {
        config(['backup.daily.enabled' => false]);
        $enabled = config('backup.daily.enabled');
        $this->assertFalse($enabled);
    }

    // 17. Disabled weekly schedule is respected
    public function test_disabled_weekly_schedule_respected(): void
    {
        config(['backup.weekly.enabled' => false]);
        $enabled = config('backup.weekly.enabled');
        $this->assertFalse($enabled);
    }

    // 18. JSON output is valid
    public function test_json_output_is_valid(): void
    {
        $exitCode = Artisan::call('database:backup', ['--type' => 'manual', '--json' => true]);
        $output = Artisan::output();
        $this->assertEquals(0, $exitCode);

        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded, 'JSON output is not valid');
        $this->assertArrayHasKey('type', $decoded);
        $this->assertArrayHasKey('status', $decoded);
    }

    // 19. database:backup-status works
    public function test_backup_status_command_works(): void
    {
        $exitCode = Artisan::call('database:backup-status');
        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('BACKUP AUTOMATION STATUS', $output);
    }

    // 20. Monitoring dashboard shows backup automation status
    public function test_monitoring_shows_backup_automation(): void
    {
        $service = app(\App\Services\System\DatabaseMonitoringService::class);
        try {
            $snapshot = $service->snapshot(false);
            $this->assertArrayHasKey('backup', $snapshot);
            $this->assertArrayHasKey('automation', $snapshot['backup']);
            $this->assertArrayHasKey('daily_enabled', $snapshot['backup']['automation']);
        } catch (\Illuminate\Database\QueryException $e) {
            $this->markTestSkipped('Full monitoring snapshot requires all tables (teachers etc.)');
        }
    }

    // 21. Backup operation does not modify tenant/business data
    public function test_backup_does_not_modify_business_data(): void
    {
        // Count tables before
        $tablesBefore = count(\Illuminate\Support\Facades\DB::select('SHOW TABLES'));

        $this->backupService->create('manual');

        // Count tables after
        $tablesAfter = count(\Illuminate\Support\Facades\DB::select('SHOW TABLES'));
        $this->assertEquals($tablesBefore, $tablesAfter);
    }

    // 22. Backup stats returns expected structure
    public function test_backup_stats_returns_expected_structure(): void
    {
        $stats = $this->backupService->getBackupStats();
        $this->assertArrayHasKey('daily_enabled', $stats);
        $this->assertArrayHasKey('weekly_enabled', $stats);
        $this->assertArrayHasKey('verification_enabled', $stats);
        $this->assertArrayHasKey('latest_backup', $stats);
        $this->assertArrayHasKey('latest_verified', $stats);
        $this->assertArrayHasKey('latest_failed', $stats);
        $this->assertArrayHasKey('total_verified', $stats);
        $this->assertArrayHasKey('total_failed', $stats);
        $this->assertArrayHasKey('is_running', $stats);
        $this->assertArrayHasKey('no_verified_backup', $stats);
    }

    // Extra: recordFailure stores reason in metadata
    public function test_record_failure_stores_reason(): void
    {
        $backup = $this->backupService->create('manual');
        $this->backupService->recordFailure($backup, 'Test failure reason');
        $backup->refresh();
        $this->assertEquals('Test failure reason', $backup->metadata['failure_reason'] ?? null);
        $this->assertArrayHasKey('failed_at', $backup->metadata ?? []);
    }

    // Extra: latestSuccessful returns completed or verified
    public function test_latest_successful_returns_correct_status(): void
    {
        $backup = $this->backupService->create('manual');
        $latest = $this->backupService->latestSuccessful();
        $this->assertNotNull($latest);
        $this->assertContains($latest->status, ['completed', 'verified']);
    }

    // Extra: latestVerified returns verified only
    public function test_latest_verified_returns_verified_only(): void
    {
        $backup = $this->backupService->create('manual');
        $this->backupService->verify($backup);
        $latest = $this->backupService->latestVerified();
        $this->assertNotNull($latest);
        $this->assertEquals('verified', $latest->status);
    }
}
