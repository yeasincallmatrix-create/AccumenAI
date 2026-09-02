<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotion policy rules: a controlled, ordered list of conditions.
 *
 * Rule types are a closed enum (overall_pass | gpa_threshold |
 * max_failed_subjects | mandatory_pass | conditional). Boolean rule types
 * (overall_pass / mandatory_pass) leave field/operator/value NULL; numeric
 * rule types compare `field` against `value` with a controlled operator.
 *
 * Each rule yields pass_action when it holds and fail_action otherwise. The
 * evaluation service combines per-rule verdicts by severity — this is NOT an
 * arbitrary programming language.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotion_policy_rules')) {
            Schema::create('promotion_policy_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('policy_id')->constrained('promotion_policies')->cascadeOnDelete();
                $table->string('rule_type', 30);
                $table->string('field', 40)->nullable();
                $table->string('operator', 10)->nullable();
                $table->string('value', 20)->nullable();
                $table->string('pass_action', 30)->default('promoted');
                $table->string('fail_action', 30)->default('repeat');
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->index(['policy_id', 'display_order'], 'ppr_policy_order_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_policy_rules');
    }
};
