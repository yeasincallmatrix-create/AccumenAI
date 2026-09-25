<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Universal Manufacturing — elaborate the `manufacturing` parent into
     * parent → parent.child pairs across 5 core engines (22 children).
     *
     * 7 core modules (parent + 6) + 16 optional modules = 23 total.
     * Idempotent: updateOrInsert keyed on `key`, so a re-run is a no-op.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        // ═══════════════════════════════════════════════════════
        // VERIFY PARENT EXISTS
        // ═══════════════════════════════════════════════════════
        $parent = DB::table('module_registry')->where('key', 'manufacturing')->first();

        if (! $parent) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => 'manufacturing'],
                [
                    'name' => 'Manufacturing',
                    'type' => 'industry',
                    'is_core' => 0,
                    'parent_key' => null,
                    'description' => 'Universal manufacturing module',
                    'dependencies' => null,
                    'sort_order' => 14,
                    'icon' => 'bi-gear',
                    'coming_soon' => false,
                    'index_route' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $parent = DB::table('module_registry')->where('key', 'manufacturing')->first();
        }

        // ═══════════════════════════════════════════════════════
        // DEFINE ALL 22 CHILDREN
        // ═══════════════════════════════════════════════════════
        $children = [
            // Core Manufacturing Engine (4)
            ['key' => 'manufacturing.bom', 'name' => 'Bill of Materials', 'icon' => 'bi-list-check', 'sort_order' => 1],
            ['key' => 'manufacturing.routing', 'name' => 'Routing', 'icon' => 'bi-diagram-3', 'sort_order' => 2],
            ['key' => 'manufacturing.work_centers', 'name' => 'Work Centers', 'icon' => 'bi-gear-wide', 'sort_order' => 3],
            ['key' => 'manufacturing.production_orders', 'name' => 'Production Orders', 'icon' => 'bi-clipboard-check', 'sort_order' => 4],

            // Quality Engine (4)
            ['key' => 'manufacturing.quality_control', 'name' => 'Quality Control', 'icon' => 'bi-check2-circle', 'sort_order' => 10],
            ['key' => 'manufacturing.quality_lab', 'name' => 'Quality Lab', 'icon' => 'bi-clipboard-pulse', 'sort_order' => 11],
            ['key' => 'manufacturing.sample_management', 'name' => 'Sample Management', 'icon' => 'bi-file-earmark', 'sort_order' => 12],
            ['key' => 'manufacturing.regulatory_compliance', 'name' => 'Regulatory Compliance', 'icon' => 'bi-shield-check', 'sort_order' => 13],

            // Traceability Engine (3)
            ['key' => 'manufacturing.batch_tracking', 'name' => 'Batch Tracking', 'icon' => 'bi-layers', 'sort_order' => 20],
            ['key' => 'manufacturing.expiry_tracking', 'name' => 'Expiry Tracking', 'icon' => 'bi-calendar-x', 'sort_order' => 21],
            ['key' => 'manufacturing.serial_number', 'name' => 'Serial Number', 'icon' => 'bi-upc', 'sort_order' => 22],

            // Cost Engine (1)
            ['key' => 'manufacturing.costing', 'name' => 'Costing', 'icon' => 'bi-calculator', 'sort_order' => 30],

            // Production Extension (2)
            ['key' => 'manufacturing.assembly_line', 'name' => 'Assembly Line', 'icon' => 'bi-diagram-3', 'sort_order' => 40],
            ['key' => 'manufacturing.mold_management', 'name' => 'Mold Management', 'icon' => 'bi-grid', 'sort_order' => 41],

            // Process Modules (6)
            ['key' => 'manufacturing.recipe', 'name' => 'Recipe / Formula', 'icon' => 'bi-journal-text', 'sort_order' => 50],
            ['key' => 'manufacturing.cutting', 'name' => 'Cutting', 'icon' => 'bi-scissors', 'sort_order' => 51],
            ['key' => 'manufacturing.welding', 'name' => 'Welding', 'icon' => 'bi-fire', 'sort_order' => 52],
            ['key' => 'manufacturing.finishing', 'name' => 'Finishing', 'icon' => 'bi-brush', 'sort_order' => 53],
            ['key' => 'manufacturing.printing', 'name' => 'Printing', 'icon' => 'bi-printer', 'sort_order' => 54],
            ['key' => 'manufacturing.packaging', 'name' => 'Packaging', 'icon' => 'bi-box', 'sort_order' => 55],

            // Post-Sales (1)
            ['key' => 'manufacturing.warranty', 'name' => 'Warranty', 'icon' => 'bi-shield-check', 'sort_order' => 60],

            // Reports (1)
            ['key' => 'manufacturing.reports', 'name' => 'Manufacturing Reports', 'icon' => 'bi-graph-up', 'sort_order' => 70],
        ];

        // ═══════════════════════════════════════════════════════
        // INSERT ALL
        // ═══════════════════════════════════════════════════════
        $inserted = 0;

        foreach ($children as $child) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $child['key']],
                [
                    'name' => $child['name'],
                    'parent_key' => 'manufacturing',
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

        echo "Inserted {$inserted} manufacturing modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        $allKeys = [
            'manufacturing.bom', 'manufacturing.routing', 'manufacturing.work_centers',
            'manufacturing.production_orders', 'manufacturing.quality_control',
            'manufacturing.quality_lab', 'manufacturing.sample_management',
            'manufacturing.regulatory_compliance', 'manufacturing.batch_tracking',
            'manufacturing.expiry_tracking', 'manufacturing.serial_number',
            'manufacturing.costing', 'manufacturing.assembly_line',
            'manufacturing.mold_management', 'manufacturing.recipe',
            'manufacturing.cutting', 'manufacturing.welding', 'manufacturing.finishing',
            'manufacturing.printing', 'manufacturing.packaging',
            'manufacturing.warranty', 'manufacturing.reports',
        ];

        DB::table('module_registry')
            ->whereIn('key', $allKeys)
            ->where('parent_key', 'manufacturing')
            ->delete();
    }
};
