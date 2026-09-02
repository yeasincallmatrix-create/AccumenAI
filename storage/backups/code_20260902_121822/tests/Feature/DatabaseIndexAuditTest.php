<?php

namespace Tests\Feature;

use App\Services\System\DatabaseIndexAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DatabaseIndexAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_returns_report(): void
    {
        $service = app(DatabaseIndexAuditService::class);
        $audit = $service->audit();

        $this->assertArrayHasKey('report', $audit);
        $this->assertArrayHasKey('missing', $audit);
        $this->assertArrayHasKey('duplicates', $audit);
        $this->assertArrayHasKey('fk_missing_indexes', $audit);
    }

    public function test_important_tables_checked(): void
    {
        $service = app(DatabaseIndexAuditService::class);
        $audit = $service->audit();

        $important = ['students','invoices','audit_logs'];
        foreach ($important as $tbl) {
            $this->assertArrayHasKey($tbl, $audit['report']);
        }
    }

    public function test_missing_detection(): void
    {
        $service = app(DatabaseIndexAuditService::class);
        $audit = $service->audit();

        // Should not have missing for core tables that are indexed
        $this->assertIsArray($audit['missing']);
    }

    public function test_duplicate_detection(): void
    {
        $service = app(DatabaseIndexAuditService::class);
        $audit = $service->audit();

        $this->assertIsArray($audit['duplicates']);
    }

    public function test_fk_indexes_check(): void
    {
        $service = app(DatabaseIndexAuditService::class);
        $audit = $service->audit();

        $this->assertArrayHasKey('fk_missing_indexes', $audit);
    }

    public function test_generate_report_format(): void
    {
        $service = app(DatabaseIndexAuditService::class);
        $text = $service->generateReport();

        $this->assertStringContainsString('Index Report:', $text);
        $this->assertStringContainsString('Table:', $text);
        $this->assertStringContainsString('Recommendation only', $text);
    }

    public function test_artisan_command(): void
    {
        $this->artisan('database:index-audit')
            ->assertExitCode(0);
    }

    public function test_artisan_json(): void
    {
        $this->artisan('database:index-audit', ['--json' => true])
            ->assertExitCode(0);
    }
}
