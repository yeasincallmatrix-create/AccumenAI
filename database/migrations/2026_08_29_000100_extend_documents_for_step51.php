<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 51 — Education Document / Workflow Automation.
 *
 * Extends the existing Step 46 document infrastructure with:
 *   - verification workflow columns on documents
 *   - expiry tracking
 *   - lifecycle-stage + requirement metadata on categories
 *   - document_versions table (preserves history on replace)
 *   - workflows + workflow_steps + workflow_histories tables
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            $table->string('code', 60)->nullable()->after('slug');
            $table->text('description')->nullable()->after('code');
            $table->boolean('is_required')->default(false)->after('description');
            $table->string('lifecycle_stage', 60)->nullable()->after('is_required');
            $table->json('allowed_file_types')->nullable()->after('lifecycle_stage');
            $table->unsignedInteger('max_file_size_kb')->nullable()->after('allowed_file_types');
            $table->boolean('expiry_applicable')->default(false)->after('max_file_size_kb');
            $table->boolean('verification_required')->default(false)->after('expiry_applicable');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('verification_status', 40)->default('pending_verification')->after('status');
            $table->foreignId('verified_by')->nullable()->after('verification_status')->constrained('institute_users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('rejection_reason')->nullable()->after('verified_at');
            $table->text('verification_notes')->nullable()->after('rejection_reason');
            $table->date('issue_date')->nullable()->after('verification_notes');
            $table->date('expiry_date')->nullable()->after('issue_date');
            $table->string('source', 30)->default('uploaded')->after('expiry_date');
            $table->unsignedBigInteger('placement_id')->nullable()->after('source');
            $table->unsignedBigInteger('enrollment_id')->nullable()->after('placement_id');
            $table->unsignedBigInteger('course_id')->nullable()->after('enrollment_id');
            $table->unsignedBigInteger('batch_id')->nullable()->after('course_id');

            $table->index(['institute_id', 'verification_status'], 'idx_documents_verification');
            $table->index(['institute_id', 'expiry_date'], 'idx_documents_expiry');
            $table->index(['documentable_type', 'documentable_id', 'category_id'], 'idx_documents_entity_category');
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('original_filename', 255);
            $table->string('file_path', 500);
            $table->string('disk', 50)->default('public');
            $table->string('mime_type', 120)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['document_id', 'version'], 'idx_document_versions_doc_ver');
        });

        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('workflow_type', 80);
            $table->string('title', 255);
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('entity_type', 120)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('status', 40)->default('draft');
            $table->unsignedInteger('current_step')->default(1);
            $table->foreignId('initiated_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id', 'status'], 'idx_workflows_institute_status');
            $table->index(['institute_id', 'workflow_type'], 'idx_workflows_institute_type');
            $table->index(['student_id'], 'idx_workflows_student');
            $table->index(['branch_id'], 'idx_workflows_branch');
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('name', 200);
            $table->string('responsible_role', 80)->nullable();
            $table->string('responsible_permission', 80)->nullable();
            $table->string('status', 40)->default('pending');
            $table->foreignId('acted_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'step_order'], 'idx_workflow_steps_order');
        });

        Schema::create('workflow_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['workflow_id'], 'idx_workflow_histories_workflow');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_histories');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('document_versions');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_documents_verification');
            $table->dropIndex('idx_documents_expiry');
            $table->dropIndex('idx_documents_entity_category');
            $table->dropColumn([
                'verification_status', 'verified_by', 'verified_at',
                'rejection_reason', 'verification_notes',
                'issue_date', 'expiry_date', 'source',
                'placement_id', 'enrollment_id', 'course_id', 'batch_id',
            ]);
        });

        Schema::table('document_categories', function (Blueprint $table) {
            $table->dropColumn([
                'code', 'description', 'is_required', 'lifecycle_stage',
                'allowed_file_types', 'max_file_size_kb',
                'expiry_applicable', 'verification_required',
            ]);
        });
    }
};
