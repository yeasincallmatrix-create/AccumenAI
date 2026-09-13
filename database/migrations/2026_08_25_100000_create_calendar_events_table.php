<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('event_type', 60)->default('class');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->time('start_time')->nullable();
            $table->date('end_date')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('class_grade_id')->nullable();
            $table->unsignedBigInteger('academic_group_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('status', 30)->default('active');
            $table->json('recurrence_rule')->nullable();
            $table->unsignedBigInteger('parent_event_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
            $table->foreign('batch_id')->references('id')->on('batches')->nullOnDelete();
            $table->foreign('class_grade_id')->references('id')->on('class_grades')->nullOnDelete();
            $table->foreign('academic_group_id')->references('id')->on('academic_groups')->nullOnDelete();
            $table->foreign('teacher_id')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->foreign('parent_event_id')->references('id')->on('calendar_events')->nullOnDelete();

            $table->index('institute_id');
            $table->index(['institute_id', 'start_date']);
            $table->index(['institute_id', 'event_type']);
            $table->index(['branch_id', 'start_date']);
            $table->index(['teacher_id', 'start_date']);
            $table->index(['batch_id', 'start_date']);
            $table->index(['room_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
