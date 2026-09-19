<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('country_currency_map')) {
            return;
        }

        Schema::create('country_currency_map', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->unique();
            $table->string('country_name', 100);
            $table->char('currency_code', 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_currency_map');
    }
};
