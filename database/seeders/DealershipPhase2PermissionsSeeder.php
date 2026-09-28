<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DealershipPhase2PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            // Phase 1 children (permission-less until now — seeded here).
            'brands'       => 'Brand Management',
            'products'     => 'Product Catalog',
            'sales_force'  => 'Sales Force (SR)',
            'beats'        => 'Beat Plan',
            'customers'    => 'Customers',
            // Phase 2 children.
            'sr_orders'      => 'SR Orders',
            'sr_collection'  => 'SR Collection',
            'order_approval' => 'Order Approval',
            'inventory_link' => 'Inventory Link',
            'price_lists'    => 'Price Lists',
            'credit_control' => 'Credit Control',
        ];

        foreach ($modules as $slug => $label) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => "{$slug}.view"],
                ['module' => 'dealership', 'name' => "{$label} View", 'created_at' => now()]
            );
            DB::table('permissions')->updateOrInsert(
                ['slug' => "{$slug}.manage"],
                ['module' => 'dealership', 'name' => "{$label} Manage", 'created_at' => now()]
            );
        }

        DB::table('permissions')->updateOrInsert(
            ['slug' => 'order_approval.approve'],
            ['module' => 'dealership', 'name' => 'Order Approve', 'created_at' => now()]
        );
    }
}
