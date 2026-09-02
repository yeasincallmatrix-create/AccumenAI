<?php

return [
    'defaults' => [
        'country' => env('TAX_DEFAULT_COUNTRY', 'BD'),
        'type' => 'vat',
        'rate_type' => 'percentage',
        'is_inclusive' => false,
    ],

    'countries' => [
        'BD' => [
            'name' => 'Bangladesh',
            'vat_rate' => 15.0,
            'types' => ['vat', 'sd', 'ait', 'at'],
            'is_inclusive' => false,
            'return_frequency' => 'monthly',
            'accounts' => [
                'output' => '2100',
                'input' => '1201',
                'clearing' => '2102',
                'withholding' => '2101',
            ],
            'rules' => [
                ['item_type' => '*', 'rate' => 15.0, 'type' => 'vat', 'description' => 'Standard VAT'],
                ['item_type' => 'exempt', 'rate' => 0, 'type' => 'vat', 'description' => 'Exempt goods'],
            ],
        ],
        'US' => [
            'name' => 'United States',
            'vat_rate' => 0.0,
            'types' => ['sales_tax', 'excise'],
            'is_inclusive' => false,
            'return_frequency' => 'monthly',
            'accounts' => [
                'output' => '2100',
                'input' => '1201',
                'clearing' => '2102',
                'withholding' => '2101',
            ],
            'rules' => [],
        ],
        'IN' => [
            'name' => 'India',
            'vat_rate' => 18.0,
            'types' => ['gst', 'cgst', 'sgst', 'igst'],
            'is_inclusive' => false,
            'return_frequency' => 'monthly',
            'accounts' => [
                'output' => '2100',
                'input' => '1201',
                'clearing' => '2102',
                'withholding' => '2101',
            ],
            'rules' => [
                ['item_type' => '*', 'rate' => 18.0, 'type' => 'gst', 'description' => 'Standard GST'],
                ['item_type' => 'essential', 'rate' => 5.0, 'type' => 'gst', 'description' => 'Essential goods'],
                ['item_type' => 'luxury', 'rate' => 28.0, 'type' => 'gst', 'description' => 'Luxury goods'],
            ],
        ],
    ],

    'compound_order' => ['vat', 'excise', 'withholding'],

    'audit_enabled' => true,
];
