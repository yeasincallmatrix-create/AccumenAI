<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Real Estate Module Configuration
    |--------------------------------------------------------------------------
    | Phase 1: Property Management (Foundation)
    | Phase 2: Leasing & Rental
    | Phase 3: Sales & CRM
    | Phase 4: Maintenance & Operations
    | Phase 5: Accounting & Reports (5A accounting + 5B reports)
    | Phase 6: Integrations + Custom module support (FINAL phase)
    | Future phases will extend this.
    */

    'engines' => [
        'property' => [
            'name' => 'Property Management',
            'icon' => 'bi-building',
            'required' => true,
            'description' => 'Properties, Buildings, Units, Owners',
            'modules' => [
                'real_estate.properties' => ['name' => 'Properties', 'icon' => 'bi-house', 'sort_order' => 1],
                'real_estate.buildings' => ['name' => 'Buildings / Projects', 'icon' => 'bi-buildings', 'sort_order' => 2],
                'real_estate.units' => ['name' => 'Units', 'icon' => 'bi-door-open', 'sort_order' => 3],
                'real_estate.owners' => ['name' => 'Owners', 'icon' => 'bi-person-badge', 'sort_order' => 4],
                'real_estate.property_types' => ['name' => 'Property Types', 'icon' => 'bi-tags', 'sort_order' => 5],
                'real_estate.amenities' => ['name' => 'Amenities', 'icon' => 'bi-star', 'sort_order' => 6],
                'real_estate.documents' => ['name' => 'Documents', 'icon' => 'bi-file-earmark-text', 'sort_order' => 7],
            ],
        ],

        'leasing' => [
            'name' => 'Leasing & Rental',
            'icon' => 'bi-key',
            'required' => false,
            'description' => 'Leases, Tenants, Rent, Deposits',
            'modules' => [
                'real_estate.leases' => ['name' => 'Leases', 'icon' => 'bi-file-earmark-text', 'sort_order' => 10],
                'real_estate.tenants' => ['name' => 'Tenants', 'icon' => 'bi-people', 'sort_order' => 11],
                'real_estate.rent_invoices' => ['name' => 'Rent Invoices', 'icon' => 'bi-receipt', 'sort_order' => 12],
                'real_estate.rent_collection' => ['name' => 'Rent Collection', 'icon' => 'bi-cash-stack', 'sort_order' => 13],
                'real_estate.security_deposits' => ['name' => 'Security Deposits', 'icon' => 'bi-shield-lock', 'sort_order' => 14],
                'real_estate.lease_renewals' => ['name' => 'Lease Renewals', 'icon' => 'bi-arrow-repeat', 'sort_order' => 15],
                'real_estate.utility_billing' => ['name' => 'Utility Billing', 'icon' => 'bi-lightning', 'sort_order' => 16],
                'real_estate.cam_charges' => ['name' => 'CAM Charges', 'icon' => 'bi-tools', 'sort_order' => 17],
            ],
        ],

        'sales_crm' => [
            'name' => 'Sales & CRM',
            'icon' => 'bi-funnel',
            'required' => false,
            'description' => 'Leads, Visits, Bookings, Agreements',
            'modules' => [
                'real_estate.leads' => ['name' => 'Leads', 'icon' => 'bi-funnel', 'sort_order' => 20],
                'real_estate.site_visits' => ['name' => 'Site Visits', 'icon' => 'bi-geo-alt', 'sort_order' => 21],
                'real_estate.bookings' => ['name' => 'Bookings', 'icon' => 'bi-calendar-check', 'sort_order' => 22],
                'real_estate.sales_agreements' => ['name' => 'Sales Agreements', 'icon' => 'bi-file-earmark-check', 'sort_order' => 23],
                'real_estate.installments' => ['name' => 'Installments', 'icon' => 'bi-calendar-range', 'sort_order' => 24],
                'real_estate.handover' => ['name' => 'Handover', 'icon' => 'bi-box-arrow-right', 'sort_order' => 25],
                'real_estate.after_sales' => ['name' => 'After Sales', 'icon' => 'bi-headset', 'sort_order' => 26],
            ],
        ],

        'maintenance' => [
            'name' => 'Maintenance & Operations',
            'icon' => 'bi-wrench',
            'required' => false,
            'description' => 'Maintenance, Work Orders, Inspections',
            'modules' => [
                'real_estate.maintenance_requests' => ['name' => 'Maintenance Requests', 'icon' => 'bi-tools', 'sort_order' => 30],
                'real_estate.work_orders' => ['name' => 'Work Orders', 'icon' => 'bi-clipboard-check', 'sort_order' => 31],
                'real_estate.vendors' => ['name' => 'Vendors', 'icon' => 'bi-people', 'sort_order' => 32],
                'real_estate.inspections' => ['name' => 'Inspections', 'icon' => 'bi-search', 'sort_order' => 33],
                'real_estate.assets' => ['name' => 'Assets', 'icon' => 'bi-box', 'sort_order' => 34],
                'real_estate.preventive_maintenance' => ['name' => 'Preventive Maintenance', 'icon' => 'bi-calendar-check', 'sort_order' => 35],
            ],
        ],

        'accounting' => [
            'name' => 'Accounting & Finance',
            'icon' => 'bi-calculator',
            'required' => true,
            'description' => 'Rent Income, Expenses, Statements',
            'modules' => [
                'real_estate.rent_income' => ['name' => 'Rent Income', 'icon' => 'bi-cash-coin', 'sort_order' => 40],
                'real_estate.property_expenses' => ['name' => 'Property Expenses', 'icon' => 'bi-wallet2', 'sort_order' => 41],
                'real_estate.service_charges' => ['name' => 'Service Charges', 'icon' => 'bi-receipt-cutoff', 'sort_order' => 42],
                'real_estate.tax_reports' => ['name' => 'Tax Reports', 'icon' => 'bi-percent', 'sort_order' => 43],
                'real_estate.financial_reports' => ['name' => 'Financial Reports', 'icon' => 'bi-graph-up', 'sort_order' => 44],
                'real_estate.owner_statements' => ['name' => 'Owner Statements', 'icon' => 'bi-file-earmark-text', 'sort_order' => 45],
                'real_estate.tenant_statements' => ['name' => 'Tenant Statements', 'icon' => 'bi-file-earmark-text', 'sort_order' => 46],
            ],
        ],

        'reports' => [
            'name' => 'Reports & Analytics',
            'icon' => 'bi-graph-up',
            'required' => true,
            'description' => 'Occupancy, Rent Roll, Portfolio',
            'modules' => [
                'real_estate.occupancy_report' => ['name' => 'Occupancy Report', 'icon' => 'bi-pie-chart', 'sort_order' => 50],
                'real_estate.rent_roll' => ['name' => 'Rent Roll', 'icon' => 'bi-list-ul', 'sort_order' => 51],
                'real_estate.aging_report' => ['name' => 'Aging Report', 'icon' => 'bi-clock-history', 'sort_order' => 52],
                'real_estate.profit_loss' => ['name' => 'Profit & Loss', 'icon' => 'bi-graph-up-arrow', 'sort_order' => 53],
                'real_estate.cash_flow' => ['name' => 'Cash Flow', 'icon' => 'bi-cash-stack', 'sort_order' => 54],
                'real_estate.portfolio_report' => ['name' => 'Portfolio Report', 'icon' => 'bi-briefcase', 'sort_order' => 55],
            ],
        ],

        'integrations' => [
            'name' => 'Integrations',
            'icon' => 'bi-link-45deg',
            'required' => false,
            'description' => 'Connect with other modules',
            'modules' => [
                'real_estate.sales_integration' => ['name' => 'Sales Integration', 'icon' => 'bi-cart', 'sort_order' => 60],
                'real_estate.purchase_integration' => ['name' => 'Purchase Integration', 'icon' => 'bi-bag', 'sort_order' => 61],
                'real_estate.finance_integration' => ['name' => 'Finance Integration', 'icon' => 'bi-cash', 'sort_order' => 62],
                'real_estate.accounting_integration' => ['name' => 'Accounting Integration', 'icon' => 'bi-journal-text', 'sort_order' => 63],
                'real_estate.hr_integration' => ['name' => 'HR Integration', 'icon' => 'bi-people', 'sort_order' => 64],
                'real_estate.crm_integration' => ['name' => 'CRM Integration', 'icon' => 'bi-person-lines-fill', 'sort_order' => 65],
                'real_estate.inventory_integration' => ['name' => 'Inventory Integration', 'icon' => 'bi-box', 'sort_order' => 66],
            ],
        ],
    ],

    'allow_custom_modules' => true,
    'custom_module_prefix' => 'real_estate.custom.',
];
