<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotion decision items: the per-student verdict of one decision cycle.
 *
 * Each item references the SOURCE placement (never updated or deleted by
 * promotion) and the student. `decision` is the promotion outcome — kept
 * strictly separate from final-result status and placement status.
 *
 * target_class_grade_id / target_academic_group_id declare where the student
 * moves next (same group, changed group, or none). next_placement_id links to
 * the NEW placement row created for the target academic year — set only at
 * approval time, which is how the promotion decision is historically traced
 * to its resulting placement.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotion_decision_items')) {
            Schema::create('promotion_decision_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('decision_id')->constrained('promotion_decisions')->cascadeOnDelete();
                $table->foreignId('placement_id')->constrained('student_academic_placements')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->string('decision', 30)->default('pending');
                $table->json('reasons')->nullable();
                $table->foreignId('target_class_grade_id')->nullable()->constrained('class_grades')->cascadeOnDelete();
                $table->foreignId('target_academic_group_id')->nullable()->constrained('academic_groups')->nullOnDelete();
                $table->foreignId('next_placement_id')->nullable()->constrained('student_academic_placements')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->unique(['decision_id', 'placement_id'], 'pdi_decision_placement_unique');
                $table->index(['decision_id', 'decision'], 'pdi_decision_verdict_idx');
                $table->index('placement_id', 'pdi_placement_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_decision_items');
    }
};
