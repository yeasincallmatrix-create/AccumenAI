<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dealership Phase 2.5 — FK restoration (15 constraints).
     *
     * Phase 1 internal (3) + Phase 2 → Phase 1 references (12).
     * Idempotent: each FK is added only if its constraint name is absent
     * from information_schema. All referencing columns are indexed
     * unsignedBigIntegers matching referenced `id` columns.
     */
    public function up(): void
    {
        $fks = [
            // Phase 1 internal.
            ['fk_dproducts_brand', 'dealership_products', 'brand_id', 'dealership_brands', 'id', 'restrict'],
            ['fk_dsf_beat', 'dealership_sales_force', 'beat_id', 'dealership_beats', 'id', 'set null'],
            ['fk_dcust_beat', 'dealership_customers', 'beat_id', 'dealership_beats', 'id', 'set null'],
            // Phase 2 → Phase 1.
            ['fk_dsr_orders_customer', 'dealership_sr_orders', 'customer_id', 'dealership_customers', 'id', 'restrict'],
            ['fk_dsr_orders_sf', 'dealership_sr_orders', 'sales_force_id', 'dealership_sales_force', 'id', 'restrict'],
            ['fk_dsr_items_order', 'dealership_sr_order_items', 'sr_order_id', 'dealership_sr_orders', 'id', 'cascade'],
            ['fk_dsr_items_product', 'dealership_sr_order_items', 'product_id', 'dealership_products', 'id', 'restrict'],
            ['fk_dapprovals_order', 'dealership_order_approvals', 'sr_order_id', 'dealership_sr_orders', 'id', 'cascade'],
            ['fk_dcoll_customer', 'dealership_sr_collections', 'customer_id', 'dealership_customers', 'id', 'restrict'],
            ['fk_dcoll_sf', 'dealership_sr_collections', 'sales_force_id', 'dealership_sales_force', 'id', 'restrict'],
            ['fk_dcoll_order', 'dealership_sr_collections', 'sr_order_id', 'dealership_sr_orders', 'id', 'set null'],
            ['fk_dpl_brand', 'dealership_price_lists', 'brand_id', 'dealership_brands', 'id', 'cascade'],
            ['fk_dpl_product', 'dealership_price_lists', 'product_id', 'dealership_products', 'id', 'set null'],
            ['fk_dcl_customer', 'dealership_credit_limits', 'customer_id', 'dealership_customers', 'id', 'cascade'],
            ['fk_dil_product', 'dealership_inventory_links', 'product_id', 'dealership_products', 'id', 'cascade'],
        ];

        foreach ($fks as [$name, $table, $column, $refTable, $refColumn, $onDelete]) {
            if (! Schema::hasTable($table) || ! Schema::hasTable($refTable)) {
                continue;
            }
            if ($this->fkExists($name)) {
                continue;
            }

            $action = match ($onDelete) {
                'cascade' => 'ON DELETE CASCADE',
                'set null' => 'ON DELETE SET NULL',
                default => 'ON DELETE RESTRICT',
            };

            DB::statement(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` " .
                "FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`{$refColumn}`) {$action}"
            );
        }
    }

    public function down(): void
    {
        // NO destructive rollback per project rules.
    }

    private function fkExists(string $name): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('constraint_name', $name)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
