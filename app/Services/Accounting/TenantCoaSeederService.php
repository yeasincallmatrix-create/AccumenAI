<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class TenantCoaSeederService
{
    public const CHILDREN = [
        '1000' => [['1000.1','Cash in Hand'],['1000.2','Petty Cash']],
        '1100' => [['1100.1','Primary Bank Account',['is_bank'=>true]]],
        '1200' => [
            ['1200.1','Trade Receivable'],
            ['1200.2','Input VAT Receivable'],
            ['1200.3','TDS Receivable'],
        ],
        '1300' => [['1300.1','Raw Materials'],['1300.2','Finished Goods']],
        '1400' => [
            ['1400.1','Land & Building'],
            ['1400.2','Machinery & Equipment'],
            ['1400.3','Furniture & Fixtures'],
            ['1400.4','Vehicles'],
            ['1400.5','Accumulated Depreciation'],
        ],
        '1500' => [['1500.1','Prepaid Expenses'],['1500.2','Security Deposits']],
        '1600' => [['1600.1','Short-term Investment']],
        '2000' => [
            ['2000.1','Trade Payables'],
            ['2000.2','Accrued Expenses'],
            ['2000.3','Salary Payable'],
        ],
        '2100' => [
            ['2100.1','VAT Output Payable'],
            ['2100.2','TDS Payable (WHT)'],
            ['2100.3','Income Tax Payable'],
            ['2100.4','Tax Clearing'],
        ],
        '2200' => [
            ['2200.1','Bank Loan - Short Term'],
            ['2200.2','Bank Loan - Long Term'],
            ['2200.3',"Director's Loan"],
        ],
        '2300' => [['2300.1','Provision for Tax']],
        '2400' => [['2400.1','Dividend Payable'],['2400.2','Interest Payable']],
        '3100' => [['3100.1',"Owner's Capital"],['3100.2',"Owner's Drawings"]],
        '3300' => [
            ['3300.1','Authorized Capital'],
            ['3300.2','Issued Capital'],
            ['3300.3','Paid-up Capital'],
            ['3300.4','Share Premium'],
        ],
        '3400' => [['3400.1','Retained Earnings'],['3400.2','Dividend Declared']],
        '4000' => [
            ['4000.1','Product Sales'],
            ['4000.2','Service Revenue'],
            ['4000.3','Consultation Fees'],
            ['4000.4','Discount Received'],
        ],
        '4100' => [
            ['4100.1','Tuition Fees'],
            ['4100.2','Admission Fees'],
            ['4100.3','Exam Fees'],
            ['4100.4','Certificate Fees'],
        ],
        '4200' => [['4200.1','Course Fees'],['4200.2','Registration Fees']],
        '4300' => [
            ['4300.1','Consultation Fees'],
            ['4300.2','Diagnostic Fees'],
            ['4300.3','Pharmacy Sales'],
        ],
        '4400' => [
            ['4400.1','Merchandise Sales'],
            ['4400.2','Other Sales Income'],
        ],
        '4900' => [
            ['4900.1','Interest Income'],
            ['4900.2','Rental Income'],
            ['4900.3','Gain on Disposal'],
            ['4900.4','Miscellaneous Income'],
        ],
        '5000' => [
            ['5000.1','Raw Material Purchase'],
            ['5000.2','Direct Labor'],
            ['5000.3','Manufacturing Overhead'],
            ['5000.4','Freight & Carriage'],
            ['5000.5','Cost of Goods Sold'],
        ],
        '5100' => [
            ['5100.1','Basic Salary'],
            ['5100.2','House Rent Allowance'],
            ['5100.3','Medical Allowance'],
            ['5100.4','Bonus & Incentives'],
            ['5100.5','Provident Fund'],
            ['5100.6','Gratuity'],
        ],
        '5200' => [
            ['5200.1','Rent'],
            ['5200.2','Utilities'],
            ['5200.3','Internet & Telephone'],
            ['5200.4','Office Supplies'],
            ['5200.5','Marketing & Advertising'],
            ['5200.6','Travel & Conveyance'],
            ['5200.7','Repairs & Maintenance'],
            ['5200.8','Legal & Professional'],
        ],
        '5300' => [['5300.1','Interest Expense'],['5300.2','Bank Charges']],
        '5400' => [['5400.1','Depreciation Expense']],
        '5500' => [['5500.1','Income Tax Expense'],['5500.2','Trade License Fees']],
        '5900' => [['5900.1','Miscellaneous Expenses']],
    ];

    public function seedForTenant(int $instituteId): int
    {
        $tenantIndustry = DB::table('institutes')->where('id', $instituteId)->value('industry');
        $created = 0;

        foreach (self::CHILDREN as $parentCode => $children) {
            $parent = ChartOfAccount::withoutGlobalScope('institute')
                ->whereNull('institute_id')
                ->where('code', $parentCode)
                ->where('is_header', true)
                ->first();

            if (!$parent) continue;

            $parentIndustries = $parent->industries
                ? (is_array($parent->industries) ? $parent->industries : json_decode($parent->industries, true))
                : [];

            if (!empty($parentIndustries)) {
                if (!$tenantIndustry || !in_array($tenantIndustry, $parentIndustries)) {
                    continue;
                }
            }

            foreach ($children as $child) {
                [$code, $name] = $child;
                $extra = $child[2] ?? [];

                $exists = ChartOfAccount::withoutGlobalScope('institute')
                    ->where('institute_id', $instituteId)
                    ->where('code', $code)
                    ->exists();

                if ($exists) continue;

                ChartOfAccount::withoutGlobalScope('institute')->create(array_merge([
                    'institute_id'     => $instituteId,
                    'code'             => $code,
                    'name'             => $name,
                    'parent_id'        => $parent->id,
                    'account_group_id' => $parent->account_group_id,
                    'type'             => $parent->type,
                    'is_header'        => false,
                    'is_postable'      => true,
                    'is_system'        => false,
                    'industries'       => $parent->industries ?: null,
                    'is_active'        => true,
                ], $extra));

                $created++;
            }
        }

        return $created;
    }
}
