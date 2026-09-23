<?php

namespace Database\Seeders;

use App\Models\ModuleRegistry;
use Illuminate\Database\Seeder;

class PurchaseSubModuleSeeder extends Seeder
{
    public function run(): void
    {
        ModuleRegistry::updateOrCreate(
            ['key' => 'purchase'],
            ['icon' => 'bi-cart4', 'parent_key' => null, 'coming_soon' => false, 'sort_order' => 7]
        );

        $subModules = [
            ['key' => 'purchase.quotations', 'name' => 'Quotations',    'icon' => 'bi-file-earmark-text', 'sort_order' => 71, 'index_route' => 'purchase.quotations.index'],
            ['key' => 'purchase.orders',     'name' => 'Orders',        'icon' => 'bi-cart-check',        'sort_order' => 72, 'index_route' => 'purchase.orders.index'],
            ['key' => 'purchase.invoices',   'name' => 'Invoices',      'icon' => 'bi-receipt',           'sort_order' => 73, 'index_route' => 'purchase.invoices.index'],
            ['key' => 'purchase.requests',   'name' => 'Requests',      'icon' => 'bi-clipboard-check',   'sort_order' => 74, 'index_route' => 'purchase.requests.index'],
            ['key' => 'purchase.returns',    'name' => 'Returns',       'icon' => 'bi-arrow-return-left', 'sort_order' => 75, 'index_route' => 'purchase.returns.index'],
            ['key' => 'purchase.receipts',   'name' => 'Goods Receipts','icon' => 'bi-box-seam',          'sort_order' => 76, 'index_route' => 'purchase.receipts.index'],
        ];

        foreach ($subModules as $sub) {
            ModuleRegistry::updateOrCreate(
                ['key' => $sub['key']],
                [
                    'name'        => $sub['name'],
                    'parent_key'  => 'purchase',
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
            $this->command->info('Purchase sub-modules seeded: ' . count($subModules));
        }
    }
}
