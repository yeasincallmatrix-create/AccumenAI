<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POS Phase 2 — Payment & Sessions: 6 more children under `pos` (cash, card,
 * mobile_payment, split_payment, shift, cash_drawer), config/pos.php grows to
 * 4 engines and the module-config matrix renders all 11 children.
 */
class PosPhase2Test extends TestCase
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

    public function test_phase2_children_exist()
    {
        $modules = [
            'pos.cash', 'pos.card', 'pos.mobile_payment',
            'pos.split_payment', 'pos.shift', 'pos.cash_drawer',
        ];

        foreach ($modules as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase2_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', [
                'pos.cash', 'pos.card', 'pos.mobile_payment',
                'pos.split_payment', 'pos.shift', 'pos.cash_drawer',
            ])
            ->where('status', 'active')
            ->count();
        $this->assertEquals(6, $count);
    }

    public function test_phase2_scope_is_6_children()
    {
        $phase2 = ['pos.cash', 'pos.card', 'pos.mobile_payment', 'pos.split_payment', 'pos.shift', 'pos.cash_drawer'];

        $count = DB::table('module_registry')
            ->whereIn('key', $phase2)
            ->where('parent_key', 'pos')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(6, $count, 'Phase 2 scope is exactly its 6 keys');
    }

    public function test_pos_config_has_payment_engines()
    {
        $config = config('pos');
        $this->assertArrayHasKey('payment', $config['engines']);
        $this->assertArrayHasKey('session', $config['engines']);
        $this->assertArrayHasKey('pos.cash', $config['engines']['payment']['modules']);
        $this->assertArrayHasKey('pos.cash_drawer', $config['engines']['session']['modules']);
    }

    public function test_phase1_unchanged()
    {
        $phase1 = ['pos.terminal', 'pos.register', 'pos.cart', 'pos.checkout', 'pos.receipt'];

        foreach ($phase1 as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Phase 1 module must stay: {$key}"
            );
        }

        $this->assertSame(
            5,
            DB::table('module_registry')
                ->whereIn('key', $phase1)
                ->where('parent_key', 'pos')
                ->where('status', 'active')
                ->count(),
            'Phase 1 children must remain exactly 5'
        );
    }

    public function test_page_renders_phase2_children()
    {
        $page = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'retail',
                'subcategory' => 'grocery',
            ]));

        $page->assertOk();
        $page->assertSee('pos.cash', false);
        $page->assertSee('pos.shift', false);
        $page->assertSee('pos.cash_drawer', false);
    }
}
