<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F-009 / N-006 — schema drift guards (TASK C3).
 *
 * Precondition: run `composer test:setup` after pulling so every migration file
 * (including the 0000_00_00_* placeholders) has been applied to this database.
 * Two of the three tests deliberately fail otherwise — that is the guard working.
 *
 * Sources of truth and the regeneration procedure live in docs/SCHEMA_DRIFT.md.
 */
class SchemaIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_migrations_table_has_no_missing_files(): void
    {
        $rows = DB::table('migrations')
            ->pluck('migration')
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->values();

        $baselines = $rows
            ->filter(fn ($name) => str_starts_with($name, '0000_00_00'))
            ->values();

        $this->assertNotEmpty(
            $baselines->all(),
            'No 0000_00_00_* rows found in migrations — run `composer test:setup`.'
        );

        foreach ($baselines as $name) {
            $this->assertFileExists(
                database_path("migrations/{$name}.php"),
                "Migrations row [{$name}] has no source file (see docs/SCHEMA_DRIFT.md)."
            );
        }

        $files = collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn ($path) => basename($path, '.php'));

        foreach ($files as $file) {
            $this->assertTrue(
                $rows->contains($file),
                "Migration file [{$file}] has no migrations row — run `composer test:setup`."
            );
        }
    }

    public function test_chart_of_accounts_matches_expected_shape(): void
    {
        $columns = [];

        foreach (DB::select(
            'SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable, EXTRA AS extra
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'chart_of_accounts\''
        ) as $row) {
            $columns[$row->name] = $row;
        }

        // The original migration declared varchar(255); live is varchar(30).
        $this->assertArrayHasKey('code', $columns);
        $this->assertSame('varchar(30)', $columns['code']->type);

        // Hybrid global rows (institute_id NULL) are legal.
        $this->assertArrayHasKey('institute_id', $columns);
        $this->assertSame('YES', $columns['institute_id']->nullable);

        // Columns live gained outside the historical migration files.
        foreach (['is_postable', 'is_header', 'industries', 'deleted_at'] as $name) {
            $this->assertArrayHasKey(
                $name,
                $columns,
                "chart_of_accounts.{$name} is missing — test database drifts from live (run `composer test:setup`)."
            );
        }

        // Tenant anchoring is expressed by generated columns, not stored values.
        foreach (['institute_key', 'branch_key', 'category'] as $name) {
            $this->assertArrayHasKey($name, $columns, "chart_of_accounts.{$name} is missing.");
            $this->assertStringContainsString(
                'GENERATED',
                strtoupper((string) $columns[$name]->extra),
                "chart_of_accounts.{$name} must be a generated column."
            );
        }
    }

    public function test_no_stale_schema_dumps(): void
    {
        $schemaDir = database_path('schema');

        $this->assertFileDoesNotExist(
            $schemaDir.'/full_data_only.sql',
            'full_data_only.sql was retired by TASK C3 — do not reintroduce it.'
        );
        $this->assertFileDoesNotExist(
            $schemaDir.'/full_data_utf8.sql',
            'full_data_utf8.sql was retired by TASK C3 — do not reintroduce it.'
        );

        $tableCount = (int) DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\''
        )->total;

        $this->assertFreshUtf8Dump(
            $schemaDir.'/mysql-schema.sql',
            $tableCount,
            'mysql-schema.sql'
        );

        $mysqlSchema = file_get_contents($schemaDir.'/mysql-schema.sql');
        $this->assertStringContainsString(
            'INSERT INTO `migrations`',
            $mysqlSchema,
            'mysql-schema.sql must carry migrations rows — without them a fresh `migrate` re-runs every migration.'
        );

        $this->assertFreshUtf8Dump(
            $schemaDir.'/schema.sql',
            $tableCount,
            'schema.sql'
        );

        $schema = file_get_contents($schemaDir.'/schema.sql');
        $this->assertStringNotContainsString(
            'INSERT INTO `migrations`',
            $schema,
            'schema.sql must stay pure DDL — migrations_data.sql owns the migrations rows for parallel imports.'
        );
    }

    private function assertFreshUtf8Dump(string $path, int $tableCount, string $label): void
    {
        $this->assertFileExists($path, "{$label} is missing.");

        $bom = file_get_contents($path, false, null, 0, 2);
        $this->assertFalse(
            $bom === "\xFF\xFE" || $bom === "\xFE\xFF",
            "{$label} is UTF-16 — the mysql client cannot load it (see docs/SCHEMA_DRIFT.md)."
        );

        $content = file_get_contents($path);
        $this->assertStringNotContainsString(
            "\0",
            $content,
            "{$label} contains NUL bytes — it is not valid UTF-8/ASCII SQL."
        );

        $this->assertSame(
            $tableCount,
            substr_count($content, 'CREATE TABLE'),
            sprintf(
                '%s is stale: dump has %d CREATE TABLE vs %d tables in this database. '.
                'If the database is current, regenerate — see docs/SCHEMA_DRIFT.md.',
                $label,
                substr_count($content, 'CREATE TABLE'),
                $tableCount
            )
        );
    }
}
