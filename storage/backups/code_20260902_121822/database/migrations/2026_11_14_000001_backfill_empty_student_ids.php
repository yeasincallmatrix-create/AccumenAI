<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('students') || ! \Illuminate\Support\Facades\Schema::hasColumn('students', 'student_id')) {
            return;
        }

        // Backfill both NULL and empty string student_id (migration 2026_11_02_000006 only handled NULL)
        $needsBackfill = DB::table('students')
            ->where(function ($q) {
                $q->whereNull('student_id')->orWhere('student_id', '')->orWhere('student_id', '0');
            })
            ->exists();

        if (! $needsBackfill) {
            return;
        }

        DB::transaction(function () {
            $students = DB::table('students')
                ->where(function ($q) {
                    $q->whereNull('student_id')->orWhere('student_id', '')->orWhere('student_id', '0');
                })
                ->get(['id', 'institute_id']);

            foreach ($students as $student) {
                $instituteId = $student->institute_id;
                // Use helper with uniqueness check per institute
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

    public function down(): void
    {
        // No rollback needed - student_id backfill is idempotent
    }
};
