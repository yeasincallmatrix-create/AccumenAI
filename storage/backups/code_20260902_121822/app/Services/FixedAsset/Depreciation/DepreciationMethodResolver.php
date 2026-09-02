<?php

namespace App\Services\FixedAsset\Depreciation;

use InvalidArgumentException;

/**
 * Resolves a depreciation method slug to its strategy instance.
 */
class DepreciationMethodResolver
{
    /** @var array<string, class-string<DepreciationMethodInterface>> */
    private const METHODS = [
        'straight_line' => StraightLineDepreciation::class,
        'declining_balance' => DecliningBalanceDepreciation::class,
        'double_declining_balance' => DoubleDecliningBalanceDepreciation::class,
        'reducing_balance' => ReducingBalanceDepreciation::class,
        'sum_of_years_digits' => SumOfYearsDigitsDepreciation::class,
        'units_of_production' => UnitsOfProductionDepreciation::class,
    ];

    public static function supported(): array
    {
        return array_keys(self::METHODS);
    }

    public function resolve(string $method): DepreciationMethodInterface
    {
        $class = self::METHODS[$method] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Unsupported depreciation method: {$method}");
        }

        return app($class);
    }

    /**
     * Whether the method is activity-based (requires per-period units input).
     */
    public function isUnitsBased(string $method): bool
    {
        return $method === 'units_of_production';
    }
}
