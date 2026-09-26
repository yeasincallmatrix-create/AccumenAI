<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POS Phase 4 — Returns & Reports: 6 more children under `pos` (return,
 * refund, exchange, daily_report, item_report, cashier_report), config/pos.php
 * grows to 8 engines and the module-config matrix renders all 22 children.
 */
class PosPhase4Test extends TestCase
{
    use DatabaseTransactions;

    private function platformAdmin(): PlatformAdmin
    {
        TenantContext::clear();

        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'platform-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_phase4_children_exist()
    {
        $modules = [
            'pos.return', 'pos.refund', 'pos.exchange',
            'pos.daily_report', 'pos.item_report', 'pos.cashier_report',
        ];

        foreach ($modules as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase4_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', [
                'pos.return', 'pos.refund', 'pos.exchange',
                'pos.daily_report', 'pos.item_report', 'pos.cashier_report',
            ])
            ->where('status', 'active')
            ->count();
        $this->assertEquals(6, $count);
    }

    public function test_pos_has_22_children()
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'pos')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(22, $count);
    }

    public function test_pos_config_has_returns_reports_engines()
    {
        $config = config('pos');
        $this->assertArrayHasKey('returns', $config['engines']);
        $this->assertArrayHasKey('reports', $config['engines']);
        $this->assertArrayHasKey('pos.return', $config['engines']['returns']['modules']);
        $this->assertArrayHasKey('pos.cashier_report', $config['engines']['reports']['modules']);
    }

    public function test_previous_phases_unchanged()
    {
        $phase1 = ['pos.terminal', 'pos.register', 'pos.cart', 'pos.checkout', 'pos.receipt'];
        $phase2 = ['pos.cash', 'pos.card', 'pos.mobile_payment', 'pos.split_payment', 'pos.shift', 'pos.cash_drawer'];
        $phase3 = ['pos.customer', 'pos.loyalty', 'pos.discount', 'pos.coupon', 'pos.gift_card'];

        foreach (array_merge($phase1, $phase2, $phase3) as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Previous phase module must stay: {$key}"
            );
        }

        $this->assertSame(5, DB::table('module_registry')->whereIn('key', $phase1)->where('parent_key', 'pos')->count(), 'Phase 1 scope unchanged');
        $this->assertSame(6, DB::table('module_registry')->whereIn('key', $phase2)->where('parent_key', 'pos')->count(), 'Phase 2 scope unchanged');
        $this->assertSame(5, DB::table('module_registry')->whereIn('key', $phase3)->where('parent_key', 'pos')->count(), 'Phase 3 scope unchanged');
    }

    public function test_page_renders_phase4_children()
    {
        $page = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'retail',
                'subcategory' => 'grocery',
            ]));

        $page->assertOk();
        $page->assertSee('pos.return', false);
        $page->assertSee('pos.daily_report', false);
        $page->assertSee('pos.cashier_report', false);
    }
}
