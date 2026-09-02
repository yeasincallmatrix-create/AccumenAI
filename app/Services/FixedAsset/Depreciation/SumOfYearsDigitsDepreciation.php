<?php

namespace App\Services\FixedAsset\Depreciation;

/**
 * Sum-of-the-years'-digits: accelerated depreciation where each period's share
 * is (remaining life / SYD) of the depreciable base. Total depreciation never
 * exceeds the depreciable base.
 */
class SumOfYearsDigitsDepreciation implements DepreciationMethodInterface
{
    public function method(): string
    {
        return 'sum_of_years_digits';
    }

    public function periodAmount(array $context): float
    {
        $cost = (float) $context['cost'];
        $residual = (float) ($context['residual_value'] ?? 0);
        $accumulated = (float) ($context['accumulated_depreciation'] ?? 0);
        $life = (int) ($context['useful_life_months'] ?? 1);
        $period = (int) ($context['period'] ?? 1);

        if ($life <= 0) {
            return 0.0;
        }

        $depreciable = round(max(0.0, $cost - $residual), 4);
        $denominator = $life * ($life + 1) / 2;
        $remainingLife = max(0, $life - ($period - 1));

        $amount = round($depreciable * ($remainingLife / $denominator), 4);
        $remaining = round(max(0.0, $depreciable - $accumulated), 4);

        return min($amount, $remaining);
    }
}
