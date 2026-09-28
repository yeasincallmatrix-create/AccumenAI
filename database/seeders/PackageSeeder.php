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
                'restaurant.delivery_zone',
                'restaurant.online_order',
                'restaurant.pos_integration',
                'restaurant.sales_integration',
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
                'restaurant.delivery_zone',
                'restaurant.delivery_rider',
                'restaurant.online_order',
                'restaurant.qr_order',
                'restaurant.tracking',
                'restaurant.pos_integration',
                'restaurant.sales_integration',
                'restaurant.purchase_integration',
                'restaurant.finance_integration',
                'restaurant.accounting_integration',
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
                'restaurant.delivery_zone',
                'restaurant.delivery_rider',
                'restaurant.online_order',
                'restaurant.qr_order',
                'restaurant.kiosk',
                'restaurant.tracking',
                'restaurant.pos_integration',
                'restaurant.sales_integration',
                'restaurant.purchase_integration',
                'restaurant.finance_integration',
                'restaurant.accounting_integration',
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

    /**
     * Part 2 - 6 industries x 3 tiers (Restaurant pattern).
     *
     * @var array<int, array{industry: string, label: string, slug_prefix: string}>
     */
    private function industryPackages(): array
    {
        return [
            ['industry' => 'real_estate', 'label' => 'Real Estate', 'slug_prefix' => 'real_estate'],
            ['industry' => 'manufacturing', 'label' => 'Manufacturing', 'slug_prefix' => 'manufacturing'],
            ['industry' => 'healthcare', 'label' => 'Medical', 'slug_prefix' => 'medical'],
            ['industry' => 'training_center', 'label' => 'Training Center', 'slug_prefix' => 'training_center'],
            ['industry' => 'education', 'label' => 'Education', 'slug_prefix' => 'education'],
            ['industry' => 'retail', 'label' => 'Retail', 'slug_prefix' => 'retail'],
        ];
    }

    /**
     * @var array<string, array{label: string, sort: int, price_m: int, price_y: int}>
     */
    private function tierMeta(): array
    {
        return [
            'starter' => ['label' => 'Starter', 'sort' => 0, 'price_m' => 1500, 'price_y' => 15000],
            'growth' => ['label' => 'Growth', 'sort' => 1, 'price_m' => 4000, 'price_y' => 40000],
            'enterprise' => ['label' => 'Enterprise', 'sort' => 2, 'price_m' => 9000, 'price_y' => 90000],
        ];
    }

    /**
     * Part 2 - industry-specific packages (6 industries x 3 tiers).
     *
     * Scoped package_industry_modules only (legacy package_modules is not
     * written for these packages - the scoped layer wins for matching
     * industries). Idempotent: packages upsert on slug, module rows rebuilt
     * per package, country prices upsert on (package, country).
     */
    private function seedIndustryPackages(): void
    {
        $map = IndustryPackageModuleMap::all();
        $bdCurrency = DB::table('country_currency_map')
            ->where('country_code', 'BD')
            ->value('currency_code') ?? 'BDT';

        foreach ($this->industryPackages() as $ind) {
            $modulesByTier = $map[$ind['industry']] ?? [];

            if (empty($modulesByTier)) {
                $this->command?->warn("SKIP {$ind['industry']}: no module map.");

                continue;
            }

            foreach ($this->tierMeta() as $tier => $meta) {
                $slug = $ind['slug_prefix'] . '_' . $tier;

                DB::table('subscription_packages')->updateOrInsert(
                    ['slug' => $slug],
                    [
                        'name' => $ind['label'] . ' ' . $meta['label'],
                        'price_monthly' => $meta['price_m'],
                        'price_yearly' => $meta['price_y'],
                        'is_default' => 0,
                        'status' => 'active',
                        'updated_at' => now(),
                    ]
                );

                $pkgId = DB::table('subscription_packages')
                    ->where('slug', $slug)
                    ->value('id');

                if (! $pkgId) {
                    $this->command?->error("Could not resolve package {$slug}.");

                    continue;
                }

                DB::table('package_industries')->updateOrInsert(
                    ['package_id' => $pkgId, 'industry_key' => $ind['industry']],
                    [
                        'is_active' => true,
                        'sort_order' => $meta['sort'],
                        'updated_at' => now(),
                    ]
                );

                $modules = IndustryPackageModuleMap::filterExisting($modulesByTier[$tier] ?? []);

                $skipped = count($modulesByTier[$tier] ?? []) - count($modules);
                if ($skipped > 0) {
                    $this->command?->warn("{$slug}: skipped {$skipped} unknown module key(s).");
                }

                DB::table('package_industry_modules')
                    ->where('package_id', $pkgId)
                    ->delete();

                foreach ($modules as $moduleKey) {
                    DB::table('package_industry_modules')->insert([
                        'package_id' => $pkgId,
                        'industry_key' => $ind['industry'],
                        'module_key' => $moduleKey,
                        'enabled' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('package_country_prices')->updateOrInsert(
                    ['package_id' => $pkgId, 'country_code' => 'BD'],
                    [
                        'currency_code' => $bdCurrency,
                        'price_monthly' => $meta['price_m'],
                        'price_yearly' => $meta['price_y'],
                        'is_active' => true,
                        'updated_at' => now(),
                    ]
                );

                $this->command?->info("Seeded {$slug}: " . count($modules) . ' modules, BD/' . $bdCurrency . '.');
            }
        }
    }

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

        // Part 2 - 6 industries x 3 tiers.
        $this->seedIndustryPackages();
    }
}
