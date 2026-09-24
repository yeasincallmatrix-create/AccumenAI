<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_batch_results')) {
            return;
        }

        Schema::create('training_batch_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('student_id');
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->decimal('obtained_marks', 8, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->enum('status', ['pass', 'fail'])->default('fail');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('batch_id')->references('id')->on('training_batches')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('training_students')->cascadeOnDelete();
            $table->unique(['batch_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_batch_results');
    }
};
