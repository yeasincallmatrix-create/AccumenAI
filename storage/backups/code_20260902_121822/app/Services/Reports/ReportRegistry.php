<?php

namespace App\Services\Reports;

use App\Models\Institute;
use App\Services\ModuleAccessService;
use App\Support\IndustryRules;
use Illuminate\Support\Facades\Auth;

/**
 * Centralized, industry-aware report registry.
 *
 * Does NOT duplicate report calculations — it discovers and routes to existing
 * report services/controllers (FinancialReportService, SalesReportService,
 * PurchaseReportService, InventoryReportService, HrReportService, etc.).
 *
 * Each definition points to the existing handler via route name.
 */
class ReportRegistry
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            // ===== CORE — Finance / Accounting (module: finance) =====
            [
                'key' => 'finance.trial_balance',
                'title' => 'Trial Balance',
                'description' => 'All chart of accounts with opening, movements and closing balances.',
                'module' => 'finance',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'reports.financial.view',
                'route' => 'finance.reports.trial-balance',
                'handler' => 'FinancialReportService::trialBalance',
                'filters' => ['asOfDate','fiscalYearId'],
                'export' => false, 'print' => true, 'search' => false,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'finance.income_statement',
                'title' => 'Income Statement',
                'description' => 'Income, expense and net for a period.',
                'module' => 'finance',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'reports.financial.view',
                'route' => 'finance.reports.income-statement',
                'handler' => 'FinancialReportService::incomeStatement',
                'filters' => ['from','to','fiscalYearId'],
                'export' => false, 'print' => true, 'search' => false,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'finance.balance_sheet',
                'title' => 'Balance Sheet',
                'description' => 'Assets, liabilities and equity as of a date.',
                'module' => 'finance',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'reports.financial.view',
                'route' => 'finance.reports.balance-sheet',
                'handler' => 'FinancialReportService::balanceSheet',
                'filters' => ['asOfDate','fiscalYearId'],
                'export' => false, 'print' => true, 'search' => false,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'finance.general_ledger',
                'title' => 'General Ledger',
                'description' => 'Per-entry ledger with running balance.',
                'module' => 'finance',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'reports.financial.view',
                'route' => 'finance.reports.ledger',
                'handler' => 'FinancialReportService::generalLedger',
                'filters' => ['coaId','from','to','fiscalYearId'],
                'export' => false, 'print' => true, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'finance.cash_bank',
                'title' => 'Cash & Bank Balances',
                'description' => 'Cash and bank account balances and flows.',
                'module' => 'finance',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'reports.financial.view',
                'route' => 'finance.reports.cash-bank',
                'handler' => 'FinancialReportService::cashBankSummary',
                'filters' => ['asOfDate','from','to'],
                'export' => false, 'print' => true, 'search' => false,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'finance.receivables',
                'title' => 'Receivables',
                'description' => 'Customer receivables and aging.',
                'module' => 'finance',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'reports.financial.view',
                'route' => 'finance.reports.receivables',
                'handler' => 'ReceivablesPayablesService::customerBalances',
                'filters' => ['asOfDate'],
                'export' => false, 'print' => true, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'finance.payables',
                'title' => 'Payables',
                'description' => 'Supplier payables and aging.',
                'module' => 'finance',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'reports.financial.view',
                'route' => 'finance.reports.payables',
                'handler' => 'ReceivablesPayablesService::supplierBalances',
                'filters' => ['asOfDate'],
                'export' => false, 'print' => true, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],

            // ===== CORE — CRM =====
            [
                'key' => 'crm.summary',
                'title' => 'CRM Summary',
                'description' => 'Leads, contacts and organizations overview.',
                'module' => 'crm',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'crm.view',
                'route' => 'crm.dashboard',
                'handler' => 'CrmDashboard:summary',
                'filters' => ['from','to'],
                'export' => false, 'print' => false, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],

            // ===== CORE — Inventory (capability-gated, Retail/Healthcare/Manufacturing) =====
            [
                'key' => 'inventory.stock_on_hand',
                'title' => 'Stock On Hand',
                'description' => 'Current stock quantity and valuation per warehouse.',
                'module' => 'inventory',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'inventory.view',
                'route' => 'purchase.reports.inventory',
                'handler' => 'InventoryReportService::stockOnHand',
                'filters' => ['warehouseId','categoryId','asOfDate'],
                'export' => true, 'print' => true, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'inventory.low_stock',
                'title' => 'Low Stock',
                'description' => 'Items below reorder level.',
                'module' => 'inventory',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'inventory.view',
                'route' => 'purchase.reports.inventory',
                'handler' => 'InventoryReportService::lowStock',
                'filters' => ['warehouseId'],
                'export' => false, 'print' => true, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'inventory.movements',
                'title' => 'Stock Movements',
                'description' => 'All inventory movements with history.',
                'module' => 'inventory',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'inventory.view',
                'route' => 'purchase.reports.inventory',
                'handler' => 'InventoryReportService::movements',
                'filters' => ['warehouse_id','item_id','movement_type','from','to'],
                'export' => true, 'print' => false, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],

            // ===== CORE — Sales (module: sales) =====
            [
                'key' => 'sales.dashboard',
                'title' => 'Sales Dashboard',
                'description' => 'Orders, sales and returns totals.',
                'module' => 'sales',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'sales.view',
                'route' => 'sales.reports.dashboard',
                'handler' => 'SalesReportService::dashboard',
                'filters' => ['from','to','branch_id'],
                'export' => true, 'print' => true, 'search' => false,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'sales.by_period',
                'title' => 'Sales by Period',
                'description' => 'Sales aggregated by day, week, month or year.',
                'module' => 'sales',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'sales.view',
                'route' => 'sales.reports.daily',
                'handler' => 'SalesReportService::salesByPeriod',
                'filters' => ['group','from','to','customer_id','status','branch_id'],
                'export' => true, 'print' => false, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'sales.product_wise',
                'title' => 'Product-wise Sales',
                'description' => 'Sales by product.',
                'module' => 'sales',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'sales.view',
                'route' => 'sales.reports.product',
                'handler' => 'SalesReportService::productWise',
                'filters' => ['from','to','product_id','category_id'],
                'export' => true, 'print' => false, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'sales.customer_statement',
                'title' => 'Customer Statement',
                'description' => 'Invoices, payments and returns for a customer with running balance.',
                'module' => 'sales',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'sales.view',
                'route' => 'sales.reports.statement',
                'handler' => 'SalesReportService::customerStatement',
                'filters' => ['customerId','from','to'],
                'export' => true, 'print' => true, 'search' => false,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'sales.returns',
                'title' => 'Sales Returns',
                'description' => 'Returns count, total and refunded.',
                'module' => 'sales',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'sales.view',
                'route' => 'sales.reports.returns',
                'handler' => 'SalesReportService::returnsReport',
                'filters' => ['from','to','customer_id'],
                'export' => true, 'print' => false, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],

            // ===== CORE — Purchase (module: purchase) =====
            [
                'key' => 'purchase.dashboard',
                'title' => 'Purchase Dashboard',
                'description' => 'Purchases, receipts and payables totals.',
                'module' => 'purchase',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'purchase.view',
                'route' => 'purchase.reports.dashboard',
                'handler' => 'PurchaseReportService::dashboardMetrics',
                'filters' => ['from','to','branch_id'],
                'export' => true, 'print' => true, 'search' => false,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'purchase.supplier_statement',
                'title' => 'Supplier Statement',
                'description' => 'Invoices, payments and credit notes for a supplier.',
                'module' => 'purchase',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'purchase.view',
                'route' => 'purchase.reports.supplier-statement',
                'handler' => 'PurchaseReportService::supplierStatement',
                'filters' => ['supplierId','from','to'],
                'export' => true, 'print' => true, 'search' => false,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'purchase.payable',
                'title' => 'Outstanding Payable',
                'description' => 'Supplier payables with aging.',
                'module' => 'purchase',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'purchase.view',
                'route' => 'purchase.reports.payable',
                'handler' => 'PurchaseReportService::outstandingPayableReport',
                'filters' => ['supplier_id'],
                'export' => true, 'print' => false, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],

            // ===== CORE — HR (module: hr) =====
            [
                'key' => 'hr.employee_directory',
                'title' => 'Employee Directory',
                'description' => 'Active employees with department and designation.',
                'module' => 'hr',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'hr.view',
                'route' => 'hr.reports.index',
                'handler' => 'HrReportService::employeeDirectory',
                'filters' => ['department_id','designation_id','status'],
                'export' => true, 'print' => true, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'hr.attendance',
                'title' => 'HR Attendance',
                'description' => 'Daily attendance with present/absence.',
                'module' => 'hr',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'hr.view',
                'route' => 'hr.reports.attendance',
                'handler' => 'HrReportService::attendance',
                'filters' => ['from','to','employee_id'],
                'export' => true, 'print' => false, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],

            // ===== CORE — Audit =====
            [
                'key' => 'audit.trail',
                'title' => 'Audit Trail',
                'description' => 'Creation, approval, posting and reversal history.',
                'module' => 'audit',
                'industry' => null,
                'sub_industry' => null,
                'permission' => 'audit.view',
                'route' => 'finance.audit.index',
                'handler' => 'AccountingAuditService::recent',
                'filters' => ['entity_type','from','to'],
                'export' => false, 'print' => false, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],

            // ===== EDUCATION — industry: education, sub: null (all education subs) =====
            [
                'key' => 'education.students',
                'title' => 'Student Report',
                'description' => 'Students with decorated rows and filters.',
                'module' => 'education',
                'industry' => 'education',
                'sub_industry' => null,
                'permission' => 'education.manage',
                'route' => 'academic.analytics.students',
                'handler' => 'EducationAnalyticsService::students',
                'filters' => ['course','batch','status'],
                'export' => true, 'print' => true, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'education.attendance_daily',
                'title' => 'Daily Attendance',
                'description' => 'Per-student status for a date with group filter.',
                'module' => 'education',
                'industry' => 'education',
                'sub_industry' => null,
                'permission' => 'attendance.manage',
                'route' => 'academic-attendance.reports.daily',
                'handler' => 'AcademicAttendanceReportService::daily',
                'filters' => ['date','group','branch'],
                'export' => true, 'print' => false, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'education.results_published',
                'title' => 'Published Results',
                'description' => 'Published result snapshots with frozen totals.',
                'module' => 'education',
                'industry' => 'education',
                'sub_industry' => null,
                'permission' => 'education.manage',
                'route' => 'academic.analytics.results',
                'handler' => 'EducationAnalyticsService::results',
                'filters' => ['from','to'],
                'export' => true, 'print' => false, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'education.promotions',
                'title' => 'Promotions per Year',
                'description' => 'Promotion outcomes per academic year.',
                'module' => 'education',
                'industry' => 'education',
                'sub_industry' => null,
                'permission' => 'education.manage',
                'route' => 'academic.analytics.promotions',
                'handler' => 'EducationAnalyticsService::promotions',
                'filters' => ['year'],
                'export' => true, 'print' => false, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'education.certificates',
                'title' => 'Certificates by Course',
                'description' => 'Certificates totals and by course.',
                'module' => 'education',
                'industry' => 'education',
                'sub_industry' => null,
                'permission' => 'education.manage',
                'route' => 'academic.analytics.certificates',
                'handler' => 'EducationAnalyticsService::certificates',
                'filters' => ['course'],
                'export' => true, 'print' => false, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'education.finance_batch',
                'title' => 'Batch Finance Report',
                'description' => 'Batches with billing totals.',
                'module' => 'education',
                'industry' => 'education',
                'sub_industry' => null,
                'permission' => 'reports.financial.view',
                'route' => 'education.reports.batches',
                'handler' => 'EducationFinanceService::batches',
                'filters' => ['batch'],
                'export' => false, 'print' => false, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],

            // ===== EDUCATION — sub-industry specific (example: School only) =====
            // Only visible if institute sub_industry == school (and industry == education)
            [
                'key' => 'education.school_attendance_summary',
                'title' => 'School Attendance Summary',
                'description' => 'Attendance summary tailored for School sub-industry.',
                'module' => 'education',
                'industry' => 'education',
                'sub_industry' => ['school'],
                'permission' => 'attendance.manage',
                'route' => 'academic.analytics.attendance',
                'handler' => 'EducationAnalyticsService::attendance',
                'filters' => ['from','to'],
                'export' => false, 'print' => false, 'search' => false,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],

            // ===== RETAIL — industry: retail =====
            [
                'key' => 'retail.stock_summary',
                'title' => 'Retail Stock Summary',
                'description' => 'Stock on hand filtered for Retail industry.',
                'module' => 'inventory',
                'industry' => 'retail',
                'sub_industry' => null,
                'permission' => 'inventory.view',
                'route' => 'purchase.reports.inventory',
                'handler' => 'InventoryReportService::stockOnHand',
                'filters' => ['warehouseId','categoryId'],
                'export' => true, 'print' => true, 'search' => true,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
            [
                'key' => 'retail.supermarket_sales',
                'title' => 'Supermarket Sales',
                'description' => 'Sales report for Supermarket sub-industry.',
                'module' => 'sales',
                'industry' => 'retail',
                'sub_industry' => ['supermarket'],
                'permission' => 'sales.view',
                'route' => 'sales.reports.dashboard',
                'handler' => 'SalesReportService::dashboard',
                'filters' => ['from','to'],
                'export' => false, 'print' => false, 'search' => false,
                'branch' => true, 'institute' => true,
                'status' => 'active',
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        foreach (self::all() as $r) {
            if ($r['key'] === $key) return $r;
        }
        return null;
    }

    /**
     * Reports visible to the current institute/user (industry + module + permission + branch).
     */
    public static function forInstitute(?Institute $institute, ?\App\Models\User $user = null, ?\App\Models\InstituteUser $instituteUser = null): array
    {
        $actor = $instituteUser ?? $user;
        $all = self::all();
        $result = [];

        foreach ($all as $report) {
            // Industry gate — core (null) always visible
            if ($report['industry'] !== null) {
                if ($institute === null || $institute->industry !== $report['industry']) continue;
                // Sub-industry gate — null means all subs of that industry
                if ($report['sub_industry'] !== null) {
                    $allowedSubs = (array) $report['sub_industry'];
                    if (! in_array($institute->sub_industry, $allowedSubs, true)) continue;
                }
            }

            // Module gate
            if (! self::moduleEnabled($institute, $report['module'])) continue;

            // Permission gate
            if (! self::hasPermission($actor, $report['permission'])) continue;

            $result[] = $report;
        }

        return $result;
    }

    protected static function moduleEnabled(?Institute $institute, string $module): bool
    {
        if ($institute === null) return false;
        // Audit module has no package gate, always enabled if permission passes
        if ($module === 'audit') return true;
        try {
            return app(ModuleAccessService::class)->isEnabled($institute, $module);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected static function hasPermission($actor, ?string $permission): bool
    {
        if ($permission === null) return true;
        if ($actor === null) return false;
        if ($actor instanceof \App\Models\PlatformAdmin) return true;
        if (method_exists($actor, 'hasPermission')) {
            return $actor->hasPermission($permission) || $actor->hasPermission('*');
        }
        if (method_exists($actor, 'hasAnyPermission')) {
            return $actor->hasAnyPermission([$permission]);
        }
        return false;
    }

    /**
     * Grouped for UI: [ Core => [Finance=>[],...], Education => [...], Retail => [...] ]
     */
    public static function grouped(?Institute $institute, $actor = null): array
    {
        $user = $actor instanceof \App\Models\User ? $actor : null;
        $instituteUser = $actor instanceof \App\Models\InstituteUser ? $actor : null;
        $reports = self::forInstitute($institute, $user, $instituteUser);
        $groups = [];
        foreach ($reports as $report) {
            $industryLabel = $report['industry'] === null ? 'Core' : ucfirst($report['industry']);
            if ($report['industry'] === 'education') $industryLabel = 'Education';
            if ($report['industry'] === 'retail') $industryLabel = 'Retail';
            $moduleLabel = ucfirst($report['module']);
            $groups[$industryLabel][$moduleLabel][] = $report;
        }
        // Sort keys for stable UI
        ksort($groups);
        foreach ($groups as &$mods) { ksort($mods); }
        return $groups;
    }
}
