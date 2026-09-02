<?php

namespace App\Services\FixedAsset\Depreciation;

/**
 * Units of production / activity: depreciation based on actual usage each
 * period — (depreciable base / total units) x units produced. The engine must
 * supply units_produced and total_units in the context.
 */
class UnitsOfProductionDepreciation implements DepreciationMethodInterface
{
    public function method(): string
    {
        return 'units_of_production';
    }

    public function periodAmount(array $context): float
    {
        $cost = (float) $context['cost'];
        $residual = (float) ($context['residual_value'] ?? 0);
        $accumulated = (float) ($context['accumulated_depreciation'] ?? 0);
        $totalUnits = (float) ($context['total_units'] ?? 0);
        $unitsProduced = (float) ($context['units_produced'] ?? 0);

        if ($totalUnits <= 0) {
            return 0.0;
        }

        $depreciable = round(max(0.0, $cost - $residual), 4);
        $rate = $depreciable / $totalUnits;
        $amount = round($unitsProduced * $rate, 4);
        $remaining = round(max(0.0, $depreciable - $accumulated), 4);

        return min($amount, $remaining);
    }
}
