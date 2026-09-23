<?php

namespace Tests\Feature;

use Database\Seeders\PackageSubModuleMappingSeeder;
use Database\Seeders\PurchaseSubModuleSeeder;
use Database\Seeders\SalesPurchaseFeatureSeeder;
use Database\Seeders\SalesSubModuleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesPurchaseSubModuleInfrastructureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        (new SalesSubModuleSeeder)->run();
        (new PurchaseSubModuleSeeder)->run();
        (new PackageSubModuleMappingSeeder)->run();
        if (Schema::hasTable('feature_registry')) {
            (new SalesPurchaseFeatureSeeder)->run();
        }
    }

    public function test_sales_parent_exists(): void
    {
        $row = DB::table('module_registry')->where('key', 'sales')->first();
        $this->assertNotNull($row);
        $this->assertEquals('active', $row->status);
        $this->assertNull($row->parent_key);
    }

    public function test_purchase_parent_exists(): void
    {
        $row = DB::table('module_registry')->where('key', 'purchase')->first();
        $this->assertNotNull($row);
        $this->assertEquals('active', $row->status);
        $this->assertNull($row->parent_key);
    }

    public function test_7_sales_sub_modules_seeded(): void
    {
        $expected = [
            'sales.quotations',
            'sales.orders',
            'sales.deliveries',
            'sales.returns',
            'sales.leads',
            'sales.customers',
            'sales.reports',
        ];

        $keys = DB::table('module_registry')
            ->where('parent_key', 'sales')
            ->pluck('key')
            ->toArray();

        foreach ($expected as $key) {
            $this->assertContains($key, $keys, "Sub-module {$key} must be registered");
        }

        $this->assertCount(7, $keys);
    }

    public function test_6_purchase_sub_modules_seeded(): void
    {
        $expected = [
            'purchase.quotations',
            'purchase.orders',
            'purchase.invoices',
            'purchase.requests',
            'purchase.returns',
            'purchase.receipts',
        ];

        $keys = DB::table('module_registry')
            ->where('parent_key', 'purchase')
            ->pluck('key')
            ->toArray();

        foreach ($expected as $key) {
            $this->assertContains($key, $keys, "Sub-module {$key} must be registered");
        }

        $this->assertCount(6, $keys);
    }

    public function test_sub_modules_are_active_with_icons_and_routes(): void
    {
        $rows = DB::table('module_registry')
            ->whereIn('parent_key', ['sales', 'purchase'])
            ->get();

        $this->assertCount(13, $rows);

        foreach ($rows as $row) {
            $this->assertEquals('active', $row->status, "{$row->key} must be active");
            $this->assertFalse((bool) $row->coming_soon, "{$row->key} must not be coming_soon");
            $this->assertNotEmpty($row->icon, "{$row->key} must have an icon");
            $this->assertNotEmpty($row->index_route, "{$row->key} must have an index_route");
            $this->assertTrue(app('router')->has($row->index_route), "{$row->index_route} must exist");
        }
    }

    public function test_sales_mapped_to_advanced_and_premium_only(): void
    {
        if (! Schema::hasTable('package_modules')) {
            $this->markTestSkipped('package_modules missing');
        }

        foreach (['advanced', 'premium'] as $slug) {
            $pkg = DB::table('subscription_packages')->where('slug', $slug)->first();
            if (! $pkg) {
                $this->markTestSkipped("package {$slug} not seeded");
            }

            $count = DB::table('package_modules')
                ->where('package_id', $pkg->id)
                ->where('module_key', 'like', 'sales%')
                ->where('enabled', 1)
                ->count();

            $this->assertSame(8, $count, "{$slug} should have sales parent + 7 sub-modules");
        }

        $free = DB::table('subscription_packages')->where('slug', 'free')->first();
        if ($free) {
            $this->assertSame(0, DB::table('package_modules')
                ->where('package_id', $free->id)
                ->where('module_key', 'like', 'sales%')
                ->count(), 'FREE must not include sales sub-modules');
        }
    }

    public function test_purchase_mapped_to_premium_only(): void
    {
        if (! Schema::hasTable('package_modules')) {
            $this->markTestSkipped('package_modules missing');
        }

        $premium = DB::table('subscription_packages')->where('slug', 'premium')->first();
        if ($premium) {
            $count = DB::table('package_modules')
                ->where('package_id', $premium->id)
                ->where('module_key', 'like', 'purchase%')
                ->where('enabled', 1)
                ->count();
            $this->assertSame(7, $count, 'PREMIUM should have purchase parent + 6 sub-modules');
        }

        foreach (['free', 'basic', 'advanced'] as $slug) {
            $pkg = DB::table('subscription_packages')->where('slug', $slug)->first();
            if (! $pkg) {
                continue;
            }

            $parent = DB::table('package_modules')
                ->where('package_id', $pkg->id)
                ->where('module_key', 'purchase')
                ->count();

            $this->assertSame(0, $parent, "{$slug} must not include purchase parent");
        }
    }

    public function test_feature_registry_entries_active(): void
    {
        if (! Schema::hasTable('feature_registry')) {
            $this->markTestSkipped('feature_registry missing');
        }

        $rows = DB::table('feature_registry')
            ->where(function ($q) {
                $q->where('feature_key', 'like', 'sales.%')
                    ->orWhere('feature_key', 'like', 'purchase.%');
            })
            ->get();

        $this->assertCount(13, $rows);

        foreach ($rows as $row) {
            $this->assertEquals('active', $row->status, "{$row->feature_key} must be active");
            $this->assertContains($row->module_key, ['sales', 'purchase']);
        }
    }

    public function test_seeders_idempotent(): void
    {
        $salesBefore = DB::table('module_registry')->where('parent_key', 'sales')->count();
        $purchaseBefore = DB::table('module_registry')->where('parent_key', 'purchase')->count();

        (new SalesSubModuleSeeder)->run();
        (new PurchaseSubModuleSeeder)->run();
        (new PackageSubModuleMappingSeeder)->run();

        $this->assertSame(7, DB::table('module_registry')->where('parent_key', 'sales')->count());
        $this->assertSame(6, DB::table('module_registry')->where('parent_key', 'purchase')->count());
        $this->assertSame($salesBefore, DB::table('module_registry')->where('parent_key', 'sales')->count());
        $this->assertSame($purchaseBefore, DB::table('module_registry')->where('parent_key', 'purchase')->count());
    }
}
