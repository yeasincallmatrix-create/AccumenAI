<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POS Phase 3 — Customer & Promotions: 5 more children under `pos` (customer,
 * loyalty, discount, coupon, gift_card), config/pos.php grows to 6 engines and
 * the module-config matrix renders all 16 children.
 */
class PosPhase3Test extends TestCase
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

    public function test_phase3_children_exist()
    {
        $modules = [
            'pos.customer', 'pos.loyalty', 'pos.discount',
            'pos.coupon', 'pos.gift_card',
        ];

        foreach ($modules as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase3_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', [
                'pos.customer', 'pos.loyalty', 'pos.discount',
                'pos.coupon', 'pos.gift_card',
            ])
            ->where('status', 'active')
            ->count();
        $this->assertEquals(5, $count);
    }

    public function test_pos_has_16_children()
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'pos')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(16, $count);
    }

    public function test_pos_config_has_customer_promotion_engines()
    {
        $config = config('pos');
        $this->assertArrayHasKey('customer', $config['engines']);
        $this->assertArrayHasKey('promotions', $config['engines']);
        $this->assertArrayHasKey('pos.customer', $config['engines']['customer']['modules']);
        $this->assertArrayHasKey('pos.gift_card', $config['engines']['promotions']['modules']);
    }

    public function test_previous_phases_unchanged()
    {
        $phase1 = ['pos.terminal', 'pos.register', 'pos.cart', 'pos.checkout', 'pos.receipt'];
        $phase2 = ['pos.cash', 'pos.card', 'pos.mobile_payment', 'pos.split_payment', 'pos.shift', 'pos.cash_drawer'];

        foreach (array_merge($phase1, $phase2) as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Previous phase module must stay: {$key}"
            );
        }

        $this->assertSame(
            5,
            DB::table('module_registry')->whereIn('key', $phase1)->where('parent_key', 'pos')->count(),
            'Phase 1 scope unchanged'
        );
        $this->assertSame(
            6,
            DB::table('module_registry')->whereIn('key', $phase2)->where('parent_key', 'pos')->count(),
            'Phase 2 scope unchanged'
        );
    }

    public function test_page_renders_phase3_children()
    {
        $page = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'retail',
                'subcategory' => 'grocery',
            ]));

        $page->assertOk();
        $page->assertSee('pos.customer', false);
        $page->assertSee('pos.gift_card', false);
    }
}
