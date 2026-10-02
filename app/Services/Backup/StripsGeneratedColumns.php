<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;

/**
 * Generated columns (VIRTUAL/STORED ... GENERATED) must never be assigned a
 * value: MySQL/MariaDB reject the statement (error 1906 / 3104). Backup dumps
 * are SELECT * exports, so they DO carry generated columns — without stripping
 * them from the write payload every INSERT/UPDATE of such a table fails, the
 * row is caught + counted as skipped/kept, and the backed-up data is silently
 * never restored (observed on 10 tables: chart_of_accounts, account_groups,
 * grade_scales, students, institute_users, ...).
 *
 * The value is recomputed by the server from its input columns, so dropping
 * the column from the payload loses nothing.
 */
trait StripsGeneratedColumns
{
    /** @var array<string, string[]> */
    private array $generatedColumnCache = [];

    /**
     * Generated columns of a table (cached per instance / per table).
     *
     * @return string[]
     */
    private function generatedColumns(string $table): array
    {
        if (array_key_exists($table, $this->generatedColumnCache)) {
            return $this->generatedColumnCache[$table];
        }

        $columns = [];

        try {
            $rows = DB::select(
                'SELECT COLUMN_NAME AS generated_column
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND GENERATION_EXPRESSION <> \'\'',
                [$table]
            );

            foreach ($rows as $row) {
                $columns[] = (string) $row->generated_column;
            }
        } catch (\Throwable $e) {
            // information_schema unavailable -> write unstripped (legacy
            // behaviour) rather than silently dropping real columns.
            $columns = [];
        }

        return $this->generatedColumnCache[$table] = $columns;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function stripGeneratedColumns(string $table, array $row): array
    {
        $generated = $this->generatedColumns($table);

        return $generated === [] ? $row : array_diff_key($row, array_flip($generated));
    }
}
