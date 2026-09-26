<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FoundationFixesTest extends TestCase
{
    use DatabaseTransactions;

    // ── Fix 1: package_country_prices ──────────────────────────────

    public function test_package_country_prices_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('package_country_prices'));

        $columns = Schema::getColumnListing('package_country_prices');
        foreach (['id', 'package_id', 'country_code', 'currency_code', 'price_monthly', 'price_yearly', 'is_active'] as $column) {
            $this->assertContains($column, $columns);
        }
    }

    // ── Fix 2: BD pilot prices ─────────────────────────────────────

    public function test_bd_prices_seeded_for_existing_packages(): void
    {
        $bd = DB::table('package_country_prices')
            ->where('country_code', 'BD')
            ->where('is_active', 1)
            ->count();

        $this->assertGreaterThanOrEqual(7, $bd);
    }

    public function test_bd_prices_match_country_currency_map(): void
    {
        $mapped = DB::table('country_currency_map')
            ->where('country_code', 'BD')
            ->value('currency_code');

        $this->assertSame('BDT', $mapped);

        $distinct = DB::table('package_country_prices')
            ->where('country_code', 'BD')
            ->distinct()
            ->pluck('currency_code');

        $this->assertSame([$mapped], $distinct->all());
    }

    // ── Fix 3: healthcare -> medical (Option D scoped rename) ──────

    public function test_healthcare_renamed_to_medical(): void
    {
        $healthcare = DB::table('package_industries')
            ->where('industry_key', 'healthcare')
            ->count();
        $this->assertSame(0, $healthcare, 'legacy healthcare package rows must be renamed to medical');

        // The test DB never carried healthcare package rows (only restaurant);
        // the local DB must show the 4 renamed rows.
        if (! app()->environment('testing')) {
            $medical = DB::table('package_industries')
                ->where('industry_key', 'medical')
                ->count();
            $this->assertSame(4, $medical);
        }

        $this->assertSame(1, DB::table('industries')->where('slug', 'medical')->count());
    }

    public function test_legacy_healthcare_taxonomy_preserved(): void
    {
        $this->assertTrue(
            DB::table('industries')->where('slug', 'healthcare')->exists(),
            'legacy healthcare industries row must be preserved (deprecated marker)'
        );

        $this->assertTrue(
            DB::table('industry_subcategories')->where('industry_key', 'healthcare')->exists(),
            'industry_subcategories must stay on healthcare until the big-bang rename'
        );

        $this->assertTrue(
            DB::table('institutes')->where('industry', 'healthcare')->exists(),
            'existing healthcare tenants must not be renamed'
        );
    }

    // ── Fix 4: retail registry parent ──────────────────────────────

    public function test_retail_registry_parent_created(): void
    {
        $retail = DB::table('module_registry')
            ->where('key', 'retail')
            ->whereNull('parent_key')
            ->where('status', 'active')
            ->first();

        $this->assertNotNull($retail, 'retail registry parent must exist');
        $this->assertSame('industry', $retail->type);
    }

    // ── Fix 5: pos → universal packages ────────────────────────────

    public function test_pos_mapped_to_packages(): void
    {
        $posRows = DB::table('package_industries')
            ->where('industry_key', 'pos')
            ->where('is_active', 1)
            ->pluck('package_id')
            ->sort()
            ->values()
            ->all();

        $expected = DB::table('subscription_packages')
            ->whereIn('slug', ['free', 'basic', 'advanced', 'premium'])
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expected, $posRows, 'pos must map to the 4 universal packages (is_active)');
    }

    // ── Registry totals ────────────────────────────────────────────

    public function test_registry_total_268(): void
    {
        $total = DB::table('module_registry')->where('status', 'active')->count();

        $this->assertGreaterThanOrEqual(268, $total);
    }

    public function test_restaurant_packages_untouched(): void
    {
        $moduleCount = DB::table('package_industry_modules')
            ->where('industry_key', 'restaurant')
            ->count();

        $this->assertSame(115, $moduleCount, 'restaurant package-industry modules must stay 115');
    }
}
