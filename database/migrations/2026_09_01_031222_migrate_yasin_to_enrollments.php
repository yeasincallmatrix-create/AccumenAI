<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Check if Yasin already exists in enrollments to avoid duplicate
        $exists = DB::table('enrollments')
            ->where('batch_id', 117)
            ->where('trainee_id', 423)
            ->exists();

        if (!$exists) {
            // 2. Fetch the legacy record
            $legacy = DB::table('student_enrollments')
                ->where('student_id', 423)
                ->where('batch_id', 117)
                ->first();

            if ($legacy) {
                // 3. Insert into new enrollments table
                DB::table('enrollments')->insert([
                    'institute_id' => $legacy->institute_id,
                    'batch_id' => $legacy->batch_id,
                    'trainee_id' => $legacy->student_id, // now students.id
                    'roll_no' => (int) $legacy->roll_number,
                    'enrollment_date' => $legacy->enrollment_date ?? now(),
                    'status' => $legacy->status ?? 'active',
                    'payment_status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Rollback: remove the inserted record (if needed)
        DB::table('enrollments')
            ->where('batch_id', 117)
            ->where('trainee_id', 423)
            ->delete();
    }
};
