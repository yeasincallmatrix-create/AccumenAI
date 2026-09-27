<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SAFETY: block if tenants use POS packages
        $tenantCount = DB::table('institutes')
            ->whereIn('package_id', function ($q) {
                $q->select('id')->from('subscription_packages')
                  ->where('slug', 'LIKE', 'pos_%');
            })
            ->count();

        if ($tenantCount > 0) {
            throw new \RuntimeException(
                "ABORT: {$tenantCount} tenants still use POS packages. Migrate them first."
            );
        }

        DB::transaction(function () {
            // --- 1. Get POS package IDs ---
            $posPackageIds = DB::table('subscription_packages')
                ->where('slug', 'LIKE', 'pos_%')
                ->pluck('id')
                ->toArray();

            // --- 2. Delete POS package_industry_modules ---
            if (!empty($posPackageIds)) {
                $delModules = DB::table('package_industry_modules')
                    ->whereIn('package_id', $posPackageIds)->delete();

                $delPrices = DB::table('package_country_prices')
                    ->whereIn('package_id', $posPackageIds)->delete();

                $delIndustries = DB::table('package_industries')
                    ->whereIn('package_id', $posPackageIds)->delete();

                $delPackages = DB::table('subscription_packages')
                    ->whereIn('id', $posPackageIds)->delete();

                echo "Deleted POS packages: pkgs={$delPackages}, industries={$delIndustries}, modules={$delModules}, prices={$delPrices}\n";
            }

            // --- 3. Delete remaining package_industries with industry_key='pos' ---
            $delPosKey = DB::table('package_industries')
                ->where('industry_key', 'pos')->delete();
            echo "Deleted package_industries.industry_key='pos' rows: {$delPosKey}\n";

            // --- 4. Remove POS from industries table (or deactivate) ---
            $industriesDeleted = DB::table('industries')
                ->where('slug', 'pos')->delete();
            echo "Removed industries.slug='pos': {$industriesDeleted}\n";

            // --- 5. Change module_registry.pos type ---
            $updated = DB::table('module_registry')
                ->where('key', 'pos')
                ->update([
                    'type' => 'core',
                    'updated_at' => now(),
                ]);
            echo "Updated module_registry.pos type → core: {$updated}\n";

            // --- 6. Verify 27 children still there ---
            $childCount = DB::table('module_registry')
                ->where('parent_key', 'pos')
                ->where('status', 'active')
                ->count();
            echo "POS children preserved: {$childCount} (expected 27)\n";

            if ($childCount !== 27) {
                throw new \RuntimeException("ABORT: POS children count changed: {$childCount}");
            }
        });
    }

    public function down(): void
    {
        // Non-reversible: restoration via backup or PackageSeeder
        // POS packages can be recreated by re-adding to PackageSeeder
        DB::table('module_registry')
            ->where('key', 'pos')
            ->update(['type' => 'industry', 'updated_at' => now()]);
    }
};
