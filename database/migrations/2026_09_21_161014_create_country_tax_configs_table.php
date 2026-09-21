<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_tax_configs', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->unique();

            // Labels
            $table->string('tds_label', 50);
            $table->string('tds_label_local', 100)->nullable();
            $table->string('module_label', 60);

            // Authority
            $table->string('tax_authority', 50);
            $table->string('tax_authority_full', 200);

            // Time / Process
            $table->string('fiscal_year_pattern', 20);
            $table->string('return_frequency', 20);
            $table->json('return_deadlines')->nullable();

            // Forms / Certificates
            $table->string('certificate_form_name', 100);
            $table->string('return_form_name', 100)->nullable();

            // TIN
            $table->string('tin_label', 30);
            $table->string('tin_format_regex', 100)->nullable();

            // Feature flags
            $table->boolean('has_advance_tax')->default(true);
            $table->boolean('has_minimum_tax')->default(false);
            $table->decimal('minimum_tax_rate', 5, 2)->nullable();

            // Corporate tax rates (JSON)
            $table->json('corporate_tax_rates')->nullable();

            // Currency
            $table->char('currency_code', 3);

            // Extensibility
            $table->json('extra')->nullable();

            $table->timestamps();

            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_tax_configs');
    }
};
