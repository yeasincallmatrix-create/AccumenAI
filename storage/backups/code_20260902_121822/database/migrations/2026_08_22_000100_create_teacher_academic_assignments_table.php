<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 36 — teaching workload / academic assignment for a teacher.
 *
 * Assigns a teacher (institute_users) to an academic year + branch + any of
 * course / subject / batch / class / group with a responsibility. History is
 * preserved: deactivating a teacher never touches these rows and academic /
 * branch reference FKs are nullOnDelete so a deleted reference (year, course,
 * subject, batch, ...) cannot destroy historical assignments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_academic_assignments', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('institute_user_id');
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('class_grade_id')->nullable();
            $table->unsignedBigInteger('academic_group_id')->nullable();
            $table->enum('responsibility', ['course_instructor', 'subject_teacher', 'class_teacher', 'batch_coordinator', 'practical_instructor', 'examiner'])->default('subject_teacher');
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->date('assigned_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'branch_id'], 'idx_teacher_assign_inst_branch');
            $table->index(['institute_id', 'institute_user_id'], 'idx_teacher_assign_inst_teacher');
            $table->index(['institute_id', 'academic_year_id'], 'idx_teacher_assign_inst_year');
            $table->index(['institute_id', 'status'], 'idx_teacher_assign_inst_status');
            $table->index(['institute_id', 'course_id'], 'idx_teacher_assign_inst_course');
            $table->index(['institute_id', 'subject_id'], 'idx_teacher_assign_inst_subject');
            $table->index(['institute_id', 'batch_id'], 'idx_teacher_assign_inst_batch');

            $table->foreign('institute_id', 'fk_teacher_assign_institute')
                ->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_teacher_assign_branch')
                ->references('id')->on('branches')->nullOnDelete();
            $table->foreign('institute_user_id', 'fk_teacher_assign_user')
                ->references('id')->on('institute_users')->cascadeOnDelete();
            $table->foreign('academic_year_id', 'fk_teacher_assign_year')
                ->references('id')->on('academic_years')->nullOnDelete();
            $table->foreign('course_id', 'fk_teacher_assign_course')
                ->references('id')->on('courses')->nullOnDelete();
            $table->foreign('subject_id', 'fk_teacher_assign_subject')
                ->references('id')->on('subjects')->nullOnDelete();
            $table->foreign('batch_id', 'fk_teacher_assign_batch')
                ->references('id')->on('batches')->nullOnDelete();
            $table->foreign('class_grade_id', 'fk_teacher_assign_class')
                ->references('id')->on('class_grades')->nullOnDelete();
            $table->foreign('academic_group_id', 'fk_teacher_assign_group')
                ->references('id')->on('academic_groups')->nullOnDelete();
            $table->foreign('created_by', 'fk_teacher_assign_created_by')
                ->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_teacher_assign_updated_by')
                ->references('id')->on('institute_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_academic_assignments');
    }
};
