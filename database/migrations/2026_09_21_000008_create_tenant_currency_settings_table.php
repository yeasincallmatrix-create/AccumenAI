<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_currency_settings')) {
            return;
        }

        Schema::create('tenant_currency_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->unique();
            $table->char('country_code', 2)->nullable();
            $table->char('base_currency', 3);
            $table->boolean('multi_currency_enabled')->default(false);
            $table->json('available_currencies')->nullable();
            $table->enum('currency_position', ['before', 'after'])->default('before');
            $table->string('thousand_separator', 5)->default(',');
            $table->string('decimal_separator', 5)->default('.');
            $table->tinyInteger('decimal_places')->default(2);
            $table->timestamps();
        });

        Schema::table('tenant_currency_settings', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_currency_settings');
    }
};
