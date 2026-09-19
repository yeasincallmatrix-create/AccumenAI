<?php

namespace Database\Seeders;

use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabAnalyzerParameterMap;
use Illuminate\Database\Seeder;

class SysmexXn550ParameterMapSeeder extends Seeder
{
    /**
     * Seeds parameter maps for all LabAnalyzers with adapter_key='sysmex_xn'.
     *
     * Note: this seeder is per-analyzer. It requires at least one
     * LabAnalyzer row with adapter_key='sysmex_xn' to exist.
     * For fresh installs, the analyzer is created via UI (Phase 5).
     */
    public function run(): void
    {
        $analyzers = LabAnalyzer::where('adapter_key', 'sysmex_xn')->get();
        if ($analyzers->isEmpty()) {
            $this->command->warn('No Sysmex XN-550 analyzers found. Seed skipped.');

            return;
        }

        $maps = $this->getSysmexParameterMaps();

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
     * Sysmex XN-550 default parameter map.
     * Units: canonical hematology units.
     */
    protected function getSysmexParameterMaps(): array
    {
        return [
            // === Core CBC ===
            ['vendor_code' => 'WBC', 'vendor_name' => 'White Blood Cell', 'universal_code' => 'WBC', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 4.0, 'ref_high' => 11.0, 'ref_range_text' => '4.0-11.0'],
            ['vendor_code' => 'RBC', 'vendor_name' => 'Red Blood Cell', 'universal_code' => 'RBC', 'unit_from' => '10^6/uL', 'unit_to' => '10^6/uL', 'conversion_factor' => 1, 'ref_low' => 4.5, 'ref_high' => 5.5, 'ref_range_text' => '4.5-5.5'],
            ['vendor_code' => 'HGB', 'vendor_name' => 'Hemoglobin', 'universal_code' => 'HGB', 'unit_from' => 'g/dL', 'unit_to' => 'g/dL', 'conversion_factor' => 1, 'ref_low' => 13.0, 'ref_high' => 17.0, 'ref_range_text' => '13.0-17.0'],
            ['vendor_code' => 'HCT', 'vendor_name' => 'Hematocrit', 'universal_code' => 'HCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 40, 'ref_high' => 52, 'ref_range_text' => '40-52'],
            ['vendor_code' => 'MCV', 'vendor_name' => 'Mean Corpuscular Volume', 'universal_code' => 'MCV', 'unit_from' => 'fL', 'unit_to' => 'fL', 'conversion_factor' => 1, 'ref_low' => 80, 'ref_high' => 100, 'ref_range_text' => '80-100'],
            ['vendor_code' => 'MCH', 'vendor_name' => 'Mean Corpuscular Hemoglobin', 'universal_code' => 'MCH', 'unit_from' => 'pg', 'unit_to' => 'pg', 'conversion_factor' => 1, 'ref_low' => 27, 'ref_high' => 33, 'ref_range_text' => '27-33'],
            ['vendor_code' => 'MCHC', 'vendor_name' => 'Mean Corpuscular Hb Conc', 'universal_code' => 'MCHC', 'unit_from' => 'g/dL', 'unit_to' => 'g/dL', 'conversion_factor' => 1, 'ref_low' => 32, 'ref_high' => 36, 'ref_range_text' => '32-36'],
            ['vendor_code' => 'PLT', 'vendor_name' => 'Platelet Count', 'universal_code' => 'PLT', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 150, 'ref_high' => 450, 'ref_range_text' => '150-450'],

            // === RDW ===
            ['vendor_code' => 'RDW-SD', 'vendor_name' => 'RDW-SD', 'universal_code' => 'RDW_SD', 'unit_from' => 'fL', 'unit_to' => 'fL', 'conversion_factor' => 1, 'ref_low' => 39, 'ref_high' => 46, 'ref_range_text' => '39-46'],
            ['vendor_code' => 'RDW-CV', 'vendor_name' => 'RDW-CV', 'universal_code' => 'RDW_CV', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 11.5, 'ref_high' => 14.5, 'ref_range_text' => '11.5-14.5'],
            ['vendor_code' => 'RDW', 'vendor_name' => 'RDW (fallback)', 'universal_code' => 'RDW', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 11.5, 'ref_high' => 14.5, 'ref_range_text' => '11.5-14.5'],

            // === Platelet indices ===
            ['vendor_code' => 'MPV', 'vendor_name' => 'Mean Platelet Volume', 'universal_code' => 'MPV', 'unit_from' => 'fL', 'unit_to' => 'fL', 'conversion_factor' => 1, 'ref_low' => 7.5, 'ref_high' => 11.5, 'ref_range_text' => '7.5-11.5'],
            ['vendor_code' => 'PDW', 'vendor_name' => 'Platelet Distribution Width', 'universal_code' => 'PDW', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 9.0, 'ref_high' => 17.0, 'ref_range_text' => '9.0-17.0'],
            ['vendor_code' => 'PCT', 'vendor_name' => 'Plateletcrit', 'universal_code' => 'PCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 0.17, 'ref_high' => 0.35, 'ref_range_text' => '0.17-0.35'],

            // === 5-Part Differential (absolute) ===
            ['vendor_code' => 'NEUT#', 'vendor_name' => 'Neutrophil Absolute', 'universal_code' => 'NEU_ABS', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 2.0, 'ref_high' => 7.5, 'ref_range_text' => '2.0-7.5'],
            ['vendor_code' => 'LYMPH#', 'vendor_name' => 'Lymphocyte Absolute', 'universal_code' => 'LYM_ABS', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 1.0, 'ref_high' => 4.0, 'ref_range_text' => '1.0-4.0'],
            ['vendor_code' => 'MONO#', 'vendor_name' => 'Monocyte Absolute', 'universal_code' => 'MON_ABS', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 0.2, 'ref_high' => 1.0, 'ref_range_text' => '0.2-1.0'],
            ['vendor_code' => 'EO#', 'vendor_name' => 'Eosinophil Absolute', 'universal_code' => 'EOS_ABS', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 0.02, 'ref_high' => 0.5, 'ref_range_text' => '0.02-0.5'],
            ['vendor_code' => 'BASO#', 'vendor_name' => 'Basophil Absolute', 'universal_code' => 'BAS_ABS', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 0.02, 'ref_high' => 0.1, 'ref_range_text' => '0.02-0.1'],

            // === 5-Part Differential (percent) ===
            ['vendor_code' => 'NEUT%', 'vendor_name' => 'Neutrophil %', 'universal_code' => 'NEU_PCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 40, 'ref_high' => 70, 'ref_range_text' => '40-70'],
            ['vendor_code' => 'LYMPH%', 'vendor_name' => 'Lymphocyte %', 'universal_code' => 'LYM_PCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 20, 'ref_high' => 45, 'ref_range_text' => '20-45'],
            ['vendor_code' => 'MONO%', 'vendor_name' => 'Monocyte %', 'universal_code' => 'MON_PCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 2, 'ref_high' => 10, 'ref_range_text' => '2-10'],
            ['vendor_code' => 'EO%', 'vendor_name' => 'Eosinophil %', 'universal_code' => 'EOS_PCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 1, 'ref_high' => 6, 'ref_range_text' => '1-6'],
            ['vendor_code' => 'BASO%', 'vendor_name' => 'Basophil %', 'universal_code' => 'BAS_PCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 0, 'ref_high' => 2, 'ref_range_text' => '0-2'],

            // === Immature Granulocytes ===
            ['vendor_code' => 'IG#', 'vendor_name' => 'Immature Granulocyte Absolute', 'universal_code' => 'IG_ABS', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 0, 'ref_high' => 0.1, 'ref_range_text' => '0-0.1'],
            ['vendor_code' => 'IG%', 'vendor_name' => 'Immature Granulocyte %', 'universal_code' => 'IG_PCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 0, 'ref_high' => 1, 'ref_range_text' => '0-1'],

            // === Reticulocyte ===
            ['vendor_code' => 'RET#', 'vendor_name' => 'Reticulocyte Absolute', 'universal_code' => 'RET_ABS', 'unit_from' => '10^6/uL', 'unit_to' => '10^6/uL', 'conversion_factor' => 1, 'ref_low' => 0.02, 'ref_high' => 0.1, 'ref_range_text' => '0.02-0.1'],
            ['vendor_code' => 'RET%', 'vendor_name' => 'Reticulocyte %', 'universal_code' => 'RET_PCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 0.5, 'ref_high' => 2.5, 'ref_range_text' => '0.5-2.5'],
            ['vendor_code' => 'IRF', 'vendor_name' => 'Immature Retic Fraction', 'universal_code' => 'IRF', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 0, 'ref_high' => 30, 'ref_range_text' => '0-30'],
            ['vendor_code' => 'LFR', 'vendor_name' => 'Low Fluorescence Ratio', 'universal_code' => 'LFR', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 80, 'ref_high' => 100, 'ref_range_text' => '80-100'],
            ['vendor_code' => 'MFR', 'vendor_name' => 'Medium Fluorescence Ratio', 'universal_code' => 'MFR', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 0, 'ref_high' => 15, 'ref_range_text' => '0-15'],
            ['vendor_code' => 'HFR', 'vendor_name' => 'High Fluorescence Ratio', 'universal_code' => 'HFR', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 0, 'ref_high' => 5, 'ref_range_text' => '0-5'],

            // === Nucleated RBC ===
            ['vendor_code' => 'NRBC#', 'vendor_name' => 'Nucleated RBC Absolute', 'universal_code' => 'NRBC_ABS', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 0, 'ref_high' => 0, 'ref_range_text' => '0'],
            ['vendor_code' => 'NRBC%', 'vendor_name' => 'Nucleated RBC %', 'universal_code' => 'NRBC_PCT', 'unit_from' => '%', 'unit_to' => '%', 'conversion_factor' => 1, 'ref_low' => 0, 'ref_high' => 0, 'ref_range_text' => '0'],

            // === Extended channels ===
            ['vendor_code' => 'RET-He', 'vendor_name' => 'Reticulocyte Hemoglobin', 'universal_code' => 'RET_HE', 'unit_from' => 'pg', 'unit_to' => 'pg', 'conversion_factor' => 1, 'ref_low' => 28, 'ref_high' => 35, 'ref_range_text' => '28-35'],
            ['vendor_code' => 'PLT-F', 'vendor_name' => 'Platelet Fluorescent Channel', 'universal_code' => 'PLT_F', 'unit_from' => '10^3/uL', 'unit_to' => '10^3/uL', 'conversion_factor' => 1, 'ref_low' => 150, 'ref_high' => 450, 'ref_range_text' => '150-450'],
        ];
    }
}
