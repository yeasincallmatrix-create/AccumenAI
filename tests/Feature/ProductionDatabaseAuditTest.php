<?php

namespace Tests\Feature;

use App\Services\System\ProductionDatabaseAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductionDatabaseAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_returns_scores(): void
    {
        $service = app(ProductionDatabaseAuditService::class);
        $result = $service->audit();

        $this->assertArrayHasKey('scores', $result);
        $this->assertArrayHasKey('overall', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertGreaterThanOrEqual(0, $result['overall']);
        $this->assertLessThanOrEqual(100, $result['overall']);
    }

    public function test_checks_include_all_required(): void
    {
        $service = app(ProductionDatabaseAuditService::class);
        $result = $service->audit();

        $checks = $result['checks'];
        $this->assertArrayHasKey('migrations', $checks);
        $this->assertArrayHasKey('missing_tables', $checks);
        $this->assertArrayHasKey('seeds', $checks);
        $this->assertArrayHasKey('indexes', $checks);
        $this->assertArrayHasKey('tenant_isolation', $checks);
        $this->assertArrayHasKey('backups', $checks);
        $this->assertArrayHasKey('restore', $checks);
        $this->assertArrayHasKey('orphans', $checks);
        $this->assertArrayHasKey('schema', $checks);
        $this->assertArrayHasKey('performance', $checks);
    }

    public function test_scores_include_required_categories(): void
    {
        $service = app(ProductionDatabaseAuditService::class);
        $result = $service->audit();

        $this->assertArrayHasKey('integrity', $result['scores']);
        $this->assertArrayHasKey('backup', $result['scores']);
        $this->assertArrayHasKey('restore', $result['scores']);
        $this->assertArrayHasKey('security', $result['scores']);
        $this->assertArrayHasKey('performance', $result['scores']);
    }

    public function test_artisan_command(): void
    {
        $this->artisan('database:production-audit')
            ->assertExitCode(0);
    }

    public function test_artisan_json(): void
    {
        $this->artisan('database:production-audit', ['--json' => true])
            ->assertExitCode(0);
    }

    public function test_overall_score_is_reasonable(): void
    {
        $service = app(ProductionDatabaseAuditService::class);
        $score = $service->score();

        $this->assertGreaterThanOrEqual(70, $score, 'Production score should be at least 70 after hardening');
    }
}
