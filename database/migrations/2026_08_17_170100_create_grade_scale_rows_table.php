<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual grade bands inside a grade scale.
 *
 * Each row maps a closed score range [min_score, max_score] to a grade, a
 * grade point, a pass/fail verdict and a GPA inclusion flag. Ranges are
 * INCLUSIVE on both ends and must not overlap within a scale, so a score like
 * 80.00 resolves deterministically to exactly one band (e.g. the band whose
 * range covers 80). Overlap + min<=max are enforced by the service layer
 * (AcademicGradingService) — never in Blade/JS.
 *
 * gpa_included = false reserves the band for pass/fail-only or non-credit
 * subjects (e.g. a "Fail", "Awarded", "NQ" band).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('grade_scale_rows')) {
            Schema::create('grade_scale_rows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('grade_scale_id')->constrained()->cascadeOnDelete();
                $table->string('grade', 20);
                $table->decimal('min_score', 8, 2);
                $table->decimal('max_score', 8, 2);
                $table->decimal('grade_point', 5, 2);
                $table->boolean('is_pass')->default(true);
                $table->boolean('gpa_included')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->index(['grade_scale_id', 'status'], 'grade_scale_rows_scale_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_scale_rows');
    }
};
