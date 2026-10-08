<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Realistic USD prices for the Global market.
     *
     * package_country_prices.country_code = 'US' rows were originally
     * seeded as base_BDT x multiplier, which produced absurd dollar
     * amounts ($15,000/mo). The landing page shows these rows to every
     * non-Bangladesh visitor, so reset them to sellable USD price points
     * (the same $29 / $79 / $199 the landing page used to hardcode).
     *
     * Yearly = monthly x 10 (two months free), matching the BDT rows.
     *
     * Tier map:
     *   free                    → $0
     *   *_starter / basic       → $29
     *   *_growth  / advanced    → $79
     *   *_enterprise / premium  → $199
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('package_country_prices')) {
            echo "package_country_prices missing - run 2026_09_27_100000 first.\n";

            return;
        }

        $tierPrices = [
            'free' => 0.0,
            'starter' => 29.0,
            'growth' => 79.0,
            'enterprise' => 199.0,
        ];
        $alias = ['basic' => 'starter', 'advanced' => 'growth', 'premium' => 'enterprise'];
        $tiers = ['starter', 'growth', 'enterprise'];

        $updated = 0;
        $slugs = DB::table('subscription_packages')->pluck('slug', 'id');

        foreach ($slugs as $packageId => $slug) {
            $slug = (string) $slug;

            if ($slug === 'free') {
                $tier = 'free';
            } elseif (isset($alias[$slug])) {
                $tier = $alias[$slug];
            } else {
                $tier = null;
                foreach ($tiers as $candidate) {
                    if (str_ends_with($slug, '_'.$candidate)) {
                        $tier = $candidate;
                        break;
                    }
                }
            }

            if ($tier === null) {
                continue;
            }

            $monthly = $tierPrices[$tier];

            $updated += DB::table('package_country_prices')
                ->where('package_id', $packageId)
                ->where('country_code', 'US')
                ->update([
                    'currency_code' => 'USD',
                    'price_monthly' => $monthly,
                    'price_yearly' => $monthly * 10,
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
        }

        echo "Reset {$updated} US/USD package price rows to realistic USD values.\n";
    }

    public function down(): void
    {
        // Data migration — the previous base x 10 amounts are not recoverable.
    }
};
