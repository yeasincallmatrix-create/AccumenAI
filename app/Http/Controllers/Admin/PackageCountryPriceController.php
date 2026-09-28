<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Country-scoped package pricing for the package-by-industry screen.
 *
 * Writes `package_country_prices` only — industry mapping
 * (package_industries) and the packages themselves are untouched.
 */
class PackageCountryPriceController extends Controller
{
    /**
     * Upsert the posted prices for one country (AJAX, JSON response).
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', Rule::exists('country_currency_map', 'country_code')],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.package_id' => ['required', 'integer', 'exists:subscription_packages,id'],
            'prices.*.monthly' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'prices.*.yearly' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'prices.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'prices.*.discount_ends_at' => ['nullable', 'date'],
            'prices.*.trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $country = $validated['country'];

        $currency = DB::table('country_currency_map')
            ->where('country_code', $country)
            ->value('currency_code');

        if ($currency === null) {
            return response()->json([
                'ok' => false,
                'message' => "No currency mapped for {$country}.",
            ], 422);
        }

        $rows = [];
        foreach ($validated['prices'] as $price) {
            $packageId = (int) $price['package_id'];
            $existing = DB::table('package_country_prices')
                ->where('package_id', $packageId)
                ->where('country_code', $country)
                ->first();

            $rows[] = [
                'package_id' => $packageId,
                'country_code' => $country,
                'currency_code' => $currency,
                // Empty price fields leave the stored value untouched so
                // saving only discount/trial never zeroes the price.
                'price_monthly' => $this->keepOrPrice($price['monthly'] ?? null, $existing?->price_monthly),
                'price_yearly' => $this->keepOrPrice($price['yearly'] ?? null, $existing?->price_yearly),
                'discount_percent' => $this->normalizeDiscount($price['discount_percent'] ?? null),
                'discount_ends_at' => $this->normalizeDate($price['discount_ends_at'] ?? null),
                'trial_days' => $this->normalizeTrialDays($price['trial_days'] ?? null),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $updated = DB::transaction(function () use ($rows) {
            return DB::table('package_country_prices')->upsert(
                $rows,
                ['package_id', 'country_code'],
                ['currency_code', 'price_monthly', 'price_yearly', 'discount_percent', 'discount_ends_at', 'trial_days', 'is_active', 'updated_at']
            );
        });

        return response()->json([
            'ok' => true,
            'country' => $country,
            'currency' => $currency,
            'updated' => $updated,
        ]);
    }

    private function normalize(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    /**
     * Price fields are NOT NULL: an empty submission keeps the stored
     * value (or 0 for brand-new rows) instead of zeroing the price.
     */
    private function keepOrPrice(mixed $value, mixed $stored): float
    {
        if ($value === null || $value === '') {
            return $stored !== null ? (float) $stored : 0.0;
        }

        return round((float) $value, 2);
    }

    private function normalizeDiscount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return min(100, max(0, round((float) $value, 2)));
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeTrialDays(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return min(365, max(0, (int) $value));
    }
}
