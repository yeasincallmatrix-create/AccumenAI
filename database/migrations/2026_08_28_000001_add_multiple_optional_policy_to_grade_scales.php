<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Phase A7 — Multiple optional subjects policy.
 *
 * Default = single (one optional, Bangladesh 4th subject). Alternatives:
 *   single = only first optional (lowest subject_id) contributes bonus
 *   best   = max bonus among optionals
 *   sum    = sum of all optional bonuses (previous accidental behavior)
 *
 * Pre-flight: ensure grade_scales table exists and column not already present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('grade_scales')) {
            return;
        }
        if (Schema::hasColumn('grade_scales', 'multiple_optional_policy')) {
            return;
        }

        // Pre-flight orphan check: no orphan needed, column is nullable default
        Schema::table('grade_scales', function (Blueprint $table) {
            $table->enum('multiple_optional_policy', ['single', 'best', 'sum'])->default('single')->after('optional_subject_bonus_enabled')
                ->comment('Multiple optional policy: single (default, one optional), best (max bonus), sum (all)');
        });

        // Backfill existing rows to default single (already default, but ensure)
        DB::table('grade_scales')->whereNull('multiple_optional_policy')->orWhere('multiple_optional_policy', '')->update(['multiple_optional_policy' => 'single']);
    }

    public function down(): void
    {
        if (Schema::hasTable('grade_scales') && Schema::hasColumn('grade_scales', 'multiple_optional_policy')) {
            Schema::table('grade_scales', function (Blueprint $table) {
                $table->dropColumn('multiple_optional_policy');
            });
        }
    }
};
