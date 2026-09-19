<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fee_structures')) {
            return;
        }

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->string('name');
            $table->unsignedTinyInteger('installments_count')->default(1);
            $table->unsignedSmallInteger('installments_interval_days')->default(30);
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->enum('billing_frequency', ['monthly', 'quarterly', 'annually', 'one_time'])->default('monthly');
            $table->boolean('auto_generate_monthly')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('batch_id')->references('id')->on('batches')->onDelete('restrict');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('institute_users')->onDelete('restrict');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('institute_users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
