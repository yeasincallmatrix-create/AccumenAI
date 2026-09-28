<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dealership Phase 5 — FK constraints (2).
     * api_endpoints carries no FK (config-like registry).
     * Idempotent via information_schema check.
     */
    public function up(): void
    {
        $fks = [
            ['fk_dapi_token_sr', 'dealership_api_tokens', 'sales_force_id', 'dealership_sales_force', 'id', 'cascade'],
            ['fk_dpn_recipient', 'dealership_push_notifications', 'recipient_sales_force_id', 'dealership_sales_force', 'id', 'cascade'],
        ];

        foreach ($fks as [$name, $table, $column, $refTable, $refColumn, $onDelete]) {
            if (! Schema::hasTable($table) || ! Schema::hasTable($refTable)) {
                continue;
            }
            if ($this->fkExists($name)) {
                continue;
            }

            $action = $onDelete === 'cascade' ? 'ON DELETE CASCADE' : 'ON DELETE RESTRICT';

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
