<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 1 — Restaurant Foundation: the `restaurant` parent becomes a
     * standalone industry with 6 children (menu, menu_category, menu_item,
     * table, table_layout, reservation) plus its 7 sub-industry rows so
     * /admin/module-config can load the restaurant matrix.
     *
     * Idempotent: parent insert is guarded, children and sub-categories use
     * updateOrInsert, so a re-run is a no-op.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        // Create parent if missing
        $parent = DB::table('module_registry')->where('key', 'restaurant')->first();
        if (! $parent) {
            DB::table('module_registry')->insert([
                'key' => 'restaurant',
                'name' => 'Restaurant',
                'type' => 'industry',
                'parent_key' => null,
                'description' => 'Restaurant management system',
                'dependencies' => null,
                'sort_order' => 16,
                'icon' => 'bi-shop',
                'coming_soon' => false,
                'index_route' => null,
                'status' => 'active',
                'is_core' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $parent = DB::table('module_registry')->where('key', 'restaurant')->first();
        }

        $parentType = $parent->type ?? 'industry';
        $parentIsCore = $parent->is_core ?? 0;

        $children = [
            ['key' => 'restaurant.menu', 'name' => 'Menu Management', 'icon' => 'bi-journal-text', 'sort_order' => 1],
            ['key' => 'restaurant.menu_category', 'name' => 'Menu Categories', 'icon' => 'bi-tags', 'sort_order' => 2],
            ['key' => 'restaurant.menu_item', 'name' => 'Menu Items', 'icon' => 'bi-egg-fried', 'sort_order' => 3],
            ['key' => 'restaurant.table', 'name' => 'Table Management', 'icon' => 'bi-grid-3x3', 'sort_order' => 10],
            ['key' => 'restaurant.table_layout', 'name' => 'Table Layout', 'icon' => 'bi-layout-text-window', 'sort_order' => 11],
            ['key' => 'restaurant.reservation', 'name' => 'Reservation', 'icon' => 'bi-calendar-check', 'sort_order' => 12],
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

        $subInserted = $this->insertSubcategories();

        echo "Inserted {$inserted} restaurant phase 1 modules.\n";
        echo "Inserted {$subInserted} restaurant sub-industries.\n";
    }

    /**
     * Restaurant sub-industries — the matrix at /admin/module-config only
     * renders once a sub-industry is selected, so these rows are part of the
     * phase foundation. Same rows IndustrySubcategorySeeder writes (mirrored
     * here so a migrate-only provision gets them too).
     */
    private function insertSubcategories(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('industry_subcategories')) {
            return 0;
        }

        $subcategories = [
            ['subcategory_key' => 'fine_dining', 'name' => 'Fine Dining', 'description' => 'Fine dining restaurant', 'icon' => 'bi-utensils', 'sort_order' => 1],
            ['subcategory_key' => 'casual', 'name' => 'Casual Dining', 'description' => 'Casual / family dining', 'icon' => 'bi-shop', 'sort_order' => 2],
            ['subcategory_key' => 'fast_food', 'name' => 'Fast Food', 'description' => 'Fast food / quick service', 'icon' => 'bi-lightning-charge', 'sort_order' => 3],
            ['subcategory_key' => 'cafe', 'name' => 'Cafe / Coffee Shop', 'description' => 'Cafe and coffee shop', 'icon' => 'bi-cup', 'sort_order' => 4],
            ['subcategory_key' => 'bakery', 'name' => 'Bakery', 'description' => 'Bakery / confectionery', 'icon' => 'bi-egg-fried', 'sort_order' => 5],
            ['subcategory_key' => 'food_court', 'name' => 'Food Court', 'description' => 'Food court / multi-vendor', 'icon' => 'bi-grid-3x3', 'sort_order' => 6],
            ['subcategory_key' => 'cloud_kitchen', 'name' => 'Cloud Kitchen', 'description' => 'Delivery-only kitchen', 'icon' => 'bi-cloud', 'sort_order' => 7],
        ];

        $inserted = 0;
        foreach ($subcategories as $sub) {
            DB::table('industry_subcategories')->updateOrInsert(
                ['industry_key' => 'restaurant', 'subcategory_key' => $sub['subcategory_key']],
                $sub + [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $inserted++;
        }

        return $inserted;
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'restaurant.menu', 'restaurant.menu_category', 'restaurant.menu_item',
            'restaurant.table', 'restaurant.table_layout', 'restaurant.reservation',
        ])->delete();
    }
};
