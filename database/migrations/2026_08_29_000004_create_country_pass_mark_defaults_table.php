<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2-6 — Country-Specific Pass Marks Defaults (table).
 * Stores per-country default pass percentages so AcademicAssessmentService
 * can auto-fill pass_mark when not provided. Seeded via PassMarkDefaultsSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('country_pass_mark_defaults')) {
            Schema::create('country_pass_mark_defaults', function (Blueprint $table) {
                $table->id();
                $table->string('country_code', 10); // ISO2 e.g. BD, US, GB, IN or GLOBAL (6 chars)
                $table->string('component_type', 40)->default('default'); // theory, practical, default
                $table->string('component_name', 120)->nullable(); // optional specific component slug/name
                $table->decimal('pass_percentage', 5, 2); // e.g. 33.00, 40.00, 60.00
                $table->timestamps();

                $table->unique(['country_code', 'component_type', 'component_name'], 'cpm_country_component_unique');
                $table->index('country_code');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('country_pass_mark_defaults');
    }
};
