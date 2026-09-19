<?php

namespace App\Services\LabIntegration;

class UnitConverter
{
    /**
     * Canonical units for common hematology/biochem parameters.
     * Parsers use this to normalize incoming units to a standard set.
     */
    public const CANONICAL_UNITS = [
        'WBC' => '10^3/uL',
        'RBC' => '10^6/uL',
        'HGB' => 'g/dL',
        'HCT' => '%',
        'MCV' => 'fL',
        'MCH' => 'pg',
        'MCHC' => 'g/dL',
        'RDW' => '%',
        'PLT' => '10^3/uL',
        'MPV' => 'fL',
        'NEU_PCT' => '%',
        'LYM_PCT' => '%',
        'MON_PCT' => '%',
        'EOS_PCT' => '%',
        'BASO_PCT' => '%',
        'GLU' => 'mg/dL',
        'CREA' => 'mg/dL',
        'UREA' => 'mg/dL',
        'CHOL' => 'mg/dL',
        'TRIG' => 'mg/dL',
        'ALT' => 'U/L',
        'AST' => 'U/L',
        'TSH' => 'mIU/L',
        'CRP' => 'mg/L',
    ];

    /**
     * Convert value from one unit to another using a factor.
     * If $fromUnit equals $toUnit, factor is 1 (no change).
     * If factor is null, returns original value + warning.
     */
    public static function convert(
        float|string $value,
        ?string $fromUnit,
        ?string $toUnit,
        ?float $factor = null
    ): array {
        $numeric = is_numeric($value) ? (float) $value : null;
        if ($numeric === null) {
            return ['value' => $value, 'unit' => $fromUnit, 'converted' => false, 'warning' => 'non-numeric value'];
        }

        if ($fromUnit === $toUnit || $toUnit === null) {
            return ['value' => $numeric, 'unit' => $fromUnit, 'converted' => false];
        }

        if ($factor === null) {
            return ['value' => $numeric, 'unit' => $fromUnit, 'converted' => false, 'warning' => "no factor for {$fromUnit}→{$toUnit}"];
        }

        return ['value' => $numeric * $factor, 'unit' => $toUnit, 'converted' => true];
    }

    public static function canonicalUnit(string $universalCode): ?string
    {
        return self::CANONICAL_UNITS[$universalCode] ?? null;
    }
}
