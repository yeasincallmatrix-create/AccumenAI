<?php

namespace Database\Seeders;

use App\Models\FeatureRegistry;
use Illuminate\Database\Seeder;

class SalesPurchaseFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            'sales.quotations' => ['name' => 'Quotations', 'description' => 'Sales quotations and conversion to orders', 'sort_order' => 61],
            'sales.orders'     => ['name' => 'Orders',     'description' => 'Sales order lifecycle and fulfilment', 'sort_order' => 62],
            'sales.deliveries' => ['name' => 'Deliveries', 'description' => 'Delivery notes and confirmation', 'sort_order' => 63],
            'sales.returns'    => ['name' => 'Returns',    'description' => 'Sales returns and credit notes', 'sort_order' => 64],
            'sales.leads'      => ['name' => 'Leads',      'description' => 'CRM lead tracking and conversion', 'sort_order' => 65],
            'sales.customers'  => ['name' => 'Customers',  'description' => 'Customer master and management', 'sort_order' => 66],
            'sales.reports'    => ['name' => 'Reports',    'description' => 'Sales analytics and reports', 'sort_order' => 67],
        ];

        foreach ($features as $key => $attrs) {
            FeatureRegistry::updateOrCreate(
                ['feature_key' => $key],
                array_merge($attrs, ['module_key' => 'sales', 'status' => 'active'])
            );
        }

        $purchaseFeatures = [
            'purchase.quotations' => ['name' => 'Quotations',     'description' => 'Supplier quotations and conversion', 'sort_order' => 71],
            'purchase.orders'     => ['name' => 'Orders',         'description' => 'Purchase order lifecycle and approval', 'sort_order' => 72],
            'purchase.invoices'   => ['name' => 'Invoices',       'description' => 'Supplier invoices and posting', 'sort_order' => 73],
            'purchase.requests'   => ['name' => 'Requests',       'description' => 'Purchase requisitions and approval', 'sort_order' => 74],
            'purchase.returns'    => ['name' => 'Returns',        'description' => 'Purchase returns and credit notes', 'sort_order' => 75],
            'purchase.receipts'   => ['name' => 'Goods Receipts', 'description' => 'Goods receipt notes and confirmation', 'sort_order' => 76],
        ];

        foreach ($purchaseFeatures as $key => $attrs) {
            FeatureRegistry::updateOrCreate(
                ['feature_key' => $key],
                array_merge($attrs, ['module_key' => 'purchase', 'status' => 'active'])
            );
        }

        if ($this->command) {
            $total = count($features) + count($purchaseFeatures);
            $this->command->info("Sales/purchase features seeded: {$total}");
        }
    }
}
