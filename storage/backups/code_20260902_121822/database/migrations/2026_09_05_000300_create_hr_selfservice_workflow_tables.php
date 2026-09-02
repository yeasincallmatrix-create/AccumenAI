<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR-9 — Employee Self-Service & Workflow.
 *
 * - hr_employee_requests: generic HR workflow (profile_update, transfer, etc.)
 * Reuses leave/attendance correction/document verification tables as workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employee_requests', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->enum('request_type', ['profile_update','transfer','promotion','other'])->default('other');
            $table->json('payload')->nullable();
            $table->text('reason')->nullable();
            $table->enum('status', ['pending','approved','rejected','cancelled'])->default('pending');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index('institute_id', 'idx_hr_req2_inst');
            $table->index('employee_id', 'idx_hr_req2_emp');
            $table->index('status', 'idx_hr_req2_status');

            $table->foreign('institute_id', 'fk_hr_req2_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_req2_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('employee_id', 'fk_hr_req2_emp')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('requested_by', 'fk_hr_req2_requested')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('reviewed_by', 'fk_hr_req2_reviewed')->references('id')->on('institute_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_requests');
    }
};
