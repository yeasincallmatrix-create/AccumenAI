<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
            if (Schema::hasTable('students') && ! Schema::hasColumn('students', 'reg_no')) {
                Schema::table('students', function (Blueprint $table) {
                    $table->string('reg_no', 20)->nullable()->unique()->after('id');
                });
            }

            // Backfill existing students
            if (Schema::hasTable('students') && Schema::hasColumn('students', 'reg_no')) {
                $students = DB::table('students')->whereNull('reg_no')->get(['id', 'institute_id']);
                foreach ($students as $student) {
                    $institute = null;
                    if (! empty($student->institute_id)) {
                        $institute = DB::table('institutes')->where('id', $student->institute_id)->first(['uid']);
                    }
                    $uid = $institute->uid ?? '0000000000';
                    // generateStudentRegNo may expect 10-char uid; fallback to 00 if short
                    $regNo = function_exists('generateStudentRegNo') ? generateStudentRegNo($uid) : $this->fallbackRegNo($uid);
                    // Ensure uniqueness (global)
                    $attempts = 0;
                    while (DB::table('students')->where('reg_no', $regNo)->where('id', '!=', $student->id)->exists()) {
                        $regNo = function_exists('generateStudentRegNo') ? generateStudentRegNo($uid) : $this->fallbackRegNo($uid);
                        $attempts++;
                        if ($attempts > 50) break;
                    }
                    DB::table('students')->where('id', $student->id)->update(['reg_no' => $regNo]);
                }
            }

            // Attempt to make NOT NULL after backfill — safe to keep nullable if doctrine/dbal missing
            // Keeping nullable for compatibility; unique index already ensures integrity
    }

    public function down(): void
    {
        if (Schema::hasTable('students') && Schema::hasColumn('students', 'reg_no')) {
            try {
                Schema::table('students', function (Blueprint $table) {
                    try { $table->dropUnique(['reg_no']); } catch (\Throwable $e) {}
                });
            } catch (\Throwable $e) {}
            try { DB::statement('ALTER TABLE `students` DROP INDEX `students_reg_no_unique`'); } catch (\Throwable $e) {}
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('reg_no');
            });
        }
    }

    private function fallbackRegNo($instituteUid): string
    {
        $lastTwo = substr((string) $instituteUid, -2);
        if (! preg_match('/^\d{2}$/', $lastTwo)) {
            $digits = preg_replace('/\D/', '', (string) $instituteUid);
            $lastTwo = strlen($digits) >= 2 ? substr($digits, -2) : str_pad($digits, 2, '0', STR_PAD_LEFT);
            $lastTwo = str_pad($lastTwo, 2, '0', STR_PAD_LEFT);
        }
        $year = date('y');
        $month = date('m');
        $random = str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
        return $lastTwo . $year . $month . $random . '0';
    }
};
