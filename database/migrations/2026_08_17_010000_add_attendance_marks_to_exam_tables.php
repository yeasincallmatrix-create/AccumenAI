<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an "Attendance" mark component so exams can record
 * Written / Practical / Viva / Attendance / Others per subject.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('exam_subjects', 'attendance_marks')) {
            Schema::table('exam_subjects', function (Blueprint $table) {
                $table->decimal('attendance_marks', 10, 2)->default(0)->after('other_marks');
            });
        }

        if (! Schema::hasColumn('exam_results', 'attendance_marks')) {
            Schema::table('exam_results', function (Blueprint $table) {
                $table->decimal('attendance_marks', 10, 2)->nullable()->after('other_marks');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exam_results', 'attendance_marks')) {
            Schema::table('exam_results', function (Blueprint $table) {
                $table->dropColumn('attendance_marks');
            });
        }

        if (Schema::hasColumn('exam_subjects', 'attendance_marks')) {
            Schema::table('exam_subjects', function (Blueprint $table) {
                $table->dropColumn('attendance_marks');
            });
        }
    }
};
