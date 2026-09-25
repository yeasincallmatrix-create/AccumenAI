<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Universal Manufacturing Configuration
    |--------------------------------------------------------------------------
    |
    | No specific niche. All modules available.
    | Admin picks & chooses (optional modules).
    | Custom modules can be created via admin UI.
    |
    */

    'engines' => [
        'core_manufacturing' => [
            'name' => 'Core Manufacturing Engine',
            'icon' => 'bi-gear',
            'required' => true,
            'description' => 'BOM, Routing, Work Centers, Production Orders',
            'modules' => [
                'manufacturing.bom' => ['name' => 'Bill of Materials', 'icon' => 'bi-list-check', 'sort_order' => 1],
                'manufacturing.routing' => ['name' => 'Routing', 'icon' => 'bi-diagram-3', 'sort_order' => 2],
                'manufacturing.work_centers' => ['name' => 'Work Centers', 'icon' => 'bi-gear-wide', 'sort_order' => 3],
                'manufacturing.production_orders' => ['name' => 'Production Orders', 'icon' => 'bi-clipboard-check', 'sort_order' => 4],
            ],
        ],

        'quality' => [
            'name' => 'Quality Engine',
            'icon' => 'bi-check2-circle',
            'required' => false,
            'description' => 'QC, Lab, Sample, Regulatory',
            'modules' => [
                'manufacturing.quality_control' => ['name' => 'Quality Control', 'icon' => 'bi-check2-circle', 'sort_order' => 10],
                'manufacturing.quality_lab' => ['name' => 'Quality Lab', 'icon' => 'bi-clipboard-pulse', 'sort_order' => 11],
                'manufacturing.sample_management' => ['name' => 'Sample Management', 'icon' => 'bi-file-earmark', 'sort_order' => 12],
                'manufacturing.regulatory_compliance' => ['name' => 'Regulatory Compliance', 'icon' => 'bi-shield-check', 'sort_order' => 13],
            ],
        ],

        'traceability' => [
            'name' => 'Traceability Engine',
            'icon' => 'bi-upc-scan',
            'required' => false,
            'description' => 'Batch, Expiry, Serial',
            'modules' => [
                'manufacturing.batch_tracking' => ['name' => 'Batch Tracking', 'icon' => 'bi-layers', 'sort_order' => 20],
                'manufacturing.expiry_tracking' => ['name' => 'Expiry Tracking', 'icon' => 'bi-calendar-x', 'sort_order' => 21],
                'manufacturing.serial_number' => ['name' => 'Serial Number', 'icon' => 'bi-upc', 'sort_order' => 22],
            ],
        ],

        'cost' => [
            'name' => 'Cost Engine',
            'icon' => 'bi-calculator',
            'required' => true,
            'description' => 'Material, Labor, Machine, Overhead',
            'modules' => [
                'manufacturing.costing' => ['name' => 'Costing', 'icon' => 'bi-calculator', 'sort_order' => 30],
            ],
        ],

        'production_extension' => [
            'name' => 'Production Extension',
            'icon' => 'bi-diagram-3',
            'required' => false,
            'description' => 'Assembly Line, Mold Management',
            'modules' => [
                'manufacturing.assembly_line' => ['name' => 'Assembly Line', 'icon' => 'bi-diagram-3', 'sort_order' => 40],
                'manufacturing.mold_management' => ['name' => 'Mold Management', 'icon' => 'bi-grid', 'sort_order' => 41],
            ],
        ],

        'process' => [
            'name' => 'Process Modules',
            'icon' => 'bi-tools',
            'required' => false,
            'description' => 'Recipe, Cutting, Welding, Finishing, Printing, Packaging',
            'modules' => [
                'manufacturing.recipe' => ['name' => 'Recipe / Formula', 'icon' => 'bi-journal-text', 'sort_order' => 50],
                'manufacturing.cutting' => ['name' => 'Cutting', 'icon' => 'bi-scissors', 'sort_order' => 51],
                'manufacturing.welding' => ['name' => 'Welding', 'icon' => 'bi-fire', 'sort_order' => 52],
                'manufacturing.finishing' => ['name' => 'Finishing', 'icon' => 'bi-brush', 'sort_order' => 53],
                'manufacturing.printing' => ['name' => 'Printing', 'icon' => 'bi-printer', 'sort_order' => 54],
                'manufacturing.packaging' => ['name' => 'Packaging', 'icon' => 'bi-box', 'sort_order' => 55],
            ],
        ],

        'post_sales' => [
            'name' => 'Post-Sales',
            'icon' => 'bi-shield-check',
            'required' => false,
            'description' => 'Warranty tracking',
            'modules' => [
                'manufacturing.warranty' => ['name' => 'Warranty', 'icon' => 'bi-shield-check', 'sort_order' => 60],
            ],
        ],

        'reports' => [
            'name' => 'Reports',
            'icon' => 'bi-graph-up',
            'required' => true,
            'description' => 'Production, Material, Quality, Cost reports',
            'modules' => [
                'manufacturing.reports' => ['name' => 'Manufacturing Reports', 'icon' => 'bi-graph-up', 'sort_order' => 70],
            ],
        ],
    ],

    'allow_custom_modules' => true,
    'custom_module_prefix' => 'manufacturing.custom.',
    'custom_module_types' => ['production_stage', 'quality_check', 'report', 'other'],
];
