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

        'kitchen' => [
            'name' => 'Kitchen Operations',
            'icon' => 'bi-fire',
            'required' => true,
            'description' => 'KDS, KOT, Chef, Stations, Recipe',
            'modules' => [
                'restaurant.kitchen' => ['name' => 'Kitchen Management', 'icon' => 'bi-fire', 'sort_order' => 30],
                'restaurant.kds' => ['name' => 'Kitchen Display System', 'icon' => 'bi-display', 'sort_order' => 31],
                'restaurant.kot' => ['name' => 'Kitchen Order Ticket', 'icon' => 'bi-receipt-cutoff', 'sort_order' => 32],
                'restaurant.chef' => ['name' => 'Chef Management', 'icon' => 'bi-person-badge', 'sort_order' => 33],
                'restaurant.station' => ['name' => 'Kitchen Stations', 'icon' => 'bi-diagram-3', 'sort_order' => 34],
                'restaurant.recipe' => ['name' => 'Recipe / BOM', 'icon' => 'bi-journal-code', 'sort_order' => 35],
            ],
        ],

        'customer' => [
            'name' => 'Customer & Loyalty',
            'icon' => 'bi-person-heart',
            'required' => false,
            'description' => 'Customer, Loyalty, Feedback, Membership',
            'modules' => [
                'restaurant.customer' => ['name' => 'Customer Management', 'icon' => 'bi-person-vcard', 'sort_order' => 40],
                'restaurant.loyalty' => ['name' => 'Loyalty Program', 'icon' => 'bi-award', 'sort_order' => 41],
                'restaurant.feedback' => ['name' => 'Feedback & Reviews', 'icon' => 'bi-star', 'sort_order' => 42],
                'restaurant.membership' => ['name' => 'Membership Tiers', 'icon' => 'bi-gem', 'sort_order' => 43],
                'restaurant.birthday_offer' => ['name' => 'Birthday Offers', 'icon' => 'bi-gift', 'sort_order' => 44],
                'restaurant.preference' => ['name' => 'Customer Preferences', 'icon' => 'bi-sliders', 'sort_order' => 45],
            ],
        ],
    ],

    'allow_custom_modules' => true,
    'custom_module_prefix' => 'restaurant.custom.',
    'custom_module_types' => ['menu_type', 'table_section', 'report', 'other'],
];
