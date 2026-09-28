<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dealership Phase 2 — Orders & Collection (7 new tables).
     *
     * NOTE: Phase 1 created registry rows only — no dealership_brands,
     * dealership_products, dealership_customers or dealership_sales_force
     * tables exist yet. All entity references are therefore plain
     * unsignedBigInteger columns + indexes (NO foreign key constraints).
     * FK wiring will be added when the Phase 1 entity tables land.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dealership_price_lists')) {
            Schema::create('dealership_price_lists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('brand_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->enum('channel', ['general', 'retail', 'wholesale', 'sub_dealer'])->default('general');
                $table->decimal('price', 15, 2);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dealership_credit_limits')) {
            Schema::create('dealership_credit_limits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->unique();
                $table->decimal('credit_limit', 15, 2)->default(0);
                $table->unsignedInteger('overdue_days_block')->default(30);
                $table->boolean('is_blocked')->default(false);
                $table->timestamp('last_reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dealership_sr_orders')) {
            Schema::create('dealership_sr_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_no')->unique();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('sales_force_id')->index();
                $table->enum('channel', ['general', 'retail', 'wholesale', 'sub_dealer'])->default('general');
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('discount', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'delivered'])->default('draft');
                $table->text('remarks')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dealership_sr_order_items')) {
            Schema::create('dealership_sr_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sr_order_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('qty', 15, 3);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('line_total', 15, 2);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dealership_order_approvals')) {
            Schema::create('dealership_order_approvals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sr_order_id')->index();
                $table->enum('action', ['submitted', 'approved', 'rejected', 'reopened']);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dealership_sr_collections')) {
            Schema::create('dealership_sr_collections', function (Blueprint $table) {
                $table->id();
                $table->string('receipt_no')->unique();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('sales_force_id')->index();
                $table->unsignedBigInteger('sr_order_id')->nullable()->index();
                $table->enum('method', ['cash', 'cheque', 'bank_transfer', 'mobile_banking']);
                $table->decimal('amount', 15, 2);
                $table->string('reference')->nullable();
                $table->date('collected_on');
                $table->enum('status', ['pending', 'cleared', 'bounced'])->default('pending');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dealership_inventory_links')) {
            Schema::create('dealership_inventory_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->unique();
                $table->unsignedBigInteger('inventory_item_id')->nullable();
                $table->string('inventory_sku')->nullable();
                $table->enum('sync_mode', ['manual', 'auto'])->default('manual');
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // NO destructive rollback per project rules.
    }
};
