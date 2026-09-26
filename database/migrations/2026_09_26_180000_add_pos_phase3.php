<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * POS Phase 3 — Customer & Promotions: 5 children under the existing `pos`
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
            ['key' => 'pos.customer', 'name' => 'Customer Lookup', 'icon' => 'bi-person-badge', 'sort_order' => 40],
            ['key' => 'pos.loyalty', 'name' => 'Loyalty Program', 'icon' => 'bi-award', 'sort_order' => 41],
            ['key' => 'pos.discount', 'name' => 'Discounts', 'icon' => 'bi-percent', 'sort_order' => 42],
            ['key' => 'pos.coupon', 'name' => 'Coupons', 'icon' => 'bi-ticket-perforated', 'sort_order' => 43],
            ['key' => 'pos.gift_card', 'name' => 'Gift Cards', 'icon' => 'bi-gift', 'sort_order' => 44],
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

        echo "Inserted {$inserted} POS phase 3 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'pos.customer', 'pos.loyalty', 'pos.discount', 'pos.coupon', 'pos.gift_card',
        ])->delete();
    }
};
