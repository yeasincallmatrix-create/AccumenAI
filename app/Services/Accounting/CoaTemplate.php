<?php

namespace App\Services\Accounting;

/**
 * SINGLE CANONICAL chart-of-accounts code map.
 *
 * Consolidates the 5 previously disagreeing hardcoded maps (audit N-3):
 *   1. TenantCoaSeederService::CHILDREN          -> childrenByParent()
 *   2. ChartOfAccountService::TEMPLATE           -> flatTyped()
 *   3. config/accounting.php `account_codes`     -> deleted (dead, 0 callers)
 *   4. AccountingSetupService::DEFAULT_SETTINGS  -> validateCode() guard
 *   5. GlobalChartOfAccountsSeeder `$accounts`   -> globalRows()
 *
 * Row tuple:
 *   [0] code            account code
 *   [1] name            canonical label (TEMPLATE wins on conflict)
 *   [2] parent_code     parent anchor code, null for category roots
 *   [3] is_header       anchor/header row
 *   [4] is_postable     leaf row
 *   [5] type            asset|liability|equity|income|expense
 *   [6] industries      array of industry slugs, null = all
 *   [7] flags           rich flags (cash_flow_category, is_cash, is_bank,
 *                       is_receivable, is_payable) - applied by flatTyped()
 *
 * View notes (deliberate, test-pinned):
 *   - globalRows()      emits the legacy global anchor tree in legacy order AND
 *                       legacy labels (LEGACY_GLOBAL_NAMES), because those rows
 *                       are is_system = 1 and ChartOfAccount refuses to rename
 *                       them - so the seeder output is byte-stable.
 *   - childrenByParent() emits the legacy CHILDREN shape and applies only
 *                       CHILDREN_FLAGS, so the tenant seeding write-set is
 *                       unchanged (it never wrote cash_flow_category etc.).
 *   - flatTyped()       emits the legacy TEMPLATE shape in legacy order.
 */
