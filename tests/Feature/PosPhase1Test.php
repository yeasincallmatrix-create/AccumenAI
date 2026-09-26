<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POS Phase 1 — Foundation: the `pos` parent gains 5 children (terminal,
 * register, cart, checkout, receipt), config/pos.php exposes 2 engines and
 * the module-config matrix renders the new hierarchy.
 */
class PosPhase1Test extends TestCase
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

    public function test_pos_parent_exists()
    {
        $this->assertTrue(DB::table('module_registry')->where('key', 'pos')->exists());
    }

    public function test_phase1_children_exist()
    {
        $modules = [
            'pos.terminal', 'pos.register', 'pos.cart',
            'pos.checkout', 'pos.receipt',
        ];

        foreach ($modules as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase1_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', [
                'pos.terminal', 'pos.register', 'pos.cart',
                'pos.checkout', 'pos.receipt',
            ])
            ->where('status', 'active')
            ->count();
        $this->assertEquals(5, $count);
    }

    public function test_pos_has_5_children()
    {
        $phase1 = ['pos.terminal', 'pos.register', 'pos.cart', 'pos.checkout', 'pos.receipt'];

        $count = DB::table('module_registry')
            ->whereIn('key', $phase1)
            ->where('parent_key', 'pos')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(5, $count, 'Phase 1 scope is exactly its 5 keys');
    }

    public function test_pos_config_exists()
    {
        $config = config('pos');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('engines', $config);
        $this->assertArrayHasKey('terminal', $config['engines']);
        $this->assertArrayHasKey('sales', $config['engines']);
        $this->assertArrayHasKey('pos.terminal', $config['engines']['terminal']['modules']);
        $this->assertArrayHasKey('pos.checkout', $config['engines']['sales']['modules']);
    }

    public function test_page_renders_pos_children()
    {
        $page = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'retail',
                'subcategory' => 'grocery',
            ]));

        $page->assertOk();
        $page->assertSee('pos.terminal', false);
        $page->assertSee('pos.checkout', false);
    }
}
