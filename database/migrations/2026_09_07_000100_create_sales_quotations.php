<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_quotations')) {
            Schema::create('sales_quotations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('quotation_number', 40);
                $table->foreignId('customer_id')->constrained('parties')->cascadeOnDelete();
                $table->date('quotation_date');
                $table->date('validity_date');
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->string('payment_terms', 40)->nullable();
                $table->text('notes')->nullable();
                $table->text('terms_conditions')->nullable();
                $table->decimal('subtotal', 19, 4)->default(0);
                $table->decimal('discount_amount', 19, 4)->default(0);
                $table->string('discount_type', 20)->default('fixed'); // fixed, percent
                $table->decimal('tax_amount', 19, 4)->default(0);
                $table->decimal('grand_total', 19, 4)->default(0);
                $table->string('status', 20)->default('draft'); // draft, sent, accepted, rejected, expired, cancelled
                $table->foreignId('converted_to_order_id')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'quotation_number'], 'uq_quotations_number');
                $table->index(['institute_id', 'branch_id', 'status'], 'idx_quotations_scope_status');
                $table->index(['institute_id', 'customer_id'], 'idx_quotations_customer');
                $table->index(['institute_id', 'quotation_date'], 'idx_quotations_date');
            });
        }

        if (! Schema::hasTable('sales_quotation_lines')) {
            Schema::create('sales_quotation_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('quotation_id')->constrained('sales_quotations')->cascadeOnDelete();
                $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
                $table->string('description', 500);
                $table->decimal('quantity', 19, 4);
                $table->string('unit', 30)->nullable();
                $table->decimal('unit_price', 19, 4);
                $table->decimal('discount_amount', 19, 4)->default(0);
                $table->string('discount_type', 20)->default('fixed');
                $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
                $table->decimal('tax_rate', 10, 4)->default(0);
                $table->decimal('tax_amount', 19, 4)->default(0);
                $table->decimal('line_total', 19, 4);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['quotation_id', 'sort_order'], 'idx_q_lines_order');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_quotation_lines');
        Schema::dropIfExists('sales_quotations');
    }
};
