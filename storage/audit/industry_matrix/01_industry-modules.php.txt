<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Industry-Module Matrix
    |--------------------------------------------------------------------------
    |
    | Defines which modules are available per industry.
    |
    | - default:  Modules enabled automatically (cannot be disabled)
    | - optional: Modules tenant can enable/disable
    | - disabled: Modules NOT available for this industry
    |
    */

    'healthcare' => [
        'name' => 'Medical / Healthcare',
        'default' => [
            'purchase',
            'medical',
            'medical.billing',
        ],
        'optional' => [
            'sales',
        ],
        'disabled' => [
            'education',
            'training_center',
        ],
    ],

    'education' => [
        'name' => 'Education',
        'default' => [
            'education',
            'education.fees',
        ],
        'optional' => [
            'sales',
            'purchase',
        ],
        'disabled' => [
            'medical',
            'medical.billing',
            'training_center',
        ],
    ],

    'training_center' => [
        'name' => 'Training Center',
        'default' => [
            'training_center',
            'training_center.courses',
        ],
        'optional' => [
            'sales',
            'purchase',
        ],
        'disabled' => [
            'medical',
            'medical.billing',
            'education',
        ],
    ],

    'retail' => [
        'name' => 'Retail / Wholesale',
        'default' => [
            'sales',
            'purchase',
            'inventory',
            'pos',
        ],
        'optional' => [],
        'disabled' => [
            'medical',
            'medical.billing',
            'education',
            'training_center',
        ],
    ],

    'manufacturing' => [
        'name' => 'Manufacturing',
        'default' => [
            'sales',
            'purchase',
            'inventory',
            'manufacturing',
        ],
        'optional' => [],
        'disabled' => [
            'medical',
            'medical.billing',
            'education',
            'training_center',
        ],
    ],

    'real_estate' => [
        'name' => 'Real Estate',
        'default' => [],
        'optional' => [
            'sales',
            'purchase',
        ],
        'disabled' => [
            'medical',
            'medical.billing',
            'education',
            'training_center',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Core Modules (Always available in ALL industries)
    |--------------------------------------------------------------------------
    */
    'core' => [
        'crm',
        'accounting',
        'finance',
        'reports',
        'notifications',
        'ai',
        'vat',
    ],
];
