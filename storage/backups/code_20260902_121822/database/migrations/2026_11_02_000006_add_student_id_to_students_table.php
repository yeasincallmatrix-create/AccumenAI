<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        // Step 1: Add nullable column (if not exists) - DDL cannot be inside transaction on MySQL (implicit commit)
        if (! Schema::hasColumn('students', 'student_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('student_id', 6)->nullable()->after('reg_no');
            });
        }

        // Step 2: Add unique constraint institute_id + student_id (if not exists)
        $hasUnique = false;
        try {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('students');
            foreach ($indexes as $idx) {
                $cols = $idx->getColumns();
                $colsLower = array_map('strtolower', $cols);
                if ($idx->isUnique() && $colsLower === ['institute_id', 'student_id']) {
                    $hasUnique = true;
                    break;
                }
                if ($idx->getName() === 'students_institute_id_student_id_unique') {
                    $hasUnique = true;
                    break;
                }
            }
        } catch (\Throwable $e) {
            try {
                $indexes = DB::select('SHOW INDEX FROM `students` WHERE Key_name = ?', ['students_institute_id_student_id_unique']);
                if (! empty($indexes)) $hasUnique = true;
            } catch (\Throwable $e2) {}
        }

        if (! $hasUnique) {
            try {
                Schema::table('students', function (Blueprint $table) {
                    $table->unique(['institute_id', 'student_id'], 'students_institute_id_student_id_unique');
                });
            } catch (\Throwable $e) {
                try {
                    DB::statement('CREATE UNIQUE INDEX `students_institute_id_student_id_unique` ON `students` (`institute_id`, `student_id`)');
                } catch (\Throwable $e2) {}
            }
        }

        // Step 3: Backfill existing students where student_id IS NULL - wrapped in transaction per safety rules (DML only)
        if (Schema::hasColumn('students', 'student_id')) {
            $needsBackfill = DB::table('students')->whereNull('student_id')->exists();
            if ($needsBackfill) {
                DB::transaction(function () {
                    $students = DB::table('students')->whereNull('student_id')->get(['id', 'institute_id']);
                    foreach ($students as $student) {
                        $instituteId = $student->institute_id;
                        $studentId = function_exists('generateInstituteStudentId')
                            ? generateInstituteStudentId($instituteId)
                            : str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);

                        $attempts = 0;
                        while (DB::table('students')->where('institute_id', $instituteId)->where('student_id', $studentId)->exists()) {
                            $studentId = function_exists('generateInstituteStudentId')
                                ? generateInstituteStudentId($instituteId)
                                : str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
                            $attempts++;
                            if ($attempts > 50) break;
                        }

                        DB::table('students')->where('id', $student->id)->update(['student_id' => $studentId]);
                    }
                });
            }

            // Step 4: Make NOT NULL after backfill - DDL, outside transaction
            $nullCount = 0;
            try {
                $nullCount = DB::table('students')->whereNull('student_id')->count();
            } catch (\Throwable $e) {}

            if ($nullCount === 0) {
                // Check if already NOT NULL to avoid unnecessary ALTER
                $isNotNull = false;
                try {
                    $col = DB::selectOne("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'student_id'");
                    if ($col && $col->IS_NULLABLE === 'NO') $isNotNull = true;
                } catch (\Throwable $e) {}

                if (! $isNotNull) {
                    try {
                        DB::statement("ALTER TABLE `students` MODIFY `student_id` VARCHAR(6) NOT NULL");
                    } catch (\Throwable $e) {
                        try {
                            Schema::table('students', function (Blueprint $table) {
                                $table->string('student_id', 6)->nullable(false)->change();
                            });
                        } catch (\Throwable $e2) {}
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('students') || ! Schema::hasColumn('students', 'student_id')) {
            return;
        }

        try {
            Schema::table('students', function (Blueprint $table) {
                $table->dropUnique('students_institute_id_student_id_unique');
            });
        } catch (\Throwable $e) {
            try {
                DB::statement('ALTER TABLE `students` DROP INDEX `students_institute_id_student_id_unique`');
            } catch (\Throwable $e2) {}
            try {
                DB::statement('DROP INDEX `students_institute_id_student_id_unique` ON `students`');
            } catch (\Throwable $e3) {}
        }

        try {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('student_id');
            });
        } catch (\Throwable $e) {
            try {
                DB::statement('ALTER TABLE `students` DROP COLUMN `student_id`');
            } catch (\Throwable $e2) {}
        }
    }
};
