<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dealership Phase 3 — FK constraints (6).
     *
     * Idempotent: each FK is added only if its constraint name is absent
     * from information_schema. All referencing columns are indexed
     * unsignedBigIntegers matching referenced `id` columns.
     */
    public function up(): void
    {
        $fks = [
            ['fk_dst_sr', 'dealership_sr_targets', 'sales_force_id', 'dealership_sales_force', 'id', 'cascade'],
            ['fk_dbt_brand', 'dealership_brand_targets', 'brand_id', 'dealership_brands', 'id', 'cascade'],
            ['fk_dsc_sr', 'dealership_sr_commission', 'sales_force_id', 'dealership_sales_force', 'id', 'cascade'],
            ['fk_di_sr', 'dealership_incentives', 'sales_force_id', 'dealership_sales_force', 'id', 'cascade'],
            ['fk_dat_sr', 'dealership_attendance', 'sales_force_id', 'dealership_sales_force', 'id', 'cascade'],
            ['fk_dat_beat', 'dealership_attendance', 'beat_id', 'dealership_beats', 'id', 'set null'],
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
