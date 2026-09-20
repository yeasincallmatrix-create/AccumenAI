<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 9: pathologist reference-range sign-off on parameter maps.
     * Additive nullable columns only; reversible.
     */
    public function up(): void
    {
        Schema::table('lab_analyzer_parameter_maps', function (Blueprint $table) {
            if (! Schema::hasColumn('lab_analyzer_parameter_maps', 'ref_range_approved_by')) {
                $table->unsignedBigInteger('ref_range_approved_by')->nullable()->after('ref_range_text');
                $table->timestamp('ref_range_approved_at')->nullable()->after('ref_range_approved_by');
                $table->text('ref_range_approval_notes')->nullable()->after('ref_range_approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lab_analyzer_parameter_maps', function (Blueprint $table) {
            foreach (['ref_range_approved_by', 'ref_range_approved_at', 'ref_range_approval_notes'] as $col) {
                if (Schema::hasColumn('lab_analyzer_parameter_maps', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
