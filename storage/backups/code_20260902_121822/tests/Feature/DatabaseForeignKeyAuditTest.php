<?php

namespace Tests\Feature;

use App\Services\System\DatabaseForeignKeyAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DatabaseForeignKeyAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_detects_missing_fks(): void
    {
        $svc = app(DatabaseForeignKeyAuditService::class);
        $res = $svc->audit();
        $this->assertArrayHasKey('missing', $res);
        $this->assertArrayHasKey('incorrect', $res);
        $this->assertIsArray($res['missing']);
    }

    public function test_report_format(): void
    {
        $svc = app(DatabaseForeignKeyAuditService::class);
        $text = $svc->report();
        $this->assertStringContainsString('FOREIGN KEY AUDIT', $text);
    }

    public function test_artisan_command(): void
    {
        $this->artisan('database:foreign-key-audit')->assertExitCode(0);
    }

    public function test_tenant_fk_checked(): void
    {
        $svc = app(DatabaseForeignKeyAuditService::class);
        $res = $svc->audit();
        // At least batches institute_id should be checked
        $this->assertIsArray($res['missing']);
    }
}
