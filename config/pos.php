<?php

return [
    /*
    |--------------------------------------------------------------------------
    | POS Module Configuration
    |--------------------------------------------------------------------------
    | Phase 1: Foundation (Terminal, Register, Cart, Checkout, Receipt)
    | Phase 2: Payment & Sessions (Cash, Card, Mobile, Split, Shift, Drawer)
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

        'payment' => [
            'name' => 'Payment Methods',
            'icon' => 'bi-credit-card',
            'required' => false,
            'description' => 'Cash, Card, Mobile, Split payments',
            'modules' => [
                'pos.cash' => ['name' => 'Cash Payments', 'icon' => 'bi-cash', 'sort_order' => 20],
                'pos.card' => ['name' => 'Card Payments', 'icon' => 'bi-credit-card', 'sort_order' => 21],
                'pos.mobile_payment' => ['name' => 'Mobile Payment', 'icon' => 'bi-phone', 'sort_order' => 22],
                'pos.split_payment' => ['name' => 'Split Payment', 'icon' => 'bi-diagram-3', 'sort_order' => 23],
            ],
        ],

        'session' => [
            'name' => 'Session Management',
            'icon' => 'bi-clock-history',
            'required' => false,
            'description' => 'Shift and cash drawer management',
            'modules' => [
                'pos.shift' => ['name' => 'Shift Management', 'icon' => 'bi-clock-history', 'sort_order' => 30],
                'pos.cash_drawer' => ['name' => 'Cash Drawer', 'icon' => 'bi-cash-stack', 'sort_order' => 31],
            ],
        ],
    ],

    'allow_custom_modules' => true,
    'custom_module_prefix' => 'pos.custom.',
];
