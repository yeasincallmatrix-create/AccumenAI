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
            $rows[] = [
                'package_id' => (int) $price['package_id'],
                'country_code' => $country,
                'currency_code' => $currency,
                'price_monthly' => $this->normalize($price['monthly'] ?? null),
                'price_yearly' => $this->normalize($price['yearly'] ?? null),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $updated = DB::transaction(function () use ($rows) {
            return DB::table('package_country_prices')->upsert(
                $rows,
                ['package_id', 'country_code'],
                ['currency_code', 'price_monthly', 'price_yearly', 'is_active', 'updated_at']
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
}
