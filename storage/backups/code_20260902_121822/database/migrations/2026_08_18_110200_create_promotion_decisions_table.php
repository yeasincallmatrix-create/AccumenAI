<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotion decisions: one evaluation cycle for ONE published final result.
 *
 * result_id MUST reference a published academic_final_results row — the
 * frozen snapshot is the only promotion source (an in-flight result can never
 * start a decision; the lifecycle service enforces that).
 *
 * Status chain: pending → review → approved.
 *   - pending:   items just materialized from the published snapshot.
 *   - review:    operator is reviewing / adjusting per-student targets.
 *   - approved:  terminal — next-year placements created, items approved.
 *
 * At most one in-flight (pending / review) decision may exist for the same
 * published result; the lifecycle service enforces that rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotion_decisions')) {
            Schema::create('promotion_decisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('policy_id')->constrained('promotion_policies')->cascadeOnDelete();
                $table->foreignId('result_id')->constrained('academic_final_results')->cascadeOnDelete();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
                $table->string('status', 20)->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamps();

                $table->index(['policy_id', 'status'], 'pd_policy_status_idx');
                $table->index(['result_id', 'status'], 'pd_result_status_idx');
                $table->index('institute_id', 'pd_institute_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_decisions');
    }
};
