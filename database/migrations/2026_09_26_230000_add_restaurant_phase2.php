<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 2 — Restaurant Order Types: 6 more children under the existing
     * `restaurant` industry (dine_in, takeaway, delivery, order,
     * order_tracking, pre_order). Children inherit type + is_core from the
     * parent row, and the whole block is updateOrInsert so a re-run is a
     * no-op. Phase 1 rows are never touched.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        $parent = DB::table('module_registry')->where('key', 'restaurant')->first();
        $parentType = $parent->type ?? 'industry';
        $parentIsCore = $parent->is_core ?? 0;

        $children = [
            ['key' => 'restaurant.dine_in', 'name' => 'Dine-in Orders', 'icon' => 'bi-shop-window', 'sort_order' => 20],
            ['key' => 'restaurant.takeaway', 'name' => 'Takeaway Orders', 'icon' => 'bi-bag', 'sort_order' => 21],
            ['key' => 'restaurant.delivery', 'name' => 'Delivery Orders', 'icon' => 'bi-truck', 'sort_order' => 22],
            ['key' => 'restaurant.order', 'name' => 'Order Management', 'icon' => 'bi-clipboard-check', 'sort_order' => 23],
            ['key' => 'restaurant.order_tracking', 'name' => 'Order Tracking', 'icon' => 'bi-geo-alt', 'sort_order' => 24],
            ['key' => 'restaurant.pre_order', 'name' => 'Pre-orders', 'icon' => 'bi-calendar-plus', 'sort_order' => 25],
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

        echo "Inserted {$inserted} restaurant phase 2 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'restaurant.dine_in', 'restaurant.takeaway', 'restaurant.delivery',
            'restaurant.order', 'restaurant.order_tracking', 'restaurant.pre_order',
        ])->delete();
    }
};
