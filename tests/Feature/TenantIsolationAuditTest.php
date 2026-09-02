<?php

namespace Tests\Feature;

use App\Services\System\TenantIsolationAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TenantIsolationAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_with_3_tenants(): void
    {
        $svc = app(TenantIsolationAuditService::class);
        $res = $svc->audit();
        $this->assertArrayHasKey('leakage', $res);
        $this->assertArrayHasKey('status', $res);
        $this->assertEquals('SECURE', $res['status']);
    }

    public function test_cross_tenant_blocked(): void
    {
        $svc = app(TenantIsolationAuditService::class);
        $res = $svc->audit();
        $this->assertEquals(0, $res['leakage']);
        $this->assertEquals(0, $res['cross_queries']);
    }

    public function test_artisan_command(): void
    {
        $this->artisan('system:tenant-isolation-audit')->assertExitCode(0);
    }

    public function test_report_format(): void
    {
        $svc = app(TenantIsolationAuditService::class);
        $text = $svc->report();
        $this->assertStringContainsString('TENANT ISOLATION AUDIT', $text);
        $this->assertStringContainsString('Status: SECURE', $text);
    }
}
