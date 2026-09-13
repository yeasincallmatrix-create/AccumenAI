<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 36 — teacher/instructor professional profile.
 *
 * The teacher identity is an existing `institute_users` row (role `teacher`);
 * this table is a 1:1 extension holding instructor-specific professional data
 * that the identity table does not carry (specialization, experience,
 * employment type/status, bio, skills, languages, emergency contacts, ...).
 *
 * Branch/tenant identity is NOT duplicated here — it stays on institute_users
 * (the source of truth) so branch scoping keeps working through the existing
 * InstituteUser global scopes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('institute_user_id');
            $table->string('specialization', 150)->nullable();
            $table->unsignedSmallInteger('experience_years')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contractual', 'adjunct', 'volunteer'])->nullable();
            $table->enum('employment_status', ['active', 'inactive', 'suspended', 'resigned', 'terminated', 'on_leave'])->default('active');
            $table->date('date_of_birth')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->text('bio')->nullable();
            $table->json('skills')->nullable();
            $table->json('languages')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique('institute_user_id', 'uq_teacher_profiles_user');
            $table->index('institute_id', 'idx_teacher_profiles_institute');

            $table->foreign('institute_id', 'fk_teacher_profiles_institute')
                ->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('institute_user_id', 'fk_teacher_profiles_user')
                ->references('id')->on('institute_users')->cascadeOnDelete();
            $table->foreign('created_by', 'fk_teacher_profiles_created_by')
                ->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_teacher_profiles_updated_by')
                ->references('id')->on('institute_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_profiles');
    }
};
