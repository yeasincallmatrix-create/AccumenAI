<?php

/*
|--------------------------------------------------------------------------
| Universal Module Config — Module Groups
|--------------------------------------------------------------------------
|
| Groups rendered by the platform-wide module matrix at /admin/module-config.
| Every entry maps a group key to a label, a Bootstrap icon and the
| module_registry keys it owns. Groups are disjoint: a module key appears in
| exactly one group so the matrix can never receive two conflicting categories
| for the same key.
|
| Keys listed here are resolved against module_registry at render time — keys
| that are not registered yet (forward-looking entries such as `bom`,
| `property`) are simply skipped, never rendered as broken rows.
|
*/

return [
    'core' => [
        'label' => 'Core Modules',
        'icon' => 'bi-house-gear',
        'description' => 'Platform backbone shared by every industry.',
        'modules' => [
            'crm',
            'accounting',
            'finance',
            'reports',
            'notifications',
            'ai',
            'vat',
            'tds',
            'hr',
        ],
    ],

    'medical' => [
        'label' => 'Medical Modules',
        'icon' => 'bi-hospital',
        'description' => 'Hospital / clinic department modules.',
        'modules' => [
            'medical',
            'medical.opd',
            'medical.ipd',
            'medical.pharmacy',
            'medical.laboratory',
            'medical.billing',
            'medical.emergency',
            'medical.radiology',
            'medical.bloodbank',
            'medical.physiotherapy',
            'medical.dental',
            'medical.vaccination',
            'medical.ambulance',
            'medical.diet',
            'medical.records',
        ],
    ],

    'education' => [
        'label' => 'Education Modules',
        'icon' => 'bi-mortarboard',
        'description' => 'School, college and university modules.',
        'modules' => [
            'education',
            'education.students',
            'education.classes',
            'education.exams',
            'education.attendance',
            'education.fees',
            'education.guardians',
            'education.analytics',
        ],
    ],

    'training_center' => [
        'label' => 'Training Center Modules',
        'icon' => 'bi-easel',
        'description' => 'Vocational, IT and language training modules.',
        'modules' => [
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
    ],

    'sales' => [
        'label' => 'Sales Modules',
        'icon' => 'bi-graph-up',
        'description' => 'Quotations, orders, deliveries and POS.',
        'modules' => [
            'sales',
            'pos',
            'sales.quotations',
            'sales.orders',
            'sales.deliveries',
            'sales.returns',
            'sales.leads',
            'sales.customers',
            'sales.reports',
        ],
    ],

    'purchase' => [
        'label' => 'Purchase Modules',
        'icon' => 'bi-cart4',
        'description' => 'Procurement, receipts and supplier returns.',
        'modules' => [
            'purchase',
            'purchase.quotations',
            'purchase.orders',
            'purchase.invoices',
            'purchase.requests',
            'purchase.returns',
            'purchase.receipts',
        ],
    ],

    'inventory' => [
        'label' => 'Inventory Modules',
        'icon' => 'bi-box-seam',
        'description' => 'Stock, warehouses and item control.',
        'modules' => [
            'inventory',
        ],
    ],

    'manufacturing' => [
        'label' => 'Manufacturing Modules',
        'icon' => 'bi-gear-wide-connected',
        'description' => 'Bills of material, production and shop floor.',
        'modules' => [
            'manufacturing',
            'bom',
            'production',
        ],
    ],

    'real_estate' => [
        'label' => 'Real Estate Modules',
        'name' => 'Real Estate Modules',
        'icon' => 'bi-building',
        'parent_key' => 'real_estate',
        'description' => 'Properties, buildings, units and owners.',
        'modules' => [
            'real_estate',
            'property',
            'lease',
            'tenant',
        ],
    ],
];
