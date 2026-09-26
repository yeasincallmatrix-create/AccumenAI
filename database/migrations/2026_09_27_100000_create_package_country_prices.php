<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foundation Fix 1+2 — per-country package pricing. Rows carry the
     * currency resolved from country_currency_map so a package can be
     * priced per country without touching the UNIQUE slug on
     * subscription_packages.
     */
    public function up(): void
    {
        if (Schema::hasTable('package_country_prices')) {
            echo "package_country_prices already exists. Skipping.\n";

            return;
        }

        Schema::create('package_country_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id');
            $table->string('country_code', 2);         // ISO2 (BD, IN, PK)
            $table->string('currency_code', 3);        // BDT, INR, PKR
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['package_id', 'country_code'], 'uq_pkg_country');
            $table->index('country_code');
            $table->foreign('package_id')
                ->references('id')
                ->on('subscription_packages')
                ->onDelete('cascade');
        });

        echo "Created package_country_prices table.\n";
    }

    public function down(): void
    {
        Schema::dropIfExists('package_country_prices');
    }
};
