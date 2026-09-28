<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DealershipPhase3RegistrySeeder extends Seeder
{
    public function run(): void
    {
        $parent = DB::table('module_registry')
            ->where('key', 'dealership')
            ->where('status', 'active')
            ->first();

        if (! $parent) {
            throw new RuntimeException('Phase 1 missing: dealership parent not found. Aborting Phase 3.');
        }

        $children = [
            ['key' => 'dealership.sr_targets',    'name' => 'SR Targets',    'icon' => 'bi-bullseye',       'sort_order' => 112],
            ['key' => 'dealership.sr_commission', 'name' => 'SR Commission', 'icon' => 'bi-percent',        'sort_order' => 113],
            ['key' => 'dealership.brand_targets', 'name' => 'Brand Targets', 'icon' => 'bi-graph-up-arrow', 'sort_order' => 114],
            ['key' => 'dealership.incentives',    'name' => 'Incentives',    'icon' => 'bi-gift',           'sort_order' => 115],
            ['key' => 'dealership.attendance',    'name' => 'Attendance',    'icon' => 'bi-calendar-check', 'sort_order' => 116],
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
