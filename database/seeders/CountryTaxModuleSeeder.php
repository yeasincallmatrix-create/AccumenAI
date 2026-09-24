<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountryTaxModuleSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'country_code' => 'BD',
                'tax_module' => 'vat',
                'tax_name' => 'VAT',
                'default_rate' => 15.00,
                'is_active' => true,
                'metadata' => json_encode(['source' => 'phase3', 'note' => 'Bangladesh standard VAT']),
            ],
            [
                'country_code' => 'BD',
                'tax_module' => 'tds',
                'tax_name' => 'TDS',
                'default_rate' => 0.00,
                'is_active' => true,
                'metadata' => json_encode(['source' => 'phase3', 'note' => 'Bangladesh TDS default (0, configured per tenant)']),
            ],
        ];

        foreach ($rows as $row) {
            DB::table('country_tax_modules')->updateOrInsert(
                ['country_code' => $row['country_code'], 'tax_module' => $row['tax_module']],
                $row + ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->command->info('Country tax modules seeded: ' . count($rows));
    }
}
