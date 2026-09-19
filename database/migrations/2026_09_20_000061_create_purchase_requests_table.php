<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_requests')) {
            return;
        }

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('request_number');
            $table->unsignedBigInteger('requester_id');
            $table->date('request_date');
            $table->date('required_by_date')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->text('justification')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('estimated_total', 19, 4)->default(0);
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('converted_by')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->unsignedBigInteger('converted_order_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('converted_order_id')->references('id')->on('purchase_orders')->onDelete('restrict');
            $table->foreign('warehouse_id')->references('id')->on('inventory_warehouses')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('requester_id')->references('id')->on('institute_users')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
