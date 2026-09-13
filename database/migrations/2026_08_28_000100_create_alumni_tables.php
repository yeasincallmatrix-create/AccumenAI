<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 48 — Alumni Management.
 *
 * A single institute-scoped alumni profile per student. Academic provenance
 * (graduation date, completion academic year, completed course/batch) is NOT
 * duplicated as a new source of truth — it is derived from the existing
 * approved promotion decision (completed / graduated on a published final
 * result) and snapshotted as foreign keys to the existing academic records,
 * while the live academic tables stay untouched.
 *
 * Only alumni-specific/career fields are mutable here. Tenant + branch
 * isolation is inherited through the owning Student (BranchScoped).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->string('alumni_reference_number', 40)->nullable();
            $table->date('graduation_date')->nullable();
            $table->foreignId('completion_academic_year_id')
                ->nullable()
                ->constrained('academic_years')
                ->nullOnDelete();
            $table->foreignId('completed_course_id')
                ->nullable()
                ->constrained('courses')
                ->nullOnDelete();
            $table->foreignId('completed_batch_id')
                ->nullable()
                ->constrained('batches')
                ->nullOnDelete();
            $table->foreignId('crm_contact_id')
                ->nullable()
                ->constrained('crm_contacts')
                ->nullOnDelete();

            $table->string('current_occupation', 150)->nullable();
            $table->string('job_title', 150)->nullable();
            $table->string('employer', 150)->nullable();
            $table->string('employment_sector', 150)->nullable();
            $table->text('higher_education')->nullable();
            $table->text('career_notes')->nullable();
            $table->string('current_city', 120)->nullable();
            $table->string('current_country', 120)->nullable();
            $table->enum('public_contact_preference', ['private', 'email', 'phone', 'both'])->default('private');
            $table->enum('profile_visibility', ['private', 'public'])->default('private');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('institute_users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('institute_users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['institute_id', 'student_id'], 'uq_alumni_institute_student');
            $table->index(['institute_id'], 'idx_alumni_institute');
            $table->index(['status'], 'idx_alumni_status');
            $table->index(['graduation_date'], 'idx_alumni_graduation_date');
            $table->index(['completion_academic_year_id'], 'idx_alumni_completion_year');
            $table->index(['completed_course_id'], 'idx_alumni_completed_course');
            $table->index(['completed_batch_id'], 'idx_alumni_completed_batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni');
    }
};
