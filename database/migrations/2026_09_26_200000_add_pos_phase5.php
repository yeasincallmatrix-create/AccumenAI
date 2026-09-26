<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('module_registry')->where('key', 'pos')->first();
        $parentType = $parent->type ?? 'core';
        $parentIsCore = $parent->is_core ?? 0;

        $children = [
            ['key' => 'pos.inventory_integration', 'name' => 'Inventory Integration', 'icon' => 'bi-box', 'sort_order' => 70],
            ['key' => 'pos.sales_integration', 'name' => 'Sales Integration', 'icon' => 'bi-cart', 'sort_order' => 71],
            ['key' => 'pos.finance_integration', 'name' => 'Finance Integration', 'icon' => 'bi-cash', 'sort_order' => 72],
            ['key' => 'pos.accounting_integration', 'name' => 'Accounting Integration', 'icon' => 'bi-journal-text', 'sort_order' => 73],
            ['key' => 'pos.crm_integration', 'name' => 'CRM Integration', 'icon' => 'bi-person-lines-fill', 'sort_order' => 74],
        ];

        $inserted = 0;
        foreach ($children as $child) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $child['key']],
                [
                    'name' => $child['name'],
                    'parent_key' => 'pos',
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

        echo "Inserted {$inserted} POS phase 5 modules.\n";
    }

    public function down(): void
    {
        DB::table('module_registry')->whereIn('key', [
            'pos.inventory_integration', 'pos.sales_integration',
            'pos.finance_integration', 'pos.accounting_integration',
            'pos.crm_integration',
        ])->delete();
    }
};
