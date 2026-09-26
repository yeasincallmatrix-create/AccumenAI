<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * POS Phase 2 — Payment & Sessions: 6 children under the existing `pos`
     * parent. Idempotent: updateOrInsert keyed on `key`, so a re-run is a
     * no-op. Children inherit the parent's type and is_core flag (registry
     * convention — see the module-children migration).
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        $parent = DB::table('module_registry')->where('key', 'pos')->first();
        $parentType = $parent->type ?? 'core';
        $parentIsCore = (int) ($parent->is_core ?? 0);

        $children = [
            ['key' => 'pos.cash', 'name' => 'Cash Payments', 'icon' => 'bi-cash', 'sort_order' => 20],
            ['key' => 'pos.card', 'name' => 'Card Payments', 'icon' => 'bi-credit-card', 'sort_order' => 21],
            ['key' => 'pos.mobile_payment', 'name' => 'Mobile Payment', 'icon' => 'bi-phone', 'sort_order' => 22],
            ['key' => 'pos.split_payment', 'name' => 'Split Payment', 'icon' => 'bi-diagram-3', 'sort_order' => 23],
            ['key' => 'pos.shift', 'name' => 'Shift Management', 'icon' => 'bi-clock-history', 'sort_order' => 30],
            ['key' => 'pos.cash_drawer', 'name' => 'Cash Drawer', 'icon' => 'bi-cash-stack', 'sort_order' => 31],
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

        echo "Inserted {$inserted} POS phase 2 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'pos.cash', 'pos.card', 'pos.mobile_payment',
            'pos.split_payment', 'pos.shift', 'pos.cash_drawer',
        ])->delete();
    }
};
