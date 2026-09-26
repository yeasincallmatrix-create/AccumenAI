<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 6 (final) — Restaurant Integrations: 5 more children under the
     * existing `restaurant` industry (pos_integration, sales_integration,
     * purchase_integration, finance_integration, accounting_integration).
     * Children inherit type + is_core from the parent row and the whole
     * block is updateOrInsert, so a re-run is a no-op. Phases 1-5 rows are
     * never touched.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        $parent = DB::table('module_registry')->where('key', 'restaurant')->first();
        if (! $parent) {
            throw new \RuntimeException('Restaurant parent missing');
        }

        $parentType = $parent->type ?? 'industry';
        $parentIsCore = $parent->is_core ?? 0;

        $children = [
            ['key' => 'restaurant.pos_integration', 'name' => 'POS Integration', 'icon' => 'bi-display', 'sort_order' => 60],
            ['key' => 'restaurant.sales_integration', 'name' => 'Sales Integration', 'icon' => 'bi-cart', 'sort_order' => 61],
            ['key' => 'restaurant.purchase_integration', 'name' => 'Purchase Integration', 'icon' => 'bi-bag', 'sort_order' => 62],
            ['key' => 'restaurant.finance_integration', 'name' => 'Finance Integration', 'icon' => 'bi-cash', 'sort_order' => 63],
            ['key' => 'restaurant.accounting_integration', 'name' => 'Accounting Integration', 'icon' => 'bi-journal-text', 'sort_order' => 64],
        ];

        $inserted = 0;
        foreach ($children as $child) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $child['key']],
                [
                    'name' => $child['name'],
                    'parent_key' => 'restaurant',
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

        echo "Inserted {$inserted} restaurant phase 6 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'restaurant.pos_integration', 'restaurant.sales_integration',
            'restaurant.purchase_integration', 'restaurant.finance_integration',
            'restaurant.accounting_integration',
        ])->delete();
    }
};
