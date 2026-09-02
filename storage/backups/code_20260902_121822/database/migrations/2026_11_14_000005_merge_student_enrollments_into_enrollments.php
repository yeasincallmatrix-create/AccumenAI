<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add student_id column to enrollments (mirrors trainee_id for backward compat)
        if (!Schema::hasColumn('enrollments', 'student_id')) {
            Schema::table('enrollments', function ($table) {
                $table->unsignedBigInteger('student_id')->nullable()->after('trainee_id');
            });
        }

        // 2. Sync student_id = trainee_id for existing rows
        DB::table('enrollments')
            ->whereNull('student_id')
            ->update(['student_id' => DB::raw('trainee_id')]);

        // 3. Copy remaining data from student_enrollments to enrollments (skip duplicates)
        $legacy = DB::table('student_enrollments')->get();
        foreach ($legacy as $row) {
            $exists = DB::table('enrollments')
                ->where('batch_id', $row->batch_id)
                ->where('student_id', $row->student_id)
                ->exists();

            if ($exists) continue;

            $status = $row->status === 'transferred' ? 'dropped' : $row->status;

            DB::table('enrollments')->insert([
                'institute_id' => $row->institute_id,
                'batch_id' => $row->batch_id,
                'trainee_id' => $row->student_id,
                'student_id' => $row->student_id,
                'roll_no' => is_numeric($row->roll_number) ? (int) $row->roll_number : null,
                'enrollment_date' => $row->enrollment_date,
                'status' => $status,
                'payment_status' => 'pending',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        // 4. Make student_id non-nullable and add index
        Schema::table('enrollments', function ($table) {
            $table->unsignedBigInteger('student_id')->nullable(false)->change();
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function ($table) {
            $table->dropIndex(['student_id']);
            $table->dropColumn('student_id');
        });
    }
};
