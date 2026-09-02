<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 32 — finance core whole-institute + industry-neutral compatibility.
 *
 * 1. opening_balances / statement_snapshots: allow branch_id = NULL so owners
 *    and institute admins can work at the whole-institute level, matching the
 *    BranchScopedOrShared convention (branch NULL = institute-wide). FK switches
 *    to nullOnDelete so deleting a branch never destroys its reports/balances.
 * 2. invoices / payments / installments: legacy student_id is made nullable so
 *    the industry-neutral finance core can invoice customers/suppliers that
 *    are not students. Education invoices keep student_id as before.
 *
 * Schema-only changes: existing rows are never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['opening_balances', 'statement_snapshots'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->unsignedBigInteger('branch_id')->nullable()->change();
                $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            });
        }

        foreach (['invoices', 'payments', 'installments'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'student_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('student_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('statement_snapshots')) {
            Schema::table('statement_snapshots', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->unsignedBigInteger('branch_id')->nullable(false)->change();
                $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('opening_balances')) {
            Schema::table('opening_balances', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->unsignedBigInteger('branch_id')->nullable(false)->change();
                $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            });
        }

        foreach (['invoices', 'payments', 'installments'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'student_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('student_id')->nullable(false)->change();
            });
        }
    }
};
