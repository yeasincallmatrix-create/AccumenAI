<?php

namespace Tests\Feature;

use App\Services\System\InventoryIntegrityAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryIntegrityAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_negative_stock_check(): void
    {
        $svc = app(InventoryIntegrityAuditService::class);
        $res = $svc->audit();
        $this->assertArrayHasKey('healthy', $res);
    }

    public function test_artisan_command(): void
    {
        $this->artisan('inventory:integrity-audit')->assertExitCode(0);
    }

    public function test_does_not_auto_correct(): void
    {
        $before = \Illuminate\Support\Facades\DB::table('inventory_stock_levels')->count();
        app(InventoryIntegrityAuditService::class)->audit();
        $this->assertEquals($before, \Illuminate\Support\Facades\DB::table('inventory_stock_levels')->count());
    }
}
