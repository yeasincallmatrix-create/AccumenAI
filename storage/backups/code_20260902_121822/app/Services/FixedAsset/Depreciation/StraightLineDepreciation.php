<?php

namespace App\Services\FixedAsset\Depreciation;

/**
 * Straight-line: depreciable base spread evenly over the useful life.
 */
class StraightLineDepreciation implements DepreciationMethodInterface
{
    public function method(): string
    {
        return 'straight_line';
    }

    public function periodAmount(array $context): float
    {
        $cost = (float) $context['cost'];
        $residual = (float) ($context['residual_value'] ?? 0);
        $accumulated = (float) ($context['accumulated_depreciation'] ?? 0);
        $life = (int) ($context['useful_life_months'] ?? 1);

        $depreciable = round(max(0.0, $cost - $residual), 4);

        if ($life <= 0) {
            return 0.0;
        }

        $monthly = round($depreciable / $life, 4);
        $remaining = round(max(0.0, $depreciable - $accumulated), 4);

        return min($monthly, $remaining);
    }
}
