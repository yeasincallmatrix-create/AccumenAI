<?php

namespace App\Services\Pricing;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Country-localized package pricing (reads package_country_prices).
 *
 * NOTE: package_country_prices is a GLOBAL table (no institute_id).
 * TenantScoped must NOT be applied here.
 *
 * Country resolution priority:
 *   1. session('signup_country')
 *   2. CF-IPCountry header (Cloudflare)
 *   3. X-Country-Code header (custom)
 *   4. fallback 'BD'
 */
class CountryPriceService
{
    public const SESSION_KEY = 'signup_country';

    public const FALLBACK_COUNTRY = 'BD';

    public const FALLBACK_CURRENCY = 'BDT';

    /** Region priority for country dropdowns. */
    public const REGION_ORDER = [
        'SAARC' => ['BD', 'IN', 'PK', 'LK', 'NP', 'BT', 'MV'],
        'Gulf' => ['AE', 'SA', 'QA', 'KW', 'BH', 'OM'],
        'SEA' => ['MY', 'TH', 'ID', 'PH', 'VN'],
        'First World' => ['US', 'GB', 'CA', 'AU', 'SG'],
    ];

    public function resolveCountry(): string
    {
        $candidates = [
            session(self::SESSION_KEY),
            request()->header('CF-IPCountry'),
            request()->header('X-Country-Code'),
        ];

        foreach ($candidates as $candidate) {
            $code = $this->normalize($candidate);
            if ($code !== null && $this->isKnown($code)) {
                return $code;
            }
        }

        return self::FALLBACK_COUNTRY;
    }

    public function setSignupCountry(string $iso2): void
    {
        $code = $this->normalize($iso2);
        if ($code === null || ! $this->isKnown($code)) {
            throw new InvalidArgumentException("Unknown country code: {$iso2}");
        }

        session([self::SESSION_KEY => $code]);
    }

    /**
     * @return array{monthly: float, yearly: float, currency: string, is_localized: bool, country_code: string}
     */
    public function priceFor(int $packageId, ?string $countryCode = null): array
    {
        $cc = $countryCode !== null ? ($this->normalize($countryCode) ?? self::FALLBACK_COUNTRY) : $this->resolveCountry();

        $row = DB::table('package_country_prices')
            ->where('package_id', $packageId)
            ->where('country_code', $cc)
            ->where('is_active', 1)
            ->first();

        if ($row) {
            return [
                'monthly' => (float) $row->price_monthly,
                'yearly' => (float) $row->price_yearly,
                'currency' => $row->currency_code,
                'is_localized' => true,
                'country_code' => $cc,
            ];
        }

        $base = DB::table('subscription_packages')->where('id', $packageId)->first();

        return [
            'monthly' => (float) ($base->price_monthly ?? 0),
            'yearly' => (float) ($base->price_yearly ?? 0),
            'currency' => self::FALLBACK_CURRENCY,
            'is_localized' => false,
            'country_code' => self::FALLBACK_COUNTRY,
        ];
    }

    /**
     * Bulk variant (single country query + single base query — no N+1).
     *
     * @param int[] $packageIds
     * @return array<int, array{monthly: float, yearly: float, currency: string, is_localized: bool, country_code: string}>
     */
    public function priceListFor(array $packageIds, ?string $countryCode = null): array
    {
        $cc = $countryCode !== null ? ($this->normalize($countryCode) ?? self::FALLBACK_COUNTRY) : $this->resolveCountry();
        $ids = array_values(array_unique(array_map('intval', $packageIds)));

        $rows = DB::table('package_country_prices')
            ->whereIn('package_id', $ids)
            ->where('country_code', $cc)
            ->where('is_active', 1)
            ->get()
            ->keyBy('package_id');

        $missing = array_values(array_diff($ids, $rows->keys()->map('intval')->all()));
        $bases = $missing === []
            ? collect()
            : DB::table('subscription_packages')->whereIn('id', $missing)->get()->keyBy('id');

        $out = [];
        foreach ($ids as $id) {
            if (isset($rows[$id])) {
                $row = $rows[$id];
                $out[$id] = [
                    'monthly' => (float) $row->price_monthly,
                    'yearly' => (float) $row->price_yearly,
                    'currency' => $row->currency_code,
                    'is_localized' => true,
                    'country_code' => $cc,
                ];
            } else {
                $base = $bases[$id] ?? null;
                $out[$id] = [
                    'monthly' => (float) ($base->price_monthly ?? 0),
                    'yearly' => (float) ($base->price_yearly ?? 0),
                    'currency' => self::FALLBACK_CURRENCY,
                    'is_localized' => false,
                    'country_code' => self::FALLBACK_COUNTRY,
                ];
            }
        }

        return $out;
    }

    /**
     * Distinct countries with currencies, region-priority ordered.
     *
     * @return array<int, array{country_code: string, currency_code: string, region: string}>
     */
    public function availableCountries(): array
    {
        $rows = DB::table('package_country_prices')
            ->select('country_code', 'currency_code')
            ->distinct()
            ->get();

        $rank = [];
        foreach (self::REGION_ORDER as $region => $codes) {
            foreach ($codes as $i => $code) {
                $rank[$code] = [array_search($region, array_keys(self::REGION_ORDER)), $i];
            }
        }

        return $rows
            ->map(fn ($r) => [
                'country_code' => $r->country_code,
                'currency_code' => $r->currency_code,
                'region' => $this->regionOf($r->country_code),
            ])
            ->sortBy(fn ($r) => [
                $rank[$r['country_code']][0] ?? 99,
                $rank[$r['country_code']][1] ?? 99,
                $r['country_code'],
            ])
            ->values()
            ->all();
    }

    public function regionOf(string $code): string
    {
        foreach (self::REGION_ORDER as $region => $codes) {
            if (in_array($code, $codes, true)) {
                return $region;
            }
        }

        return 'EU';
    }

    private function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $code = strtoupper(trim($value));
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return null;
        }

        return $code;
    }

    private function isKnown(string $code): bool
    {
        static $known = null;

        if ($known === null) {
            $known = DB::table('package_country_prices')
                ->distinct()
                ->pluck('country_code')
                ->map(fn ($c) => strtoupper($c))
                ->all();
        }

        return in_array($code, $known, true);
    }
}
