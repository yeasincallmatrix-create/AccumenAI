<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\CountryCurrencyMap;
use App\Models\ExchangeRate;
use App\Models\TenantCurrencySetting;
use Illuminate\Support\Facades\Cache;

class CurrencyService
{
    private ?TenantCurrencySetting $setting = null;

    public function getSetting(): TenantCurrencySetting
    {
        if ($this->setting === null) {
            $this->setting = TenantCurrencySetting::forTenant(\App\Support\TenantContext::id());
        }

        return $this->setting;
    }

    public function getBaseCurrency(): string
    {
        return $this->getSetting()->base_currency;
    }

    public function isMultiCurrencyEnabled(): bool
    {
        return $this->getSetting()->isMultiCurrencyEnabled();
    }

    /**
     * Returns available currency codes. Only the base currency if multi-currency is disabled.
     */
    public function getAvailableCurrencies(): array
    {
        $setting = $this->getSetting();

        if (! $setting->isMultiCurrencyEnabled()) {
            return [$setting->base_currency];
        }

        return $setting->available_currencies ?? [$setting->base_currency];
    }

    /**
     * Returns currencies suitable for dropdown selection.
     */
    public function getSelectableCurrencies(): array
    {
        $codes = $this->getAvailableCurrencies();

        return Currency::whereIn('code', $codes)
            ->active()
            ->orderBy('code')
            ->get()
            ->toArray();
    }

    /**
     * Format a monetary amount using tenant settings.
     */
    public function format(float $amount, ?string $code = null): string
    {
        $setting = $this->getSetting();
        $code = $code ?? $setting->base_currency;

        $currency = Currency::where('code', $code)->first();
        $symbol = $currency?->symbol ?? $code;
        $decimals = $currency?->decimal_places ?? $setting->decimal_places;

        $formatted = number_format(
            $amount,
            $decimals,
            $setting->decimal_separator,
            $setting->thousand_separator
        );

        return $setting->currency_position === 'before'
            ? $symbol . $formatted
            : $formatted . $symbol;
    }

    /**
     * Update the tenant's currency settings.
     */
    public function update(int $instituteId, array $data): TenantCurrencySetting
    {
        $setting = TenantCurrencySetting::firstOrCreate(
            ['institute_id' => $instituteId],
            TenantCurrencySetting::defaultsForTenant($instituteId)
        );

        // Ensure base_currency is always in the available list
        if (isset($data['available_currencies']) && is_array($data['available_currencies'])) {
            if (! in_array($data['base_currency'], $data['available_currencies'], true)) {
                $data['available_currencies'][] = $data['base_currency'];
            }
            $data['available_currencies'] = array_values(array_unique($data['available_currencies']));
        }

        $setting->update($data);

        return $setting->fresh();
    }

    /**
     * Enable multi-currency for a tenant.
     */
    public function enableMultiCurrency(int $instituteId, array $additionalCurrencies = []): void
    {
        $setting = TenantCurrencySetting::forTenant($instituteId);

        $available = array_unique(array_merge(
            [$setting->base_currency],
            $additionalCurrencies
        ));

        $setting->update([
            'multi_currency_enabled' => true,
            'available_currencies' => array_values($available),
        ]);
    }

    /**
     * Disable multi-currency for a tenant. Only the base currency remains.
     */
    public function disableMultiCurrency(int $instituteId): void
    {
        $setting = TenantCurrencySetting::forTenant($instituteId);
        $setting->disableMultiCurrency();
    }

    /**
     * Convert an amount between currencies using stored exchange rates.
     */
    public function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        $fromCurrency = Currency::where('code', $from)->first();
        $toCurrency = Currency::where('code', $to)->first();

        if (! $fromCurrency || ! $toCurrency) {
            return $amount;
        }

        $rate = ExchangeRate::where('institute_id', \App\Support\TenantContext::id())
            ->where('from_currency_id', $fromCurrency->id)
            ->where('to_currency_id', $toCurrency->id)
            ->where('rate_date', '<=', now()->toDateString())
            ->orderByDesc('rate_date')
            ->value('rate');

        if ($rate === null) {
            return $amount;
        }

        return round($amount * (float) $rate, 6);
    }

    /**
     * Detect the currency code for a given 2-letter country code.
     */
    public function detectCurrencyForCountry(string $countryCode): string
    {
        return CountryCurrencyMap::currencyForCountry($countryCode) ?? 'BDT';
    }
}
