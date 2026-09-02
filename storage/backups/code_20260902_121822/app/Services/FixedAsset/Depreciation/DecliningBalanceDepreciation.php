<?php

namespace App\Services\FixedAsset\Depreciation;

/**
 * Declining balance: a configured rate applied to opening net book value each
 * period, never reducing NBV below residual value.
 */
class DecliningBalanceDepreciation implements DepreciationMethodInterface
{
    public function method(): string
    {
        return 'declining_balance';
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
