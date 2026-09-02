<?php

namespace App\Services\FixedAsset;

use App\Models\FixedAsset;
use App\Services\FixedAsset\Depreciation\DepreciationMethodResolver;

/**
 * Depreciation calculation engine. Routes to the resolved method strategy and
 * centralises the schedule generation so posting and reporting are consistent
 * and reproducible.
 */
class DepreciationEngine
{
    public function __construct(
        private readonly DepreciationMethodResolver $resolver,
    ) {}

    /**
     * Depreciation amount for a single period.
     */
    public function periodAmount(
        FixedAsset $asset,
        float $accumulated,
        int $period,
        ?float $unitsProduced = null,
    ): float {
        $strategy = $this->resolver->resolve($asset->depreciation_method);

        $openingNbv = $this->openingNbv($asset, $accumulated);

        return $strategy->periodAmount([
            'cost' => $asset->cost(),
            'residual_value' => (float) $asset->residual_value,
            'accumulated_depreciation' => $accumulated,
            'opening_nbv' => $openingNbv,
            'useful_life_months' => (int) $asset->useful_life_months,
            'rate' => $asset->depreciation_rate !== null ? (float) $asset->depreciation_rate : null,
            'period' => $period,
            'units_produced' => $unitsProduced,
            'total_units' => $asset->total_units !== null ? (float) $asset->total_units : null,
        ]);
    }

    /**
     * Full depreciation schedule for an asset. Units-based methods require
     * per-period units; other methods run over the useful life (months).
     *
     * @param  array<int, float>|null  $unitsPerPeriod
     * @return array<int, array{period:int, opening_nbv:float, depreciation:float, accumulated_depreciation:float, closing_nbv:float}>
     */
    public function schedule(FixedAsset $asset, ?array $unitsPerPeriod = null): array
    {
        $rows = [];
        $accumulated = 0.0;
        $period = 1;
        $life = max(1, (int) $asset->useful_life_months);
        $isUnits = $this->resolver->isUnitsBased($asset->depreciation_method);
        $depreciable = $asset->depreciableBase();

        while (true) {
            $units = $isUnits && is_array($unitsPerPeriod)
                ? (float) ($unitsPerPeriod[$period - 1] ?? 0)
                : null;

            $amount = round($this->periodAmount($asset, $accumulated, $period, $units), 4);
            $newAccumulated = round($accumulated + $amount, 4);
            $closingNbv = round($asset->cost() - $newAccumulated - (float) $asset->impairment_amount, 4);

            $rows[] = [
                'period' => $period,
                'opening_nbv' => round($asset->cost() - $accumulated - (float) $asset->impairment_amount, 4),
                'depreciation' => $amount,
                'accumulated_depreciation' => $newAccumulated,
                'closing_nbv' => $closingNbv,
            ];

            $accumulated = $newAccumulated;

            if ($isUnits) {
                if (! is_array($unitsPerPeriod) || $period >= count($unitsPerPeriod)) {
                    break;
                }
            } elseif ($period >= $life) {
                break;
            }

            if ($accumulated >= $depreciable - 0.00005) {
                break;
            }

            $period++;
            if ($period > 120000) {
                break;
            }
        }

        return $rows;
    }

    public function openingNbv(FixedAsset $asset, float $accumulated): float
    {
        return round($asset->cost() - $accumulated - (float) $asset->impairment_amount, 4);
    }
}
