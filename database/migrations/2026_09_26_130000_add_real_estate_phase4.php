<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 4 — Real Estate Maintenance & Operations: 6 more children under
     * the existing `real_estate` parent (7 + 8 + 7 + 6 = 28 total).
     *
     * Idempotent: updateOrInsert keyed on `key`, so a re-run is a no-op.
     * Phase 1, 2 and 3 rows are never touched.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        $parent = DB::table('module_registry')->where('key', 'real_estate')->first();

        $children = [
            ['key' => 'real_estate.maintenance_requests', 'name' => 'Maintenance Requests', 'icon' => 'bi-tools', 'sort_order' => 30],
            ['key' => 'real_estate.work_orders', 'name' => 'Work Orders', 'icon' => 'bi-clipboard-check', 'sort_order' => 31],
            ['key' => 'real_estate.vendors', 'name' => 'Vendors', 'icon' => 'bi-people', 'sort_order' => 32],
            ['key' => 'real_estate.inspections', 'name' => 'Inspections', 'icon' => 'bi-search', 'sort_order' => 33],
            ['key' => 'real_estate.assets', 'name' => 'Assets', 'icon' => 'bi-box', 'sort_order' => 34],
            ['key' => 'real_estate.preventive_maintenance', 'name' => 'Preventive Maintenance', 'icon' => 'bi-calendar-check', 'sort_order' => 35],
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

        echo "Inserted {$inserted} real estate phase 4 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'real_estate.maintenance_requests', 'real_estate.work_orders',
            'real_estate.vendors', 'real_estate.inspections',
            'real_estate.assets', 'real_estate.preventive_maintenance',
        ])->delete();
    }
};
