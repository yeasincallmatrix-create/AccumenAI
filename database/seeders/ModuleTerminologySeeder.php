<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleTerminologySeeder extends Seeder
{
    public function run(): void
    {
        // country_code = NULL → global terms (TerminologyService priority:
        // tenant override > country > global > default).
        $globalTerms = [
            ['term_key' => 'medical.opd', 'term_value' => 'OPD', 'context' => 'medical'],
            ['term_key' => 'medical.ipd', 'term_value' => 'IPD', 'context' => 'medical'],
            ['term_key' => 'medical.pharmacy', 'term_value' => 'Pharmacy', 'context' => 'medical'],
            ['term_key' => 'medical.billing', 'term_value' => 'Billing', 'context' => 'medical'],
            ['term_key' => 'medical.laboratory', 'term_value' => 'Laboratory', 'context' => 'medical'],
            ['term_key' => 'education.student', 'term_value' => 'Student', 'context' => 'education'],
            ['term_key' => 'education.class', 'term_value' => 'Class', 'context' => 'education'],
            ['term_key' => 'education.exam', 'term_value' => 'Exam', 'context' => 'education'],
            ['term_key' => 'tax.vat', 'term_value' => 'VAT', 'context' => 'tax'],
            ['term_key' => 'tax.tds', 'term_value' => 'TDS', 'context' => 'tax'],
            ['term_key' => 'common.invoice', 'term_value' => 'Invoice', 'context' => 'common'],
            ['term_key' => 'common.customer', 'term_value' => 'Customer', 'context' => 'common'],
            ['term_key' => 'common.vendor', 'term_value' => 'Vendor', 'context' => 'common'],
        ];

        $bdTerms = [
            ['term_key' => 'medical.opd', 'term_value' => 'ওপিডি', 'context' => 'medical'],
            ['term_key' => 'medical.ipd', 'term_value' => 'আইপিডি', 'context' => 'medical'],
            ['term_key' => 'tax.vat', 'term_value' => 'মূসক', 'context' => 'tax'],
        ];

        $count = 0;

        foreach ($globalTerms as $term) {
            DB::table('module_terminology')->updateOrInsert(
                ['country_code' => null, 'term_key' => $term['term_key']],
                $term + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
            $count++;
        }

        foreach ($bdTerms as $term) {
            DB::table('module_terminology')->updateOrInsert(
                ['country_code' => 'BD', 'term_key' => $term['term_key']],
                $term + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
            $count++;
        }

        $this->command->info('Terminology seeded: ' . $count . ' (13 global + 3 BD)');
    }
}
