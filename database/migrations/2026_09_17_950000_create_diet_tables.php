<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diet_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('plan_number', 50);
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('prescribed_by');
            $table->unsignedBigInteger('admission_id')->nullable();

            $table->string('plan_name', 200);
            $table->string('diet_type', 50);
            $table->text('restrictions')->nullable();
            $table->text('medical_notes')->nullable();

            $table->integer('daily_calories')->nullable();
            $table->decimal('protein_grams', 6, 2)->nullable();
            $table->decimal('carbs_grams', 6, 2)->nullable();
            $table->decimal('fat_grams', 6, 2)->nullable();
            $table->decimal('sodium_mg', 8, 2)->nullable();
            $table->decimal('potassium_mg', 8, 2)->nullable();
            $table->decimal('fluid_ml', 8, 2)->nullable();

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('days_planned')->nullable();

            $table->string('status', 30)->default('active');
            $table->text('discontinue_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('prescribed_by')->references('id')->on('users')->onDelete('restrict');
            $table->index(['institute_id', 'status']);
            $table->index(['patient_id', 'start_date']);
            $table->unique(['institute_id', 'plan_number'], 'uniq_diet_plan_number');
        });

        Schema::create('meal_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('diet_plan_id');
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('meal_date');
            $table->string('meal_type', 30);
            $table->time('scheduled_time');
            $table->text('menu_items');
            $table->integer('calories')->nullable();

            $table->string('status', 20)->default('scheduled');
            $table->timestamp('prepared_at')->nullable();
            $table->unsignedBigInteger('prepared_by')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->unsignedBigInteger('served_by')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('diet_plan_id')->references('id')->on('diet_plans')->onDelete('cascade');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->index(['institute_id', 'meal_date', 'meal_type']);
            $table->index(['diet_plan_id', 'meal_date']);
        });

        Schema::create('diet_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->nullable();
            $table->string('name', 200);
            $table->string('diet_type', 50);
            $table->text('description')->nullable();
            $table->json('meal_items')->nullable();
            $table->integer('total_calories')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->index(['institute_id', 'diet_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diet_templates');
        Schema::dropIfExists('meal_schedules');
        Schema::dropIfExists('diet_plans');
    }
};
