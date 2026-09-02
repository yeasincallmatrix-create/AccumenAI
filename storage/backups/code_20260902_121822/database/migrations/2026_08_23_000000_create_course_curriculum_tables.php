<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curriculum entity + module/lesson hierarchy (Step 42).
 *
 * A curriculum is the academic structure of a course for one institute:
 * it has an auto-incrementing version number (unique per institute+course),
 * a draft/active/archived lifecycle and an effective date. It only carries
 * planned/academic-structure information — actual marks and grading stay in
 * the existing Assessment / Final Result pipeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_curricula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title', 200);
            $table->unsignedInteger('version');
            $table->date('effective_date')->nullable();
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->text('description')->nullable();
            $table->decimal('total_duration_hours', 8, 2)->nullable();
            $table->unsignedInteger('total_classes')->nullable();
            $table->json('learning_objectives')->nullable();
            $table->text('version_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['institute_id', 'course_id', 'version'], 'uq_curricula_institute_course_version');
            $table->index(['institute_id', 'course_id', 'status'], 'idx_curricula_institute_course_status');
        });

        Schema::create('curriculum_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('curriculum_id')->constrained('course_curricula')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('code', 40)->nullable();
            $table->text('description')->nullable();
            $table->string('module_type', 40)->nullable();
            $table->decimal('theory_marks', 6, 2)->nullable();
            $table->decimal('practical_marks', 6, 2)->nullable();
            $table->decimal('viva_marks', 6, 2)->nullable();
            $table->decimal('total_marks', 6, 2)->nullable();
            $table->decimal('credit_hours', 5, 2)->nullable();
            $table->unsignedInteger('class_count')->nullable();
            $table->decimal('duration_hours', 6, 2)->nullable();
            $table->boolean('is_optional')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['curriculum_id', 'display_order'], 'idx_curriculum_modules_order');
        });

        Schema::create('curriculum_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('curriculum_module_id')->constrained('curriculum_modules')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('learning_objective')->nullable();
            $table->string('content_reference', 500)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['curriculum_module_id', 'display_order'], 'idx_curriculum_lessons_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_lessons');
        Schema::dropIfExists('curriculum_modules');
        Schema::dropIfExists('course_curricula');
    }
};
