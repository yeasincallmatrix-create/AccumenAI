<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Global geographic data model — one unified hierarchy for every country:
     *
     *   countries  (1) ─── has 3 (at most) administrative_levels
     *   administrative_levels (1) ─── has many administrative_units
     *   administrative_units (1) ─── parent_id (0..1) → itself
     *
     * Level 1 = top administrative division (Division / State / Province),
     * Level 2 = second level (District / County / LGA),
     * Level 3 = third level (Upazila / City / Suburb).
     *
     * The schema is intentionally country-neutral: names, slugs and labels live
     * in data, never hard-coded per country.
     */
    public function up(): void
    {
        if (! Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->char('iso2', 2)->unique();
                $table->char('iso3', 3)->nullable();
                $table->string('phone_code', 10)->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('administrative_levels')) {
            Schema::create('administrative_levels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('level_number'); // 1, 2, 3
                $table->string('name', 80);                  // display label: Division / State / ...
                $table->string('slug', 80);
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->unique(['country_id', 'level_number']);
                $table->index(['country_id', 'status']);
            });
        }

        if (! Schema::hasTable('administrative_units')) {
            Schema::create('administrative_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->constrained()->cascadeOnDelete();
                $table->foreignId('administrative_level_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->foreign('parent_id')->references('id')->on('administrative_units')->nullOnDelete();
                $table->string('name', 160);
                $table->string('code', 120)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->index(['country_id', 'administrative_level_id', 'parent_id'], 'au_country_level_parent_idx');
                $table->index(['parent_id'], 'au_parent_idx');
                $table->index(['code'], 'au_code_idx');
                $table->index(['name'], 'au_name_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_units');
        Schema::dropIfExists('administrative_levels');
        Schema::dropIfExists('countries');
    }
};
