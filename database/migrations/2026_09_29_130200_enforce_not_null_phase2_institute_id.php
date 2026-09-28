<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dealership Phase 3 (optional cleanup) — enforce NOT NULL on the
     * Phase 2 institute_id columns to match the codebase tenancy pattern.
     *
     * Safe: all 7 tables are verified empty before altering. Aborts with
     * an exception (no change applied to that table) if any NULL rows or
     * existing rows are found. Idempotent via information_schema check.
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
            $total = DB::table($table)->count();
            $nulls = DB::table($table)->whereNull('institute_id')->count();

            if ($total > 0 || $nulls > 0) {
                throw new RuntimeException(
                    "STOP: {$table} has {$total} rows ({$nulls} NULL institute_id). NOT NULL enforcement skipped."
                );
            }

            $nullable = DB::table('information_schema.columns')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('column_name', 'institute_id')
                ->value('is_nullable');

            if ($nullable === 'NO') {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `institute_id` BIGINT UNSIGNED NOT NULL");
        }
    }

    public function down(): void
    {
        // NO destructive rollback per project rules.
    }
};
