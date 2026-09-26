<?php

return [
    /*
    |--------------------------------------------------------------------------
    | POS Module Configuration
    |--------------------------------------------------------------------------
    | Phase 1: Foundation (Terminal, Register, Cart, Checkout, Receipt)
    | Future phases will extend this.
    */

    'engines' => [
        'terminal' => [
            'name' => 'Terminal & Register',
            'icon' => 'bi-pc-display',
            'required' => true,
            'description' => 'POS terminals and registers',
            'modules' => [
                'pos.terminal' => ['name' => 'POS Terminal', 'icon' => 'bi-pc-display', 'sort_order' => 1],
                'pos.register' => ['name' => 'Registers', 'icon' => 'bi-box', 'sort_order' => 2],
            ],
        ],

        'sales' => [
            'name' => 'Sales & Cart',
            'icon' => 'bi-cart',
            'required' => true,
            'description' => 'Cart, checkout, receipt',
            'modules' => [
                'pos.cart' => ['name' => 'Cart / Basket', 'icon' => 'bi-cart', 'sort_order' => 10],
                'pos.checkout' => ['name' => 'Checkout', 'icon' => 'bi-cart-check', 'sort_order' => 11],
                'pos.receipt' => ['name' => 'Receipts', 'icon' => 'bi-receipt', 'sort_order' => 12],
            ],
        ],
    ],

    'allow_custom_modules' => true,
    'custom_module_prefix' => 'pos.custom.',
];
