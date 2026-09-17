<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dental_charts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('dentist_id')->nullable();

            $table->json('tooth_conditions')->nullable();

            $table->integer('total_teeth')->default(32);
            $table->integer('caries_count')->default(0);
            $table->integer('filled_count')->default(0);
            $table->integer('missing_count')->default(0);
            $table->integer('crown_count')->default(0);
            $table->integer('rct_count')->default(0);

            $table->text('general_notes')->nullable();
            $table->string('oral_hygiene', 30)->nullable();
            $table->timestamp('last_assessed_at')->nullable();
            $table->unsignedBigInteger('assessed_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('dentist_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['institute_id', 'patient_id']);
            $table->unique(['institute_id', 'patient_id'], 'uniq_patient_dental_chart');
        });

        Schema::create('dental_procedures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('procedure_number', 50);
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('dentist_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('dental_chart_id')->nullable();

            $table->string('procedure_code', 30)->nullable();
            $table->string('procedure_name', 200);
            $table->string('category', 50)->nullable();
            $table->string('tooth_number', 10)->nullable();
            $table->string('tooth_surface', 50)->nullable();
            $table->string('quadrant', 20)->nullable();

            $table->text('diagnosis')->nullable();
            $table->text('procedure_notes')->nullable();
            $table->string('anesthesia_type', 50)->nullable();
            $table->string('anesthesia_agent', 100)->nullable();
            $table->decimal('anesthesia_volume_ml', 5, 2)->nullable();
            $table->text('medications_prescribed')->nullable();

            $table->json('materials_used')->nullable();

            $table->timestamp('performed_at');
            $table->integer('duration_minutes')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_instructions')->nullable();

            $table->string('status', 20)->default('completed');

            $table->decimal('fee', 10, 2)->default(0);
            $table->string('payment_status', 20)->default('pending');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('dentist_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('dental_chart_id')->references('id')->on('dental_charts')->onDelete('set null');
            $table->index(['institute_id', 'status']);
            $table->index(['patient_id', 'performed_at']);
            $table->unique(['institute_id', 'procedure_number'], 'uniq_dental_procedure_number');
        });

        Schema::create('dental_treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('plan_number', 50);
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('dentist_id');

            $table->text('chief_complaint');
            $table->text('diagnosis')->nullable();
            $table->text('treatment_summary')->nullable();

            $table->json('planned_steps')->nullable();
            $table->integer('total_steps')->default(0);
            $table->integer('completed_steps')->default(0);

            $table->date('start_date');
            $table->date('expected_end_date')->nullable();
            $table->decimal('total_estimated_fee', 10, 2)->default(0);

            $table->string('status', 30)->default('active');
            $table->text('discontinue_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('dentist_id')->references('id')->on('users')->onDelete('restrict');
            $table->index(['institute_id', 'status']);
            $table->unique(['institute_id', 'plan_number'], 'uniq_dental_plan_number');
        });

        Schema::create('dental_procedure_catalog', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->nullable();
            $table->string('code', 30)->nullable();
            $table->string('name', 200);
            $table->string('category', 50);
            $table->string('body_site', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('default_fee', 10, 2)->default(0);
            $table->integer('default_duration_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->index(['institute_id', 'category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_procedure_catalog');
        Schema::dropIfExists('dental_treatment_plans');
        Schema::dropIfExists('dental_procedures');
        Schema::dropIfExists('dental_charts');
    }
};
