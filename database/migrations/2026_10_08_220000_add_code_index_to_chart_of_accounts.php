<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLE = 'chart_of_accounts';

    private const INDEX = 'idx_coa_code';

    public function up(): void
    {
        if (! $this->tableExists() || $this->indexExists()) {
            return;
        }

        DB::statement('ALTER TABLE `chart_of_accounts` ADD INDEX `idx_coa_code` (`code`)');
    }

    public function down(): void
    {
        if (! $this->tableExists() || ! $this->indexExists()) {
            return;
        }

        DB::statement('ALTER TABLE `chart_of_accounts` DROP INDEX `idx_coa_code`');
    }

    private function tableExists(): bool
    {
        return DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             LIMIT 1',
            [self::TABLE]
        ) !== null;
    }

    private function indexExists(): bool
    {
        return DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
             LIMIT 1',
            [self::TABLE, self::INDEX]
        ) !== null;
    }
};
