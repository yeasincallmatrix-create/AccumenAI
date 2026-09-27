<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Universal package slugs that must be offered for the retail industry,
     * with their canonical sort positions.
     *
     * @var array<string, int>
     */
    private const RETAIL_UNIVERSALS = [
        'free' => 0,
        'basic' => 1,
        'advanced' => 2,
        'premium' => 3,
    ];

    public function up(): void
    {
        DB::transaction(function () {
            // 1. Ensure the retail industry is offered on all 4 universal
            //    packages (insert only what is missing — local already has
            //    free/basic/advanced; premium was the known gap; fixture DBs
            //    may lack the whole set).
            $added = [];

            foreach (self::RETAIL_UNIVERSALS as $slug => $sortOrder) {
                $packageId = DB::table('subscription_packages')
                    ->where('slug', $slug)
                    ->value('id');

                if (! $packageId) {
                    continue;
                }

                $exists = DB::table('package_industries')
                    ->where('package_id', $packageId)
                    ->where('industry_key', 'retail')
                    ->exists();

                if (! $exists) {
                    DB::table('package_industries')->insert([
                        'package_id' => $packageId,
                        'industry_key' => 'retail',
                        'is_active' => true,
                        'sort_order' => $sortOrder,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $added[] = $slug;
                }
            }

            if ($added !== []) {
                echo 'Added retail package mapping(s): '.implode(', ', $added)."\n";
            } else {
                echo "retail package mappings already exist.\n";
            }

            // 2. Backfill Mawa Supershop subcategory (if the tenant row exists
            //    and its subcategory is still NULL — fixture DBs have no id 192).
            $tenant = DB::table('institutes')->where('id', 192)->first();

            if ($tenant && empty($tenant->subcategory_key)) {
                DB::table('institutes')
                    ->where('id', 192)
                    ->update(['subcategory_key' => 'grocery', 'updated_at' => now()]);

                echo "Backfilled tenant 192 subcategory_key = grocery.\n";
            } elseif ($tenant) {
                echo "Tenant 192 subcategory already set.\n";
            } else {
                echo "Tenant 192 not found (fixture DB) - skip backfill.\n";
            }
        });
    }

    public function down(): void
    {
        // Rollback removes only the premium mapping this migration introduced
        // as the gap fix. free/basic/advanced are owned by
        // PackageIndustrySeeder and are left intact on rollback.
        $premiumId = DB::table('subscription_packages')
            ->where('slug', 'premium')
            ->value('id');

        if ($premiumId) {
            DB::table('package_industries')
                ->where('package_id', $premiumId)
                ->where('industry_key', 'retail')
                ->delete();
        }
    }
};
