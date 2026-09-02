<?php

namespace App\Services\FixedAsset\Depreciation;

/**
 * A depreciation method strategy. Implementations compute the depreciation
 * amount for a single period from an asset context array. Adding a new method
 * means adding one class + a registry entry — never rewriting the engine.
 */
interface DepreciationMethodInterface
{
    public function method(): string;

    /**
     * Compute the depreciation amount for one period.
     *
     * Context keys (all money is float of DECIMAL(19,4) values, already cast):
     *   cost, residual_value, accumulated_depreciation, opening_nbv,
     *   useful_life_months, rate, period (1-based), units_produced, total_units.
     *
     * Implementations MUST NOT return an amount that pushes accumulated
     * depreciation above the depreciable base or opening NBV below residual.
     */
    public function periodAmount(array $context): float;
}
