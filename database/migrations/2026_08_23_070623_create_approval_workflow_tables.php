<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STEP 75 — Approval Workflow tables.
 *
 * approval_workflows: workflow definitions (e.g. "Expense > 50000")
 * approval_steps: ordered approver steps per workflow
 * approval_requests: instances of a workflow being triggered
 * approval_actions: individual approve/reject actions on requests
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('module', ['expense', 'purchase', 'payment', 'journal_adjustment']);
            $table->decimal('amount_from', 19, 4)->default(0);
            $table->decimal('amount_to', 19, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id', 'module', 'is_active']);
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->foreignId('approver_role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['workflow_id', 'step_order']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->string('ref_type');
            $table->unsignedBigInteger('ref_id');
            $table->decimal('amount', 19, 4)->default(0);
            $table->enum('status', ['draft', 'submitted', 'pending_approval', 'approved', 'rejected'])->default('draft');
            $table->unsignedInteger('current_step')->default(0);
            $table->foreignId('requested_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'status']);
            $table->index(['ref_type', 'ref_id']);
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->foreignId('approver_id')->constrained('institute_users')->cascadeOnDelete();
            $table->enum('action', ['approved', 'rejected']);
            $table->text('notes')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['request_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
