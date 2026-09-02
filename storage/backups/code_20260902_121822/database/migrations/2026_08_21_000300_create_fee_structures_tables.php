<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Step 37 — education fee structures. A fee structure targets an optional
     * branch / course / batch / academic year (NULL = any / institute-wide)
     * and carries the list of fee-head items that make up the bill plus an
     * installment plan (count + interval in days). When an enrollment is
     * billed, the most specific active structure is resolved and its items are
     * turned into an invoice through the existing InvoiceService.
     */
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->string('name', 150);
            $table->tinyInteger('installments_count')->unsigned()->default(1);
            $table->smallInteger('installments_interval_days')->unsigned()->default(30);
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'branch_id', 'status'], 'idx_fee_structures_inst_branch_status');
            $table->index(['institute_id', 'course_id'], 'idx_fee_structures_inst_course');
            $table->index(['institute_id', 'batch_id'], 'idx_fee_structures_inst_batch');

            $table->foreign('institute_id', 'fk_fee_structures_institute')
                ->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_fee_structures_branch')
                ->references('id')->on('branches')->nullOnDelete();
            $table->foreign('course_id', 'fk_fee_structures_course')
                ->references('id')->on('courses')->nullOnDelete();
            $table->foreign('batch_id', 'fk_fee_structures_batch')
                ->references('id')->on('batches')->nullOnDelete();
            $table->foreign('academic_year_id', 'fk_fee_structures_academic_year')
                ->references('id')->on('academic_years')->nullOnDelete();
        });

        Schema::create('fee_structure_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fee_structure_id');
            $table->unsignedBigInteger('fee_head_id');
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('is_optional')->default(false);
            $table->timestamps();

            $table->unique(['fee_structure_id', 'fee_head_id'], 'uq_fee_structure_items_struct_head');
            $table->index(['fee_structure_id'], 'idx_fee_structure_items_structure');

            $table->foreign('fee_structure_id', 'fk_fee_structure_items_structure')
                ->references('id')->on('fee_structures')->cascadeOnDelete();
            $table->foreign('fee_head_id', 'fk_fee_structure_items_head')
                ->references('id')->on('fee_heads')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structure_items');
        Schema::dropIfExists('fee_structures');
    }
};
