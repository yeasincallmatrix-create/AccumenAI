<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 36 — per-institute employee/instructor code sequence + uniqueness.
 *
 * `institute_users.employee_id` is the instructor code column (already exists,
 * currently unused). This migration adds a per-institute atomic counter table
 * (mirrors the reg_no_sequence pattern) and a unique composite index on
 * (institute_id, employee_id) so the generated codes are collision-safe within
 * the tenant scope. NULL employee_ids (all non-teacher staff) never conflict.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_code_sequences', function (Blueprint $table) {
            $table->unsignedBigInteger('institute_id')->primary();
            $table->unsignedBigInteger('last_sequence');
            $table->timestamps();

            $table->foreign('institute_id', 'fk_teacher_code_seq_institute')
                ->references('id')->on('institutes')->cascadeOnDelete();
        });

        Schema::table('institute_users', function (Blueprint $table) {
            $table->unique(['institute_id', 'employee_id'], 'uq_institute_users_institute_employee');
        });
    }

    public function down(): void
    {
        Schema::table('institute_users', function (Blueprint $table) {
            $table->dropUnique('uq_institute_users_institute_employee');
        });

        Schema::dropIfExists('teacher_code_sequences');
    }
};
