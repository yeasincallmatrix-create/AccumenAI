<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Elaborate 9 already-shipped parents into parent → parent.child pairs so
     * /admin/module-config renders them as collapsible groups.
     *
     * manufacturing and pos are intentionally left childless (not built yet).
     * Idempotent: updateOrInsert keyed on `key`, so a re-run is a no-op.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        $children = [
            // ═══════════════════════════════════════════════════════
            // ACCOUNTING (28 children)
            // ═══════════════════════════════════════════════════════
            'accounting' => [
                ['key' => 'accounting.chart_of_accounts', 'name' => 'Chart of Accounts', 'icon' => 'bi-list-ul', 'sort_order' => 1],
                ['key' => 'accounting.journal_entries', 'name' => 'Journal Entries', 'icon' => 'bi-journal-text', 'sort_order' => 2],
                ['key' => 'accounting.general_ledger', 'name' => 'General Ledger', 'icon' => 'bi-book', 'sort_order' => 3],
                ['key' => 'accounting.periods', 'name' => 'Accounting Periods', 'icon' => 'bi-calendar', 'sort_order' => 4],
                ['key' => 'accounting.fiscal_years', 'name' => 'Fiscal Years', 'icon' => 'bi-calendar-range', 'sort_order' => 5],
                ['key' => 'accounting.invoices', 'name' => 'Invoices', 'icon' => 'bi-receipt', 'sort_order' => 10],
                ['key' => 'accounting.bills', 'name' => 'Bills', 'icon' => 'bi-file-earmark-text', 'sort_order' => 11],
                ['key' => 'accounting.payments', 'name' => 'Payments', 'icon' => 'bi-cash', 'sort_order' => 12],
                ['key' => 'accounting.expenses', 'name' => 'Expenses', 'icon' => 'bi-wallet2', 'sort_order' => 13],
                ['key' => 'accounting.receipts', 'name' => 'Receipts', 'icon' => 'bi-receipt-cutoff', 'sort_order' => 14],
                ['key' => 'accounting.receivables', 'name' => 'Receivables', 'icon' => 'bi-arrow-down-circle', 'sort_order' => 15],
                ['key' => 'accounting.payables', 'name' => 'Payables', 'icon' => 'bi-arrow-up-circle', 'sort_order' => 16],
                ['key' => 'accounting.bank_feed', 'name' => 'Bank Feed', 'icon' => 'bi-rss', 'sort_order' => 20],
                ['key' => 'accounting.bank_reconciliation', 'name' => 'Bank Reconciliation', 'icon' => 'bi-arrow-left-right', 'sort_order' => 21],
                ['key' => 'accounting.bank_accounts', 'name' => 'Bank Accounts', 'icon' => 'bi-bank', 'sort_order' => 22],
                ['key' => 'accounting.reports', 'name' => 'Reports', 'icon' => 'bi-graph-up', 'sort_order' => 30],
                ['key' => 'accounting.trial_balance', 'name' => 'Trial Balance', 'icon' => 'bi-bar-chart', 'sort_order' => 31],
                ['key' => 'accounting.balance_sheet', 'name' => 'Balance Sheet', 'icon' => 'bi-clipboard-data', 'sort_order' => 32],
                ['key' => 'accounting.profit_loss', 'name' => 'Profit & Loss', 'icon' => 'bi-graph-up-arrow', 'sort_order' => 33],
                ['key' => 'accounting.cash_flow', 'name' => 'Cash Flow', 'icon' => 'bi-cash-stack', 'sort_order' => 34],
                ['key' => 'accounting.ratios', 'name' => 'Ratio Analysis', 'icon' => 'bi-percent', 'sort_order' => 35],
                ['key' => 'accounting.tax_reports', 'name' => 'Tax Reports', 'icon' => 'bi-file-tax', 'sort_order' => 40],
                ['key' => 'accounting.vat_input', 'name' => 'Input VAT', 'icon' => 'bi-arrow-down', 'sort_order' => 41],
                ['key' => 'accounting.vat_output', 'name' => 'Output VAT', 'icon' => 'bi-arrow-up', 'sort_order' => 42],
                ['key' => 'accounting.vat_summary', 'name' => 'VAT Summary', 'icon' => 'bi-file-earmark-bar-graph', 'sort_order' => 43],
                ['key' => 'accounting.executive_dashboard', 'name' => 'Executive Dashboard', 'icon' => 'bi-speedometer2', 'sort_order' => 50],
                ['key' => 'accounting.approvals', 'name' => 'Approvals', 'icon' => 'bi-check2-circle', 'sort_order' => 51],
                ['key' => 'accounting.security_audit', 'name' => 'Security Audit', 'icon' => 'bi-shield-check', 'sort_order' => 52],
            ],

            // ═══════════════════════════════════════════════════════
            // HR (7 children)
            // ═══════════════════════════════════════════════════════
            'hr' => [
                ['key' => 'hr.employees', 'name' => 'Employees', 'icon' => 'bi-people', 'sort_order' => 1],
                ['key' => 'hr.attendance', 'name' => 'Attendance', 'icon' => 'bi-clock', 'sort_order' => 2],
                ['key' => 'hr.payroll', 'name' => 'Payroll', 'icon' => 'bi-cash-stack', 'sort_order' => 3],
                ['key' => 'hr.leaves', 'name' => 'Leaves', 'icon' => 'bi-calendar-x', 'sort_order' => 4],
                ['key' => 'hr.departments', 'name' => 'Departments', 'icon' => 'bi-diagram-3', 'sort_order' => 5],
                ['key' => 'hr.designations', 'name' => 'Designations', 'icon' => 'bi-award', 'sort_order' => 6],
                ['key' => 'hr.recruitment', 'name' => 'Recruitment', 'icon' => 'bi-person-plus', 'sort_order' => 7],
            ],

            // ═══════════════════════════════════════════════════════
            // AI (4 children)
            // ═══════════════════════════════════════════════════════
            'ai' => [
                ['key' => 'ai.assistant', 'name' => 'AI Assistant', 'icon' => 'bi-chat-dots', 'sort_order' => 1],
                ['key' => 'ai.analysis', 'name' => 'AI Analysis', 'icon' => 'bi-graph-up', 'sort_order' => 2],
                ['key' => 'ai.insights', 'name' => 'AI Insights', 'icon' => 'bi-lightbulb', 'sort_order' => 3],
                ['key' => 'ai.tools', 'name' => 'AI Tools', 'icon' => 'bi-tools', 'sort_order' => 4],
            ],

            // ═══════════════════════════════════════════════════════
            // REPORTS (6 children)
            // ═══════════════════════════════════════════════════════
            'reports' => [
                ['key' => 'reports.sales', 'name' => 'Sales Reports', 'icon' => 'bi-cart', 'sort_order' => 1],
                ['key' => 'reports.purchase', 'name' => 'Purchase Reports', 'icon' => 'bi-bag', 'sort_order' => 2],
                ['key' => 'reports.financial', 'name' => 'Financial Reports', 'icon' => 'bi-cash-stack', 'sort_order' => 3],
                ['key' => 'reports.inventory', 'name' => 'Inventory Reports', 'icon' => 'bi-box', 'sort_order' => 4],
                ['key' => 'reports.tax', 'name' => 'Tax Reports', 'icon' => 'bi-percent', 'sort_order' => 5],
                ['key' => 'reports.hr', 'name' => 'HR Reports', 'icon' => 'bi-people', 'sort_order' => 6],
            ],

            // ═══════════════════════════════════════════════════════
            // VAT (4 children)
            // ═══════════════════════════════════════════════════════
            'vat' => [
                ['key' => 'vat.rates', 'name' => 'VAT Rates', 'icon' => 'bi-percent', 'sort_order' => 1],
                ['key' => 'vat.returns', 'name' => 'VAT Returns', 'icon' => 'bi-file-earmark-text', 'sort_order' => 2],
                ['key' => 'vat.reports', 'name' => 'VAT Reports', 'icon' => 'bi-graph-up', 'sort_order' => 3],
                ['key' => 'vat.transactions', 'name' => 'VAT Transactions', 'icon' => 'bi-list-ul', 'sort_order' => 4],
            ],

            // ═══════════════════════════════════════════════════════
            // TDS (4 children)
            // ═══════════════════════════════════════════════════════
            'tds' => [
                ['key' => 'tds.rates', 'name' => 'TDS Rates', 'icon' => 'bi-percent', 'sort_order' => 1],
                ['key' => 'tds.deductions', 'name' => 'TDS Deductions', 'icon' => 'bi-file-earmark-minus', 'sort_order' => 2],
                ['key' => 'tds.returns', 'name' => 'TDS Returns', 'icon' => 'bi-file-earmark-text', 'sort_order' => 3],
                ['key' => 'tds.reports', 'name' => 'TDS Reports', 'icon' => 'bi-graph-up', 'sort_order' => 4],
            ],

            // ═══════════════════════════════════════════════════════
            // CRM (6 children)
            // ═══════════════════════════════════════════════════════
            'crm' => [
                ['key' => 'crm.contacts', 'name' => 'Contacts', 'icon' => 'bi-person-lines-fill', 'sort_order' => 1],
                ['key' => 'crm.leads', 'name' => 'Leads', 'icon' => 'bi-funnel', 'sort_order' => 2],
                ['key' => 'crm.organizations', 'name' => 'Organizations', 'icon' => 'bi-building', 'sort_order' => 3],
                ['key' => 'crm.activities', 'name' => 'Activities', 'icon' => 'bi-activity', 'sort_order' => 4],
                ['key' => 'crm.tasks', 'name' => 'Tasks', 'icon' => 'bi-check2-square', 'sort_order' => 5],
                ['key' => 'crm.reports', 'name' => 'CRM Reports', 'icon' => 'bi-graph-up', 'sort_order' => 6],
            ],

            // ═══════════════════════════════════════════════════════
            // FINANCE (5 children)
            // ═══════════════════════════════════════════════════════
            'finance' => [
                ['key' => 'finance.invoices', 'name' => 'Invoices', 'icon' => 'bi-receipt', 'sort_order' => 1],
                ['key' => 'finance.payments', 'name' => 'Payments', 'icon' => 'bi-cash', 'sort_order' => 2],
                ['key' => 'finance.expenses', 'name' => 'Expenses', 'icon' => 'bi-wallet2', 'sort_order' => 3],
                ['key' => 'finance.parties', 'name' => 'Parties', 'icon' => 'bi-people', 'sort_order' => 4],
                ['key' => 'finance.progressive_contracts', 'name' => 'Progressive Contracts', 'icon' => 'bi-file-earmark-text', 'sort_order' => 5],
            ],

            // ═══════════════════════════════════════════════════════
            // INVENTORY (9 children)
            // ═══════════════════════════════════════════════════════
            'inventory' => [
                ['key' => 'inventory.items', 'name' => 'Items / Products', 'icon' => 'bi-box', 'sort_order' => 1],
                ['key' => 'inventory.stock_ledger', 'name' => 'Stock Ledger', 'icon' => 'bi-journal', 'sort_order' => 2],
                ['key' => 'inventory.warehouses', 'name' => 'Warehouses', 'icon' => 'bi-building', 'sort_order' => 3],
                ['key' => 'inventory.adjustments', 'name' => 'Stock Adjustments', 'icon' => 'bi-sliders', 'sort_order' => 4],
                ['key' => 'inventory.batches', 'name' => 'Batches', 'icon' => 'bi-layers', 'sort_order' => 5],
                ['key' => 'inventory.transfers', 'name' => 'Stock Transfers', 'icon' => 'bi-arrow-left-right', 'sort_order' => 6],
                ['key' => 'inventory.barcode', 'name' => 'Barcode Search', 'icon' => 'bi-upc-scan', 'sort_order' => 7],
                ['key' => 'inventory.counts', 'name' => 'Stock Counts', 'icon' => 'bi-clipboard-check', 'sort_order' => 8],
                ['key' => 'inventory.reports', 'name' => 'Inventory Reports', 'icon' => 'bi-graph-up', 'sort_order' => 9],
            ],
        ];

        $inserted = 0;

        foreach ($children as $parentKey => $childList) {
            $parent = DB::table('module_registry')->where('key', $parentKey)->first();

            if (! $parent) {
                echo "Parent not found: {$parentKey}\n";

                continue;
            }

            foreach ($childList as $child) {
                DB::table('module_registry')->updateOrInsert(
                    ['key' => $child['key']],
                    [
                        'name' => $child['name'],
                        'parent_key' => $parentKey,
                        'type' => $parent->type ?? 'core',
                        'is_core' => (int) $parent->is_core,
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
        }

        echo "Inserted {$inserted} children.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        $allKeys = [
            // accounting (28)
            'accounting.chart_of_accounts', 'accounting.journal_entries', 'accounting.general_ledger',
            'accounting.periods', 'accounting.fiscal_years', 'accounting.invoices', 'accounting.bills',
            'accounting.payments', 'accounting.expenses', 'accounting.receipts', 'accounting.receivables',
            'accounting.payables', 'accounting.bank_feed', 'accounting.bank_reconciliation', 'accounting.bank_accounts',
            'accounting.reports', 'accounting.trial_balance', 'accounting.balance_sheet', 'accounting.profit_loss',
            'accounting.cash_flow', 'accounting.ratios', 'accounting.tax_reports', 'accounting.vat_input',
            'accounting.vat_output', 'accounting.vat_summary', 'accounting.executive_dashboard',
            'accounting.approvals', 'accounting.security_audit',
            // hr (7)
            'hr.employees', 'hr.attendance', 'hr.payroll', 'hr.leaves', 'hr.departments', 'hr.designations', 'hr.recruitment',
            // ai (4)
            'ai.assistant', 'ai.analysis', 'ai.insights', 'ai.tools',
            // reports (6)
            'reports.sales', 'reports.purchase', 'reports.financial', 'reports.inventory', 'reports.tax', 'reports.hr',
            // vat (4)
            'vat.rates', 'vat.returns', 'vat.reports', 'vat.transactions',
            // tds (4)
            'tds.rates', 'tds.deductions', 'tds.returns', 'tds.reports',
            // crm (6)
            'crm.contacts', 'crm.leads', 'crm.organizations', 'crm.activities', 'crm.tasks', 'crm.reports',
            // finance (5)
            'finance.invoices', 'finance.payments', 'finance.expenses', 'finance.parties', 'finance.progressive_contracts',
            // inventory (9)
            'inventory.items', 'inventory.stock_ledger', 'inventory.warehouses', 'inventory.adjustments',
            'inventory.batches', 'inventory.transfers', 'inventory.barcode', 'inventory.counts', 'inventory.reports',
        ];

        DB::table('module_registry')
            ->whereIn('key', $allKeys)
            ->whereIn('parent_key', ['accounting', 'hr', 'ai', 'reports', 'vat', 'tds', 'crm', 'finance', 'inventory'])
            ->delete();
    }
};