final class CoaTemplate
{
    /** Canonical rows forming the global anchor tree (legacy seeder order). */
    private const GLOBAL_ROWS = [
        ['1', 'Assets', null, true, false, 'asset', null, []],
        ['1000', 'Cash & Cash Equivalents', '1', true, false, 'asset', null, []],
        ['1000.1', 'Cash in Hand', '1000', false, true, 'asset', null, ['is_cash' => true]],
        ['1000.2', 'Petty Cash', '1000', false, true, 'asset', null, []],
        ['1100', 'Bank Accounts', '1', true, false, 'asset', null, []],
        ['1100.1', 'Primary Bank Account', '1100', false, true, 'asset', null, ['is_bank' => true]],
        ['1200', 'Accounts Receivable', '1', true, false, 'asset', null, []],
        ['1200.1', 'Trade Receivable', '1200', false, true, 'asset', null, ['is_receivable' => true, 'cash_flow_category' => 'operating']],
        ['1200.2', 'Input VAT Receivable', '1200', false, true, 'asset', null, ['cash_flow_category' => 'operating']],
        ['1200.3', 'TDS Receivable', '1200', false, true, 'asset', null, []],
        ['1300', 'Inventory', '1', true, false, 'asset', null, []],
        ['1300.1', 'Raw Materials', '1300', false, true, 'asset', null, ['cash_flow_category' => 'operating']],
        ['1300.2', 'Finished Goods', '1300', false, true, 'asset', null, []],
        ['1400', 'Fixed Assets', '1', true, false, 'asset', null, []],
        ['1400.1', 'Land & Building', '1400', false, true, 'asset', null, ['cash_flow_category' => 'investing']],
        ['1400.2', 'Machinery & Equipment', '1400', false, true, 'asset', null, []],
        ['1400.3', 'Furniture & Fixtures', '1400', false, true, 'asset', null, []],
        ['1400.4', 'Vehicles', '1400', false, true, 'asset', null, []],
        ['1400.5', 'Accumulated Depreciation', '1400', false, true, 'asset', null, ['cash_flow_category' => 'investing']],
        ['1500', 'Other Assets', '1', true, false, 'asset', null, []],
        ['1500.1', 'Prepaid Expenses', '1500', false, true, 'asset', null, ['cash_flow_category' => 'operating']],
        ['1500.2', 'Security Deposits', '1500', false, true, 'asset', null, []],
        ['1600', 'Investments', '1', true, false, 'asset', null, []],
        ['1600.1', 'Short-term Investment', '1600', false, true, 'asset', null, []],
        ['2', 'Liabilities', null, true, false, 'liability', null, []],
        ['2000', 'Accounts Payable', '2', true, false, 'liability', null, []],
        ['2000.1', 'Trade Payables', '2000', false, true, 'liability', null, ['is_payable' => true, 'cash_flow_category' => 'operating']],
        ['2000.2', 'Accrued Expenses', '2000', false, true, 'liability', null, ['cash_flow_category' => 'operating']],
        ['2000.3', 'Salary Payable', '2000', false, true, 'liability', null, []],
        ['2100', 'Tax Payable', '2', true, false, 'liability', null, []],
        ['2100.1', 'VAT Output Payable', '2100', false, true, 'liability', null, ['cash_flow_category' => 'operating']],
        ['2100.2', 'TDS Payable (WHT)', '2100', false, true, 'liability', null, ['cash_flow_category' => 'operating']],
        ['2100.3', 'Income Tax Payable', '2100', false, true, 'liability', null, []],
        ['2100.4', 'Tax Clearing', '2100', false, true, 'liability', null, ['cash_flow_category' => 'operating']],
        ['2200', 'Loans', '2', true, false, 'liability', null, []],
        ['2200.1', 'Bank Loan - Short Term', '2200', false, true, 'liability', null, ['cash_flow_category' => 'financing']],
        ['2200.2', 'Bank Loan - Long Term', '2200', false, true, 'liability', null, []],
        ['2200.3', 'Director\'s Loan', '2200', false, true, 'liability', null, []],
        ['2300', 'Provisions', '2', true, false, 'liability', null, []],
        ['2300.1', 'Provision for Tax', '2300', false, true, 'liability', null, []],
        ['2400', 'Other Liabilities', '2', true, false, 'liability', null, []],
        ['2400.1', 'Dividend Payable', '2400', false, true, 'liability', null, []],
        ['2400.2', 'Interest Payable', '2400', false, true, 'liability', null, []],
        ['3', 'Equity', null, true, false, 'equity', null, []],
        ['3100', 'Owner\'s Capital (Sole)', '3', true, false, 'equity', null, []],
        ['3100.1', 'Owner\'s Capital', '3100', false, true, 'equity', null, ['cash_flow_category' => 'financing']],
        ['3100.2', 'Owner\'s Drawings', '3100', false, true, 'equity', null, []],
        ['3200', 'Partners\' Capital (Partnership)', '3', true, false, 'equity', null, []],
        ['3300', 'Share Capital (Pvt Ltd)', '3', true, false, 'equity', null, []],
        ['3300.1', 'Authorized Capital', '3300', false, true, 'equity', null, []],
        ['3300.2', 'Issued Capital', '3300', false, true, 'equity', null, []],
        ['3300.3', 'Paid-up Capital', '3300', false, true, 'equity', null, []],
        ['3300.4', 'Share Premium', '3300', false, true, 'equity', null, []],
        ['3400', 'Retained Earnings', '3', true, false, 'equity', null, []],
        ['3400.1', 'Retained Earnings', '3400', false, true, 'equity', null, ['cash_flow_category' => 'financing']],
        ['3400.2', 'Dividend Declared', '3400', false, true, 'equity', null, []],
        ['4', 'Income', null, true, false, 'income', null, []],
        ['4000', 'Operating Revenue', '4', true, false, 'income', null, []],
        ['4000.1', 'Product Sales', '4000', false, true, 'income', null, []],
        ['4000.2', 'Other Income', '4000', false, true, 'income', null, ['cash_flow_category' => 'operating']],
        ['4000.3', 'Inventory Adjustment Income', '4000', false, true, 'income', null, ['cash_flow_category' => 'operating']],
        ['4000.4', 'Discount Received', '4000', false, true, 'income', null, []],
        ['4100', 'Education Income', '4', true, false, 'income', ['education'], []],
        ['4100.1', 'Tuition Fees', '4100', false, true, 'income', ['education'], ['cash_flow_category' => 'operating']],
        ['4100.2', 'Admission Fees', '4100', false, true, 'income', ['education'], ['cash_flow_category' => 'operating']],
        ['4100.3', 'Exam Fees', '4100', false, true, 'income', ['education'], []],
        ['4100.4', 'Certificate Fees', '4100', false, true, 'income', ['education', 'training_center'], []],
        ['4200', 'Training Income', '4', true, false, 'income', ['training_center'], []],
        ['4200.1', 'Course Fees', '4200', false, true, 'income', ['training_center'], []],
        ['4200.2', 'Registration Fees', '4200', false, true, 'income', ['training_center'], []],
        ['4300', 'Medical Income', '4', true, false, 'income', ['medical'], []],
        ['4300.1', 'Consultation Fees', '4300', false, true, 'income', ['medical'], []],
        ['4300.2', 'Diagnostic Fees', '4300', false, true, 'income', ['medical'], []],
        ['4300.3', 'Pharmacy Sales', '4300', false, true, 'income', ['medical'], []],
        ['4400', 'Retail Income', '4', true, false, 'income', ['retail'], []],
        ['4400.1', 'Merchandise Sales', '4400', false, true, 'income', ['retail'], ['cash_flow_category' => 'operating']],
        ['4900', 'Other Income', '4', true, false, 'income', null, []],
        ['4900.1', 'Interest Income', '4900', false, true, 'income', null, ['cash_flow_category' => 'operating']],
        ['4900.2', 'Rental Income', '4900', false, true, 'income', null, []],
        ['4900.3', 'Gain on Disposal', '4900', false, true, 'income', null, ['cash_flow_category' => 'operating']],
        ['4900.4', 'Miscellaneous Income', '4900', false, true, 'income', null, []],
        ['5', 'Expenses', null, true, false, 'expense', null, []],
        ['5000', 'Cost of Goods Sold', '5', true, false, 'expense', null, []],
        ['5000.1', 'Raw Material Purchase', '5000', false, true, 'expense', null, []],
        ['5000.2', 'Direct Labor', '5000', false, true, 'expense', null, []],
        ['5000.3', 'Manufacturing Overhead', '5000', false, true, 'expense', null, []],
        ['5000.4', 'Freight & Carriage', '5000', false, true, 'expense', null, []],
        ['5000.5', 'Cost of Goods Sold', '5000', false, true, 'expense', null, ['cash_flow_category' => 'operating']],
        ['5100', 'Employee Benefits', '5', true, false, 'expense', null, []],
        ['5100.1', 'Basic Salary', '5100', false, true, 'expense', null, ['cash_flow_category' => 'operating']],
        ['5100.2', 'House Rent Allowance', '5100', false, true, 'expense', null, []],
        ['5100.3', 'Medical Allowance', '5100', false, true, 'expense', null, []],
        ['5100.4', 'Bonus & Incentives', '5100', false, true, 'expense', null, []],
        ['5100.5', 'Provident Fund', '5100', false, true, 'expense', null, []],
        ['5100.6', 'Gratuity', '5100', false, true, 'expense', null, []],
        ['6000', 'Operating Expenses', '5', true, false, 'expense', null, []],
        ['6000.1', 'Rent', '6000', false, true, 'expense', null, ['cash_flow_category' => 'operating']],
        ['6000.2', 'Utilities', '6000', false, true, 'expense', null, ['cash_flow_category' => 'operating']],
        ['6000.3', 'Internet & Telephone', '6000', false, true, 'expense', null, []],
        ['6000.4', 'Office Supplies', '6000', false, true, 'expense', null, ['cash_flow_category' => 'operating']],
        ['6000.5', 'Marketing & Advertising', '6000', false, true, 'expense', null, []],
        ['6000.6', 'Travel & Conveyance', '6000', false, true, 'expense', null, ['cash_flow_category' => 'operating']],
        ['6000.7', 'Repairs & Maintenance', '6000', false, true, 'expense', null, []],
        ['6000.8', 'Legal & Professional', '6000', false, true, 'expense', null, []],
        ['5300', 'Financial Expenses', '5', true, false, 'expense', null, []],
        ['5300.1', 'Interest Expense', '5300', false, true, 'expense', null, []],
        ['5300.2', 'Bank Charges', '5300', false, true, 'expense', null, []],
        ['5400', 'Depreciation', '5', true, false, 'expense', null, []],
        ['5400.1', 'Depreciation Expense', '5400', false, true, 'expense', null, ['cash_flow_category' => 'operating']],
        ['5500', 'Taxes & Licenses', '5', true, false, 'expense', null, []],
        ['5500.1', 'Income Tax Expense', '5500', false, true, 'expense', null, []],
        ['5500.2', 'Trade License Fees', '5500', false, true, 'expense', null, []],
        ['5900', 'Other Expenses', '5', true, false, 'expense', null, []],
        ['5900.1', 'Miscellaneous Expenses', '5900', false, true, 'expense', null, ['cash_flow_category' => 'operating']],
    ];

