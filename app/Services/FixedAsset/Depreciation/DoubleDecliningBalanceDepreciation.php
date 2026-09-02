<?php

namespace App\Services\FixedAsset\Depreciation;

/**
 * Double declining balance: 200% of the straight-line rate applied to opening
 * NBV each period. Final periods must never push NBV below residual.
 */
class DoubleDecliningBalanceDepreciation implements DepreciationMethodInterface
{
    public function method(): string
    {
        return 'double_declining_balance';
    }

    public function periodAmount(array $context): float
    {
        $openingNbv = (float) ($context['opening_nbv'] ?? 0);
        $residual = (float) ($context['residual_value'] ?? 0);
        $life = (int) ($context['useful_life_months'] ?? 1);

        if ($life <= 0) {
            return 0.0;
        }

        $rate = 2.0 / $life;
        $amount = round($openingNbv * $rate, 4);
        $floor = round(max(0.0, $openingNbv - $residual), 4);

        return min($amount, $floor);
    }
}
