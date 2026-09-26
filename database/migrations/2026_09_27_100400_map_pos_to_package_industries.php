<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Foundation Fix 5 — `pos` is an industry parent in module_registry
     * (sort_order 13) but was never offered any package. Maps the 4
     * universal tiers (free, basic, advanced, premium) to it. Restaurant
     * tiers are intentionally NOT mapped (restaurant packages stay
     * restaurant-only).
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('package_industries')) {
            return;
        }

        $slugs = ['free', 'basic', 'advanced', 'premium'];

        $packages = DB::table('subscription_packages')
            ->whereIn('slug', $slugs)
            ->pluck('id', 'slug');

        $sortMap = ['free' => 10, 'basic' => 11, 'advanced' => 12, 'premium' => 13];
        $inserted = 0;

        foreach ($packages as $slug => $id) {
            $exists = DB::table('package_industries')
                ->where('package_id', $id)
                ->where('industry_key', 'pos')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('package_industries')->insert([
                'package_id' => $id,
                'industry_key' => 'pos',
                'is_active' => true,
                'sort_order' => $sortMap[$slug] ?? 99,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inserted++;
        }

        echo "Mapped pos to {$inserted} universal packages.\n";
    }

    public function down(): void
    {
        if (DB::getSchemaBuilder()->hasTable('package_industries')) {
            DB::table('package_industries')->where('industry_key', 'pos')->delete();
        }
    }
};
