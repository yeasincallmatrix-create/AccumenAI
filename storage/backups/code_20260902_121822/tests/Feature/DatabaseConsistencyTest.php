<?php

namespace Tests\Feature;

use App\Services\System\DatabaseConsistencyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DatabaseConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tenant_consistency(): void
    {
        $svc = app(DatabaseConsistencyService::class);
        $res = $svc->checkTenant();
        $this->assertArrayHasKey('status', $res);
        $this->assertArrayHasKey('issues', $res);
    }

    public function test_relationship_consistency(): void
    {
        $svc = app(DatabaseConsistencyService::class);
        $res = $svc->checkRelationships();
        $this->assertArrayHasKey('status', $res);
    }

    public function test_soft_delete_consistency(): void
    {
        $svc = app(DatabaseConsistencyService::class);
        $res = $svc->checkSoftDelete();
        $this->assertArrayHasKey('status', $res);
    }

    public function test_overall_report(): void
    {
        $svc = app(DatabaseConsistencyService::class);
        $report = $svc->check();
        $this->assertArrayHasKey('overall', $report);
        $this->assertContains($report['overall'], ['CLEAN','WARNING']);
    }

    public function test_report_format(): void
    {
        $svc = app(DatabaseConsistencyService::class);
        $text = $svc->report();
        $this->assertStringContainsString('DATABASE CONSISTENCY REPORT', $text);
        $this->assertStringContainsString('Tenant Integrity:', $text);
        $this->assertStringContainsString('Overall:', $text);
    }

    public function test_artisan_command(): void
    {
        $this->artisan('system:consistency-check')->assertExitCode(0);
    }

    public function test_artisan_json(): void
    {
        $this->artisan('system:consistency-check', ['--json'=>true])->assertExitCode(0);
    }

    public function test_never_auto_deletes(): void
    {
        $before = \Illuminate\Support\Facades\DB::table('students')->count();
        app(DatabaseConsistencyService::class)->check();
        $this->assertEquals($before, \Illuminate\Support\Facades\DB::table('students')->count());
    }
}
