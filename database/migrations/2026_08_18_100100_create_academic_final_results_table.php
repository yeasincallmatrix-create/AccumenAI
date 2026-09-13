<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Final-result lifecycle records for ONE publish cycle of a policy.
 *
 * Status flow (Step 10):
 *   review   →  approved  →  locked  →  published
 *
 * review            = the derived preview (Step 9) is being verified; nothing
 *                     is persisted yet for the cycle beyond this header.
 * approved          = a reviewer signed off (approver + timestamp recorded).
 * locked            = the snapshot rows (academic_final_result_rows /
 *                     academic_final_result_students) are materialized and the
 *                     cycle is frozen; source marks participate in a
 *                     published/locked cycle can no longer be edited.
 * published         = terminal; the snapshot is the official result.
 *
 * At most one non-published (active) result exists per policy; published
 * cycles accumulate so a policy can carry term/version history. The lifecycle
 * service enforces active-uniqueness; there is no fragile partial index here.
 *
 * Institute + branch scoping mirrors the aggregation scheme (branch_id NULL =
 * whole-institute result).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_final_results')) {
            Schema::create('academic_final_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('policy_id')->constrained('academic_final_result_policies')->cascadeOnDelete();
                $table->foreignId('scheme_id')->constrained('academic_result_aggregation_schemes')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('status', 20)->default('review');
                $table->foreignId('reviewed_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('published_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('computed_at')->nullable();
                $table->timestamps();

                $table->index(['policy_id', 'status'], 'afr_policy_status_idx');
                $table->index('institute_id', 'afr_institute_idx');
                $table->index('scheme_id', 'afr_scheme_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_final_results');
    }
};
