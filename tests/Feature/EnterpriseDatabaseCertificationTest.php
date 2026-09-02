<?php

namespace Tests\Feature;

use App\Services\System\EnterpriseDatabaseCertificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EnterpriseDatabaseCertificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_combines_all_audits(): void
    {
        $svc = app(EnterpriseDatabaseCertificationService::class);
        $res = $svc->certify();
        $this->assertArrayHasKey('scores', $res);
        $this->assertArrayHasKey('overall', $res);
        $this->assertArrayHasKey('checks', $res);
    }

    public function test_scores_include_all_categories(): void
    {
        $svc = app(EnterpriseDatabaseCertificationService::class);
        $res = $svc->certify();
        foreach (['Integrity','Tenant Safety','Accounting','Inventory','Backup','Restore','Security','Performance','Schema','Seeds'] as $k) {
            $this->assertArrayHasKey($k, $res['scores']);
        }
    }

    public function test_status_is_certified_or_warning(): void
    {
        $svc = app(EnterpriseDatabaseCertificationService::class);
        $res = $svc->certify();
        $this->assertContains($res['status'], ['CERTIFIED','CERTIFIED WITH WARNINGS','NOT CERTIFIED']);
    }

    public function test_artisan_command(): void
    {
        $this->artisan('database:certify')->assertExitCode(0);
    }

    public function test_artisan_json(): void
    {
        $this->artisan('database:certify', ['--json'=>true])->assertExitCode(0);
    }

    public function test_overall_score_reasonable(): void
    {
        $svc = app(EnterpriseDatabaseCertificationService::class);
        $res = $svc->certify();
        $this->assertGreaterThanOrEqual(70, $res['overall']);
    }
}
