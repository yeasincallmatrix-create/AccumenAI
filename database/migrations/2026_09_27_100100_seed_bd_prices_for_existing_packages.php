<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Foundation pilot — BD/BDT price rows for every existing package,
     * copying the package's own price_monthly/price_yearly. Currency comes
     * from country_currency_map (BD -> BDT). Idempotent: skips packages that
     * already have a BD row.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('package_country_prices')) {
            echo "package_country_prices missing - run 2026_09_27_100000 first.\n";

            return;
        }

        $bdCurrency = DB::table('country_currency_map')
            ->where('country_code', 'BD')
            ->value('currency_code') ?? 'BDT';

        $packages = DB::table('subscription_packages')->get();
        $inserted = 0;

        foreach ($packages as $pkg) {
            $exists = DB::table('package_country_prices')
                ->where('package_id', $pkg->id)
                ->where('country_code', 'BD')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('package_country_prices')->insert([
                'package_id' => $pkg->id,
                'country_code' => 'BD',
                'currency_code' => $bdCurrency,
                'price_monthly' => $pkg->price_monthly,
                'price_yearly' => $pkg->price_yearly,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inserted++;
        }

        echo "Seeded BD prices for {$inserted} packages.\n";
    }

    public function down(): void
    {
        if (DB::getSchemaBuilder()->hasTable('package_country_prices')) {
            DB::table('package_country_prices')->where('country_code', 'BD')->delete();
        }
    }
};
