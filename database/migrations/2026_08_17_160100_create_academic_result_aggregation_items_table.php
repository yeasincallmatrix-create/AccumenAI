<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Items of an aggregation scheme: which assessment participates and with what
 * manually configured weight.
 *
 * - weight is a percentage (0..100); the scheme's manually configured weights
 *   are NEVER overwritten by the calculation layer — that is a configuration,
 *   not a derived value.
 * - The same assessment is reusable in multiple schemes (unique is per scheme).
 * - status 'active' marks the item as participating; other statuses are
 *   reserved for later result-policy layers.
 * - display_order controls the configured ordering shown in the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_result_aggregation_items')) {
            Schema::create('academic_result_aggregation_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('scheme_id')->constrained('academic_result_aggregation_schemes')->cascadeOnDelete();
                $table->foreignId('academic_assessment_id')->constrained('academic_assessments')->cascadeOnDelete();
                $table->decimal('weight', 8, 2)->default(0);
                $table->unsignedInteger('display_order')->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['scheme_id', 'academic_assessment_id'], 'ari_scheme_assessment_unique');
                $table->index('academic_assessment_id', 'ari_assessment_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_result_aggregation_items');
    }
};