    /**
     * Codes outside the global anchor tree: 4400.2 (tenant-child only) plus the
     * FX accounts 4901/5901 that only TEMPLATE knew about. Onboarding previously
     * never created 4901/5901 while DEFAULT_SETTINGS pointed at them.
     */
    private const EXTRA_ROWS = [
        ['4400.2', 'Other Sales Income', '4400', false, true, 'income', null, []],
        ['4901', 'Unrealized FX Gain', '4900', false, true, 'income', null, ['cash_flow_category' => 'operating']],
        ['5901', 'Unrealized FX Loss', '5900', false, true, 'expense', null, ['cash_flow_category' => 'operating']],
    ];

    /** Legacy ChartOfAccountService::TEMPLATE order (flat typed view). */
    private const TEMPLATE_ORDER = [
        '1000.1', '1100.1', '1200.1', '1200.2', '1300.1', '1400.1', '1400.5', '1500.1', '2000.1', '2000.2', '2100.1', '2100.2', '2100.4', '2200.1', '3100.1', '3400.1', '4000.2', '4000.3', '4100.1', '4100.2', '4400.1', '4900.1', '4900.3', '4901', '5000.5', '5100.1', '6000.1', '6000.2', '6000.4', '6000.6', '5400.1', '5900.1', '5901',
    ];

