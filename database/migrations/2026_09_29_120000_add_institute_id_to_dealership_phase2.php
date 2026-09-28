<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dealership Phase 2.5 — tenancy remediation.
     *
     * Adds nullable institute_id (+index) to all 7 Phase 2 tables and
     * converts single-column uniques into per-tenant composite uniques.
     * Tables are empty (verified pre-migration), so constraint changes
     * cannot lose data. Fully idempotent via information_schema checks.
     */
    public function up(): void
    {
        $tables = [
            'dealership_sr_orders',
            'dealership_sr_order_items',
            'dealership_order_approvals',
            'dealership_sr_collections',
            'dealership_price_lists',
            'dealership_credit_limits',
            'dealership_inventory_links',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'institute_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('institute_id')->nullable()->index()->after('id');
            });
        }

        // dealership_sr_orders: unique(order_no) -> unique(institute_id, order_no)
        $this->replaceUnique(
            'dealership_sr_orders',
            'dealership_sr_orders_order_no_unique',
            'dealership_sr_orders_institute_order_unique',
            ['institute_id', 'order_no']
        );

        // dealership_sr_collections: unique(receipt_no) -> unique(institute_id, receipt_no)
        $this->replaceUnique(
            'dealership_sr_collections',
            'dealership_sr_collections_receipt_no_unique',
            'dealership_sr_collections_institute_receipt_unique',
            ['institute_id', 'receipt_no']
        );

        // dealership_credit_limits: unique(customer_id) -> unique(institute_id, customer_id)
        $this->replaceUnique(
            'dealership_credit_limits',
            'dealership_credit_limits_customer_id_unique',
            'dealership_credit_limits_institute_customer_unique',
            ['institute_id', 'customer_id']
        );

        // dealership_inventory_links: unique(product_id) -> unique(institute_id, product_id)
        $this->replaceUnique(
            'dealership_inventory_links',
            'dealership_inventory_links_product_id_unique',
            'dealership_inventory_links_institute_product_unique',
            ['institute_id', 'product_id']
        );

        // dealership_price_lists: scoped lookup index (no pre-existing unique)
        $this->ensureIndex(
            'dealership_price_lists',
            'dpl_scope_idx',
            ['institute_id', 'brand_id', 'channel', 'effective_from'],
            false
        );
    }

    public function down(): void
    {
        // NO destructive rollback per project rules.
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function replaceUnique(string $table, string $oldIndex, string $newIndex, array $columns): void
    {
        if ($this->indexExists($table, $oldIndex)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$oldIndex}`");
        }

        $this->ensureIndex($table, $newIndex, $columns, true);
    }

    private function ensureIndex(string $table, string $index, array $columns, bool $unique): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        $cols = implode('`, `', $columns);
        $kind = $unique ? 'UNIQUE INDEX' : 'INDEX';
        DB::statement("ALTER TABLE `{$table}` ADD {$kind} `{$index}` (`{$cols}`)");
    }
};
