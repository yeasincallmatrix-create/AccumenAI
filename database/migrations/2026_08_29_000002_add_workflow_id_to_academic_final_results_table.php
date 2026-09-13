<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2-2 — Wire Workflow into Final Result Lifecycle.
 * Adds workflow_id to academic_final_results so the multi-step
 * approval workflow (final_result_review) can be linked to the result.
 * Nullable, nullOnDelete — existing results remain valid without workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('academic_final_results') && ! Schema::hasColumn('academic_final_results', 'workflow_id')) {
            Schema::table('academic_final_results', function (Blueprint $table) {
                $table->foreignId('workflow_id')->nullable()->after('scheme_id')->constrained('workflows')->nullOnDelete();
                $table->index('workflow_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('academic_final_results') && Schema::hasColumn('academic_final_results', 'workflow_id')) {
            Schema::table('academic_final_results', function (Blueprint $table) {
                $table->dropForeign(['workflow_id']);
                $table->dropColumn('workflow_id');
            });
        }
    }
};
