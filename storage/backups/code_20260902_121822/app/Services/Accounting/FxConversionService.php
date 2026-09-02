<?php

namespace App\Services\Accounting;

use App\Models\Currency;
use Illuminate\Validation\ValidationException;

/**
 * FX conversion engine (STEP 19).
 *
 * All monetary math uses BCMath string arithmetic — never PHP floats. Internal
 * calculations run at 8-decimal scale; rounding to the DECIMAL(19,4) accounting
 * boundary happens only at conversion time, and currency-aware rounding uses
 * currencies.decimal_places.
 *
 * Rate resolution is deterministic and fail-safe:
 *   1. explicit transaction rate
 *   2. exact branch rate for the transaction date
 *   3. exact institute-wide rate for the transaction date
 *   4. latest effective branch rate on/before the date (historical)
 *   5. latest effective institute-wide rate on/before the date (historical)
 *   6. otherwise FAIL — a missing rate is never silently treated as 1:1.
 */
class FxConversionService
{
    public const INTERNAL_SCALE = 8;

    public const MONEY_SCALE = 4;

    public function __construct(
        private readonly AccountingSetupService $settings,
        private readonly ExchangeRateService $rates,
    ) {}

    /**
     * The institute's authoritative base currency (accounting_settings).
     */
    public function baseCurrency(int $instituteId, ?int $branchId = null): Currency
    {
        $code = $this->settings->getSetting($instituteId, 'base_currency', null, $branchId);

        $currency = $code !== null
            ? Currency::query()->where('code', $code)->first()
            : null;

        return $currency ?? Currency::query()->orderBy('code')->firstOrFail();
    }

    public function baseCurrencyId(int $instituteId, ?int $branchId = null): int
    {
        return (int) $this->baseCurrency($instituteId, $branchId)->id;
    }

    public function isBaseCurrency(int $instituteId, ?int $branchId, int $currencyId): bool
    {
        return $this->baseCurrencyId($instituteId, $branchId) === $currencyId;
    }

    /**
     * Resolve the exchange rate from one currency to another for a date.
     *
     * @return array{rate: string, source: string}
     *
     * @throws ValidationException when no rate can be resolved
     */
    public function resolveRate(
        int $instituteId,
        ?int $branchId,
        int $fromCurrencyId,
        int $toCurrencyId,
        string $date,
        ?string $explicitRate = null,
    ): array {
        if ($explicitRate !== null) {
            return ['rate' => $this->normalizeRate($explicitRate), 'source' => 'explicit'];
        }

        if ($fromCurrencyId === $toCurrencyId) {
            return ['rate' => '1.00000000', 'source' => 'identity'];
        }

        $effective = $this->rates->findEffective($instituteId, $branchId, $fromCurrencyId, $toCurrencyId, $date);

        if ($effective === null) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'No exchange rate is configured for this currency pair on '.$date.'. Add an exchange rate before posting.',
            ]);
        }

        return $effective;
    }

    /**
     * Convert a foreign amount to the base amount: amount * rate, rounded at
     * the accounting boundary (DECIMAL(19,4) by default).
     */
    public function convert(string $amount, string $rate, int $scale = self::MONEY_SCALE): string
    {
        return $this->round(bcmul($amount, $rate, self::INTERNAL_SCALE), $scale);
    }

    /**
     * Deterministic half-up rounding via BCMath (no floats).
     */
    public function round(string $amount, int $scale): string
    {
        $amount = trim($amount);
        $negative = str_starts_with($amount, '-');
        $absolute = $negative ? substr($amount, 1) : $amount;

        $shifted = bcmul($absolute, bcpow('10', (string) $scale, 0), 0);
        $remainder = bcsub(bcmul($absolute, bcpow('10', (string) ($scale + 1), 0), 0), bcmul($shifted, '10', 0), 0);

        if (bccomp($remainder, '5', 0) >= 0) {
            $shifted = bcadd($shifted, '1', 0);
        }

        $rounded = bcdiv($shifted, bcpow('10', (string) $scale, 0), $scale);

        return $negative && bccomp($rounded, '0', $scale) !== 0 ? '-'.$rounded : $rounded;
    }

    /**
     * Round an amount to its currency's decimal precision (presentation /
     * settlement boundary).
     */
    public function roundForCurrency(string $amount, Currency $currency): string
    {
        return $this->round($amount, max(0, (int) $currency->decimal_places));
    }

    /**
     * Validate + normalize a rate to an 8-decimal BCMath string.
     *
     * @throws ValidationException
     */
    public function normalizeRate(string $rate): string
    {
        $trimmed = trim($rate);

        if (preg_match('/^-?\d+(\.\d+)?$/', $trimmed) !== 1) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'The exchange rate must be a number.',
            ]);
        }

        $normalized = bcadd($trimmed, '0', self::INTERNAL_SCALE);

        if (bccomp($normalized, '0', self::INTERNAL_SCALE) <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'The exchange rate must be greater than zero.',
            ]);
        }

        return $normalized;
    }
}
