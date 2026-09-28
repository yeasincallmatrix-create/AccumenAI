<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-industry package discount (admin/package-industries page).
     *
     * discount_percent: 0–100, applied on top of the industry price
     * (or package default). discount_ends_at: last valid day; countdown
     * = ends_at − today. Null ends_at = no expiry. Expired rows are
     * ignored by price resolution (fail-open to full price).
     */
    public function up(): void
    {
        Schema::table('package_industries', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->nullable()->after('price_yearly');
            $table->date('discount_ends_at')->nullable()->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('package_industries', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_ends_at']);
        });
    }
};
