<?php

namespace App\Services\LabIntegration;

use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabAnalyzerParameterMap;

class ParameterMapper
{
    /**
     * Resolve a vendor parameter code to a universal code + unit conversion,
     * using lab_analyzer_parameter_maps for this analyzer.
     *
     * Returns:
     * [
     *   'universal_code'      => 'WBC',
     *   'parameter_key'       => 'WBC',
     *   'lab_test_id'         => 12 | null,
     *   'unit_from'           => 'K/uL',
     *   'unit_to'             => '10^3/uL',
     *   'conversion_factor'   => 1.0,
     *   'ref_low'             => 4.0,
     *   'ref_high'            => 11.0,
     *   'ref_range_text'      => '4.0-11.0',
     *   'mapped'              => true|false,
     * ]
     */
    public static function resolve(LabAnalyzer $analyzer, string $vendorCode): array
    {
        $map = LabAnalyzerParameterMap::where('analyzer_id', $analyzer->id)
            ->where('vendor_code', $vendorCode)
            ->where('is_active', true)
            ->first();

        if (! $map) {
            // Fall back: assume vendor_code == universal_code if it matches a known key
            $canonical = UnitConverter::canonicalUnit(strtoupper($vendorCode));

            return [
                'universal_code' => strtoupper($vendorCode),
                'parameter_key' => strtoupper($vendorCode),
                'lab_test_id' => null,
                'unit_from' => null,
                'unit_to' => $canonical,
                'conversion_factor' => 1.0,
                'ref_low' => null,
                'ref_high' => null,
                'ref_range_text' => null,
                'mapped' => false,
            ];
        }

        return [
            'universal_code' => $map->universal_code,
            'parameter_key' => $map->parameter_key ?? $map->universal_code,
            'lab_test_id' => $map->lab_test_id,
            'unit_from' => $map->unit_from,
            'unit_to' => $map->unit_to ?? UnitConverter::canonicalUnit($map->universal_code),
            'conversion_factor' => (float) ($map->conversion_factor ?? 1.0),
            'ref_low' => $map->ref_low !== null ? (float) $map->ref_low : null,
            'ref_high' => $map->ref_high !== null ? (float) $map->ref_high : null,
            'ref_range_text' => $map->ref_range_text,
            'mapped' => true,
        ];
    }
}
