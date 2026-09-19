<?php

namespace Database\Seeders;

use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabAnalyzerParameterMap;
use Illuminate\Database\Seeder;

class MindrayBc5150ParameterMapSeeder extends Seeder
{
    /**
     * Seeds parameter maps for all LabAnalyzers with adapter_key='mindray_bc'.
     * Idempotent via updateOrCreate — safe to re-run.
     */
    public function run(): void
    {
        $analyzers = LabAnalyzer::where('adapter_key', 'mindray_bc')->get();
        if ($analyzers->isEmpty()) {
            $this->command->warn('No Mindray BC-5150 analyzers found. Seed skipped.');

            return;
        }

        $maps = $this->getMindrayParameterMaps();

        foreach ($analyzers as $analyzer) {
            foreach ($maps as $map) {
                LabAnalyzerParameterMap::updateOrCreate(
                    [
                        'analyzer_id' => $analyzer->id,
                        'vendor_code' => $map['vendor_code'],
                    ],
                    array_merge($map, [
                        'institute_id' => $analyzer->institute_id,
                        'is_active' => true,
                    ])
                );
            }
            $this->command->info('Seeded '.count($maps)." parameter maps for analyzer #{$analyzer->id} ({$analyzer->name}).");
        }
    }

    /**
     * Mindray BC-5150 3-part differential map.
     * NOTE: vendor codes (LYM%/MID%/GRAN%) differ from Sysmex (NEUT%/MONO%/LYMPH%).
     * Universal codes are the SAME platform standard (LYM_PCT, MID_PCT, GRAN_PCT).
     */
    protected function getMindrayParameterMaps(): array
    {
        return [
            // === Core CBC ===
            ['vendor_code' => 'WBC', 'vendor_name' => 'WBC', 'universal_code' => 'WBC', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 4.0, 'ref_high' => 11.0, 'ref_range_text' => '4.0-11.0'],
            ['vendor_code' => 'RBC', 'vendor_name' => 'RBC', 'universal_code' => 'RBC', 'unit_from' => '10^6/uL', 'unit_to' => '10^6/uL', 'conversion_factor' => 1, 'ref_low' => 4.5, 'ref_high' => 5.5, 'ref_range_text' => '4.5-5.5'],
            ['vendor_code' => 'HGB', 'vendor_name' => 'Hemoglobin', 'universal_code' => 'HGB', 'unit_from' => 'g/dL', 'unit_to' => 'g/dL', 'conversion_factor' => 1, 'ref_low' => 13.0, 'ref_high' => 17.0, 'ref_range_text' => '13.0-17.0'],
            ['vendor_code' => 'HCT', 'vendor_name' => 'Hematocrit', 'universal_code' => 'HCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 40, 'ref_high' => 52, 'ref_range_text' => '40-52'],
            ['vendor_code' => 'MCV', 'vendor_name' => 'Mean Corpuscular Volume', 'universal_code' => 'MCV', 'unit_from' => 'fL', 'unit_to' => 'fL', 'conversion_factor' => 1, 'ref_low' => 80, 'ref_high' => 100, 'ref_range_text' => '80-100'],
            ['vendor_code' => 'MCH', 'vendor_name' => 'MCH', 'universal_code' => 'MCH', 'unit_from' => 'pg', 'unit_to' => 'pg', 'conversion_factor' => 1, 'ref_low' => 27, 'ref_high' => 33, 'ref_range_text' => '27-33'],
            ['vendor_code' => 'MCHC', 'vendor_name' => 'MCHC', 'universal_code' => 'MCHC', 'unit_from' => 'g/dL', 'unit_to' => 'g/dL', 'conversion_factor' => 1, 'ref_low' => 32, 'ref_high' => 36, 'ref_range_text' => '32-36'],
            ['vendor_code' => 'PLT', 'vendor_name' => 'Platelet Count', 'universal_code' => 'PLT', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 150, 'ref_high' => 450, 'ref_range_text' => '150-450'],

            // === RDW ===
            ['vendor_code' => 'RDW-CV', 'vendor_name' => 'RDW-CV', 'universal_code' => 'RDW_CV', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 11.5, 'ref_high' => 14.5, 'ref_range_text' => '11.5-14.5'],
            ['vendor_code' => 'RDW-SD', 'vendor_name' => 'RDW-SD', 'universal_code' => 'RDW_SD', 'unit_from' => 'fL', 'unit_to' => 'fL', 'conversion_factor' => 1, 'ref_low' => 39, 'ref_high' => 46, 'ref_range_text' => '39-46'],

            // === Platelet indices ===
            ['vendor_code' => 'MPV', 'vendor_name' => 'Mean Platelet Volume', 'universal_code' => 'MPV', 'unit_from' => 'fL', 'unit_to' => 'fL', 'conversion_factor' => 1, 'ref_low' => 7.5, 'ref_high' => 11.5, 'ref_range_text' => '7.5-11.5'],
            ['vendor_code' => 'PDW', 'vendor_name' => 'Platelet Distribution Width', 'universal_code' => 'PDW', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 9.0, 'ref_high' => 17.0, 'ref_range_text' => '9.0-17.0'],
            ['vendor_code' => 'PCT', 'vendor_name' => 'Plateletcrit', 'universal_code' => 'PCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 0.17, 'ref_high' => 0.35, 'ref_range_text' => '0.17-0.35'],

            // === 3-Part Differential (Mindray MID / GRAN / LYM) ===
            ['vendor_code' => 'LYM%', 'vendor_name' => 'Lymphocyte %', 'universal_code' => 'LYM_PCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 20, 'ref_high' => 45, 'ref_range_text' => '20-45'],
            ['vendor_code' => 'MID%', 'vendor_name' => 'Mid Cell %', 'universal_code' => 'MID_PCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 2, 'ref_high' => 12, 'ref_range_text' => '2-12'],
            ['vendor_code' => 'GRAN%', 'vendor_name' => 'Granulocyte %', 'universal_code' => 'GRAN_PCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 40, 'ref_high' => 70, 'ref_range_text' => '40-70'],

            // === 3-Part Differential (absolute) ===
            ['vendor_code' => 'LYM#', 'vendor_name' => 'Lymphocyte Absolute', 'universal_code' => 'LYM_ABS', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 1.0, 'ref_high' => 4.0, 'ref_range_text' => '1.0-4.0'],
            ['vendor_code' => 'MID#', 'vendor_name' => 'Mid Cell Absolute', 'universal_code' => 'MID_ABS', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 0.1, 'ref_high' => 1.5, 'ref_range_text' => '0.1-1.5'],
            ['vendor_code' => 'GRAN#', 'vendor_name' => 'Granulocyte Absolute', 'universal_code' => 'GRAN_ABS', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 2.0, 'ref_high' => 7.5, 'ref_range_text' => '2.0-7.5'],
        ];
    }
}
