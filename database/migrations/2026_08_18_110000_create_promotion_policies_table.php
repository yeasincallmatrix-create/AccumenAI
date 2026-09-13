<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotion policies: one configurable rule set per academic context
 * (source academic year + class + optional group).
 *
 * A policy is pure configuration. It never touches final results or
 * placements; it only declares WHICH published academic_final_result is
 * evaluated and HOW (through promotion_policy_rules). The academic context
 * columns are FKs to existing master data (academic_years / class_grades /
 * academic_groups) — no academic structure is duplicated here.
 *
 * branch_id NULL = whole-institute policy; otherwise the policy belongs to
 * one branch. The global branch scope (PromotionPolicy::booted) enforces
 * visibility and branch_id is never read from request input.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotion_policies')) {
            Schema::create('promotion_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 120);
                $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
                $table->foreignId('class_grade_id')->constrained('class_grades')->cascadeOnDelete();
                $table->foreignId('academic_group_id')->nullable()->constrained('academic_groups')->cascadeOnDelete();
                $table->string('status', 20)->default('draft');
                $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamps();

                $table->index(['institute_id', 'status'], 'pp_institute_status_idx');
                $table->index(['academic_year_id', 'class_grade_id'], 'pp_year_class_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_policies');
    }
};
