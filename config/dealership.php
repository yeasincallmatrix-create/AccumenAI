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

        'targets' => [
            'name' => 'Targets',
            'icon' => 'bi-bullseye',
            'required' => false,
            'description' => 'SR and brand targets',
            'modules' => [
                'dealership.sr_targets'    => ['name' => 'SR Targets',    'icon' => 'bi-bullseye',       'sort_order' => 112],
                'dealership.brand_targets' => ['name' => 'Brand Targets', 'icon' => 'bi-graph-up-arrow', 'sort_order' => 114],
            ],
        ],

        'commission' => [
            'name' => 'Commission',
            'icon' => 'bi-percent',
            'required' => false,
            'description' => 'SR commission',
            'modules' => [
                'dealership.sr_commission' => ['name' => 'SR Commission', 'icon' => 'bi-percent', 'sort_order' => 113],
            ],
        ],

        'incentives' => [
            'name' => 'Incentives',
            'icon' => 'bi-gift',
            'required' => false,
            'description' => 'SR incentives',
            'modules' => [
                'dealership.incentives' => ['name' => 'Incentives', 'icon' => 'bi-gift', 'sort_order' => 115],
            ],
        ],

        'attendance' => [
            'name' => 'Attendance',
            'icon' => 'bi-calendar-check',
            'required' => false,
            'description' => 'SR attendance',
            'modules' => [
                'dealership.attendance' => ['name' => 'Attendance', 'icon' => 'bi-calendar-check', 'sort_order' => 116],
            ],
        ],

        'reports' => [
            'name' => 'Reports',
            'icon' => 'bi-bar-chart',
            'required' => false,
            'description' => 'SR, sales, collection and target reports',
            'modules' => [
                'dealership.sr_reports'         => ['name' => 'SR Reports',         'icon' => 'bi-person-badge',   'sort_order' => 117],
                'dealership.sales_reports'      => ['name' => 'Sales Reports',      'icon' => 'bi-bar-chart',      'sort_order' => 118],
                'dealership.collection_reports' => ['name' => 'Collection Reports', 'icon' => 'bi-cash-stack',     'sort_order' => 119],
                'dealership.target_reports'     => ['name' => 'Target Reports',     'icon' => 'bi-clipboard-data', 'sort_order' => 120],
            ],
        ],

        'dashboard' => [
            'name' => 'Dashboard',
            'icon' => 'bi-speedometer2',
            'required' => false,
            'description' => 'Dealership KPI dashboard',
            'modules' => [
                'dealership.dashboard' => ['name' => 'Dashboard', 'icon' => 'bi-speedometer2', 'sort_order' => 121],
            ],
        ],

        'api' => [
            'name' => 'API',
            'icon' => 'bi-key',
            'required' => false,
            'description' => 'Mobile API tokens, endpoints and docs',
            'modules' => [
                'dealership.api_tokens'   => ['name' => 'API Tokens',    'icon' => 'bi-key',       'sort_order' => 122],
                'dealership.api_endpoints' => ['name' => 'API Endpoints', 'icon' => 'bi-diagram-3', 'sort_order' => 123],
                'dealership.api_docs'     => ['name' => 'API Docs',      'icon' => 'bi-file-code', 'sort_order' => 124],
            ],
        ],

        'notify' => [
            'name' => 'Notify',
            'icon' => 'bi-bell',
            'required' => false,
            'description' => 'Push notification queue',
            'modules' => [
                'dealership.push_notifications' => ['name' => 'Push Notifications', 'icon' => 'bi-bell', 'sort_order' => 125],
            ],
        ],
    ],

    'order_statuses' => ['draft', 'submitted', 'approved', 'rejected', 'delivered'],

    'collection_methods' => ['cash', 'cheque', 'bank_transfer', 'mobile_banking'],

    'period_types' => ['monthly', 'quarterly', 'yearly'],

    'commission_statuses' => ['pending', 'approved', 'paid', 'cancelled'],

    'attendance_statuses' => ['present', 'absent', 'half_day', 'leave'],

    'report_keys' => [
        'sr_sales_summary',
        'sr_collection_summary',
        'brand_sales_summary',
        'product_sales_summary',
        'channel_sales_summary',
        'customer_outstanding',
        'collection_aging',
        'target_vs_achievement',
        'dashboard_kpis',
    ],

    'snapshot_ttl_minutes' => 15,

    'api' => [
        'token_ttl_days' => 90,
        'default_abilities' => ['orders.view', 'orders.create', 'collections.view', 'collections.create'],
        'api_version' => 'v1',
    ],

    'push' => [
        'channels' => ['database'],
        'batch_size' => 100,
        'max_retries' => 3,
    ],

    'allow_custom_modules' => true,
    'custom_module_prefix' => 'dealership.custom.',
    'custom_module_types' => ['brand_type', 'channel', 'report', 'workflow', 'other'],
];
