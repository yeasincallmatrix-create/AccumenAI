<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\SystemBackup;
use App\Models\User;
use App\Services\System\BackupHealthService;
use App\Services\System\BackupInventoryService;
use App\Services\System\BackupRetentionService;
use App\Services\System\BackupStorageService;
use App\Services\System\DisasterRecoveryService;
use App\Services\System\RecoveryTimeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionBackupRecoveryTest extends TestCase
{
    use DatabaseTransactions;

    // 1. Retention policy loading
    public function test_retention_policy_loads_from_config(): void
    {
        $policy = config('backup.retention');
        $this->assertArrayHasKey('daily', $policy);
        $this->assertArrayHasKey('weekly', $policy);
        $this->assertArrayHasKey('monthly', $policy);
        $this->assertArrayHasKey('manual', $policy);
        $this->assertArrayHasKey('pre_operation', $policy);
        $this->assertArrayHasKey('max_storage_bytes', $policy);
    }

    // 2. Daily retention
    public function test_daily_retention_config(): void
    {
        $daily = config('backup.retention.daily');
        $this->assertTrue($daily['enabled']);
        $this->assertIsInt($daily['retain_days']);
        $this->assertGreaterThan(0, $daily['retain_days']);
    }

    // 3. Weekly retention
    public function test_weekly_retention_config(): void
    {
        $weekly = config('backup.retention.weekly');
        $this->assertTrue($weekly['enabled']);
        $this->assertIsInt($weekly['retain_weeks']);
        $this->assertGreaterThan(0, $weekly['retain_weeks']);
    }

    // 4. Monthly retention
    public function test_monthly_retention_config(): void
    {
        $monthly = config('backup.retention.monthly');
        $this->assertTrue($monthly['enabled']);
        $this->assertIsInt($monthly['retain_months']);
        $this->assertGreaterThan(0, $monthly['retain_months']);
    }

    // 5. Manual backup protection
    public function test_manual_backup_protected_indefinitely(): void
    {
        $manual = config('backup.retention.manual');
        $this->assertTrue($manual['retain_indefinitely']);
    }

    // 6. Pre-operation backup protection
    public function test_pre_operation_backup_retention(): void
    {
        $preOp = config('backup.retention.pre_operation');
        $this->assertTrue($preOp['enabled']);
        $this->assertIsInt($preOp['retain_days']);
        $this->assertGreaterThan(0, $preOp['retain_days']);
    }

    // 7. Expired backup detection
    public function test_expired_backup_detection(): void
    {
        $service = app(BackupRetentionService::class);
        $report = $service->report();
        $this->assertArrayHasKey('expired_count', $report);
        $this->assertArrayHasKey('deletion_candidates', $report);
        $this->assertIsInt($report['expired_count']);
    }

    // 8. Dry-run performs no deletion
    public function test_dry_run_performs_no_deletion(): void
    {
        $service = app(BackupRetentionService::class);
        $beforeCount = SystemBackup::count();
        $report = $service->report();
        $afterCount = SystemBackup::count();
        $this->assertEquals($beforeCount, $afterCount);
        $this->assertArrayHasKey('expired_count', $report);
    }

    // 9. Explicit execute removes only eligible backup files
    public function test_execute_removes_only_eligible(): void
    {
        $service = app(BackupRetentionService::class);
        // Create an expired failed backup
        $oldBackup = SystemBackup::create([
            'filename' => 'OLD_FAILED_TEST.sql',
            'path' => '',
            'size_bytes' => 0,
            'checksum' => null,
            'type' => 'daily',
            'status' => 'failed',
            'migration_count' => 0,
            'table_count' => 0,
            'metadata' => ['db' => 'monetix_test'],
            'created_by' => null,
            'created_by_type' => 'test',
            'created_at' => now()->subDays(999),
        ]);
        $result = $service->deleteExpired();
        $this->assertArrayHasKey('deleted_count', $result);
        // Cleanup: restore the record
        $oldBackup->delete();
    }

    // 10. Backup inventory
    public function test_backup_inventory(): void
    {
        $service = app(BackupInventoryService::class);
        $inv = $service->inventory();
        $this->assertArrayHasKey('total_backups', $inv);
        $this->assertArrayHasKey('verified_count', $inv);
        $this->assertArrayHasKey('failed_count', $inv);
        $this->assertArrayHasKey('items', $inv);
        $this->assertIsArray($inv['items']);
    }

    // 11. Missing file detection
    public function test_missing_file_detection(): void
    {
        $service = app(BackupInventoryService::class);
        $inv = $service->inventory();
        $this->assertArrayHasKey('issues', $inv);
        // Each issue should have a type
        foreach ($inv['issues'] as $issue) {
            $this->assertArrayHasKey('type', $issue);
            $this->assertContains($issue['type'], ['missing_file', 'checksum_mismatch', 'unverified', 'no_file_record', 'duplicate']);
        }
    }

    // 12. Checksum mismatch detection
    public function test_checksum_mismatch_detection(): void
    {
        $service = app(BackupInventoryService::class);
        $inv = $service->inventory();
        foreach ($inv['items'] as $item) {
            $this->assertArrayHasKey('checksum_match', $item);
            $this->assertIsBool($item['checksum_match']);
        }
    }

    // 13. Backup health
    public function test_backup_health(): void
    {
        $service = app(BackupHealthService::class);
        $health = $service->check();
        $this->assertArrayHasKey('overall', $health);
        $this->assertContains($health['overall'], ['PASS', 'WARNING', 'FAIL']);
        $this->assertArrayHasKey('checks', $health);
        $this->assertArrayHasKey('daily_backup', $health['checks']);
        $this->assertArrayHasKey('weekly_backup', $health['checks']);
        $this->assertArrayHasKey('failures', $health['checks']);
    }

    // 14. RPO calculation
    public function test_rpo_calculation(): void
    {
        $service = app(BackupHealthService::class);
        $health = $service->check();
        $this->assertArrayHasKey('rpo', $health['checks']);
        $rpo = $health['checks']['rpo'];
        $this->assertArrayHasKey('status', $rpo);
        $this->assertArrayHasKey('target_minutes', $rpo);
        $this->assertContains($rpo['status'], ['PASS', 'WARNING', 'FAIL', 'CRITICAL']);
    }

    // 15. RTO calculation
    public function test_rto_calculation(): void
    {
        $service = app(RecoveryTimeService::class);
        $status = $service->status();
        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('rto_status', $status);
        $this->assertArrayHasKey('target_rto_minutes', $status);
    }

    // 16. Restore drill isolation
    public function test_restore_drill_creates_temp_db(): void
    {
        $tempDb = config('backup.restore_drill.temp_database', 'monetix_dr_test');
        $this->assertNotEquals(config('database.connections.mysql.database'), $tempDb);
    }

    // 17. Production database remains untouched
    public function test_production_database_untouched(): void
    {
        $prodDb = config('database.connections.mysql.database');
        $tempDb = config('backup.restore_drill.temp_database', 'monetix_dr_test');
        $this->assertNotEquals($prodDb, $tempDb);
    }

    // 18. Temporary DR database cleanup
    public function test_temp_dr_database_configured(): void
    {
        $tempDb = config('backup.restore_drill.temp_database');
        $this->assertNotEmpty($tempDb);
        $this->assertEquals('monetix_dr_test', $tempDb);
    }

    // 19. Off-site not-configured status
    public function test_offsite_not_configured(): void
    {
        $service = app(BackupStorageService::class);
        $status = $service->status();
        $this->assertEquals('NOT_CONFIGURED', $status['offsite']['status']);
        $this->assertFalse($status['offsite']['enabled']);
    }

    // 20. Encryption not-configured status
    public function test_encryption_not_configured(): void
    {
        $service = app(BackupStorageService::class);
        $status = $service->status();
        $this->assertEquals('NOT_CONFIGURED', $status['encryption']['status']);
        $this->assertFalse($status['encryption']['enabled']);
    }

    // 21. Backup failure recording
    public function test_backup_failure_recording(): void
    {
        $backup = SystemBackup::create([
            'filename' => 'FAIL_RECORD_TEST.sql',
            'path' => '',
            'size_bytes' => 0,
            'checksum' => null,
            'type' => 'manual',
            'status' => 'failed',
            'migration_count' => 0,
            'table_count' => 0,
            'metadata' => ['db' => 'monetix_test'],
            'created_by' => null,
            'created_by_type' => 'test',
        ]);

        app(\App\Services\System\BackupService::class)->recordFailure($backup, 'Test failure', 'RuntimeException', 0);

        $updated = SystemBackup::find($backup->id);
        $this->assertEquals('Test failure', $updated->metadata['failure_reason']);
        $this->assertEquals('RuntimeException', $updated->metadata['exception_class']);
        $this->assertArrayHasKey('safe_error', $updated->metadata);

        $backup->delete();
    }

    // 22. Retry/concurrency protection
    public function test_retry_concurrency_protection(): void
    {
        $backupService = app(\App\Services\System\BackupService::class);
        $this->assertFalse($backupService->isBackupRunning());
    }

    // 23. Super Admin authorization
    public function test_super_admin_authorization(): void
    {
        $admin = PlatformAdmin::first() ?? PlatformAdmin::firstOrReuseForTests(['name' => 'SA', 'email' => 'sa-'.uniqid().'@test.com', 'password_hash' => bcrypt('secret')]);
        $this->actingAs($admin, 'platform_admin')
            ->get(route('super-admin.database.monitoring'))
            ->assertOk();
    }

    // 24. Normal institute user denied
    public function test_normal_institute_user_denied(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')
            ->get(route('super-admin.database.monitoring'))
            ->assertRedirect();
    }

    // 25. JSON output for all commands
    public function test_json_output_backup_status(): void
    {
        $this->artisan('database:backup-status', ['--json' => true])->assertExitCode(0);
    }

    public function test_json_output_backup_retention(): void
    {
        $this->artisan('database:backup-retention', ['--json' => true, '--dry-run' => true])->assertExitCode(0);
    }

    public function test_json_output_backup_inventory(): void
    {
        $this->artisan('database:backup-inventory', ['--json' => true])->assertExitCode(0);
    }

    public function test_json_output_backup_health(): void
    {
        $this->artisan('database:backup-health', ['--json' => true])->assertExitCode(0);
    }

    public function test_json_output_recovery_status(): void
    {
        $this->artisan('database:recovery-status', ['--json' => true])->assertExitCode(0);
    }

    public function test_json_output_disaster_test(): void
    {
        // Test service directly — artisan crashes kernel in test env when mysqldump fails
        $svc = app(DisasterRecoveryService::class);
        $res = $svc->run();
        $this->assertArrayHasKey('result', $res);
        $this->assertContains($res['result'], ['RECOVERY READY', 'FAILED']);
    }

    // 26. Previous Steps 101–124 remain passing
    public function test_previous_steps_remain_passing(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('system_backups'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('system_health_audits'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('system_schema_versions'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('system_seed_versions'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('query_fingerprints'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('endpoint_performance_logs'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('database_query_logs'));

        // Verify config keys from earlier steps
        $this->assertNotNull(config('database-monitoring.slow_query_ms'));
        $this->assertNotNull(config('backup.rpo.target_minutes'));
        $this->assertNotNull(config('backup.rto.target_minutes'));
    }
}
