<?php

namespace Database\Seeders;

use App\Models\ModuleRegistry;
use Illuminate\Database\Seeder;

class SalesSubModuleSeeder extends Seeder
{
    public function run(): void
    {
        ModuleRegistry::updateOrCreate(
            ['key' => 'sales'],
            ['icon' => 'bi-graph-up', 'parent_key' => null, 'coming_soon' => false, 'sort_order' => 6]
        );

        $subModules = [
            ['key' => 'sales.quotations', 'name' => 'Quotations', 'icon' => 'bi-file-earmark-text', 'sort_order' => 61, 'index_route' => 'sales.quotations.index'],
            ['key' => 'sales.orders',     'name' => 'Orders',     'icon' => 'bi-cart-check',        'sort_order' => 62, 'index_route' => 'sales.orders.index'],
            ['key' => 'sales.deliveries', 'name' => 'Deliveries', 'icon' => 'bi-truck',             'sort_order' => 63, 'index_route' => 'sales.deliveries.index'],
            ['key' => 'sales.returns',    'name' => 'Returns',    'icon' => 'bi-arrow-return-left', 'sort_order' => 64, 'index_route' => 'sales.returns.index'],
            ['key' => 'sales.leads',      'name' => 'Leads',      'icon' => 'bi-funnel',            'sort_order' => 65, 'index_route' => 'sales.leads.index'],
            ['key' => 'sales.customers',  'name' => 'Customers',  'icon' => 'bi-people',            'sort_order' => 66, 'index_route' => 'sales.customers.manage.index'],
            ['key' => 'sales.reports',    'name' => 'Reports',    'icon' => 'bi-graph-up',          'sort_order' => 67, 'index_route' => 'sales.reports.dashboard'],
        ];

        foreach ($subModules as $sub) {
            ModuleRegistry::updateOrCreate(
                ['key' => $sub['key']],
                [
                    'name'        => $sub['name'],
                    'parent_key'  => 'sales',
                    'icon'        => $sub['icon'],
                    'sort_order'  => $sub['sort_order'],
                    'type'        => 'core',
                    'coming_soon' => false,
                    'index_route' => $sub['index_route'],
                    'status'      => 'active',
                ]
            );
        }

        if ($this->command) {
            $this->command->info('Sales sub-modules seeded: ' . count($subModules));
        }
    }
}
