<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Restaurant Module Configuration
    |--------------------------------------------------------------------------
    | Phase 1: Foundation (Menu, Table, Reservation)
    | Future phases will extend this.
    */

    'engines' => [
        'menu' => [
            'name' => 'Menu Management',
            'icon' => 'bi-journal-text',
            'required' => true,
            'description' => 'Menu categories, items, variants',
            'modules' => [
                'restaurant.menu' => ['name' => 'Menu Management', 'icon' => 'bi-journal-text', 'sort_order' => 1],
                'restaurant.menu_category' => ['name' => 'Menu Categories', 'icon' => 'bi-tags', 'sort_order' => 2],
                'restaurant.menu_item' => ['name' => 'Menu Items', 'icon' => 'bi-egg-fried', 'sort_order' => 3],
            ],
        ],

        'table' => [
            'name' => 'Table Management',
            'icon' => 'bi-grid-3x3',
            'required' => true,
            'description' => 'Tables, layout, reservation',
            'modules' => [
                'restaurant.table' => ['name' => 'Table Management', 'icon' => 'bi-grid-3x3', 'sort_order' => 10],
                'restaurant.table_layout' => ['name' => 'Table Layout', 'icon' => 'bi-layout-text-window', 'sort_order' => 11],
                'restaurant.reservation' => ['name' => 'Reservation', 'icon' => 'bi-calendar-check', 'sort_order' => 12],
            ],
        ],

        'orders' => [
            'name' => 'Order Management',
            'icon' => 'bi-clipboard-check',
            'required' => true,
            'description' => 'Dine-in, Takeaway, Delivery, Tracking',
            'modules' => [
                'restaurant.dine_in' => ['name' => 'Dine-in Orders', 'icon' => 'bi-shop-window', 'sort_order' => 20],
                'restaurant.takeaway' => ['name' => 'Takeaway Orders', 'icon' => 'bi-bag', 'sort_order' => 21],
                'restaurant.delivery' => ['name' => 'Delivery Orders', 'icon' => 'bi-truck', 'sort_order' => 22],
                'restaurant.order' => ['name' => 'Order Management', 'icon' => 'bi-clipboard-check', 'sort_order' => 23],
                'restaurant.order_tracking' => ['name' => 'Order Tracking', 'icon' => 'bi-geo-alt', 'sort_order' => 24],
                'restaurant.pre_order' => ['name' => 'Pre-orders', 'icon' => 'bi-calendar-plus', 'sort_order' => 25],
            ],
        ],
    ],

    'allow_custom_modules' => true,
    'custom_module_prefix' => 'restaurant.custom.',
    'custom_module_types' => ['menu_type', 'table_section', 'report', 'other'],
];
