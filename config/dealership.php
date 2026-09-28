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

        'orders' => [
            'name' => 'Orders',
            'icon' => 'bi-cart-check',
            'required' => true,
            'description' => 'SR orders and approval',
            'modules' => [
                'dealership.sr_orders'      => ['name' => 'SR Orders',      'icon' => 'bi-cart-check',    'sort_order' => 106],
                'dealership.order_approval' => ['name' => 'Order Approval', 'icon' => 'bi-check2-square', 'sort_order' => 108],
            ],
        ],

        'collection' => [
            'name' => 'Collection',
            'icon' => 'bi-cash-coin',
            'required' => true,
            'description' => 'SR cash collection',
            'modules' => [
                'dealership.sr_collection' => ['name' => 'SR Collection', 'icon' => 'bi-cash-coin', 'sort_order' => 107],
            ],
        ],

        'pricing' => [
            'name' => 'Pricing',
            'icon' => 'bi-tags',
            'required' => false,
            'description' => 'Channel price lists',
            'modules' => [
                'dealership.price_lists' => ['name' => 'Price Lists', 'icon' => 'bi-tags', 'sort_order' => 110],
            ],
        ],

        'credit' => [
            'name' => 'Credit',
            'icon' => 'bi-shield-check',
            'required' => false,
            'description' => 'Credit limits and blocks',
            'modules' => [
                'dealership.credit_control' => ['name' => 'Credit Control', 'icon' => 'bi-shield-check', 'sort_order' => 111],
            ],
        ],

        'inventory_bridge' => [
            'name' => 'Inventory Bridge',
            'icon' => 'bi-link-45deg',
            'required' => false,
            'description' => 'Product to inventory linking',
            'modules' => [
                'dealership.inventory_link' => ['name' => 'Inventory Link', 'icon' => 'bi-link-45deg', 'sort_order' => 109],
            ],
        ],
    ],

    'order_statuses' => ['draft', 'submitted', 'approved', 'rejected', 'delivered'],

    'collection_methods' => ['cash', 'cheque', 'bank_transfer', 'mobile_banking'],

    'allow_custom_modules' => true,
    'custom_module_prefix' => 'dealership.custom.',
    'custom_module_types' => ['brand_type', 'channel', 'report', 'workflow', 'other'],
];
