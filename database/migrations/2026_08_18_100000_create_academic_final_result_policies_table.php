<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Final-result policies: one configuration row per aggregation scheme (1:1).
 *
 * A scheme (Step 8) already declares the academic context (year + class +
 * group) and which assessments combine. The policy adds the result-lifecycle
 * knobs a Step-10 owner needs without touching the aggregation scheme itself:
 *
 *   - absent_renormalization  whether ENTERED weights are re-scaled to 100%
 *     when an ABSENT assessment drops out (default TRUE = the long-standing
 *     behavior; exposing it here is the Step-10 seam, the default never
 *     changes what existing schemes produce).
 *   - grade_scale_id          optional per-context grade-scale override;
 *     NULL keeps the normal resolution ladder (institute → level → system →
 *     country → global).
 *   - require_approval        whether LOCK requires an explicit APPROVE step
 *     (default TRUE = the review → approve → lock → publish chain).
 *
 * Branch rule mirrors academic_result_aggregation_schemes: branch_id NULL =
 * whole-institute policy; otherwise it belongs to one branch and the global
 * branch scope enforces visibility. branch_id is never read from input.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_final_result_policies')) {
            Schema::create('academic_final_result_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('scheme_id')->constrained('academic_result_aggregation_schemes')->cascadeOnDelete();
                $table->string('name', 120);
                $table->boolean('absent_renormalization')->default(true);
                $table->foreignId('grade_scale_id')->nullable()->constrained('grade_scales')->nullOnDelete();
                $table->boolean('require_approval')->default(true);
                $table->string('status', 20)->default('active');
                $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamps();

                $table->unique('scheme_id', 'frp_scheme_unique');
                $table->index('institute_id', 'frp_institute_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_final_result_policies');
    }
};
