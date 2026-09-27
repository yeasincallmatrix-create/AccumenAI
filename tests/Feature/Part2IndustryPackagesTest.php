<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use Database\Seeders\IndustryPackageModuleMap;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Part2IndustryPackagesTest extends TestCase
{
    private array $industries = [
        'real_estate', 'manufacturing', 'pos', 'medical', 'training_center', 'education',
    ];

    public function test_all_18_packages_exist()
    {
        $count = DB::table('subscription_packages')
            ->whereRaw("slug REGEXP '^(real_estate|manufacturing|pos|medical|training_center|education)_'")
            ->count();
        $this->assertEquals(18, $count);
    }

    public function test_each_industry_has_3_tiers()
    {
        foreach ($this->industries as $industry) {
            $count = DB::table('subscription_packages')
                ->where('slug', 'LIKE', "{$industry}_%")
                ->count();
            $this->assertEquals(3, $count, "Industry {$industry} should have 3 tiers");
        }
    }

    public function test_package_industries_mapping_exists()
    {
        foreach ($this->industries as $industry) {
            $count = DB::table('package_industries')
                ->where('industry_key', $industry)
                ->count();
            $this->assertGreaterThanOrEqual(3, $count);
        }
    }

    public function test_module_mappings_exist()
    {
        foreach ($this->industries as $industry) {
            $count = DB::table('package_industry_modules')
                ->where('industry_key', $industry)
                ->count();
            $this->assertGreaterThan(0, $count, "{$industry} should have module mappings");
        }
    }

    public function test_bd_prices_for_new_packages()
    {
        $count = DB::table('package_country_prices')
            ->where('country_code', 'BD')
            ->where('currency_code', 'BDT')
            ->whereIn('package_id', function ($q) {
                $q->select('id')->from('subscription_packages')
                    ->whereRaw("slug REGEXP '^(real_estate|manufacturing|pos|medical|training_center|education)_'");
            })
            ->count();
        $this->assertEquals(18, $count);
    }

    public function test_restaurant_unchanged()
    {
        $count = DB::table('package_industry_modules')
            ->whereIn('package_id', function ($q) {
                $q->select('id')->from('subscription_packages')
                    ->where('slug', 'LIKE', 'restaurant_%');
            })
            ->count();
        $this->assertEquals(115, $count);
    }

    public function test_free_basic_advanced_premium_unchanged()
    {
        $count = DB::table('subscription_packages')
            ->whereIn('slug', ['free', 'basic', 'advanced', 'premium'])
            ->count();
        $this->assertEquals(4, $count);
    }

    public function test_total_packages_is_28()
    {
        // Wave 2: +3 retail tiers (25 -> 28).
        $this->assertEquals(28, DB::table('subscription_packages')->count());
    }

    public function test_page_renders_all_industries()
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Part2',
            'last_name' => 'Packages',
            'email' => 'part2-packages-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin, 'platform_admin');

        foreach ($this->industries as $industry) {
            $page = $this->get('/admin/package-industries?industry=' . $industry);
            $page->assertOk();
        }
    }

    // ── Part 2 additions (confirmed findings B + registry safety) ──

    public function test_pos_industries_row_exists()
    {
        $row = DB::table('industries')->where('slug', 'pos')->first();
        $this->assertNotNull($row, 'industries.slug pos row must exist (finding B)');
        $this->assertSame('POS', $row->name);
        $this->assertSame('active', $row->status);
    }

    public function test_manufacturing_module_keys_valid()
    {
        foreach (IndustryPackageModuleMap::all() as $industry => $tiers) {
            foreach ($tiers as $tier => $modules) {
                foreach ($modules as $key) {
                    $exists = DB::table('module_registry')
                        ->where('key', $key)
                        ->where('status', 'active')
                        ->exists();
                    $this->assertTrue($exists, "Map key '{$key}' ({$industry}/{$tier}) must exist in module_registry");
                }
            }
        }
    }
}
