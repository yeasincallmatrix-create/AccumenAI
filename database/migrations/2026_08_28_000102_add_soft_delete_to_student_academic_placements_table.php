<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_academic_placements')) {
            Schema::table('student_academic_placements', function (Blueprint $table) {
                if (! Schema::hasColumn('student_academic_placements', 'deleted_at')) {
                    $table->softDeletes()->after('updated_at');
                }
            });

            // Backfill: ensure existing archived status rows are soft-deleted if needed
            // No existing data rows modified per safety rule, only schema change.
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('student_academic_placements') && Schema::hasColumn('student_academic_placements', 'deleted_at')) {
            Schema::table('student_academic_placements', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
