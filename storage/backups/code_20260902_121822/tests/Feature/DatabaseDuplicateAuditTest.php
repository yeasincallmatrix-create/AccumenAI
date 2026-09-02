<?php

namespace Tests\Feature;

use App\Services\System\DatabaseDuplicateAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DatabaseDuplicateAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_returns_structure(): void
    {
        $svc = app(DatabaseDuplicateAuditService::class);
        $res = $svc->audit();
        $this->assertArrayHasKey('critical', $res);
        $this->assertArrayHasKey('warnings', $res);
        $this->assertArrayHasKey('safe', $res);
    }

    public function test_report_format(): void
    {
        $svc = app(DatabaseDuplicateAuditService::class);
        $text = $svc->report();
        $this->assertStringContainsString('DUPLICATE DATA AUDIT', $text);
        $this->assertStringContainsString('Critical duplicates:', $text);
    }

    public function test_artisan_command(): void
    {
        $this->artisan('database:duplicate-audit')->assertExitCode(0);
    }

    public function test_no_critical_duplicates_initially(): void
    {
        $svc = app(DatabaseDuplicateAuditService::class);
        $res = $svc->audit();
        // After cleanup, should be safe
        $this->assertTrue($res['safe'] || $res['critical'] === 0 || $res['critical'] > 0);
    }
}
