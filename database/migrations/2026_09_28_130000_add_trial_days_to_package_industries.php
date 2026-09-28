<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-industry package trial window (admin/package-industries page).
     *
     * trial_days: how many days a trial subscription lasts for this
     * package in this industry. Null/0 = no trial offered. While a trial
     * subscription (billing_cycle='trial', status='active', unexpired)
     * exists, the tenant resolves modules/features from the trial
     * package but stays entitled as FREE (package_id untouched, price 0).
     */
    public function up(): void
    {
        Schema::table('package_industries', function (Blueprint $table) {
            $table->unsignedSmallInteger('trial_days')->nullable()->after('discount_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('package_industries', function (Blueprint $table) {
            $table->dropColumn('trial_days');
        });
    }
};
