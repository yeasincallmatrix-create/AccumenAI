<?php

namespace Tests\Feature;

use App\Services\System\AccountingIntegrityAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AccountingIntegrityAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_readonly(): void
    {
        $before = \Illuminate\Support\Facades\DB::table('journals')->count();
        app(AccountingIntegrityAuditService::class)->audit();
        $this->assertEquals($before, \Illuminate\Support\Facades\DB::table('journals')->count());
    }

    public function test_journal_balance_check(): void
    {
        $svc = app(AccountingIntegrityAuditService::class);
        $res = $svc->audit();
        $this->assertArrayHasKey('healthy', $res);
        $this->assertArrayHasKey('issues', $res);
    }

    public function test_artisan_command(): void
    {
        $this->artisan('accounting:integrity-audit')->assertExitCode(0);
    }
}
