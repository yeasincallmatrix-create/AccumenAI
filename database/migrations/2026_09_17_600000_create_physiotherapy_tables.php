<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physiotherapy_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('plan_number', 50);
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('therapist_id');
            $table->unsignedBigInteger('referring_doctor_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();

            $table->text('chief_complaint');
            $table->text('assessment')->nullable();
            $table->string('diagnosis', 200)->nullable();
            $table->text('treatment_goals')->nullable();
            $table->integer('pain_score_initial')->nullable();

            $table->string('modality', 100)->nullable();
            $table->integer('sessions_planned')->default(10);
            $table->integer('sessions_completed')->default(0);
            $table->string('frequency', 50)->nullable();

            $table->date('start_date');
            $table->date('expected_end_date')->nullable();

            $table->string('status', 30)->default('active');
            $table->text('discontinue_reason')->nullable();

            $table->decimal('fee_per_session', 10, 2)->default(0);
            $table->decimal('total_fee', 10, 2)->default(0);
            $table->string('payment_status', 20)->default('pending');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('therapist_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('referring_doctor_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['institute_id', 'status']);
            $table->index(['patient_id', 'start_date']);
            $table->unique(['institute_id', 'plan_number'], 'uniq_physiotherapy_plan_number');
        });

        Schema::create('physiotherapy_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('physiotherapy_plan_id');
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('session_number', 50);
            $table->integer('session_order');

            $table->unsignedBigInteger('therapist_id');
            $table->date('session_date');
            $table->integer('duration_minutes')->default(30);

            $table->integer('pain_score_before')->nullable();
            $table->integer('pain_score_after')->nullable();
            $table->text('assessment_notes')->nullable();
            $table->text('treatment_given')->nullable();
            $table->text('exercises_done')->nullable();
            $table->text('equipment_used')->nullable();
            $table->text('progress_notes')->nullable();
            $table->text('next_session_focus')->nullable();

            $table->string('status', 20)->default('scheduled');
            $table->timestamp('attended_at')->nullable();

            $table->decimal('fee', 10, 2)->default(0);
            $table->string('payment_status', 20)->default('pending');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('physiotherapy_plan_id')->references('id')->on('physiotherapy_plans')->onDelete('cascade');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('therapist_id')->references('id')->on('users')->onDelete('restrict');
            $table->index(['physiotherapy_plan_id', 'session_order']);
            $table->index(['institute_id', 'session_date']);
            $table->unique(['institute_id', 'session_number'], 'uniq_physiotherapy_session_number');
        });

        Schema::create('physiotherapy_exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('name', 200);
            $table->string('category', 100)->nullable();
            $table->string('body_area', 100)->nullable();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->integer('default_reps')->nullable();
            $table->integer('default_sets')->nullable();
            $table->integer('default_hold_seconds')->nullable();
            $table->string('difficulty', 20)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->index(['institute_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physiotherapy_exercises');
        Schema::dropIfExists('physiotherapy_sessions');
        Schema::dropIfExists('physiotherapy_plans');
    }
};
