<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DealershipPhase2RegistrySeeder extends Seeder
{
    public function run(): void
    {
        $parent = DB::table('module_registry')
            ->where('key', 'dealership')
            ->where('status', 'active')
            ->first();

        if (! $parent) {
            throw new RuntimeException('Phase 1 missing: dealership parent not found. Aborting Phase 2.');
        }

        $children = [
            ['key' => 'dealership.sr_orders',      'name' => 'SR Orders',      'icon' => 'bi-cart-check',    'sort_order' => 106],
            ['key' => 'dealership.sr_collection',  'name' => 'SR Collection',  'icon' => 'bi-cash-coin',     'sort_order' => 107],
            ['key' => 'dealership.order_approval', 'name' => 'Order Approval', 'icon' => 'bi-check2-square', 'sort_order' => 108],
            ['key' => 'dealership.inventory_link', 'name' => 'Inventory Link', 'icon' => 'bi-link-45deg',    'sort_order' => 109],
            ['key' => 'dealership.price_lists',    'name' => 'Price Lists',    'icon' => 'bi-tags',          'sort_order' => 110],
            ['key' => 'dealership.credit_control', 'name' => 'Credit Control', 'icon' => 'bi-shield-check',  'sort_order' => 111],
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
