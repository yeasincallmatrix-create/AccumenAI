<?php

return [
    /*
    |--------------------------------------------------------------------------
    | POS Module Configuration
    |--------------------------------------------------------------------------
    | Phase 1: Foundation (Terminal, Register, Cart, Checkout, Receipt)
    | Phase 2: Payment & Sessions (Cash, Card, Mobile, Split, Shift, Drawer)
    | Phase 3: Customer & Promotions (Customer, Loyalty, Discount, Coupon,
    |          Gift Card)
    | Phase 4: Returns & Reports (Return, Refund, Exchange, Daily, Item,
    |          Cashier reports)
    | Phase 5: Integrations + Custom (Inventory, Sales, Finance, Accounting,
    |          CRM integrations; admin-defined custom modules)
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

        'customer' => [
            'name' => 'Customer',
            'icon' => 'bi-person-badge',
            'required' => false,
            'description' => 'Customer lookup and loyalty',
            'modules' => [
                'pos.customer' => ['name' => 'Customer Lookup', 'icon' => 'bi-person-badge', 'sort_order' => 40],
                'pos.loyalty' => ['name' => 'Loyalty Program', 'icon' => 'bi-award', 'sort_order' => 41],
            ],
        ],

        'promotions' => [
            'name' => 'Promotions',
            'icon' => 'bi-tag',
            'required' => false,
            'description' => 'Discounts, coupons, gift cards',
            'modules' => [
                'pos.discount' => ['name' => 'Discounts', 'icon' => 'bi-percent', 'sort_order' => 42],
                'pos.coupon' => ['name' => 'Coupons', 'icon' => 'bi-ticket-perforated', 'sort_order' => 43],
                'pos.gift_card' => ['name' => 'Gift Cards', 'icon' => 'bi-gift', 'sort_order' => 44],
            ],
        ],

        'returns' => [
            'name' => 'Returns & Refunds',
            'icon' => 'bi-arrow-return-left',
            'required' => false,
            'description' => 'Returns, refunds, exchanges',
            'modules' => [
                'pos.return' => ['name' => 'Returns', 'icon' => 'bi-arrow-return-left', 'sort_order' => 50],
                'pos.refund' => ['name' => 'Refunds', 'icon' => 'bi-cash-coin', 'sort_order' => 51],
                'pos.exchange' => ['name' => 'Exchanges', 'icon' => 'bi-arrow-left-right', 'sort_order' => 52],
            ],
        ],

        'reports' => [
            'name' => 'Reports & Analytics',
            'icon' => 'bi-graph-up',
            'required' => true,
            'description' => 'Daily, item, cashier reports',
            'modules' => [
                'pos.daily_report' => ['name' => 'Daily Sales Report', 'icon' => 'bi-calendar-check', 'sort_order' => 60],
                'pos.item_report' => ['name' => 'Item Sales Report', 'icon' => 'bi-list-ul', 'sort_order' => 61],
                'pos.cashier_report' => ['name' => 'Cashier Report', 'icon' => 'bi-person-badge', 'sort_order' => 62],
            ],
        ],

        'integrations' => [
            'name' => 'Integrations',
            'icon' => 'bi-link-45deg',
            'required' => false,
            'description' => 'Connect with inventory, sales, finance, accounting, CRM',
            'modules' => [
                'pos.inventory_integration' => ['name' => 'Inventory Integration', 'icon' => 'bi-box', 'sort_order' => 70],
                'pos.sales_integration' => ['name' => 'Sales Integration', 'icon' => 'bi-cart', 'sort_order' => 71],
                'pos.finance_integration' => ['name' => 'Finance Integration', 'icon' => 'bi-cash', 'sort_order' => 72],
                'pos.accounting_integration' => ['name' => 'Accounting Integration', 'icon' => 'bi-journal-text', 'sort_order' => 73],
                'pos.crm_integration' => ['name' => 'CRM Integration', 'icon' => 'bi-person-lines-fill', 'sort_order' => 74],
            ],
        ],
    ],

    'allow_custom_modules' => true,
    'custom_module_prefix' => 'pos.custom.',
    'custom_module_types' => ['payment_method', 'report', 'workflow', 'other'],
];
