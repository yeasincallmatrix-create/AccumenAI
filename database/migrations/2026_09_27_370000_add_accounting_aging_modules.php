<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounting Phase A — 5 aging report modules under the `accounting`
     * parent, plus activation backfill mirroring the existing accounting
     * children (package_industry_modules + subcategory_default_modules).
     *
     * Registry-only children are inert: resolveEnabled() never sees them
     * unless they also exist in one of those two activation tables (the
     * same two paths every existing accounting.* child uses).
     */
    private const AGING_KEYS = [
        'accounting.ar_aging',
        'accounting.ap_aging',
        'accounting.invoice_aging',
        'accounting.aging_summary',
        'accounting.aging_config',
    ];

    private const CHILDREN = [
        ['key' => 'accounting.ar_aging', 'name' => 'AR Aging Report', 'icon' => 'bi-arrow-down-circle', 'sort_order' => 100],
        ['key' => 'accounting.ap_aging', 'name' => 'AP Aging Report', 'icon' => 'bi-arrow-up-circle', 'sort_order' => 101],
        ['key' => 'accounting.invoice_aging', 'name' => 'Invoice Aging', 'icon' => 'bi-receipt', 'sort_order' => 102],
        ['key' => 'accounting.aging_summary', 'name' => 'Aging Summary Dashboard', 'icon' => 'bi-speedometer2', 'sort_order' => 103],
        ['key' => 'accounting.aging_config', 'name' => 'Aging Configuration', 'icon' => 'bi-sliders', 'sort_order' => 104],
    ];

    public function up(): void
    {
        $parent = DB::table('module_registry')->where('key', 'accounting')->first();
        if (! $parent) {
            throw new RuntimeException('Accounting parent missing');
        }

        $parentType = $parent->type ?? 'core';
        $parentIsCore = $parent->is_core ?? 1;

        $inserted = 0;
        foreach (self::CHILDREN as $child) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $child['key']],
                [
                    'name' => $child['name'],
                    'parent_key' => 'accounting',
                    'type' => $parentType,
                    'is_core' => $parentIsCore,
                    'description' => null,
                    'dependencies' => null,
                    'sort_order' => $child['sort_order'],
                    'icon' => $child['icon'],
                    'coming_soon' => false,
                    'index_route' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $inserted++;
        }

        $packageRows = $this->backfillPackageIndustryModules();
        $subcategoryRows = $this->backfillSubcategoryDefaults();

        echo "Inserted {$inserted} accounting aging modules "
            ."({$packageRows} package_industry_modules, {$subcategoryRows} subcategory_default_modules).\n";
    }

    /**
     * Mirror activation: every (package, industry) pair that already carries
     * accounting.* rows gets the 5 aging keys, inheriting `enabled` from the
     * accounting root row of that pair.
     */
    private function backfillPackageIndustryModules(): int
    {
        if (! Schema::hasTable('package_industry_modules')) {
            return 0;
        }

        $pairs = DB::table('package_industry_modules')
            ->where('module_key', 'like', 'accounting.%')
            ->groupBy('package_id', 'industry_key')
            ->select('package_id', 'industry_key')
            ->get();

        $count = 0;
        foreach ($pairs as $pair) {
            $enabled = DB::table('package_industry_modules')
                ->where('package_id', $pair->package_id)
                ->where('industry_key', $pair->industry_key)
                ->where('module_key', 'accounting')
                ->value('enabled');
            $enabled = $enabled === null ? 1 : (int) $enabled;

            foreach (self::AGING_KEYS as $key) {
                DB::table('package_industry_modules')->updateOrInsert(
                    [
                        'package_id' => $pair->package_id,
                        'industry_key' => $pair->industry_key,
                        'module_key' => $key,
                    ],
                    [
                        'enabled' => $enabled,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Mirror activation: every subcategory that already carries accounting.*
     * rows gets the 5 aging keys, inheriting `category` from the accounting
     * root row of that subcategory.
     */
    private function backfillSubcategoryDefaults(): int
    {
        if (! Schema::hasTable('subcategory_default_modules')) {
            return 0;
        }

        $subcategories = DB::table('subcategory_default_modules')
            ->where('module_key', 'like', 'accounting.%')
            ->groupBy('subcategory_id')
            ->select('subcategory_id')
            ->get();

        $count = 0;
        foreach ($subcategories as $subcategory) {
            $category = DB::table('subcategory_default_modules')
                ->where('subcategory_id', $subcategory->subcategory_id)
                ->where('module_key', 'accounting')
                ->value('category');
            $category = $category ?: 'default';

            foreach (self::AGING_KEYS as $key) {
                DB::table('subcategory_default_modules')->updateOrInsert(
                    [
                        'subcategory_id' => $subcategory->subcategory_id,
                        'module_key' => $key,
                    ],
                    [
                        'category' => $category,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    public function down(): void
    {
        DB::table('module_registry')->whereIn('key', self::AGING_KEYS)->delete();

        if (Schema::hasTable('package_industry_modules')) {
            DB::table('package_industry_modules')->whereIn('module_key', self::AGING_KEYS)->delete();
        }

        if (Schema::hasTable('subcategory_default_modules')) {
            DB::table('subcategory_default_modules')->whereIn('module_key', self::AGING_KEYS)->delete();
        }
    }
};
