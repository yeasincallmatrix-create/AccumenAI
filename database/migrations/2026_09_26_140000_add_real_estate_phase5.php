<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 5 — Real Estate Accounting & Reports: 13 more children under
     * the existing `real_estate` parent (7 + 8 + 7 + 6 + 13 = 41 total).
     *
     * Phase 5A: Accounting (7, sort_order 40-46).
     * Phase 5B: Reports (6, sort_order 50-55).
     *
     * Idempotent: updateOrInsert keyed on `key`, so a re-run is a no-op.
     * Phase 1-4 rows are never touched.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        $parent = DB::table('module_registry')->where('key', 'real_estate')->first();

        $children = [
            // Phase 5A: Accounting
            ['key' => 'real_estate.rent_income', 'name' => 'Rent Income', 'icon' => 'bi-cash-coin', 'sort_order' => 40],
            ['key' => 'real_estate.property_expenses', 'name' => 'Property Expenses', 'icon' => 'bi-wallet2', 'sort_order' => 41],
            ['key' => 'real_estate.service_charges', 'name' => 'Service Charges', 'icon' => 'bi-receipt-cutoff', 'sort_order' => 42],
            ['key' => 'real_estate.tax_reports', 'name' => 'Tax Reports', 'icon' => 'bi-percent', 'sort_order' => 43],
            ['key' => 'real_estate.financial_reports', 'name' => 'Financial Reports', 'icon' => 'bi-graph-up', 'sort_order' => 44],
            ['key' => 'real_estate.owner_statements', 'name' => 'Owner Statements', 'icon' => 'bi-file-earmark-text', 'sort_order' => 45],
            ['key' => 'real_estate.tenant_statements', 'name' => 'Tenant Statements', 'icon' => 'bi-file-earmark-text', 'sort_order' => 46],

            // Phase 5B: Reports
            ['key' => 'real_estate.occupancy_report', 'name' => 'Occupancy Report', 'icon' => 'bi-pie-chart', 'sort_order' => 50],
            ['key' => 'real_estate.rent_roll', 'name' => 'Rent Roll', 'icon' => 'bi-list-ul', 'sort_order' => 51],
            ['key' => 'real_estate.aging_report', 'name' => 'Aging Report', 'icon' => 'bi-clock-history', 'sort_order' => 52],
            ['key' => 'real_estate.profit_loss', 'name' => 'Profit & Loss', 'icon' => 'bi-graph-up-arrow', 'sort_order' => 53],
            ['key' => 'real_estate.cash_flow', 'name' => 'Cash Flow', 'icon' => 'bi-cash-stack', 'sort_order' => 54],
            ['key' => 'real_estate.portfolio_report', 'name' => 'Portfolio Report', 'icon' => 'bi-briefcase', 'sort_order' => 55],
        ];

        $inserted = 0;
        foreach ($children as $child) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $child['key']],
                [
                    'name' => $child['name'],
                    'parent_key' => 'real_estate',
                    'type' => $parent->type ?? 'industry',
                    'is_core' => (int) ($parent->is_core ?? 0),
                    'description' => null,
                    'dependencies' => null,
                    'sort_order' => $child['sort_order'],
                    'icon' => $child['icon'],
                    'coming_soon' => false,
                    'index_route' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $inserted++;
        }

        echo "Inserted {$inserted} real estate phase 5 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'real_estate.rent_income', 'real_estate.property_expenses', 'real_estate.service_charges',
            'real_estate.tax_reports', 'real_estate.financial_reports',
            'real_estate.owner_statements', 'real_estate.tenant_statements',
            'real_estate.occupancy_report', 'real_estate.rent_roll', 'real_estate.aging_report',
            'real_estate.profit_loss', 'real_estate.cash_flow', 'real_estate.portfolio_report',
        ])->delete();
    }
};
