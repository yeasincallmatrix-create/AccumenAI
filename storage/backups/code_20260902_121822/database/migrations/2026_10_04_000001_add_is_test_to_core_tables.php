<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * DATA SAFETY GUARDRAILS — Explicit test data flag.
 *
 * Adds is_test to core tables:
 *  - users
 *  - institutes
 *  - institution_user (memberships)
 *
 * Default: false (production-safe). Existing rows default to 0 via DB default.
 * NULL must be treated as PROTECTED (not safe to delete) by application logic.
 *
 * This migration is NON-DESTRUCTIVE: additive only, no data loss.
 */
return new class extends Migration
{
    private array $tables = ['users', 'institutes', 'institution_user'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'is_test')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('is_test')->default(false)->after('id')->comment('Explicit test/demo marker; NULL/false = PROTECTED production data');
            });
            // Backfill existing rows explicitly to false for clarity (DB default already 0, but ensure deterministic)
            try {
                DB::table($table)->whereNull('is_test')->update(['is_test' => false]);
            } catch (\Throwable $e) {}
        }

        // Optional: also add to students and courses for business-data safety where relevant
        $extra = ['students', 'courses', 'batches'];
        foreach ($extra as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'is_test')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('is_test')->default(false)->after('id')->comment('Explicit test/demo marker; NULL/false = PROTECTED');
            });
            try {
                DB::table($table)->whereNull('is_test')->update(['is_test' => false]);
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        $all = ['users', 'institutes', 'institution_user', 'students', 'courses', 'batches'];
        foreach ($all as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'is_test')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('is_test');
            });
        }
    }
};
