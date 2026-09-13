<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Inventory Engine — STEP 16.
 *
 * Tenant-scoped inventory subsystem on top of the accounting engine.
 *
 * Design rules:
 *  - Every table carries institute_id (tenant) and a nullable branch_id that
 *    follows the existing BranchScopedOrShared convention (NULL = institute-wide
 *    shared inventory). Reuses TenantContext/BranchContext, never a second
 *    tenancy system.
 *  - inventory_movements is the stock ledger / source of truth; quantity is
 *    SIGNED (positive = increase, negative = decrease). inventory_stock_levels
 *    is a cached balance (per item + warehouse + optional batch) that is always
 *    rebuildable from the movement ledger.
 *  - Money uses the existing DECIMAL(19,4) precision convention. No floats.
 *  - Business invariants (negative stock, balanced entries, COA ownership,
 *    fiscal-period locking) are enforced in services, not via DB CHECKs
 *    (MariaDB portability, matching the accounting engine).
 *  - Status columns use string instead of enum so capability-driven feature
 *    sets can extend without enum redefinition pain.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->categories();
        $this->warehouses();
        $this->items();
        $this->batches();
        $this->serialNumbers();
        $this->movements();
        $this->stockLevels();
        $this->transfers();
        $this->adjustments();
        $this->counts();
        $this->linkInvoiceItems();
    }

    private function categories(): void
    {
        if (Schema::hasTable('inventory_categories')) {
            return;
        }

        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('name', 150);
            $table->foreignId('inventory_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('cogs_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('sales_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'branch_id', 'name'], 'uq_inventory_categories_name');
            $table->index(['institute_id', 'parent_id'], 'idx_inventory_categories_parent');
        });
    }

    private function warehouses(): void
    {
        if (Schema::hasTable('inventory_warehouses')) {
            return;
        }

        Schema::create('inventory_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name', 150);
            $table->string('code', 30);
            $table->string('location', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'branch_id', 'code'], 'uq_inventory_warehouses_code');
        });
    }

    private function items(): void
    {
        if (Schema::hasTable('inventory_items')) {
            return;
        }

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('item_type', 30)->default('stock_item');
            $table->string('sku', 60)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('purchase_price', 19, 4)->default(0);
            $table->decimal('selling_price', 19, 4)->default(0);
            $table->decimal('reorder_level', 19, 4)->default(0);
            $table->decimal('min_stock', 19, 4)->nullable();
            $table->decimal('max_stock', 19, 4)->nullable();
            $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
            $table->foreignId('inventory_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('cogs_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('sales_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'branch_id', 'sku'], 'uq_inventory_items_sku');
            $table->unique(['institute_id', 'barcode'], 'uq_inventory_items_barcode');
            $table->index(['institute_id', 'item_type', 'is_active'], 'idx_inventory_items_type');
            $table->index('category_id', 'idx_inventory_items_category');
        });
    }

    private function batches(): void
    {
        if (Schema::hasTable('inventory_batches')) {
            return;
        }

        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->string('batch_number', 80);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 19, 4)->default(0);
            $table->decimal('unit_cost', 19, 4)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'item_id', 'warehouse_id', 'batch_number'], 'uq_inventory_batches_no');
            $table->index(['institute_id', 'expiry_date'], 'idx_inventory_batches_expiry');
        });
    }

    private function serialNumbers(): void
    {
        if (Schema::hasTable('inventory_serial_numbers')) {
            return;
        }

        Schema::create('inventory_serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->string('serial_number', 100);
            $table->string('status', 20)->default('in_stock');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'item_id', 'serial_number'], 'uq_inventory_serials_no');
        });
    }

    private function movements(): void
    {
        if (Schema::hasTable('inventory_movements')) {
            return;
        }

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->cascadeOnDelete();
            $table->string('movement_type', 30);
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_cost', 19, 4)->default(0);
            $table->string('movement_no', 30);
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->date('occurred_at');
            $table->string('reason', 255)->nullable();
            $table->json('line_meta')->nullable();
            $table->string('status', 20)->default('posted');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'movement_no'], 'uq_inventory_movements_no');
            $table->index(['institute_id', 'warehouse_id', 'item_id', 'occurred_at'], 'idx_inventory_movements_wh');
            $table->index(['institute_id', 'item_id', 'batch_id'], 'idx_inventory_movements_item');
            $table->index(['reference_type', 'reference_id'], 'idx_inventory_movements_ref');
        });
    }

    private function stockLevels(): void
    {
        if (Schema::hasTable('inventory_stock_levels')) {
            return;
        }

        Schema::create('inventory_stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->cascadeOnDelete();
            $table->decimal('quantity', 19, 4)->default(0);
            $table->decimal('avg_cost', 19, 4)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'warehouse_id', 'item_id', 'batch_id'], 'uq_inventory_stock_levels');
        });
    }

    private function transfers(): void
    {
        if (Schema::hasTable('inventory_transfers')) {
            return;
        }

        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('source_warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->string('transfer_no', 30);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'transfer_no'], 'uq_inventory_transfers_no');
        });

        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('transfer_id')->constrained('inventory_transfers')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->cascadeOnDelete();
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_cost', 19, 4)->default(0);

            $table->index('transfer_id', 'idx_inventory_transfer_items_transfer');
        });
    }

    private function adjustments(): void
    {
        if (Schema::hasTable('inventory_adjustments')) {
            return;
        }

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->string('adjustment_no', 30);
            $table->string('adjustment_type', 20);
            $table->string('reason', 255)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'adjustment_no'], 'uq_inventory_adjustments_no');
        });

        Schema::create('inventory_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('adjustment_id')->constrained('inventory_adjustments')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->cascadeOnDelete();
            $table->decimal('system_qty', 19, 4)->default(0);
            $table->decimal('counted_qty', 19, 4)->default(0);
            $table->decimal('difference', 19, 4)->default(0);
            $table->decimal('unit_cost', 19, 4)->default(0);

            $table->index('adjustment_id', 'idx_inventory_adjustment_items_adjustment');
        });
    }

    private function counts(): void
    {
        if (Schema::hasTable('inventory_counts')) {
            return;
        }

        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->string('count_no', 30);
            $table->string('status', 20)->default('draft');
            $table->date('counted_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('counted_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'count_no'], 'uq_inventory_counts_no');
        });

        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('count_id')->constrained('inventory_counts')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->cascadeOnDelete();
            $table->decimal('system_qty', 19, 4)->default(0);
            $table->decimal('counted_qty', 19, 4)->default(0);
            $table->decimal('difference', 19, 4)->default(0);

            $table->index('count_id', 'idx_inventory_count_items_count');
        });
    }

    private function linkInvoiceItems(): void
    {
        if (! Schema::hasTable('invoice_items')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'inventory_item_id')) {
                $table->foreignId('inventory_item_id')
                    ->nullable()
                    ->after('coa_id')
                    ->constrained('inventory_items')
                    ->nullOnDelete();
            }
            if (! Schema::hasIndex('invoice_items', 'idx_invoice_items_inventory')) {
                $table->index('inventory_item_id', 'idx_invoice_items_inventory');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_items')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                if (Schema::hasColumn('invoice_items', 'inventory_item_id')) {
                    $table->dropConstrainedForeignId('inventory_item_id');
                }
                if (Schema::hasIndex('invoice_items', 'idx_invoice_items_inventory')) {
                    $table->dropIndex('idx_invoice_items_inventory');
                }
            });
        }

        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_counts');
        Schema::dropIfExists('inventory_adjustment_items');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_transfer_items');
        Schema::dropIfExists('inventory_transfers');
        Schema::dropIfExists('inventory_stock_levels');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_serial_numbers');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_warehouses');
        Schema::dropIfExists('inventory_categories');
    }
};
