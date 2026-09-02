<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which subjects an academic assessment covers.
 *
 * Pivot between academic_assessments and subjects. Every row can then be
 * independently configured with its own components (assessment_subject_components),
 * so Mathematics and Physics can carry entirely different component splits
 * inside the same assessment. One subject can only appear once per assessment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assessment_subjects')) {
            Schema::create('assessment_subjects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_id')->constrained('academic_assessments')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->unsignedInteger('display_order')->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['assessment_id', 'subject_id']);
                $table->index('subject_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_subjects');
    }
};
