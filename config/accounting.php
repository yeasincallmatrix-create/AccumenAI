<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Aging Reports (Phase A)
    |--------------------------------------------------------------------------
    |
    | Flat top-level block — this file has no `engines` key, so the aging
    | engine is declared here. Buckets drive the Phase B calculator/service;
    | `modules` mirrors module_registry metadata for reference only (the
    | registry itself is the source of truth, seeded by migration
    | 2026_09_27_370000_add_accounting_aging_modules).
    |
    */
    'aging' => [
        'name' => 'Aging Reports',
        'icon' => 'bi-hourglass-split',
        'required' => false,
        'description' => 'AR/AP aging by 30/60/90/120+ day buckets',
        'source' => [
            'ar' => 'invoices',
            'ap' => 'purchase_invoices',
        ],
        'buckets' => [
            ['key' => 'current', 'label' => '0-30', 'min' => 0, 'max' => 30],
            ['key' => 'd31_60', 'label' => '31-60', 'min' => 31, 'max' => 60],
            ['key' => 'd61_90', 'label' => '61-90', 'min' => 61, 'max' => 90],
            ['key' => 'd91_120', 'label' => '91-120', 'min' => 91, 'max' => 120],
            ['key' => 'd121_plus', 'label' => '120+', 'min' => 121, 'max' => null],
        ],
        'modules' => [
            'accounting.ar_aging' => ['name' => 'AR Aging Report', 'icon' => 'bi-arrow-down-circle', 'sort_order' => 100],
            'accounting.ap_aging' => ['name' => 'AP Aging Report', 'icon' => 'bi-arrow-up-circle', 'sort_order' => 101],
            'accounting.invoice_aging' => ['name' => 'Invoice Aging', 'icon' => 'bi-receipt', 'sort_order' => 102],
            'accounting.aging_summary' => ['name' => 'Aging Summary Dashboard', 'icon' => 'bi-speedometer2', 'sort_order' => 103],
            'accounting.aging_config' => ['name' => 'Aging Configuration', 'icon' => 'bi-sliders', 'sort_order' => 104],
        ],
    ],
];
