<?php

namespace Tests\Feature;

use App\Services\System\DisasterRecoveryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DisasterRecoveryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_backup(): void
    {
        $svc = app(DisasterRecoveryService::class);
        $res = $svc->run();
        $this->assertEquals('PASS', $res['backup']);
    }

    public function test_verify_checksum(): void
    {
        $svc = app(DisasterRecoveryService::class);
        $res = $svc->run();
        $this->assertEquals('PASS', $res['checksum']);
    }

    public function test_verify_tables(): void
    {
        $svc = app(DisasterRecoveryService::class);
        $res = $svc->run();
        $this->assertEquals('PASS', $res['tables']);
    }

    public function test_verify_migrations(): void
    {
        $svc = app(DisasterRecoveryService::class);
        $res = $svc->run();
        $this->assertEquals('PASS', $res['migrations']);
    }

    public function test_verify_seeds(): void
    {
        $svc = app(DisasterRecoveryService::class);
        $res = $svc->run();
        $this->assertEquals('PASS', $res['seeds']);
    }

    public function test_verify_row_counts(): void
    {
        $svc = app(DisasterRecoveryService::class);
        $res = $svc->run();
        $this->assertEquals('PASS', $res['row_counts']);
    }

    public function test_verify_schema_compatibility(): void
    {
        $svc = app(DisasterRecoveryService::class);
        $res = $svc->run();
        $this->assertEquals('PASS', $res['schema']);
    }

    public function test_perform_dr_dry_run(): void
    {
        $svc = app(DisasterRecoveryService::class);
        $res = $svc->run();
        $this->assertEquals('PASS', $res['restore_simulation']);
        $this->assertEquals('RECOVERY READY', $res['result']);
    }

    public function test_verify_backup_metadata(): void
    {
        $svc = app(DisasterRecoveryService::class);
        $res = $svc->run();
        $this->assertEquals('PASS', $res['backup_metadata']);
    }

    public function test_artisan_command(): void
    {
        $this->artisan('backup:disaster-test')->assertExitCode(0);
    }

    public function test_temp_db_cleanup(): void
    {
        $svc = app(DisasterRecoveryService::class);
        $svc->run();
        // Temp DB should be removed
        $exists = \Illuminate\Support\Facades\DB::select("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='monetix_dr_test'");
        $this->assertEmpty($exists);
    }
}
