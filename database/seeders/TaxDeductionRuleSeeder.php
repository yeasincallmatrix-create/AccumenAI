<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxDeductionRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'country_code' => 'BD',
                'currency_code' => 'BDT',
                'code' => 'VENDOR_5',
                'name' => 'Vendor Payment — 5%',
                'description' => 'TDS on payments to vendors under section 153',
                'category' => 'tds',
                'rate' => 5.00,
                'threshold' => 30000,
                'is_active' => true,
            ],
            [
                'country_code' => 'BD',
                'currency_code' => 'BDT',
                'code' => 'CONTRACTOR_7',
                'name' => 'Contractor Payment — 7%',
                'description' => 'TDS on payments to contractors under section 153',
                'category' => 'tds',
                'rate' => 7.00,
                'threshold' => 50000,
                'is_active' => true,
            ],
            [
                'country_code' => 'BD',
                'currency_code' => 'BDT',
                'code' => 'PROFESSIONAL_10',
                'name' => 'Professional Fee — 10%',
                'description' => 'TDS on professional / technical fees under section 153',
                'category' => 'tds',
                'rate' => 10.00,
                'threshold' => 30000,
                'is_active' => true,
            ],
            [
                'country_code' => 'BD',
                'currency_code' => 'BDT',
                'code' => 'RENT_5',
                'name' => 'Rent — 5%',
                'description' => 'TDS on rent payments under section 153',
                'category' => 'tds',
                'rate' => 5.00,
                'threshold' => 50000,
                'is_active' => true,
            ],
            [
                'country_code' => 'BD',
                'currency_code' => 'BDT',
                'code' => 'INTEREST_10',
                'name' => 'Interest Payment — 10%',
                'description' => 'TDS on interest payments under section 153',
                'category' => 'tds',
                'rate' => 10.00,
                'threshold' => null,
                'is_active' => true,
            ],
            [
                'country_code' => 'BD',
                'currency_code' => 'BDT',
                'code' => 'DIVIDEND_10',
                'name' => 'Dividend — 10%',
                'description' => 'TDS on dividend payments under section 153',
                'category' => 'tds',
                'rate' => 10.00,
                'threshold' => null,
                'is_active' => true,
            ],
            [
                'country_code' => 'BD',
                'currency_code' => 'BDT',
                'code' => 'SALARY_SLAB',
                'name' => 'Salary TDS — Slab',
                'description' => 'TDS on salary as per income tax slab rates',
                'category' => 'salary',
                'rate' => 0,
                'threshold' => null,
                'is_active' => true,
                'metadata' => json_encode([
                    'type' => 'slab',
                    'slabs' => [
                        ['min' => 0, 'max' => 300000, 'rate' => 0],
                        ['min' => 300001, 'max' => 600000, 'rate' => 5],
                        ['min' => 600001, 'max' => 900000, 'rate' => 10],
                        ['min' => 900001, 'max' => 1200000, 'rate' => 15],
                        ['min' => 1200001, 'max' => 1500000, 'rate' => 20],
                        ['min' => 1500001, 'max' => 0, 'rate' => 25],
                    ],
                ]),
            ],
        ];

        foreach ($rules as $rule) {
            DB::table('tax_deduction_rules')->updateOrInsert(
                ['country_code' => $rule['country_code'], 'code' => $rule['code']],
                array_merge($rule, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
