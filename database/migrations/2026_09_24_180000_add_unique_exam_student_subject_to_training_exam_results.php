<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_exam_results', function (Blueprint $table) {
            $table->unique(
                ['exam_id', 'student_id', 'subject_id'],
                'training_exam_results_exam_student_subject_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('training_exam_results', function (Blueprint $table) {
            $table->dropUnique('training_exam_results_exam_student_subject_unique');
        });
    }
};
