<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createExpensesTableIfMissing();
        $this->addInvoiceItemReferenceColumns();
        $this->alignModuleRegistryComingSoonNullability();
        $this->alignDealershipIncentivesToLiveShape();
        $this->normalizeMigrationsBatchColumnCase();
    }

    public function down(): void
    {
        // Intentionally a no-op: this migration only converges schema shape toward
        // the live accumen_ai schema. Reverting it would reintroduce the drift it
        // removes. See docs/SCHEMA_DRIFT.md.
    }

    private function createExpensesTableIfMissing(): void
    {
        if (Schema::hasTable('expenses')) {
            return;
        }

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('expense_number', 50);

            $table->unsignedBigInteger('paid_by_user_id')->nullable();
            $table->unsignedBigInteger('payment_account_id');

            $table->date('expense_date');
            $table->string('vendor_name', 200)->nullable();
            $table->string('reference_number', 50)->nullable();
            $table->string('expense_category', 50)->nullable();

            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('BDT');
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('tax_group_id')->nullable();

            $table->unsignedBigInteger('expense_account_id');
            $table->unsignedBigInteger('journal_entry_id')->nullable();

            $table->boolean('is_billable')->default(false);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('markup_percentage', 5, 2)->default(0);
            $table->decimal('billable_amount', 15, 2)->nullable();
            $table->string('billing_status', 20)->default('unbillable');
            $table->unsignedBigInteger('billed_invoice_id')->nullable();
            $table->timestamp('billed_at')->nullable();

            $table->string('receipt_path', 255)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('paid_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('payment_account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('expense_account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('customer_id')->references('id')->on('parties')->onDelete('set null');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('tax_group_id')->references('id')->on('tax_groups')->onDelete('set null');
            $table->foreign('billed_invoice_id')->references('id')->on('invoices')->onDelete('set null');

            $table->unique(['institute_id', 'expense_number'], 'uniq_expense_number');
            $table->index(['institute_id', 'expense_date']);
            $table->index(['institute_id', 'is_billable', 'billing_status'], 'idx_billable_status');
            $table->index(['customer_id', 'billing_status']);
        });
    }

    private function addInvoiceItemReferenceColumns(): void
    {
        if (! Schema::hasTable('invoice_items')) {
            return;
        }

        if (! Schema::hasColumn('invoice_items', 'reference_type')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->string('reference_type')->nullable()->after('fee_head_id');
            });
        }

        if (! Schema::hasColumn('invoice_items', 'reference_id')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->unsignedBigInteger('reference_id')->nullable()->after(
                    Schema::hasColumn('invoice_items', 'reference_type') ? 'reference_type' : 'fee_head_id'
                );
            });
        }
    }

    private function alignModuleRegistryComingSoonNullability(): void
    {
        $column = DB::selectOne(
            "SELECT IS_NULLABLE AS is_nullable FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'module_registry' AND COLUMN_NAME = 'coming_soon'"
        );

        if ($column && $column->is_nullable === 'NO') {
            DB::statement('ALTER TABLE module_registry MODIFY coming_soon tinyint(1) NULL DEFAULT 0');
        }
    }

    private function alignDealershipIncentivesToLiveShape(): void
    {
        if (! Schema::hasTable('dealership_incentives')) {
            return;
        }

        // Helper indexes declared by create_dealership_phase3_tables that the live
        // schema does not carry.
        foreach ([
            'dealership_incentives_institute_id_index',
            'di_sr_earned_idx',
            'di_status_idx',
        ] as $index) {
            if (Schema::hasIndex('dealership_incentives', $index)) {
                Schema::table('dealership_incentives', function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            }
        }

        // The fk_di_sr foreign key exists on both sides but on databases built from
        // migrations its backing index kept the Laravel-generated name. MariaDB 10.4
        // has no RENAME INDEX, so swap the index underneath the FK: drop FK, drop
        // legacy index, add the live-named index, re-add the FK.
        $legacyNameExists = Schema::hasIndex('dealership_incentives', 'dealership_incentives_sales_force_id_index');
        $liveNameExists = Schema::hasIndex('dealership_incentives', 'fk_di_sr');

        if ($legacyNameExists && ! $liveNameExists) {
            $foreign = DB::selectOne(
                "SELECT COUNT(*) AS total FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dealership_incentives'
                   AND CONSTRAINT_NAME = 'fk_di_sr' AND REFERENCED_TABLE_NAME IS NOT NULL"
            );

            if ((int) $foreign->total > 0) {
                DB::statement('ALTER TABLE dealership_incentives DROP FOREIGN KEY `fk_di_sr`');
            }

            DB::statement('ALTER TABLE dealership_incentives DROP INDEX `dealership_incentives_sales_force_id_index`');

            Schema::table('dealership_incentives', function (Blueprint $table) {
                $table->index(['sales_force_id'], 'fk_di_sr');
            });
        }

        $foreign = DB::selectOne(
            "SELECT COUNT(*) AS total FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dealership_incentives'
               AND CONSTRAINT_NAME = 'fk_di_sr' AND REFERENCED_TABLE_NAME IS NOT NULL"
        );

        if ((int) $foreign->total === 0) {
            Schema::table('dealership_incentives', function (Blueprint $table) {
                $table->foreign('sales_force_id', 'fk_di_sr')
                    ->references('id')
                    ->on('dealership_sales_force')
                    ->cascadeOnDelete();
            });
        }
    }

    private function normalizeMigrationsBatchColumnCase(): void
    {
        // Databases built from an old dump stored the column as `BATCH`; live uses
        // `batch`. Column names are case-insensitive to SQL, but the stored case
        // keeps structure digests from matching, so converge it. Compared with
        // BINARY so the check itself is case-sensitive.
        $column = DB::selectOne(
            "SELECT COLUMN_NAME AS column_name FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'migrations' AND BINARY COLUMN_NAME = 'BATCH'"
        );

        if ($column) {
            DB::statement('ALTER TABLE `migrations` CHANGE COLUMN `BATCH` `batch` int(11) NOT NULL');
        }
    }
};
