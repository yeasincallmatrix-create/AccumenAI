<?php

namespace App\Services\FixedAsset\Depreciation;

/**
 * Reducing balance: a configured percentage of the reducing (opening) NBV,
 * floored at residual value. Functionally the declining-balance family with an
 * explicit configured rate; kept distinct so policy may diverge later.
 */
class ReducingBalanceDepreciation implements DepreciationMethodInterface
{
    public function method(): string
    {
        return 'reducing_balance';
    }

    public function periodAmount(array $context): float
    {
        $openingNbv = (float) ($context['opening_nbv'] ?? 0);
        $residual = (float) ($context['residual_value'] ?? 0);
        $rate = (float) ($context['rate'] ?? 0);

        if ($rate <= 0) {
            return 0.0;
        }

        $amount = round($openingNbv * $rate, 4);
        $floor = round(max(0.0, $openingNbv - $residual), 4);

        return min($amount, $floor);
    }
}
