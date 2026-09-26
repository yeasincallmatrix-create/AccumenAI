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
    ],

    'allow_custom_modules' => true,
    'custom_module_prefix' => 'restaurant.custom.',
    'custom_module_types' => ['menu_type', 'table_section', 'report', 'other'],
];
