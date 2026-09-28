<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable the free trial for all packages, industries and countries.
     *
     * trial_days = 0 means "explicitly blocked" (see
     * ModuleAccessService::packageTrialDays): it does NOT fall through to
     * the next level, startTrial() rejects with "Trial is not offered",
     * and the pricing cards hide the trial badge/button. This covers the
     * per-industry rows (package_industries) and every per-country price
     * override (package_country_prices, e.g. the BD 30-day row).
     */
    public function up(): void
    {
        if (Schema::hasTable('package_industries') && Schema::hasColumn('package_industries', 'trial_days')) {
            DB::table('package_industries')->update(['trial_days' => 0, 'updated_at' => now()]);
        }

        if (Schema::hasTable('package_country_prices') && Schema::hasColumn('package_country_prices', 'trial_days')) {
            DB::table('package_country_prices')->update(['trial_days' => 0, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Previous per-row values are not recoverable; fall back to "none".
        if (Schema::hasTable('package_industries') && Schema::hasColumn('package_industries', 'trial_days')) {
            DB::table('package_industries')->update(['trial_days' => null, 'updated_at' => now()]);
        }

        if (Schema::hasTable('package_country_prices') && Schema::hasColumn('package_country_prices', 'trial_days')) {
            DB::table('package_country_prices')->update(['trial_days' => null, 'updated_at' => now()]);
        }
    }
};
