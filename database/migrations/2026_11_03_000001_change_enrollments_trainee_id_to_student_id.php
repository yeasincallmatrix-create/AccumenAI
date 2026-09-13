<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('enrollments')) {
            return;
        }

        // 1. Drop existing FK to users if exists
        try {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropForeign(['trainee_id']);
            });
        } catch (\Throwable $e) {
            try {
                DB::statement('ALTER TABLE `enrollments` DROP FOREIGN KEY `enrollments_trainee_id_foreign`');
            } catch (\Throwable $e2) {}
        }

        // 2. Handle orphaned rows: trainee_id referencing users that have no corresponding student
        // We map orphaned user-based enrollments to a student record if possible
        try {
            $orphaned = DB::table('enrollments as e')
                ->leftJoin('students as s', 'e.trainee_id', '=', 's.id')
                ->whereNull('s.id')
                ->select('e.id', 'e.trainee_id', 'e.institute_id')
                ->get();

            foreach ($orphaned as $row) {
                $user = DB::table('users')->where('id', $row->trainee_id)->first();
                if ($user) {
                    // Create minimal student from user
                    $names = explode(' ', trim($user->name ?? 'Unknown'), 2);
                    $first = $names[0] ?? 'Unknown';
                    $last = $names[1] ?? '';
                    // Generate student_id via helper if available
                    $studentId = null;
                    try {
                        if (function_exists('generateInstituteStudentId')) {
                            $studentId = generateInstituteStudentId($row->institute_id);
                        } else {
                            $studentId = str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
                        }
                    } catch (\Throwable $e) {
                        $studentId = str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
                    }
                    $newStudentId = DB::table('students')->insertGetId([
                        'institute_id' => $row->institute_id,
                        'first_name' => $first,
                        'last_name' => $last,
                        'email' => $user->email ?? null,
                        'user_id' => $user->id,
                        'student_id' => $studentId,
                        'status' => 'active',
                        'admission_status' => 'enrolled',
                        'admission_date' => now()->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('enrollments')->where('id', $row->id)->update(['trainee_id' => $newStudentId]);
                } else {
                    // No user found - keep as is, FK to students will fail, so we delete orphan enrollment to allow FK creation
                    // For safety, we delete it (can be restored from backup)
                    DB::table('enrollments')->where('id', $row->id)->delete();
                }
            }
        } catch (\Throwable $e) {
            // Log but don't block migration
            \Illuminate\Support\Facades\Log::warning('Orphan enrollment migration failed: '.$e->getMessage());
        }

        // 3. Ensure column type is compatible with students.id (bigint unsigned)
        // Already unsignedBigInteger via foreignId, no change needed, but ensure if needed
        // No-op: trainee_id is already foreignId (bigint unsigned)

        // 4. Add new FK to students
        try {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->foreign('trainee_id')->references('id')->on('students')->onDelete('cascade');
            });
        } catch (\Throwable $e) {
            // If FK already exists, ignore
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('enrollments')) {
            return;
        }
        try {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropForeign(['trainee_id']);
            });
        } catch (\Throwable $e) {
            try {
                DB::statement('ALTER TABLE `enrollments` DROP FOREIGN KEY `enrollments_trainee_id_foreign`');
            } catch (\Throwable $e2) {}
        }
        try {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->foreign('trainee_id')->references('id')->on('users')->onDelete('cascade');
            });
        } catch (\Throwable $e) {}
    }
};
