<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_requests')) {
            Schema::create('purchase_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('request_number', 40);
                $table->foreignId('requester_id')->constrained('institute_users')->cascadeOnDelete();
                $table->date('request_date');
                $table->date('required_by_date')->nullable();
                $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
                $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
                $table->text('justification')->nullable();
                $table->text('notes')->nullable();
                $table->decimal('estimated_total', 19, 4)->default(0);
                $table->string('status', 30)->default('draft');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('converted_by')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->foreignId('converted_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'request_number'], 'uq_purchase_requests_number');
                $table->index(['institute_id', 'branch_id', 'status'], 'idx_pr_scope_status');
                $table->index(['institute_id', 'requester_id'], 'idx_pr_requester');
                $table->index(['institute_id', 'request_date'], 'idx_pr_date');
            });
        }

        if (! Schema::hasTable('purchase_request_items')) {
            Schema::create('purchase_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
                $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
                $table->string('description', 500);
                $table->decimal('quantity', 19, 4);
                $table->string('unit', 30)->nullable();
                $table->decimal('estimated_unit_price', 19, 4)->default(0);
                $table->decimal('line_total', 19, 4)->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['purchase_request_id', 'sort_order'], 'idx_pr_lines_request');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
    }
};
