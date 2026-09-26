<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POS Phase 5 (final) — Integrations + Custom: 5 more children under `pos`
 * (inventory/sales/finance/accounting/crm integrations), config/pos.php grows
 * to 9 engines, custom-module support keys are on, and the module-config
 * matrix renders all 27 children.
 */
class PosPhase5Test extends TestCase
{
    use DatabaseTransactions;

    private const PHASE5_KEYS = [
        'pos.inventory_integration', 'pos.sales_integration',
        'pos.finance_integration', 'pos.accounting_integration',
        'pos.crm_integration',
    ];

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

    public function test_phase5_integrations_exist()
    {
        foreach (self::PHASE5_KEYS as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase5_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', self::PHASE5_KEYS)
            ->where('status', 'active')
            ->count();
        $this->assertEquals(5, $count);
    }

    public function test_pos_has_27_children()
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'pos')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(27, $count);
    }

    public function test_pos_config_has_integrations_engine()
    {
        $config = config('pos');
        $this->assertArrayHasKey('integrations', $config['engines']);
        $this->assertArrayHasKey('pos.crm_integration', $config['engines']['integrations']['modules']);
        $this->assertCount(5, $config['engines']['integrations']['modules']);
    }

    public function test_custom_module_support_enabled()
    {
        $config = config('pos');
        $this->assertTrue($config['allow_custom_modules']);
        $this->assertEquals('pos.custom.', $config['custom_module_prefix']);
        $this->assertEquals(['payment_method', 'report', 'workflow', 'other'], $config['custom_module_types']);
    }

    public function test_all_phases_unchanged()
    {
        $phase1 = ['pos.terminal', 'pos.register', 'pos.cart', 'pos.checkout', 'pos.receipt'];
        $phase2 = ['pos.cash', 'pos.card', 'pos.mobile_payment', 'pos.split_payment', 'pos.shift', 'pos.cash_drawer'];
        $phase3 = ['pos.customer', 'pos.loyalty', 'pos.discount', 'pos.coupon', 'pos.gift_card'];
        $phase4 = ['pos.return', 'pos.refund', 'pos.exchange', 'pos.daily_report', 'pos.item_report', 'pos.cashier_report'];

        foreach (array_merge($phase1, $phase2, $phase3, $phase4) as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Previous phase module must stay: {$key}"
            );
        }

        $this->assertSame(5, DB::table('module_registry')->whereIn('key', $phase1)->where('parent_key', 'pos')->count(), 'Phase 1 scope unchanged');
        $this->assertSame(6, DB::table('module_registry')->whereIn('key', $phase2)->where('parent_key', 'pos')->count(), 'Phase 2 scope unchanged');
        $this->assertSame(5, DB::table('module_registry')->whereIn('key', $phase3)->where('parent_key', 'pos')->count(), 'Phase 3 scope unchanged');
        $this->assertSame(6, DB::table('module_registry')->whereIn('key', $phase4)->where('parent_key', 'pos')->count(), 'Phase 4 scope unchanged');
    }

    public function test_page_renders_phase5_children()
    {
        $page = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'retail',
                'subcategory' => 'grocery',
            ]));

        $page->assertOk();
        $page->assertSee('pos.inventory_integration', false);
        $page->assertSee('pos.crm_integration', false);
    }
}
