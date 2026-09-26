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
            'pos.terminal',
            'pos.register',
            'pos.cart',
            'pos.checkout',
            'pos.receipt',
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
            'pos',
            'pos.terminal',
            'pos.register',
            'pos.cart',
            'pos.checkout',
            'pos.receipt',
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
        'default' => [
            'real_estate',
            'real_estate.properties',
            'real_estate.buildings',
            'real_estate.units',
            'real_estate.owners',
            'real_estate.leases',
            'real_estate.tenants',
            'real_estate.rent_invoices',
            'real_estate.rent_collection',
            'real_estate.leads',
            'real_estate.site_visits',
            'real_estate.bookings',
            'real_estate.maintenance_requests',
            'real_estate.work_orders',
            'real_estate.rent_income',
            'real_estate.property_expenses',
            'real_estate.occupancy_report',
            'real_estate.rent_roll',
        ],
        'optional' => [
            'real_estate.property_types',
            'real_estate.amenities',
            'real_estate.documents',
            'real_estate.security_deposits',
            'real_estate.lease_renewals',
            'real_estate.utility_billing',
            'real_estate.cam_charges',
            'real_estate.sales_agreements',
            'real_estate.installments',
            'real_estate.handover',
            'real_estate.after_sales',
            'real_estate.vendors',
            'real_estate.inspections',
            'real_estate.assets',
            'real_estate.preventive_maintenance',
            'real_estate.service_charges',
            'real_estate.tax_reports',
            'real_estate.financial_reports',
            'real_estate.owner_statements',
            'real_estate.tenant_statements',
            'real_estate.aging_report',
            'real_estate.profit_loss',
            'real_estate.cash_flow',
            'real_estate.portfolio_report',
            'real_estate.sales_integration',
            'real_estate.purchase_integration',
            'real_estate.finance_integration',
            'real_estate.accounting_integration',
            'real_estate.hr_integration',
            'real_estate.crm_integration',
            'real_estate.inventory_integration',
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
