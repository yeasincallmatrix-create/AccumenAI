<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Country-scoped discount + trial (package_country_prices).
     *
     * Country rows win over the industry row (package_industries) in
     * price resolution: country price → country discount →
     * country trial_days, each falling back to the industry value.
     */
    public function up(): void
    {
        Schema::table('package_country_prices', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->nullable()->after('price_yearly');
            $table->date('discount_ends_at')->nullable()->after('discount_percent');
            $table->unsignedSmallInteger('trial_days')->nullable()->after('discount_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('package_country_prices', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_ends_at', 'trial_days']);
        });
    }
};
