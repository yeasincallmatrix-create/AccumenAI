<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GlobalChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $groupMap = [
            'asset' => 1, 'liability' => 2, 'equity' => 3,
            'income' => 4, 'expense' => 5,
        ];

        $accounts = [
            // [code, name, parent_code, is_header, is_postable, type, industries]
            ['1', 'Assets', null, true, false, 'asset', null],
            ['1000', 'Cash & Cash Equivalents', '1', true, false, 'asset', null],
            ['1000.1', 'Cash in Hand', '1000', false, true, 'asset', null],
            ['1000.2', 'Petty Cash', '1000', false, true, 'asset', null],
            ['1100', 'Bank Accounts', '1', true, false, 'asset', null],
            ['1100.1', 'Primary Bank Account', '1100', false, true, 'asset', null],
            ['1200', 'Accounts Receivable', '1', true, false, 'asset', null],
            ['1200.1', 'Trade Receivable', '1200', false, true, 'asset', null],
            ['1200.2', 'Input VAT Receivable', '1200', false, true, 'asset', null],
            ['1200.3', 'TDS Receivable', '1200', false, true, 'asset', null],
            ['1300', 'Inventory', '1', true, false, 'asset', null],
            ['1300.1', 'Raw Materials', '1300', false, true, 'asset', null],
            ['1300.2', 'Finished Goods', '1300', false, true, 'asset', null],
            ['1400', 'Fixed Assets', '1', true, false, 'asset', null],
            ['1400.1', 'Land & Building', '1400', false, true, 'asset', null],
            ['1400.2', 'Machinery & Equipment', '1400', false, true, 'asset', null],
            ['1400.3', 'Furniture & Fixtures', '1400', false, true, 'asset', null],
            ['1400.4', 'Vehicles', '1400', false, true, 'asset', null],
            ['1400.5', 'Accumulated Depreciation', '1400', false, true, 'asset', null],
            ['1500', 'Other Assets', '1', true, false, 'asset', null],
            ['1500.1', 'Prepaid Expenses', '1500', false, true, 'asset', null],
            ['1500.2', 'Security Deposits', '1500', false, true, 'asset', null],
            ['1600', 'Investments', '1', true, false, 'asset', null],
            ['1600.1', 'Short-term Investment', '1600', false, true, 'asset', null],
            ['2', 'Liabilities', null, true, false, 'liability', null],
            ['2000', 'Accounts Payable', '2', true, false, 'liability', null],
            ['2000.1', 'Trade Payables', '2000', false, true, 'liability', null],
            ['2000.2', 'Accrued Expenses', '2000', false, true, 'liability', null],
            ['2000.3', 'Salary Payable', '2000', false, true, 'liability', null],
            ['2100', 'Tax Payable', '2', true, false, 'liability', null],
            ['2100.1', 'VAT Output Payable', '2100', false, true, 'liability', null],
            ['2100.2', 'TDS Payable (WHT)', '2100', false, true, 'liability', null],
            ['2100.3', 'Income Tax Payable', '2100', false, true, 'liability', null],
            ['2100.4', 'Tax Clearing', '2100', false, true, 'liability', null],
            ['2200', 'Loans', '2', true, false, 'liability', null],
            ['2200.1', 'Bank Loan - Short Term', '2200', false, true, 'liability', null],
            ['2200.2', 'Bank Loan - Long Term', '2200', false, true, 'liability', null],
            ['2200.3', "Director's Loan", '2200', false, true, 'liability', null],
            ['2300', 'Provisions', '2', true, false, 'liability', null],
            ['2300.1', 'Provision for Tax', '2300', false, true, 'liability', null],
            ['2400', 'Other Liabilities', '2', true, false, 'liability', null],
            ['2400.1', 'Dividend Payable', '2400', false, true, 'liability', null],
            ['2400.2', 'Interest Payable', '2400', false, true, 'liability', null],
            ['3', 'Equity', null, true, false, 'equity', null],
            ['3100', "Owner's Capital (Sole)", '3', true, false, 'equity', null],
            ['3100.1', "Owner's Capital", '3100', false, true, 'equity', null],
            ['3100.2', "Owner's Drawings", '3100', false, true, 'equity', null],
            ['3200', "Partners' Capital (Partnership)", '3', true, false, 'equity', null],
            ['3300', 'Share Capital (Pvt Ltd)', '3', true, false, 'equity', null],
            ['3300.1', 'Authorized Capital', '3300', false, true, 'equity', null],
            ['3300.2', 'Issued Capital', '3300', false, true, 'equity', null],
            ['3300.3', 'Paid-up Capital', '3300', false, true, 'equity', null],
            ['3300.4', 'Share Premium', '3300', false, true, 'equity', null],
            ['3400', 'Retained Earnings', '3', true, false, 'equity', null],
            ['3400.1', 'Retained Earnings', '3400', false, true, 'equity', null],
            ['3400.2', 'Dividend Declared', '3400', false, true, 'equity', null],
            ['4', 'Income', null, true, false, 'income', null],
            ['4000', 'Operating Revenue', '4', true, false, 'income', null],
            ['4000.1', 'Product Sales', '4000', false, true, 'income', null],
            ['4000.2', 'Service Revenue', '4000', false, true, 'income', null],
            ['4000.3', 'Consultation Fees', '4000', false, true, 'income', null],
            ['4000.4', 'Discount Received', '4000', false, true, 'income', null],
            ['4100', 'Education Income', '4', true, false, 'income', ['education']],
            ['4100.1', 'Tuition Fees', '4100', false, true, 'income', ['education']],
            ['4100.2', 'Admission Fees', '4100', false, true, 'income', ['education']],
            ['4100.3', 'Exam Fees', '4100', false, true, 'income', ['education']],
            ['4100.4', 'Certificate Fees', '4100', false, true, 'income', ['education', 'training_center']],
            ['4200', 'Training Income', '4', true, false, 'income', ['training_center']],
            ['4200.1', 'Course Fees', '4200', false, true, 'income', ['training_center']],
            ['4200.2', 'Registration Fees', '4200', false, true, 'income', ['training_center']],
            ['4300', 'Medical Income', '4', true, false, 'income', ['medical']],
            ['4300.1', 'Consultation Fees', '4300', false, true, 'income', ['medical']],
            ['4300.2', 'Diagnostic Fees', '4300', false, true, 'income', ['medical']],
            ['4300.3', 'Pharmacy Sales', '4300', false, true, 'income', ['medical']],
            ['4400', 'Retail Income', '4', true, false, 'income', ['retail']],
            ['4400.1', 'Merchandise Sales', '4400', false, true, 'income', ['retail']],
            ['4900', 'Other Income', '4', true, false, 'income', null],
            ['4900.1', 'Interest Income', '4900', false, true, 'income', null],
            ['4900.2', 'Rental Income', '4900', false, true, 'income', null],
            ['4900.3', 'Gain on Disposal', '4900', false, true, 'income', null],
            ['4900.4', 'Miscellaneous Income', '4900', false, true, 'income', null],
            ['5', 'Expenses', null, true, false, 'expense', null],
            ['5000', 'Cost of Goods Sold', '5', true, false, 'expense', null],
            ['5000.1', 'Raw Material Purchase', '5000', false, true, 'expense', null],
            ['5000.2', 'Direct Labor', '5000', false, true, 'expense', null],
            ['5000.3', 'Manufacturing Overhead', '5000', false, true, 'expense', null],
            ['5000.4', 'Freight & Carriage', '5000', false, true, 'expense', null],
            ['5000.5', 'Cost of Goods Sold', '5000', false, true, 'expense', null],
            ['5100', 'Employee Benefits', '5', true, false, 'expense', null],
            ['5100.1', 'Basic Salary', '5100', false, true, 'expense', null],
            ['5100.2', 'House Rent Allowance', '5100', false, true, 'expense', null],
            ['5100.3', 'Medical Allowance', '5100', false, true, 'expense', null],
            ['5100.4', 'Bonus & Incentives', '5100', false, true, 'expense', null],
            ['5100.5', 'Provident Fund', '5100', false, true, 'expense', null],
            ['5100.6', 'Gratuity', '5100', false, true, 'expense', null],
            ['5200', 'Operating Expenses', '5', true, false, 'expense', null],
            ['5200.1', 'Rent', '5200', false, true, 'expense', null],
            ['5200.2', 'Utilities', '5200', false, true, 'expense', null],
            ['5200.3', 'Internet & Telephone', '5200', false, true, 'expense', null],
            ['5200.4', 'Office Supplies', '5200', false, true, 'expense', null],
            ['5200.5', 'Marketing & Advertising', '5200', false, true, 'expense', null],
            ['5200.6', 'Travel & Conveyance', '5200', false, true, 'expense', null],
            ['5200.7', 'Repairs & Maintenance', '5200', false, true, 'expense', null],
            ['5200.8', 'Legal & Professional', '5200', false, true, 'expense', null],
            ['5300', 'Financial Expenses', '5', true, false, 'expense', null],
            ['5300.1', 'Interest Expense', '5300', false, true, 'expense', null],
            ['5300.2', 'Bank Charges', '5300', false, true, 'expense', null],
            ['5400', 'Depreciation', '5', true, false, 'expense', null],
            ['5400.1', 'Depreciation Expense', '5400', false, true, 'expense', null],
            ['5500', 'Taxes & Licenses', '5', true, false, 'expense', null],
            ['5500.1', 'Income Tax Expense', '5500', false, true, 'expense', null],
            ['5500.2', 'Trade License Fees', '5500', false, true, 'expense', null],
            ['5900', 'Other Expenses', '5', true, false, 'expense', null],
            ['5900.1', 'Miscellaneous Expenses', '5900', false, true, 'expense', null],
        ];

        DB::transaction(function () use ($accounts, $groupMap) {
            // Pass 1: headers (is_system=1)
            foreach ($accounts as $row) {
                [$code, $name, $parentCode, $isHeader, $isPostable, $type, $industries] = $row;
                if (!$isHeader) continue;

                ChartOfAccount::withoutGlobalScope('institute')->updateOrCreate(
                    ['code' => $code, 'institute_id' => null],
                    [
                        'name' => $name,
                        'parent_id' => null,
                        'is_header' => true,
                        'is_postable' => false,
                        'is_system' => true,
                        'type' => $type,
                        'account_group_id' => $groupMap[$type] ?? null,
                        'industries' => $industries ?: null,
                        'is_active' => true,
                    ]
                );
            }

            // Pass 2: leaves (is_system=1 for defaults)
            foreach ($accounts as $row) {
                [$code, $name, $parentCode, $isHeader, $isPostable, $type, $industries] = $row;
                if ($isHeader) continue;

                $parentId = $parentCode
                    ? ChartOfAccount::withoutGlobalScope('institute')
                        ->whereNull('institute_id')->where('code', $parentCode)->value('id')
                    : null;

                ChartOfAccount::withoutGlobalScope('institute')->updateOrCreate(
                    ['code' => $code, 'institute_id' => null],
                    [
                        'name' => $name,
                        'parent_id' => $parentId,
                        'is_header' => false,
                        'is_postable' => true,
                        'is_system' => true,
                        'type' => $type,
                        'account_group_id' => $groupMap[$type] ?? null,
                        'industries' => $industries ?: null,
                        'is_active' => true,
                    ]
                );
            }
        });

        $this->command->info('✅ Global COA seeded: ' . count($accounts));
    }
}
