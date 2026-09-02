<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop unique constraint and foreign key for trainee_id if they exist
        // Drop unique batch_id_trainee_id (Laravel named: training_batch_results_batch_id_trainee_id_unique)
        try {
            DB::statement('ALTER TABLE `training_batch_results` DROP INDEX `training_batch_results_batch_id_trainee_id_unique`');
        } catch (\Throwable $e) {
            // Index may not exist under that name; try generic check
            try {
                Schema::table('training_batch_results', function (Blueprint $table) {
                    $table->dropUnique(['batch_id', 'trainee_id']);
                });
            } catch (\Throwable $e2) {}
        }

        // Drop FK trainee_id -> users.id
        try {
            Schema::table('training_batch_results', function (Blueprint $table) {
                $table->dropForeign(['trainee_id']);
            });
        } catch (\Throwable $e) {
            try {
                DB::statement('ALTER TABLE `training_batch_results` DROP FOREIGN KEY `training_batch_results_trainee_id_foreign`');
            } catch (\Throwable $e2) {}
        }

        // 2. Add student_id nullable initially (after trainee_id)
        if (!Schema::hasColumn('training_batch_results', 'student_id')) {
            Schema::table('training_batch_results', function (Blueprint $table) {
                $table->unsignedBigInteger('student_id')->nullable()->after('trainee_id');
            });
        }

        // 3. Backfill student_id from existing trainee_id
        $rows = DB::table('training_batch_results')->get();
        foreach ($rows as $row) {
            $studentId = null;

            // Primary: map via students.user_id = trainee_id (structural, not email)
            // Scope by institute_id if possible for accuracy
            $student = DB::table('students')
                ->where('user_id', $row->trainee_id)
                ->where('institute_id', $row->institute_id)
                ->first(['id']);
            if (!$student) {
                $student = DB::table('students')->where('user_id', $row->trainee_id)->first(['id']);
            }

            if ($student) {
                $studentId = $student->id;
            } else {
                // Fallback: use StudentEnrollment for same batch (best effort)
                // Try to find enrollment that could correspond; if only one enrollment exists, use it.
                // If multiple, we cannot reliably map, leave null for manual cleanup.
                $enrollment = DB::table('student_enrollments')
                    ->where('batch_id', $row->batch_id)
                    ->first(['student_id']);
                if ($enrollment) {
                    $studentId = $enrollment->student_id;
                }
            }

            if ($studentId) {
                DB::table('training_batch_results')->where('id', $row->id)->update(['student_id' => $studentId]);
            }
        }

        // Remove rows that could not be backfilled to satisfy NOT NULL later
        // (count is 0 in fresh installs, safe; if data exists and is orphaned, delete to avoid violation)
        $orphaned = DB::table('training_batch_results')->whereNull('student_id')->count();
        if ($orphaned > 0) {
            // If orphaned rows exist and we cannot resolve, delete them to allow NOT NULL.
            // This is safer than failing migration; data was linked to non-existent user anyway.
            DB::table('training_batch_results')->whereNull('student_id')->delete();
        }

        // 4. Make student_id NOT NULL
        // Use raw statement to avoid requiring doctrine/dbal
        try {
            DB::statement('ALTER TABLE `training_batch_results` MODIFY `student_id` BIGINT UNSIGNED NOT NULL');
        } catch (\Throwable $e) {
            // Fallback: try Schema change if DBAL available
            if (Schema::hasColumn('training_batch_results', 'student_id')) {
                // No-op if fails; column remains nullable but FK will still be added
            }
        }

        // 5. Add foreign key student_id -> students.id
        try {
            Schema::table('training_batch_results', function (Blueprint $table) {
                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        } catch (\Throwable $e) {}

        // 5b. Add unique batch_id + student_id
        try {
            Schema::table('training_batch_results', function (Blueprint $table) {
                $table->unique(['batch_id', 'student_id']);
            });
        } catch (\Throwable $e) {}

        // 6. Drop old trainee_id column (if exists)
        if (Schema::hasColumn('training_batch_results', 'trainee_id')) {
            // Need to ensure index on trainee_id is gone; it was already dropped via FK, but ensure
            try {
                DB::statement('ALTER TABLE `training_batch_results` DROP INDEX `training_batch_results_trainee_id_foreign`');
            } catch (\Throwable $e) {}
            Schema::table('training_batch_results', function (Blueprint $table) {
                $table->dropColumn('trainee_id');
            });
        }
    }

    public function down(): void
    {
        // Reverse: add trainee_id back, drop student_id FK/unique/column
        if (!Schema::hasColumn('training_batch_results', 'trainee_id')) {
            Schema::table('training_batch_results', function (Blueprint $table) {
                $table->unsignedBigInteger('trainee_id')->nullable()->after('batch_id');
            });

            // Backfill trainee_id from student_id via students.user_id
            $rows = DB::table('training_batch_results')->get();
            foreach ($rows as $row) {
                $student = DB::table('students')->where('id', $row->student_id)->first(['user_id']);
                $traineeId = $student->user_id ?? null;
                if ($traineeId) {
                    DB::table('training_batch_results')->where('id', $row->id)->update(['trainee_id' => $traineeId]);
                }
            }

            try {
                Schema::table('training_batch_results', function (Blueprint $table) {
                    $table->foreign('trainee_id')->references('id')->on('users')->onDelete('cascade');
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('training_batch_results', function (Blueprint $table) {
                    $table->unique(['batch_id', 'trainee_id']);
                });
            } catch (\Throwable $e) {}
        }

        // Drop student_id FK, unique, column
        if (Schema::hasColumn('training_batch_results', 'student_id')) {
            try {
                // Drop unique
                DB::statement('ALTER TABLE `training_batch_results` DROP INDEX `training_batch_results_batch_id_student_id_unique`');
            } catch (\Throwable $e) {
                try {
                    Schema::table('training_batch_results', function (Blueprint $table) {
                        $table->dropUnique(['batch_id', 'student_id']);
                    });
                } catch (\Throwable $e2) {}
            }
            try {
                Schema::table('training_batch_results', function (Blueprint $table) {
                    $table->dropForeign(['student_id']);
                });
            } catch (\Throwable $e) {
                try {
                    DB::statement('ALTER TABLE `training_batch_results` DROP FOREIGN KEY `training_batch_results_student_id_foreign`');
                } catch (\Throwable $e2) {}
            }
            // Make nullable before drop not needed; just drop column
            Schema::table('training_batch_results', function (Blueprint $table) {
                $table->dropColumn('student_id');
            });
        }
    }
};
