<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 6 (FINAL) — Real Estate Integrations: 7 more children under
     * the existing `real_estate` parent (7 + 8 + 7 + 6 + 13 + 7 = 48 total).
     *
     * Idempotent: updateOrInsert keyed on `key`, so a re-run is a no-op.
     * Phase 1-5 rows are never touched.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        $parent = DB::table('module_registry')->where('key', 'real_estate')->first();

        $children = [
            ['key' => 'real_estate.sales_integration', 'name' => 'Sales Integration', 'icon' => 'bi-cart', 'sort_order' => 60],
            ['key' => 'real_estate.purchase_integration', 'name' => 'Purchase Integration', 'icon' => 'bi-bag', 'sort_order' => 61],
            ['key' => 'real_estate.finance_integration', 'name' => 'Finance Integration', 'icon' => 'bi-cash', 'sort_order' => 62],
            ['key' => 'real_estate.accounting_integration', 'name' => 'Accounting Integration', 'icon' => 'bi-journal-text', 'sort_order' => 63],
            ['key' => 'real_estate.hr_integration', 'name' => 'HR Integration', 'icon' => 'bi-people', 'sort_order' => 64],
            ['key' => 'real_estate.crm_integration', 'name' => 'CRM Integration', 'icon' => 'bi-person-lines-fill', 'sort_order' => 65],
            ['key' => 'real_estate.inventory_integration', 'name' => 'Inventory Integration', 'icon' => 'bi-box', 'sort_order' => 66],
        ];

        $inserted = 0;
        foreach ($children as $child) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $child['key']],
                [
                    'name' => $child['name'],
                    'parent_key' => 'real_estate',
                    'type' => $parent->type ?? 'industry',
                    'is_core' => (int) ($parent->is_core ?? 0),
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

        echo "Inserted {$inserted} real estate phase 6 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'real_estate.sales_integration', 'real_estate.purchase_integration',
            'real_estate.finance_integration', 'real_estate.accounting_integration',
            'real_estate.hr_integration', 'real_estate.crm_integration',
            'real_estate.inventory_integration',
        ])->delete();
    }
};
