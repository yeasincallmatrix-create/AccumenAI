<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 43 — per-assessment lock.
 *
 * A locked assessment is frozen: marks entry, configuration edits and deletion
 * are refused until an explicitly permission-gated unlock. lock/unlock are
 * audited on the existing audit_logs table (module=education). The lock is a
 * flag (locked_at/locked_by) rather than a new lifecycle status so it can be
 * applied on top of any existing status (draft/scheduled/open/completed).
 * Published/frozen final-result snapshots remain protected by the existing
 * AcademicFinalResultLifecycleService guard; this lock protects assessments
 * that are not yet part of any frozen final result.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('academic_assessments', 'locked_at')) {
            Schema::table('academic_assessments', function (Blueprint $table) {
                $table->dateTime('locked_at')->nullable()->after('notes');
                $table->foreignId('locked_by')->nullable()->after('locked_at')
                    ->constrained('institute_users')->nullOnDelete();
                $table->index('locked_at', 'aca_locked_at_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('academic_assessments') && Schema::hasColumn('academic_assessments', 'locked_at')) {
            Schema::table('academic_assessments', function (Blueprint $table) {
                $table->dropIndex('aca_locked_at_idx');
                $table->dropConstrainedForeignId('locked_by');
                $table->dropColumn('locked_at');
            });
        }
    }
};
