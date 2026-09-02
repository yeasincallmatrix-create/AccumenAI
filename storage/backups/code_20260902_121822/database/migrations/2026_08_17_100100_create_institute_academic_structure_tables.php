<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Institute-level customization for the academic structure.
 *
 * Rows are created ONLY when an institute customizes something (override or
 * custom addition) — an untouched institute has zero rows and inherits the
 * whole global country structure. Global masters are never copied.
 *
 * Reference semantics per table:
 *   - institute_academic_levels:
 *       academic_level_id set  → override of a global level (can disable, rename, reorder)
 *       academic_level_id null → institute-created custom level (is_custom = 1)
 *   - institute_class_grades (exactly one parent applies):
 *       class_grade_id set               → override of a global class
 *       academic_level_id set            → custom class under a global level
 *       institute_academic_level_id set  → custom class under a custom level
 *   - institute_academic_groups (exactly one parent applies):
 *       academic_group_id set           → override of a global group
 *       class_grade_id set              → custom group under a global class
 *       institute_class_grade_id set    → custom group under a custom class
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institute_academic_levels')) {
            Schema::create('institute_academic_levels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('academic_level_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('education_system_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120)->nullable();       // override/custom label; null → inherit global
                $table->unsignedInteger('display_order')->nullable(); // override; null → inherit global
                $table->boolean('status')->default(true);      // false = disabled for this institute
                $table->boolean('is_custom')->default(false);  // true = institute-created addition
                $table->timestamps();

                $table->unique(['institute_id', 'academic_level_id']);
                $table->index(['institute_id', 'education_system_id', 'status'], 'ial_institute_system_status_idx');
            });
        }

        if (! Schema::hasTable('institute_class_grades')) {
            Schema::create('institute_class_grades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('class_grade_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('academic_level_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('institute_academic_level_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 120)->nullable();
                $table->unsignedInteger('sequence')->nullable();
                $table->unsignedInteger('display_order')->nullable();
                $table->boolean('status')->default(true);
                $table->boolean('is_custom')->default(false);
                $table->timestamps();

                $table->unique(['institute_id', 'class_grade_id']);
                $table->index(['institute_id', 'academic_level_id', 'status'], 'icg_institute_level_status_idx');
                $table->index(['institute_id', 'institute_academic_level_id', 'status'], 'icg_institute_customlevel_status_idx');
            });
        }

        if (! Schema::hasTable('institute_academic_groups')) {
            Schema::create('institute_academic_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('academic_group_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('class_grade_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('institute_class_grade_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 120)->nullable();
                $table->unsignedInteger('display_order')->nullable();
                $table->boolean('status')->default(true);
                $table->boolean('is_custom')->default(false);
                $table->timestamps();

                $table->unique(['institute_id', 'academic_group_id']);
                $table->index(['institute_id', 'class_grade_id', 'status'], 'iag_institute_class_status_idx');
            });
        }

        Schema::table('institute_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('institute_settings', 'academic_unit_label')) {
                $table->string('academic_unit_label', 40)->nullable()->after('language');
            }
        });
    }

    public function down(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            if (Schema::hasColumn('institute_settings', 'academic_unit_label')) {
                $table->dropColumn('academic_unit_label');
            }
        });

        Schema::dropIfExists('institute_academic_groups');
        Schema::dropIfExists('institute_class_grades');
        Schema::dropIfExists('institute_academic_levels');
    }
};
