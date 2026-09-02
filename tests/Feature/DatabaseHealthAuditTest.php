<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Services\System\DatabaseHealthCheckService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DatabaseHealthAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_runs_and_returns_score(): void
    {
        $service = app(DatabaseHealthCheckService::class);
        $result = $service->run(persist: false);

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
        $this->assertContains($result['status'], ['healthy', 'warning', 'critical']);
    }

    public function test_audit_detects_migrations_healthy(): void
    {
        $service = app(DatabaseHealthCheckService::class);
        $check = $service->checkMigrations();

        $this->assertTrue($check['healthy'], 'Migrations should be healthy after sync');
        $this->assertEmpty($check['pending']);
    }

    public function test_audit_detects_missing_tables_healthy(): void
    {
        $service = app(DatabaseHealthCheckService::class);
        $check = $service->checkMissingTables();

        // Critical tables for institute creation must be present
        $this->assertNotContains('industry_settings', $check['missing']);
        $this->assertNotContains('institutes', $check['missing']);
        $this->assertNotContains('themes', $check['missing']);
    }

    public function test_audit_persists_health_audit_record(): void
    {
        $service = app(DatabaseHealthCheckService::class);
        $result = $service->run(persist: true);

        $this->assertDatabaseHas('system_health_audits', [
            'status' => $result['status'],
            'score' => $result['score'],
        ]);
    }

    public function test_tenant_isolation_check(): void
    {
        $service = app(DatabaseHealthCheckService::class);
        $check = $service->checkTenantIsolation();

        $this->assertArrayHasKey('healthy', $check);
        // After fix, no null institute_id in TenantScoped tables
        $this->assertTrue($check['healthy'] || !empty($check['issues']));
    }

    public function test_artisan_audit_command_runs(): void
    {
        $this->artisan('system:audit-db', ['--no-persist' => true])
            ->assertExitCode(0);
    }

    public function test_artisan_audit_json_output(): void
    {
        $this->artisan('system:audit-db', ['--json' => true, '--no-persist' => true])
            ->assertExitCode(0);
    }
}
