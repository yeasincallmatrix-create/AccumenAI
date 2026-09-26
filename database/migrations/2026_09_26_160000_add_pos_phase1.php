<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * POS Phase 1 — Foundation: 5 children under the existing `pos` parent.
     * Idempotent: parent insert is guarded, children use updateOrInsert keyed
     * on `key`, so a re-run is a no-op. Children inherit the parent's type and
     * is_core flag (registry convention — see the module-children migration).
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        // Ensure parent exists
        $parent = DB::table('module_registry')->where('key', 'pos')->first();
        if (! $parent) {
            DB::table('module_registry')->insert([
                'key' => 'pos',
                'name' => 'POS',
                'type' => 'core',
                'parent_key' => null,
                'description' => 'Point of Sale system',
                'dependencies' => null,
                'sort_order' => 15,
                'icon' => 'bi-display',
                'coming_soon' => false,
                'index_route' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $parent = DB::table('module_registry')->where('key', 'pos')->first();
        }

        $parentType = $parent->type ?? 'core';
        $parentIsCore = (int) ($parent->is_core ?? 0);

        $children = [
            ['key' => 'pos.terminal', 'name' => 'POS Terminal', 'icon' => 'bi-pc-display', 'sort_order' => 1],
            ['key' => 'pos.register', 'name' => 'Registers', 'icon' => 'bi-box', 'sort_order' => 2],
            ['key' => 'pos.cart', 'name' => 'Cart / Basket', 'icon' => 'bi-cart', 'sort_order' => 10],
            ['key' => 'pos.checkout', 'name' => 'Checkout', 'icon' => 'bi-cart-check', 'sort_order' => 11],
            ['key' => 'pos.receipt', 'name' => 'Receipts', 'icon' => 'bi-receipt', 'sort_order' => 12],
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

        echo "Inserted {$inserted} POS phase 1 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'pos.terminal', 'pos.register', 'pos.cart', 'pos.checkout', 'pos.receipt',
        ])->delete();
    }
};
