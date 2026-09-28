<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dealership Module Configuration
    |--------------------------------------------------------------------------
    | Module group (type=core) — NOT an industry.
    | Provides SR (Sales Representative) management for dealers.
    |
    | Phase 1: Foundation (brands, products, SR, customers, beats)
    | Future phases: orders, collection, targets, commission, reports
    */

    'engines' => [
        'catalog' => [
            'name' => 'Catalog',
            'icon' => 'bi-box-seam',
            'required' => true,
            'description' => 'Brands and products',
            'modules' => [
                'dealership.brands'   => ['name' => 'Brand Management',  'icon' => 'bi-award',       'sort_order' => 1],
                'dealership.products' => ['name' => 'Product Catalog',   'icon' => 'bi-box',         'sort_order' => 2],
            ],
        ],

        'field_force' => [
            'name' => 'Field Force',
            'icon' => 'bi-people',
            'required' => true,
            'description' => 'SR management and routes',
            'modules' => [
                'dealership.sales_force' => ['name' => 'Sales Force (SR)', 'icon' => 'bi-person-badge', 'sort_order' => 10],
                'dealership.beats'       => ['name' => 'Beat Plan',         'icon' => 'bi-geo-alt',     'sort_order' => 11],
            ],
        ],

        'customers' => [
            'name' => 'Customers',
            'icon' => 'bi-shop',
            'required' => true,
            'description' => 'Shops and sub-dealers',
            'modules' => [
                'dealership.customers' => ['name' => 'Customers', 'icon' => 'bi-shop', 'sort_order' => 20],
            ],
        ],
    ],

    'allow_custom_modules' => true,
    'custom_module_prefix' => 'dealership.custom.',
    'custom_module_types' => ['brand_type', 'channel', 'report', 'workflow', 'other'],
];
