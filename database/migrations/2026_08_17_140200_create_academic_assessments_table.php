<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic assessment / exam INSTANCE for one academic year + class/grade
 * (optionally an academic group/stream).
 *
 * - institute-scoped (TenantScoped on the model).
 * - branch_id NULL = whole-institute assessment (visible to every branch);
 *   otherwise the assessment belongs to one branch (BranchScoped-style rule).
 * - name is free-text so institutions can define their own assessments; it
 *   normally mirrors the selected assessment_type name.
 * - display_order gives explicit sequencing within the same
 *   year + class + group context (spec: never rely on DB id for ordering).
 * - status lifecycle: draft → scheduled → open → completed (or cancelled).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_assessments')) {
            Schema::create('academic_assessments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
                $table->foreignId('class_grade_id')->nullable()->constrained('class_grades')->nullOnDelete();
                $table->foreignId('academic_group_id')->nullable()->constrained('academic_groups')->nullOnDelete();
                $table->foreignId('assessment_type_id')->nullable()->constrained('assessment_types')->nullOnDelete();
                $table->string('name', 120);
                $table->dateTime('exam_date')->nullable();
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('display_order')->default(0);
                $table->string('notes', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamps();

                $table->index(['institute_id', 'academic_year_id', 'class_grade_id', 'status'], 'aca_year_class_status_idx');
                $table->index(['institute_id', 'branch_id'], 'aca_institute_branch_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_assessments');
    }
};
