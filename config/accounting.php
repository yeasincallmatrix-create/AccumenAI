<?php

return [
    'account_codes' => [
        'cash' => '1000.1',
        'bank' => '1100.1',
        'accounts_receivable' => '1200.1',
        'accounts_payable' => '2000.1',
        'inventory' => '1300.1',
        'input_vat' => '1200.2',
        'fixed_assets' => '1400.1',
        'accumulated_depreciation' => '1400.5',
        'revaluation_surplus' => '3400.1',
        'gain_on_disposal' => '4900.3',
        'depreciation_expense' => '5400.1',
        'loss_on_disposal' => '5900.1',
        'impairment_expense' => '5900.1',
        'output_vat_payable' => '2100.1',
        'withholding_tax_payable' => '2100.2',
        'tax_clearing' => '2100.4',
        'merchandise_sales' => '4400.1',
        'inventory_adjustment_income' => '4000.3',
        'expense' => '5900.1',
        'cogs' => '5000.5',
        'inventory_adjustment_expense' => '5000.5',
        'inventory_wastage' => '5000.5',
        'fx_gain' => '4900.1',
        'unrealized_fx_gain' => '4900.4',
        'fx_loss' => '5900.1',
        'unrealized_fx_loss' => '5900.1',
        'tuition_income' => '4100.1',
        'admission_income' => '4100.2',
        'registration_exam_certificate_income' => '4000.2',
        'default_revenue' => '4000.1',
        'default_expense' => '5000.5',
        'output_vat' => '2100.1',
        'tds_payable' => '2100.2',
        'tds_receivable' => '1200.3',
        'sales' => '4000.1',
        'service_revenue' => '4000.2',
        'retained_earnings' => '3400.1',
        'salary' => '5100.1',
        'basic_salary' => '5100.1',
        'depreciation' => '5400.1',
        'interest_expense' => '5300.1',
    ],
    'payment_methods' => [
        'cash' => ['label' => 'Cash', 'type' => 'cash', 'is_bank_like' => false],
        'bank' => ['label' => 'Bank Transfer', 'type' => 'bank', 'is_bank_like' => true],
        'cheque' => ['label' => 'Cheque', 'type' => 'bank', 'is_bank_like' => true],
        'card' => ['label' => 'Card', 'type' => 'digital', 'is_bank_like' => false],
        'online' => ['label' => 'Online Payment', 'type' => 'digital', 'is_bank_like' => false],
        'bkash' => ['label' => 'bKash', 'type' => 'mobile_wallet', 'is_bank_like' => true, 'country' => 'BD'],
        'nagad' => ['label' => 'Nagad', 'type' => 'mobile_wallet', 'is_bank_like' => true, 'country' => 'BD'],
        'rocket' => ['label' => 'Rocket', 'type' => 'mobile_wallet', 'is_bank_like' => true, 'country' => 'BD'],
    ],
    'default_currency' => env('ACCOUNTING_DEFAULT_CURRENCY', 'BDT'),
    'fiscal_year_start_month' => env('ACCOUNTING_FY_START_MONTH', 1),
    'period_lock_enabled' => true,
    'snapshot_on_period_close' => true,

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
