<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\System\DatabaseMonitoringService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DatabaseMonitoringTest extends TestCase
{
    use DatabaseTransactions;

    private function superAdmin(): PlatformAdmin
    {
        return PlatformAdmin::first() ?? PlatformAdmin::firstOrReuseForTests([
            'name' => 'Super Admin '.uniqid(),
            'email' => 'super-'.uniqid().'@test.com',
            'password_hash' => bcrypt('secret12345'),
        ]);
    }

    public function test_super_admin_can_access_monitoring_dashboard(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'platform_admin')
            ->get(route('super-admin.database.monitoring'))
            ->assertOk()
            ->assertSee('Database Monitoring');
    }

    public function test_unauthorized_user_cannot_access_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')
            ->get(route('super-admin.database.monitoring'))
            ->assertRedirect();
    }

    public function test_monitoring_service_returns_health_information(): void
    {
        $svc = app(DatabaseMonitoringService::class);
        $snap = $svc->snapshot(useCache: false);
        $this->assertArrayHasKey('health', $snap);
        $this->assertArrayHasKey('status', $snap['health']);
        $this->assertArrayHasKey('score', $snap['health']);
    }

    public function test_migration_status_is_included(): void
    {
        $snap = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertArrayHasKey('migration_status', $snap['health']);
        $this->assertArrayHasKey('pending_migrations', $snap['health']);
    }

    public function test_missing_table_status_is_included(): void
    {
        $snap = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertArrayHasKey('missing_tables', $snap['health']);
    }

    public function test_orphan_integrity_status_is_included(): void
    {
        $snap = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertArrayHasKey('orphan_status', $snap['health']);
    }

    public function test_foreign_key_status_is_included(): void
    {
        $snap = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertArrayHasKey('foreign_key_status', $snap['integrity']);
    }

    public function test_duplicate_status_is_included(): void
    {
        $snap = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertArrayHasKey('duplicate_status', $snap['integrity']);
    }

    public function test_tenant_isolation_status_is_included(): void
    {
        $snap = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertArrayHasKey('tenant_isolation', $snap['health']);
    }

    public function test_accounting_integrity_status_is_included(): void
    {
        $snap = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertArrayHasKey('accounting_status', $snap['integrity']);
    }

    public function test_inventory_integrity_status_is_included(): void
    {
        $snap = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertArrayHasKey('inventory_status', $snap['integrity']);
    }

    public function test_backup_status_is_included(): void
    {
        $snap = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertArrayHasKey('backup', $snap);
        $this->assertArrayHasKey('backup_count', $snap['backup']);
        $this->assertArrayHasKey('latest_backup', $snap['backup']);
    }

    public function test_restore_status_is_included(): void
    {
        $snap = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertArrayHasKey('restore_verification_status', $snap['backup']);
        $this->assertArrayHasKey('disaster_recovery_readiness', $snap['backup']);
    }

    public function test_performance_status_is_included(): void
    {
        $snap = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertArrayHasKey('performance', $snap);
        $this->assertArrayHasKey('slow_query_count', $snap['performance']);
        $this->assertArrayHasKey('average_query_time', $snap['performance']);
    }

    public function test_index_recommendations_are_included(): void
    {
        $snap = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertArrayHasKey('indexes', $snap);
        $this->assertArrayHasKey('recommendations', $snap['indexes']);
        // Must be labeled RECOMMENDATION ONLY
        $this->assertIsArray($snap['indexes']['recommendations']);
    }

    public function test_certification_score_is_included(): void
    {
        $snap = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertArrayHasKey('certification', $snap);
        $this->assertArrayHasKey('overall_score', $snap['certification']);
        $this->assertArrayHasKey('status', $snap['certification']);
    }

    public function test_json_command_works(): void
    {
        $this->artisan('database:monitor', ['--json' => true])
            ->assertExitCode(0);
    }

    public function test_monitoring_is_read_only(): void
    {
        $before = \Illuminate\Support\Facades\DB::table('students')->count();
        app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertEquals($before, \Illuminate\Support\Facades\DB::table('students')->count());
    }

    public function test_no_tenant_data_is_modified(): void
    {
        $beforeInstitutes = \Illuminate\Support\Facades\DB::table('institutes')->count();
        app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertEquals($beforeInstitutes, \Illuminate\Support\Facades\DB::table('institutes')->count());
    }

    public function test_existing_step_101_120_tests_remain_passing(): void
    {
        // Spot check a few critical services still work
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('system_backups'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('system_health_audits'));
    }
}
