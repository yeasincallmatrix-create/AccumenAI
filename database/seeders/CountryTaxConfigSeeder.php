<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountryTaxConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            [
                'country_code' => 'BD',
                'tds_label' => 'TDS',
                'tds_label_local' => 'উৎসে কর',
                'module_label' => 'TDS & Tax',
                'tax_authority' => 'NBR',
                'tax_authority_full' => 'National Board of Revenue',
                'fiscal_year_pattern' => 'Jul-Jun',
                'return_frequency' => 'half_yearly',
                'return_deadlines' => json_encode(['Jan 31', 'Jul 31']),
                'certificate_form_name' => 'TDS Certificate',
                'return_form_name' => 'TDS Return',
                'tin_label' => 'e-TIN',
                'tin_format_regex' => '^\d{12}$',
                'has_advance_tax' => true,
                'has_minimum_tax' => true,
                'minimum_tax_rate' => 0.60,
                'corporate_tax_rates' => json_encode([
                    'private_limited' => 27.5,
                    'public_limited' => 22.5,
                    'bank' => 37.5,
                    'default' => 27.5,
                ]),
                'currency_code' => 'BDT',
                'extra' => json_encode([
                    'cit_discounted_rate' => 25.0,
                    'discount_condition' => '100% banking channel transactions',
                ]),
            ],
            [
                'country_code' => 'IN',
                'tds_label' => 'TDS',
                'tds_label_local' => 'स्रोत पर कर',
                'module_label' => 'TDS & Tax',
                'tax_authority' => 'CBDT',
                'tax_authority_full' => 'Central Board of Direct Taxes',
                'fiscal_year_pattern' => 'Apr-Mar',
                'return_frequency' => 'quarterly',
                'return_deadlines' => json_encode(['Jul 31', 'Oct 31', 'Jan 31', 'May 31']),
                'certificate_form_name' => 'Form 16A',
                'return_form_name' => 'Form 26Q',
                'tin_label' => 'PAN',
                'tin_format_regex' => '^[A-Z]{5}\d{4}[A-Z]$',
                'has_advance_tax' => true,
                'has_minimum_tax' => true,
                'minimum_tax_rate' => 15.00,
                'corporate_tax_rates' => json_encode([
                    'domestic' => 30.0,
                    'foreign' => 40.0,
                    'default' => 30.0,
                ]),
                'currency_code' => 'INR',
                'extra' => null,
            ],
            [
                'country_code' => 'US',
                'tds_label' => 'Withholding',
                'tds_label_local' => null,
                'module_label' => 'Withholding & Tax',
                'tax_authority' => 'IRS',
                'tax_authority_full' => 'Internal Revenue Service',
                'fiscal_year_pattern' => 'Jan-Dec',
                'return_frequency' => 'quarterly',
                'return_deadlines' => json_encode(['Apr 30', 'Jul 31', 'Oct 31', 'Jan 31']),
                'certificate_form_name' => 'W-9 / 1099',
                'return_form_name' => 'Form 941',
                'tin_label' => 'EIN',
                'tin_format_regex' => '^\d{2}-\d{7}$',
                'has_advance_tax' => true,
                'has_minimum_tax' => false,
                'minimum_tax_rate' => 0.00,
                'corporate_tax_rates' => json_encode([
                    'federal' => 21.0,
                    'default' => 21.0,
                ]),
                'currency_code' => 'USD',
                'extra' => null,
            ],
            [
                'country_code' => 'GB',
                'tds_label' => 'PAYE',
                'tds_label_local' => null,
                'module_label' => 'PAYE & Tax',
                'tax_authority' => 'HMRC',
                'tax_authority_full' => 'HM Revenue & Customs',
                'fiscal_year_pattern' => 'Apr-Apr',
                'return_frequency' => 'monthly',
                'return_deadlines' => json_encode(['Monthly']),
                'certificate_form_name' => 'P60 / P45',
                'return_form_name' => 'RTI',
                'tin_label' => 'UTR',
                'tin_format_regex' => '^\d{10}$',
                'has_advance_tax' => false,
                'has_minimum_tax' => false,
                'minimum_tax_rate' => 0.00,
                'corporate_tax_rates' => json_encode(['default' => 25.0]),
                'currency_code' => 'GBP',
                'extra' => null,
            ],
            [
                'country_code' => 'DE',
                'tds_label' => 'Quellensteuer',
                'tds_label_local' => null,
                'module_label' => 'Quellensteuer & Tax',
                'tax_authority' => 'BZSt',
                'tax_authority_full' => 'Bundeszentralamt für Steuern',
                'fiscal_year_pattern' => 'Jan-Dec',
                'return_frequency' => 'monthly',
                'return_deadlines' => json_encode(['Monthly']),
                'certificate_form_name' => 'Steuerbescheinigung',
                'return_form_name' => 'USt-Voranmeldung',
                'tin_label' => 'Steuernummer',
                'tin_format_regex' => null,
                'has_advance_tax' => true,
                'has_minimum_tax' => false,
                'minimum_tax_rate' => 0.00,
                'corporate_tax_rates' => json_encode(['default' => 15.0]),
                'currency_code' => 'EUR',
                'extra' => null,
            ],
        ];

        foreach ($configs as $config) {
            DB::table('country_tax_configs')->updateOrInsert(
                ['country_code' => $config['country_code']],
                array_merge($config, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command?->info('Country tax configs seeded: ' . count($configs));
    }
}
