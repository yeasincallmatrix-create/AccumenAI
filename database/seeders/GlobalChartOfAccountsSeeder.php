<?php

namespace Database\Seeders;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class GlobalChartOfAccountsSeeder extends Seeder
{
    /**
     * Global COA — 38 accounts shared by all tenants.
     * - institute_id = NULL
     * - branch_id = NULL
     * - is_system = 1
     * - account_group_id → global group (by category/type)
     *
     * Extracted EXACTLY from ChartOfAccountService::TEMPLATE (lines 33-72).
     * Requires GlobalAccountGroupsSeeder to have run first.
     */
    public function run(): void
    {
        // Map type → global group ID.
        $groupMap = AccountGroup::whereNull('institute_id')
            ->where('is_system', 1)
            ->pluck('id', 'category')
            ->toArray();

        if (empty($groupMap)) {
            throw new \RuntimeException(
                'Global groups not seeded. Run GlobalAccountGroupsSeeder first.'
            );
        }

        // [code, name, type, flags?] — verbatim from TEMPLATE const.
        $accounts = [
            ['1001', 'Cash in Hand', 'asset', ['is_cash' => true]],
            ['1002', 'Bank Account', 'asset', ['is_bank' => true]],
            ['1100', 'Accounts Receivable', 'asset', ['is_receivable' => true, 'cash_flow_category' => 'operating']],
            ['1200', 'Inventory Asset', 'asset', ['cash_flow_category' => 'operating']],
            ['1201', 'Input VAT / Tax Receivable', 'asset', ['cash_flow_category' => 'operating']],
            ['1300', 'Fixed Assets', 'asset', ['cash_flow_category' => 'investing']],
            ['1301', 'Accumulated Depreciation', 'asset', ['cash_flow_category' => 'investing']],
            ['2001', 'Accounts Payable', 'liability', ['is_payable' => true, 'cash_flow_category' => 'operating']],
            ['2002', 'Unearned Revenue', 'liability', ['cash_flow_category' => 'operating']],
            ['2003', 'Loans Payable', 'liability', ['cash_flow_category' => 'financing']],
            ['2100', 'VAT Payable', 'liability', ['cash_flow_category' => 'operating']],
            ['2101', 'Withholding Tax Payable', 'liability', ['cash_flow_category' => 'operating']],
            ['2102', 'Tax Clearing', 'liability', ['cash_flow_category' => 'operating']],
            ['3001', "Owner's Capital", 'equity', ['cash_flow_category' => 'financing']],
            ['3002', 'Retained Earnings', 'equity', ['cash_flow_category' => 'financing']],
            ['3100', 'Revaluation Surplus', 'equity', ['cash_flow_category' => 'financing']],
            ['4001', 'Tuition Fees', 'income', ['cash_flow_category' => 'operating']],
            ['4002', 'Admission Fees', 'income', ['cash_flow_category' => 'operating']],
            ['4003', 'Merchandise Sales', 'income', ['cash_flow_category' => 'operating']],
            ['4004', 'Other Income', 'income', ['cash_flow_category' => 'operating']],
            ['4005', 'Inventory Adjustment Income', 'income', ['cash_flow_category' => 'operating']],
            ['4010', 'Gain on Disposal', 'income', ['cash_flow_category' => 'operating']],
            ['4900', 'Realized FX Gain', 'income', ['cash_flow_category' => 'operating']],
            ['4901', 'Unrealized FX Gain', 'income', ['cash_flow_category' => 'operating']],
            ['5001', 'Salary & Wages', 'expense', ['cash_flow_category' => 'operating']],
            ['5002', 'Rent', 'expense', ['cash_flow_category' => 'operating']],
            ['5003', 'Utilities', 'expense', ['cash_flow_category' => 'operating']],
            ['5004', 'Office Supplies', 'expense', ['cash_flow_category' => 'operating']],
            ['5005', 'Travel', 'expense', ['cash_flow_category' => 'operating']],
            ['5006', 'Miscellaneous Expense', 'expense', ['cash_flow_category' => 'operating']],
            ['5007', 'Cost of Goods Sold', 'expense', ['cash_flow_category' => 'operating']],
            ['5008', 'Inventory Adjustment Expense', 'expense', ['cash_flow_category' => 'operating']],
            ['5009', 'Inventory Wastage', 'expense', ['cash_flow_category' => 'operating']],
            ['5010', 'Depreciation Expense', 'expense', ['cash_flow_category' => 'operating']],
            ['5011', 'Loss on Disposal', 'expense', ['cash_flow_category' => 'operating']],
            ['5012', 'Impairment Expense', 'expense', ['cash_flow_category' => 'operating']],
            ['5900', 'Realized FX Loss', 'expense', ['cash_flow_category' => 'operating']],
            ['5901', 'Unrealized FX Loss', 'expense', ['cash_flow_category' => 'operating']],
        ];

        $created = 0;
        $existing = 0;
        $skipped = 0;

        foreach ($accounts as $row) {
            [$code, $name, $type] = $row;
            $flags = $row[3] ?? [];
            $category = $type;

            if (! isset($groupMap[$category])) {
                $this->command->warn("Skipping {$code}: no global group for category '{$category}'");
                $skipped++;
                continue;
            }

            $account = ChartOfAccount::firstOrCreate(
                [
                    'institute_id' => null,
                    'branch_id' => null,
                    'code' => $code,
                ],
                array_merge([
                    'institute_id' => null,
                    'branch_id' => null,
                    'account_group_id' => $groupMap[$category],
                    'name' => $name,
                    'type' => $type,
                    'is_system' => 1,
                    'is_active' => 1,
                ], $flags)
            );

            $account->wasRecentlyCreated ? $created++ : $existing++;
        }

        $this->command->info(
            "Global COA: {$created} created, {$existing} existed, {$skipped} skipped"
        );
    }
}