    /** Legacy CHILDREN write-set (only these extras were merged into rows). */
    private const CHILDREN_FLAGS = [
        '1100.1' => ['is_bank' => true],
    ];

    /**
     * Legacy GlobalChartOfAccountsSeeder labels that must stay byte-stable.
     *
     * The canonical name for these two codes is TEMPLATE's (decision: TEMPLATE
     * wins on conflict), and childrenByParent()/flatTyped() emit it. The global
     * tree, however, is seeded into rows with is_system = 1, and
     * ChartOfAccount::updating throws a DomainException whenever a system
     * account's name changes — so re-running the seeder with the canonical
     * labels would hard-fail on every existing install. The global view keeps
     * the historical labels; only the canonical row and the tenant-facing views
     * carry the new ones.
     */
    private const LEGACY_GLOBAL_NAMES = [
        '4000.2' => 'Service Revenue',
        '4000.3' => 'Consultation Fees',
    ];

    /** @return array<string, array> merged map keyed by code */
    private static function map(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        foreach (array_merge(self::GLOBAL_ROWS, self::EXTRA_ROWS) as $row) {
            $map[$row[0]] = $row;
        }

        return $map;
    }

    /** @return array<int, array> all canonical rows, global-tree order first */
    public static function all(): array
    {
        return array_merge(self::GLOBAL_ROWS, self::EXTRA_ROWS);
    }

    /** @return list<string> every code in the registry */
    public static function codes(): array
    {
        return array_keys(self::map());
    }

    public static function has(string $code): bool
    {
        return isset(self::map()[$code]);
    }

    /** Fail loud when a hardcoded code is absent from the canonical registry. */
    public static function validateCode(string $code): void
    {
        if (! isset(self::map()[$code])) {
            throw new \RuntimeException(
                "Account code '{$code}' is missing from the canonical CoaTemplate registry."
            );
        }
    }

    /**
     * Derivation of the legacy TenantCoaSeederService::CHILDREN constant:
     * parent-code-keyed arrays of [code, name] / [code, name, extras].
     *
     * @return array<string, array<int, array>>
     */
    public static function childrenByParent(): array
    {
        $out = [];
        foreach (self::all() as $row) {
            if ($row[3]) {           // headers are anchors, never children
                continue;
            }
            $parent = $row[2];
            if ($parent === null) {
                continue;
            }
            $entry = [$row[0], $row[1]];
            if (isset(self::CHILDREN_FLAGS[$row[0]])) {
                $entry[] = self::CHILDREN_FLAGS[$row[0]];
            }
            $out[$parent][] = $entry;
        }

        return $out;
    }

    /**
     * Derivation of the legacy ChartOfAccountService::TEMPLATE constant:
     * flat [code, name, type, flags] rows in legacy order.
     *
     * @return array<int, array>
     */
    public static function flatTyped(): array
    {
        $map = self::map();
        $out = [];
        foreach (self::TEMPLATE_ORDER as $code) {
            $row = $map[$code];
            $out[] = [$row[0], $row[1], $row[5], $row[7]];
        }

        return $out;
    }

    /**
     * Derivation of the legacy GlobalChartOfAccountsSeeder `$accounts` array:
     * [code, name, parent_code, is_header, is_postable, type, industries]
     * in legacy order (114 rows).
     *
     * Names come from LEGACY_GLOBAL_NAMES where the canonical label would
     * otherwise rename an existing is_system row (see that constant).
     *
     * @return array<int, array>
     */
    public static function globalRows(): array
    {
        $out = [];
        foreach (self::GLOBAL_ROWS as $row) {
            $out[] = [
                $row[0],
                self::LEGACY_GLOBAL_NAMES[$row[0]] ?? $row[1],
                $row[2], $row[3], $row[4], $row[5], $row[6],
            ];
        }

        return $out;
    }
}
