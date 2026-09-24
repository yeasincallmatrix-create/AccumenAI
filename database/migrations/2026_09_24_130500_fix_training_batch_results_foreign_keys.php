<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_batch_results')) {
            return;
        }

        $count = DB::table('training_batch_results')->count();
        if ($count > 0) {
            throw new \RuntimeException('training_batch_results has ' . $count . ' rows — FK swap not safe. Aborting.');
        }

        Schema::table('training_batch_results', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropForeign(['student_id']);
        });

        Schema::table('training_batch_results', function (Blueprint $table) {
            $table->foreign('batch_id')->references('id')->on('training_batches')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('training_students')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_batch_results')) {
            return;
        }

        Schema::table('training_batch_results', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropForeign(['student_id']);
        });

        Schema::table('training_batch_results', function (Blueprint $table) {
            $table->foreign('batch_id')->references('id')->on('batches')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });
    }
};
