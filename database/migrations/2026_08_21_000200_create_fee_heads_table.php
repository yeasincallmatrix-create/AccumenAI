<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Step 37 — education fee heads catalog. Per-institute (optionally
     * branch-scoped) definitions of the education-specific fees an institute
     * bills its students: admission, course/tuition, registration, exam,
     * certificate and other approved fees. Each head maps to an income CoA
     * so invoices generated from fee structures credit the right revenue line.
     */
    public function up(): void
    {
        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 150);
            $table->string('code', 40)->nullable();
            $table->enum('type', ['admission', 'course_tuition', 'registration', 'exam', 'certificate', 'other'])
                ->default('other');
            $table->decimal('default_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('income_coa_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'branch_id', 'name'], 'uq_fee_heads_inst_branch_name');
            $table->index(['institute_id', 'branch_id', 'type'], 'idx_fee_heads_inst_branch_type');

            $table->foreign('institute_id', 'fk_fee_heads_institute')
                ->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_fee_heads_branch')
                ->references('id')->on('branches')->nullOnDelete();
            $table->foreign('income_coa_id', 'fk_fee_heads_income_coa')
                ->references('id')->on('chart_of_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_heads');
    }
};
