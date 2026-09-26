<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 3 — Restaurant Kitchen Operations: 6 more children under the
     * existing `restaurant` industry (kitchen, kds, kot, chef, station,
     * recipe). Children inherit type + is_core from the parent row and the
     * whole block is updateOrInsert, so a re-run is a no-op. Phase 1 and
     * Phase 2 rows are never touched.
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
            ['key' => 'restaurant.kitchen', 'name' => 'Kitchen Management', 'icon' => 'bi-fire', 'sort_order' => 30],
            ['key' => 'restaurant.kds', 'name' => 'Kitchen Display System', 'icon' => 'bi-display', 'sort_order' => 31],
            ['key' => 'restaurant.kot', 'name' => 'Kitchen Order Ticket', 'icon' => 'bi-receipt-cutoff', 'sort_order' => 32],
            ['key' => 'restaurant.chef', 'name' => 'Chef Management', 'icon' => 'bi-person-badge', 'sort_order' => 33],
            ['key' => 'restaurant.station', 'name' => 'Kitchen Stations', 'icon' => 'bi-diagram-3', 'sort_order' => 34],
            ['key' => 'restaurant.recipe', 'name' => 'Recipe / BOM', 'icon' => 'bi-journal-code', 'sort_order' => 35],
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

        echo "Inserted {$inserted} restaurant phase 3 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'restaurant.kitchen', 'restaurant.kds', 'restaurant.kot',
            'restaurant.chef', 'restaurant.station', 'restaurant.recipe',
        ])->delete();
    }
};
