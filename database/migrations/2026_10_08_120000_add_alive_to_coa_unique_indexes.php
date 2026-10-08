<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'chart_of_accounts' => 'uq_coa_code',
        'account_groups' => 'uq_account_groups_code',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $indexBase) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->replaceGeneratedAliveWithPlainColumn($table);
            $this->swapUniqueIndex($table, $indexBase);
            $this->createAliveTriggers($table);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $indexBase) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->dropAliveTriggers($table);

            if ($this->indexExists($table, $indexBase.'_v3')) {
                $addV2 = $this->indexExists($table, $indexBase.'_v2') ? '' : ", ADD UNIQUE `{$indexBase}_v2` (`institute_key`, `branch_key`, `code`)";
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexBase}_v3`{$addV2}");
            }

            if ($this->isPlainAliveColumn($table)) {
                DB::statement("ALTER TABLE `{$table}` DROP COLUMN `alive`");
            }
        }
    }

    private function replaceGeneratedAliveWithPlainColumn(string $table): void
    {
        $column = $this->columnInfo($table, 'alive');

        if ($column === null) {
            DB::statement("ALTER TABLE `{$table}` ADD COLUMN `alive` BIGINT NOT NULL DEFAULT 1");

            return;
        }

        if (stripos((string) $column->EXTRA, 'GENERATED') !== false) {
            if ($this->indexExists($table, $this->indexBase($table).'_v3')) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$this->indexBase($table)}_v3`");
            }

            DB::statement("ALTER TABLE `{$table}` DROP COLUMN `alive`");
            DB::statement("ALTER TABLE `{$table}` ADD COLUMN `alive` BIGINT NOT NULL DEFAULT 1");
        }
    }

    private function swapUniqueIndex(string $table, string $indexBase): void
    {
        if ($this->indexExists($table, $indexBase.'_v3')) {
            return;
        }

        if ($this->indexExists($table, $indexBase.'_v2')) {
            DB::statement(
                "ALTER TABLE `{$table}` DROP INDEX `{$indexBase}_v2`, ADD UNIQUE `{$indexBase}_v3` (`institute_key`, `branch_key`, `code`, `alive`)"
            );

            return;
        }

        DB::statement(
            "ALTER TABLE `{$table}` ADD UNIQUE `{$indexBase}_v3` (`institute_key`, `branch_key`, `code`, `alive`)"
        );
    }

    private function createAliveTriggers(string $table): void
    {
        foreach ($this->triggerNames($table) as $name => $statement) {
            if (! $this->triggerExists($name)) {
                DB::statement($statement);
            }
        }
    }

    private function dropAliveTriggers(string $table): void
    {
        foreach (array_keys($this->triggerNames($table)) as $name) {
            if ($this->triggerExists($name)) {
                DB::statement("DROP TRIGGER `{$name}`");
            }
        }
    }

    private function triggerNames(string $table): array
    {
        return [
            "trg_{$table}_alive_ins" => "CREATE TRIGGER `trg_{$table}_alive_ins` BEFORE INSERT ON `{$table}` FOR EACH ROW SET NEW.alive = IF(NEW.deleted_at IS NULL, 1, -NEW.id)",
            "trg_{$table}_alive_upd" => "CREATE TRIGGER `trg_{$table}_alive_upd` BEFORE UPDATE ON `{$table}` FOR EACH ROW SET NEW.alive = IF(NEW.deleted_at IS NULL, 1, -OLD.id)",
        ];
    }

    private function isPlainAliveColumn(string $table): bool
    {
        $column = $this->columnInfo($table, 'alive');

        return $column !== null && stripos((string) $column->EXTRA, 'GENERATED') === false;
    }

    private function columnInfo(string $table, string $column): ?object
    {
        return DB::selectOne(
            'SELECT EXTRA, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
             LIMIT 1',
            [$table, $index]
        ) !== null;
    }

    private function triggerExists(string $name): bool
    {
        return DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?
             LIMIT 1',
            [$name]
        ) !== null;
    }

    private function indexBase(string $table): string
    {
        return self::TABLES[$table];
    }
};
