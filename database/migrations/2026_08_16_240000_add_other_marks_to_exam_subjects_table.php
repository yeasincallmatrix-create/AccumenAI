<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exams can now record an "Other" component alongside Written / Practical / Viva.
 * Each selected subject stores its own max marks per component in exam_subjects.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('exam_subjects', 'other_marks')) {
            Schema::table('exam_subjects', function (Blueprint $table) {
                $table->decimal('other_marks', 6, 2)->default(0)->after('viva_marks');
            });
        }

        if (! Schema::hasColumn('exam_subjects', 'exam_date')) {
            Schema::table('exam_subjects', function (Blueprint $table) {
                $table->dateTime('exam_date')->nullable()->after('other_marks');
            });
        }

        $csType = DB::selectOne("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_subjects' AND COLUMN_NAME = 'exam_date'");
        if ($csType && strtolower($csType->DATA_TYPE) === 'date') {
            DB::statement('ALTER TABLE exam_subjects MODIFY exam_date DATETIME NULL');
        }

        $eType = DB::selectOne("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'exam_date'");
        if ($eType && strtolower($eType->DATA_TYPE) === 'date') {
            DB::statement('ALTER TABLE exams MODIFY exam_date DATETIME NOT NULL');
        }

        if (! Schema::hasColumn('exam_results', 'other_marks')) {
            Schema::table('exam_results', function (Blueprint $table) {
                $table->decimal('other_marks', 6, 2)->nullable()->after('viva_marks');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exam_subjects', 'exam_date')) {
            Schema::table('exam_subjects', function (Blueprint $table) {
                $table->dropColumn('exam_date');
            });
        }

        if (Schema::hasColumn('exam_results', 'other_marks')) {
            Schema::table('exam_results', function (Blueprint $table) {
                $table->dropColumn('other_marks');
            });
        }

        if (Schema::hasColumn('exam_subjects', 'other_marks')) {
            Schema::table('exam_subjects', function (Blueprint $table) {
                $table->dropColumn('other_marks');
            });
        }
    }
};
