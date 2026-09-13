<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable grading scales resolved through the hierarchy:
 *
 *   GLOBAL DEFAULT
 *     → COUNTRY DEFAULT
 *     → EDUCATION SYSTEM DEFAULT
 *     → ACADEMIC LEVEL DEFAULT
 *     → INSTITUTE OVERRIDE
 *
 * A scale is pure configuration: it never touches aggregate scores, marks or
 * assessments. The ladder is encoded with nullable scope columns on the same
 * table (mirroring how AssessmentType/Component use institute_id = NULL for
 * defaults):
 *
 *   institute_id = NULL                 ⇒ a default (any institute inherits it)
 *     + country_id         set           ⇒ country default
 *     + education_system_id set          ⇒ education-system default
 *     + academic_level_id   set          ⇒ academic-level default
 *     + nothing set                     ⇒ global default
 *   institute_id set                    ⇒ an institute override (never mutates
 *                                          the defaults it overrides)
 *
 * `scope_key` is a virtual column that encodes the four scope columns (with
 * NULL → 0 sentinels) so a single UNIQUE index can enforce "one scale per
 * scope" even though MySQL treats NULLs as distinct in UNIQUE indexes (the
 * same pattern as subject_academic_assignments.group_key).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('grade_scales')) {
            Schema::create('grade_scales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('country_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('education_system_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('academic_level_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('gpa_mode', 20)->default('equal_weight');   // credit_weighted | equal_weight
                $table->string('optional_subject_gpa', 20)->default('included'); // included | excluded
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->string('scope_key', 120)->virtualAs(
                    "CONCAT_WS(':', IFNULL(institute_id,0), IFNULL(country_id,0), IFNULL(education_system_id,0), IFNULL(academic_level_id,0))"
                );
                $table->unique('scope_key', 'grade_scales_scope_unique');
                $table->index(['country_id', 'institute_id', 'status'], 'grade_scales_resolve_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_scales');
    }
};
