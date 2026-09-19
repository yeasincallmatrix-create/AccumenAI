<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MigrationIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_all_accounting_tables_have_migrations(): void
    {
        $files = File::glob(database_path("migrations/2026_09_20_*_table.php"));
        $this->assertGreaterThanOrEqual(75, count($files), "Expected at least 75 migration files");
    }

    public function test_migration_files_are_valid_php(): void
    {
        $files = File::glob(database_path("migrations/2026_09_20_*_table.php"));
        foreach ($files as $file) {
            $content = File::get($file);
            $this->assertStringContainsString("Schema::create", $content, basename($file) . " should use Schema::create");
            $this->assertStringContainsString("Schema::dropIfExists", $content, basename($file) . " should use Schema::dropIfExists in down()");
            $this->assertStringContainsString("hasTable", $content, basename($file) . " should have hasTable guard");
        }
    }

    public function test_no_duplicate_migration_files(): void
    {
        $files = File::glob(database_path("migrations/2026_09_20_*_table.php"));
        $names = array_map(fn($f) => basename($f), $files);
        $unique = array_unique($names);
        $this->assertCount(count($unique), $names, "Found duplicate migration filenames");
    }
}
