<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        // Create parent (module group, type=core)
        $parent = DB::table('module_registry')->where('key', 'dealership')->first();

        if (!$parent) {
            DB::table('module_registry')->insert([
                'key' => 'dealership',
                'name' => 'Dealership',
                'type' => 'core',
                'parent_key' => null,
                'description' => 'Dealership and SR management',
                'dependencies' => null,
                'sort_order' => 25,
                'icon' => 'bi-shop',
                'coming_soon' => false,
                'index_route' => null,
                'status' => 'active',
                'is_core' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "Created dealership parent (module group).\n";
        } else {
            echo "dealership parent already exists.\n";
        }

        // Determine inherited type/is_core from parent
        $parentRow = DB::table('module_registry')->where('key', 'dealership')->first();
        $parentType = $parentRow->type ?? 'core';
        $parentIsCore = (int) ($parentRow->is_core ?? 1);

        $children = [
            ['key' => 'dealership.brands',       'name' => 'Brand Management',        'icon' => 'bi-award',        'sort_order' => 1],
            ['key' => 'dealership.products',     'name' => 'Product Catalog',         'icon' => 'bi-box',          'sort_order' => 2],
            ['key' => 'dealership.sales_force',  'name' => 'Sales Force (SR)',        'icon' => 'bi-person-badge', 'sort_order' => 10],
            ['key' => 'dealership.beats',        'name' => 'Beat Plan',               'icon' => 'bi-geo-alt',      'sort_order' => 11],
            ['key' => 'dealership.customers',    'name' => 'Customers',               'icon' => 'bi-shop',         'sort_order' => 20],
        ];

        $inserted = 0;
        foreach ($children as $child) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $child['key']],
                [
                    'name' => $child['name'],
                    'parent_key' => 'dealership',
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

        echo "Inserted {$inserted} dealership children.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'dealership.brands', 'dealership.products', 'dealership.sales_force',
            'dealership.beats', 'dealership.customers',
        ])->delete();

        DB::table('module_registry')->where('key', 'dealership')->delete();
    }
};
