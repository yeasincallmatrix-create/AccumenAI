<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per assessment-subject component configuration: full mark / pass mark /
 * order / status for one component inside one subject of one assessment.
 *
 * The authoritative full marks live here (spec: never store a typed total;
 * the total full mark is derived from these rows). mandatory_pass is prepared
 * for the later pass/fail layer and defaults to false.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assessment_subject_components')) {
            Schema::create('assessment_subject_components', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_subject_id')->constrained('assessment_subjects')->cascadeOnDelete();
                $table->foreignId('component_id')->constrained('components')->cascadeOnDelete();
                $table->decimal('full_mark', 10, 2)->default(0);
                $table->decimal('pass_mark', 10, 2)->default(0);
                $table->boolean('mandatory_pass')->default(false);
                $table->unsignedInteger('display_order')->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['assessment_subject_id', 'component_id'], 'asc_component_unique');
                $table->index('component_id', 'asc_component_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_subject_components');
    }
};
