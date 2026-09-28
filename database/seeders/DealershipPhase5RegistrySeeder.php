<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DealershipPhase5RegistrySeeder extends Seeder
{
    public function run(): void
    {
        $parent = DB::table('module_registry')
            ->where('key', 'dealership')
            ->where('status', 'active')
            ->first();

        if (! $parent) {
            throw new RuntimeException('Phase 1 missing: dealership parent not found. Aborting Phase 5.');
        }

        $children = [
            ['key' => 'dealership.api_tokens',         'name' => 'API Tokens',         'icon' => 'bi-key',        'sort_order' => 122],
            ['key' => 'dealership.api_endpoints',      'name' => 'API Endpoints',      'icon' => 'bi-diagram-3',  'sort_order' => 123],
            ['key' => 'dealership.api_docs',           'name' => 'API Docs',           'icon' => 'bi-file-code',  'sort_order' => 124],
            ['key' => 'dealership.push_notifications', 'name' => 'Push Notifications', 'icon' => 'bi-bell',       'sort_order' => 125],
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
