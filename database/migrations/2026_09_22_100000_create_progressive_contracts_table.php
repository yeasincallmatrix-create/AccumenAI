<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('progressive_contracts')) {
            return;
        }

        Schema::create('progressive_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('contract_number', 50);

            $table->unsignedBigInteger('party_id');
            $table->unsignedBigInteger('sales_quotation_id')->nullable();

            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->decimal('total_value', 15, 2);
            $table->string('currency', 3)->default('BDT');
            $table->decimal('exchange_rate', 15, 6)->default(1);

            $table->decimal('retention_percentage', 5, 2)->default(0);
            $table->decimal('retention_amount', 15, 2)->default(0);
            $table->decimal('retention_released', 15, 2)->default(0);

            $table->decimal('total_billed', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('remaining_value', 15, 2);

            $table->decimal('progress_percentage', 5, 2)->default(0);

            $table->date('start_date');
            $table->date('expected_end_date')->nullable();
            $table->date('actual_end_date')->nullable();

            $table->string('status', 30)->default('active');

            $table->unsignedBigInteger('tax_group_id')->nullable();
            $table->string('tax_method', 20)->default('exclusive');

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('party_id')->references('id')->on('parties')->onDelete('restrict');
            $table->foreign('sales_quotation_id')->references('id')->on('sales_quotations')->onDelete('set null');
            $table->foreign('tax_group_id')->references('id')->on('tax_groups')->onDelete('set null');

            $table->unique(['institute_id', 'contract_number'], 'uniq_contract_number');
            $table->index(['institute_id', 'status']);
            $table->index(['party_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progressive_contracts');
    }
};
