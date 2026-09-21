<?php

namespace App\Services\Accounting;

use App\Models\CountryTaxConfig;
use Illuminate\Support\Facades\Cache;

class CountryTaxConfigService
{
    public const CACHE_TTL = 3600;

    public function config(?int $instituteId = null, ?string $countryCode = null): array
    {
        $code = strtoupper($countryCode ?? tenant_country($instituteId));

        return Cache::remember("country_tax_config_{$code}", self::CACHE_TTL, function () use ($code) {
            $model = CountryTaxConfig::forCountry($code);
            if ($model) {
                return $model->toArray();
            }

            // Fallback to BD
            $fallback = CountryTaxConfig::forCountry('BD');
            if ($fallback) {
                $arr = $fallback->toArray();
                $arr['country_code'] = $code;
                $arr['_fallback_used'] = true;

                return $arr;
            }

            return $this->hardcodedFallback($code);
        });
    }

    public function label(?int $instituteId = null): string
    {
        return $this->config($instituteId)['tds_label'] ?? 'Withholding';
    }

    public function localLabel(?int $instituteId = null): ?string
    {
        return $this->config($instituteId)['tds_label_local'] ?? null;
    }

    public function moduleLabel(?int $instituteId = null): string
    {
        return $this->config($instituteId)['module_label'] ?? 'Tax';
    }

    public function authority(?int $instituteId = null): string
    {
        return $this->config($instituteId)['tax_authority'] ?? 'Tax Authority';
    }

    public function authorityFull(?int $instituteId = null): string
    {
        return $this->config($instituteId)['tax_authority_full'] ?? '';
    }

    public function fiscalYearPattern(?int $instituteId = null): string
    {
        return $this->config($instituteId)['fiscal_year_pattern'] ?? 'Jan-Dec';
    }

    public function returnFrequency(?int $instituteId = null): string
    {
        return $this->config($instituteId)['return_frequency'] ?? 'yearly';
    }

    public function certificateFormName(?int $instituteId = null): string
    {
        return $this->config($instituteId)['certificate_form_name'] ?? 'Certificate';
    }

    public function tinLabel(?int $instituteId = null): string
    {
        return $this->config($instituteId)['tin_label'] ?? 'TIN';
    }

    public function extra(?int $instituteId = null, ?string $key = null): mixed
    {
        $extra = $this->config($instituteId)['extra'] ?? [];

        return $key ? ($extra[$key] ?? null) : $extra;
    }

    public function clearCache(string $countryCode): void
    {
        Cache::forget('country_tax_config_' . strtoupper($countryCode));
    }

    protected function hardcodedFallback(string $code): array
    {
        return [
            'country_code' => $code,
            'tds_label' => 'Withholding',
            'tds_label_local' => null,
            'module_label' => 'Withholding & Tax',
            'tax_authority' => 'Tax Authority',
            'tax_authority_full' => '',
            'fiscal_year_pattern' => 'Jan-Dec',
            'return_frequency' => 'yearly',
            'return_deadlines' => [],
            'certificate_form_name' => 'Certificate',
            'return_form_name' => null,
            'tin_label' => 'TIN',
            'tin_format_regex' => null,
            'has_advance_tax' => false,
            'has_minimum_tax' => false,
            'minimum_tax_rate' => 0.00,
            'corporate_tax_rates' => [],
            'currency_code' => 'USD',
            'extra' => [],
        ];
    }
}
