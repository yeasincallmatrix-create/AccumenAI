<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_return_periods')) {
            return;
        }

        Schema::create('tax_return_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('jurisdiction_id')->nullable();
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->enum('status', ['open', 'filed', 'overdue'])->default('open');
            $table->decimal('total_sales', 19, 4)->default(0);
            $table->decimal('total_purchases', 19, 4)->default(0);
            $table->decimal('tax_collected', 19, 4)->default(0);
            $table->decimal('tax_paid', 19, 4)->default(0);
            $table->decimal('net_tax', 19, 4)->default(0);
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::table('tax_return_periods', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('jurisdiction_id')->references('id')->on('tax_jurisdictions')->onDelete('restrict');
            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_return_periods');
    }
};
