<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 1 — Real Estate Foundation: elaborate the `real_estate` parent
     * into parent → parent.child pairs (7 children: Properties, Buildings,
     * Units, Owners, Property Types, Amenities, Documents).
     *
     * Idempotent: parent insert is guarded, children use updateOrInsert keyed
     * on `key`, so a re-run is a no-op.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        // Verify/create parent
        $parent = DB::table('module_registry')->where('key', 'real_estate')->first();
        if (! $parent) {
            DB::table('module_registry')->insert([
                'key' => 'real_estate',
                'name' => 'Real Estate',
                'type' => 'industry',
                'is_core' => 0,
                'parent_key' => null,
                'description' => 'Real estate property management',
                'dependencies' => null,
                'sort_order' => 1,
                'icon' => 'bi-building',
                'coming_soon' => false,
                'index_route' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $parent = DB::table('module_registry')->where('key', 'real_estate')->first();
        }

        $children = [
            ['key' => 'real_estate.properties', 'name' => 'Properties', 'icon' => 'bi-house', 'sort_order' => 1],
            ['key' => 'real_estate.buildings', 'name' => 'Buildings / Projects', 'icon' => 'bi-buildings', 'sort_order' => 2],
            ['key' => 'real_estate.units', 'name' => 'Units', 'icon' => 'bi-door-open', 'sort_order' => 3],
            ['key' => 'real_estate.owners', 'name' => 'Owners', 'icon' => 'bi-person-badge', 'sort_order' => 4],
            ['key' => 'real_estate.property_types', 'name' => 'Property Types', 'icon' => 'bi-tags', 'sort_order' => 5],
            ['key' => 'real_estate.amenities', 'name' => 'Amenities', 'icon' => 'bi-star', 'sort_order' => 6],
            ['key' => 'real_estate.documents', 'name' => 'Documents', 'icon' => 'bi-file-earmark-text', 'sort_order' => 7],
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

        echo "Inserted {$inserted} real estate phase 1 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'real_estate.properties', 'real_estate.buildings', 'real_estate.units',
            'real_estate.owners', 'real_estate.property_types',
            'real_estate.amenities', 'real_estate.documents',
        ])->delete();
    }
};
