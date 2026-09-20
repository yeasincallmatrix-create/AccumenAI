<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\TenantScoped;

/**
 * Per-tenant currency configuration. Controls which currencies the institute
 * can use and how amounts are displayed.
 */
class TenantCurrencySetting extends Model
{
    use TenantScoped;

    protected $table = 'tenant_currency_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'available_currencies' => 'array',
            'multi_currency_enabled' => 'boolean',
            'decimal_places' => 'integer',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    /**
     * Get or create the currency setting for a tenant, auto-detecting
     * the base currency from the institute's country.
     */
    public static function forTenant(int $instituteId): self
    {
        return static::firstOrCreate(
            ['institute_id' => $instituteId],
            static::defaultsForTenant($instituteId)
        );
    }

    /**
     * Build sensible defaults based on the institute's country.
     */
    public static function defaultsForTenant(int $instituteId): array
    {
        $institute = Institute::find($instituteId);
        $countryCode = $institute?->country_id
            ? (Country::find($institute->country_id)?->iso2 ?? null)
            : null;

        $baseCurrency = $countryCode
            ? CountryCurrencyMap::currencyForCountry($countryCode)
            : null;

        // 9b-3: fallback via locale config (default 'BDT', unchanged).
        $baseCurrency = $baseCurrency ?? config('locale.currency.default_code', 'BDT');

        return [
            'country_code' => $countryCode,
            'base_currency' => $baseCurrency,
            'multi_currency_enabled' => false,
            'available_currencies' => [$baseCurrency],
            'currency_position' => 'before',
            'thousand_separator' => ',',
            'decimal_separator' => '.',
            'decimal_places' => 2,
        ];
    }

    public function enableMultiCurrency(): void
    {
        $this->update(['multi_currency_enabled' => true]);
    }

    public function disableMultiCurrency(): void
    {
        $this->update([
            'multi_currency_enabled' => false,
            'available_currencies' => [$this->base_currency],
        ]);
    }

    public function isMultiCurrencyEnabled(): bool
    {
        return (bool) $this->multi_currency_enabled;
    }
}
