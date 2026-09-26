<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Restaurant package tiers (Phase 2 - restaurant becomes a main industry).
 *
 * Three restaurant-only packages are created and wired into the per-industry
 * package configuration:
 *
 *   subscription_packages          -> the tier itself
 *   package_industries             -> tier is offered for industry_key 'restaurant'
 *   package_modules                -> legacy package -> module list
 *   package_industry_modules       -> package + industry explicit module list
 *
 * Idempotent: packages are upserted on slug, module rows are rebuilt for these
 * three packages only (other industries/packages are never touched).
 */
class PackageSeeder extends Seeder
{
    protected string $industryKey = 'restaurant';

    /**
     * Tier module lists are additive (resolveEnabled unions them with the
     * core + industry default layers), so a tier only ever adds modules.
     *
     * @var array<int, array{name: string, slug: string, price_monthly: float, price_yearly: float, modules: array<int, string>}>
     */
    protected array $packages = [
        [
            'name' => 'RESTAURANT STARTER',
            'slug' => 'restaurant_starter',
            'price_monthly' => 1500,
            'price_yearly' => 15000,
            'modules' => [
                'restaurant',
                'restaurant.menu',
                'restaurant.menu_category',
                'restaurant.menu_item',
                'restaurant.table',
                'restaurant.table_layout',
                'restaurant.reservation',
                'restaurant.dine_in',
                'restaurant.takeaway',
                'restaurant.order',
                'restaurant.kitchen',
                'restaurant.kot',
                'restaurant.customer',
                'restaurant.loyalty',
            ],
        ],
        [
            'name' => 'RESTAURANT GROWTH',
            'slug' => 'restaurant_growth',
            'price_monthly' => 4000,
            'price_yearly' => 40000,
            'modules' => [
                'restaurant',
                'restaurant.menu',
                'restaurant.menu_category',
                'restaurant.menu_item',
                'restaurant.table',
                'restaurant.table_layout',
                'restaurant.reservation',
                'restaurant.dine_in',
                'restaurant.takeaway',
                'restaurant.delivery',
                'restaurant.order',
                'restaurant.order_tracking',
                'restaurant.pre_order',
                'restaurant.kitchen',
                'restaurant.kds',
                'restaurant.kot',
                'restaurant.chef',
                'restaurant.customer',
                'restaurant.loyalty',
                'restaurant.feedback',
                'restaurant.membership',
                'restaurant.preference',
                'pos.split_payment',
                'pos.cash_drawer',
                'pos.loyalty',
                'pos.discount',
                'pos.coupon',
                'pos.gift_card',
                'pos.return',
                'pos.refund',
                'pos.exchange',
            ],
        ],
        [
            'name' => 'RESTAURANT ENTERPRISE',
            'slug' => 'restaurant_enterprise',
            'price_monthly' => 9000,
            'price_yearly' => 90000,
            'modules' => [
                'restaurant',
                'restaurant.menu',
                'restaurant.menu_category',
                'restaurant.menu_item',
                'restaurant.table',
                'restaurant.table_layout',
                'restaurant.reservation',
                'restaurant.dine_in',
                'restaurant.takeaway',
                'restaurant.delivery',
                'restaurant.order',
                'restaurant.order_tracking',
                'restaurant.pre_order',
                'restaurant.kitchen',
                'restaurant.kds',
                'restaurant.kot',
                'restaurant.chef',
                'restaurant.station',
                'restaurant.recipe',
                'restaurant.customer',
                'restaurant.loyalty',
                'restaurant.feedback',
                'restaurant.membership',
                'restaurant.birthday_offer',
                'restaurant.preference',
                'pos.split_payment',
                'pos.cash_drawer',
                'pos.loyalty',
                'pos.discount',
                'pos.coupon',
                'pos.gift_card',
                'pos.return',
                'pos.refund',
                'pos.exchange',
                'pos.item_report',
                'pos.cashier_report',
                'pos.inventory_integration',
                'pos.sales_integration',
                'pos.finance_integration',
                'pos.accounting_integration',
                'pos.crm_integration',
                'inventory',
                'crm',
                'reports',
                'reports.sales',
            ],
        ],
    ];

    public function run(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('subscription_packages')) {
            $this->command?->error('subscription_packages table missing - run migrations first.');

            return;
        }

        $industryExists = DB::table('industries')
            ->where('slug', $this->industryKey)
            ->where('status', 'active')
            ->exists();

        if (! $industryExists) {
            $this->command?->error("Industry '{$this->industryKey}' not active - run IndustryTaxonomySeeder first.");

            return;
        }

        $knownModules = DB::table('module_registry')
            ->where('status', 'active')
            ->pluck('key')
            ->all();

        $position = 0;

        foreach ($this->packages as $tier) {
            DB::table('subscription_packages')->updateOrInsert(
                ['slug' => $tier['slug']],
                [
                    'name' => $tier['name'],
                    'price_monthly' => $tier['price_monthly'],
                    'price_yearly' => $tier['price_yearly'],
                    'status' => 'active',
                    'is_default' => false,
                    'updated_at' => now(),
                ]
            );

            $packageId = DB::table('subscription_packages')
                ->where('slug', $tier['slug'])
                ->value('id');

            if (! $packageId) {
                $this->command?->error("Could not resolve package {$tier['slug']}.");

                continue;
            }

            // Tier is offered for the restaurant industry only.
            DB::table('package_industries')->updateOrInsert(
                ['package_id' => $packageId, 'industry_key' => $this->industryKey],
                [
                    'is_active' => true,
                    'sort_order' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $moduleKeys = [];
            foreach ($tier['modules'] as $moduleKey) {
                if (in_array($moduleKey, $knownModules, true)) {
                    $moduleKeys[] = $moduleKey;
                } else {
                    $this->command?->warn("Skipping unknown module for {$tier['slug']}: {$moduleKey}");
                }
            }

            $moduleKeys = array_values(array_unique($moduleKeys));

            // Rebuild this package's module rows so the tier is deterministic.
            DB::table('package_modules')->where('package_id', $packageId)->delete();
            DB::table('package_industry_modules')
                ->where('package_id', $packageId)
                ->where('industry_key', $this->industryKey)
                ->delete();

            foreach ($moduleKeys as $moduleKey) {
                DB::table('package_modules')->insert([
                    'package_id' => $packageId,
                    'module_key' => $moduleKey,
                    'enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('package_industry_modules')->insert([
                    'package_id' => $packageId,
                    'industry_key' => $this->industryKey,
                    'module_key' => $moduleKey,
                    'enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->command?->info("{$tier['slug']} (id {$packageId}): " . count($moduleKeys) . ' module(s).');

            $position++;
        }

        $mapped = DB::table('package_industries')
            ->where('industry_key', $this->industryKey)
            ->count();

        $this->command?->info("Restaurant package tiers seeded: {$mapped} package_industries row(s).");
    }
}
