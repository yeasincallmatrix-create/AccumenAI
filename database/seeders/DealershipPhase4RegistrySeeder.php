<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DealershipPhase4RegistrySeeder extends Seeder
{
    public function run(): void
    {
        $parent = DB::table('module_registry')
            ->where('key', 'dealership')
            ->where('status', 'active')
            ->first();

        if (! $parent) {
            throw new RuntimeException('Phase 1 missing: dealership parent not found. Aborting Phase 4.');
        }

        $children = [
            ['key' => 'dealership.sr_reports',         'name' => 'SR Reports',         'icon' => 'bi-person-badge',   'sort_order' => 117],
            ['key' => 'dealership.sales_reports',      'name' => 'Sales Reports',      'icon' => 'bi-bar-chart',      'sort_order' => 118],
            ['key' => 'dealership.collection_reports', 'name' => 'Collection Reports', 'icon' => 'bi-cash-stack',     'sort_order' => 119],
            ['key' => 'dealership.target_reports',     'name' => 'Target Reports',     'icon' => 'bi-clipboard-data', 'sort_order' => 120],
            ['key' => 'dealership.dashboard',          'name' => 'Dashboard',          'icon' => 'bi-speedometer2',   'sort_order' => 121],
        ];

        foreach ($children as $child) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $child['key']],
                [
                    'name' => $child['name'],
                    'parent_key' => 'dealership',
                    'type' => 'core',
                    'is_core' => 1,
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
        }
    }
}
