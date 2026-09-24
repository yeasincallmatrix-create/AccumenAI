<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_schedules')) {
            Schema::create('training_schedules', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_test')->default(false);
                $table->unsignedBigInteger('institute_id');
                $table->unsignedBigInteger('batch_id');
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->unsignedTinyInteger('day_of_week')->default(0);
                $table->time('start_time');
                $table->time('end_time');
                $table->string('title', 150)->nullable();
                $table->string('room', 80)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('institute_id');
                $table->index('batch_id');
                $table->index('subject_id');
                $table->index(['batch_id', 'day_of_week']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_schedules');
    }
};
