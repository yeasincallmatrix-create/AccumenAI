<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student's subject selection for one academic placement.
 *
 * Only SELECTED subjects are stored (mandatory are auto-included when the
 * placement is created/saved). Each row references the real Subject Master and
 * the source requirement/source-of-record at selection time so historical
 * selections are never silently recalculated when curriculum config changes.
 *
 * unique(academic_placement_id, subject_id) prevents selecting the same subject
 * twice for one placement at the database level.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_subject_selections')) {
            Schema::create('student_subject_selections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_placement_id')->constrained('student_academic_placements')->cascadeOnDelete();
                $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
                $table->foreignId('selection_group_id')->nullable()->constrained('academic_selection_groups')->nullOnDelete();
                $table->boolean('is_selected')->default(true);
                $table->boolean('is_mandatory')->default(false);
                $table->string('source', 20)->nullable(); // inherited | customized | custom
                $table->timestamps();

                $table->unique(['academic_placement_id', 'subject_id'], 'sss_placement_subject_unique');
                $table->index(['academic_placement_id', 'is_selected'], 'sss_placement_selected_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subject_selections');
    }
};
