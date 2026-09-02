<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global, country-scoped academic structure masters (shared reference data —
 * NOT tenant-scoped, no institute_id):
 *
 *   countries (1) ─── has many education_systems
 *   education_systems (1) ─── has many academic_levels
 *   academic_levels (1) ─── has many class_grades
 *   class_grades (1) ─── has many academic_groups (optional; a class may have none)
 *
 * Terminologies are configurable: a country defines its academic unit label
 * (Class/Grade/Year/Form/Level) on countries.academic_unit_label, and the names
 * stored here (e.g. "Class 8") are display labels, never hard-coded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('education_systems')) {
            Schema::create('education_systems', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);          // General / Madrasa / Technical ...
                $table->string('code', 60);
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('status')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['country_id', 'code']);
                $table->index(['country_id', 'status']);
            });
        }

        if (! Schema::hasTable('academic_levels')) {
            Schema::create('academic_levels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->constrained()->cascadeOnDelete();
                $table->foreignId('education_system_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);          // Primary / Secondary / Elementary / ...
                $table->string('code', 60);
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('status')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['education_system_id', 'code']);
                $table->index(['country_id', 'education_system_id', 'status']);
            });
        }

        if (! Schema::hasTable('class_grades')) {
            Schema::create('class_grades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->constrained()->cascadeOnDelete();
                $table->foreignId('education_system_id')->constrained()->cascadeOnDelete();
                $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);          // Class 8 / Grade 8 / Year 9 ...
                $table->string('code', 60);
                $table->unsignedInteger('sequence')->nullable();  // grade number (1..n), when meaningful
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('status')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['academic_level_id', 'code']);
                $table->index(['country_id', 'education_system_id', 'academic_level_id', 'status'], 'cg_country_system_level_status_idx');
            });
        }

        if (! Schema::hasTable('academic_groups')) {
            Schema::create('academic_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->constrained()->cascadeOnDelete();
                $table->foreignId('education_system_id')->constrained()->cascadeOnDelete();
                $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
                $table->foreignId('class_grade_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);          // Science / Humanities / Business Studies ...
                $table->string('code', 60);
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('status')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['class_grade_id', 'code']);
                $table->index(['academic_level_id', 'status']);
            });
        }

        Schema::table('countries', function (Blueprint $table) {
            if (! Schema::hasColumn('countries', 'academic_unit_label')) {
                $table->string('academic_unit_label', 40)->nullable()->after('phone_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            if (Schema::hasColumn('countries', 'academic_unit_label')) {
                $table->dropColumn('academic_unit_label');
            }
        });

        Schema::dropIfExists('academic_groups');
        Schema::dropIfExists('class_grades');
        Schema::dropIfExists('academic_levels');
        Schema::dropIfExists('education_systems');
    }
};
