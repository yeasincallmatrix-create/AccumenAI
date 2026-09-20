<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B84 (CRITICAL): retarget course_curricula actor FKs to institute_users.
 *
 * Drift: created_by / updated_by reference users(id), but every caller
 * (CourseCurriculumService via CurriculumController, institute_user guard)
 * writes institute_users ids — the app-wide convention documented in
 * ResolvesInstitute ("created_by/updated_by references institute_users").
 * Any curriculum creation with a real actor violates the FK, in production
 * as well as tests (9 errors in CourseCurriculumManagementTest).
 *
 * Data safety (verified 2026-09-20 on accumen_ai): course_curricula holds
 * 0 rows, so no existing row can violate the new target. Both PK types
 * are bigint unsigned — type-compatible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_curricula')) {
            return;
        }

        if ($this->referencedTable('course_curricula_created_by_foreign') === 'institute_users'
            && $this->referencedTable('course_curricula_updated_by_foreign') === 'institute_users') {
            return;
        }

        Schema::table('course_curricula', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });
        Schema::table('course_curricula', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
        });

        Schema::table('course_curricula', function (Blueprint $table) {
            $table->foreign('created_by', 'course_curricula_created_by_foreign')
                ->references('id')->on('institute_users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'course_curricula_updated_by_foreign')
                ->references('id')->on('institute_users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('course_curricula')) {
            return;
        }

        Schema::table('course_curricula', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });
        Schema::table('course_curricula', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
        });

        Schema::table('course_curricula', function (Blueprint $table) {
            $table->foreign('created_by', 'course_curricula_created_by_foreign')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'course_curricula_updated_by_foreign')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    private function referencedTable(string $constraint): ?string
    {
        $row = DB::selectOne(
            "SELECT REFERENCED_TABLE_NAME AS t FROM information_schema.key_column_usage
             WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?",
            [$constraint]
        );

        return $row?->t;
    }
};
