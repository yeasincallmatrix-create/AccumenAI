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
            'training_center.batches',
            'training_center.students',
            'training_center.classes',
            'training_center.attendance',
            'training_center.exams',
            'training_center.certificates',
            'training_center.fees',
            'training_center.reports',
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
            'manufacturing',
            'manufacturing.bom',
            'manufacturing.routing',
            'manufacturing.work_centers',
            'manufacturing.production_orders',
            'manufacturing.costing',
            'manufacturing.reports',
            'sales',
            'purchase',
            'inventory',
        ],
        'optional' => [
            'manufacturing.quality_control',
            'manufacturing.quality_lab',
            'manufacturing.sample_management',
            'manufacturing.regulatory_compliance',
            'manufacturing.batch_tracking',
            'manufacturing.expiry_tracking',
            'manufacturing.serial_number',
            'manufacturing.assembly_line',
            'manufacturing.mold_management',
            'manufacturing.recipe',
            'manufacturing.cutting',
            'manufacturing.welding',
            'manufacturing.finishing',
            'manufacturing.printing',
            'manufacturing.packaging',
            'manufacturing.warranty',
        ],
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
