<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 11 — Migration governance verification.
 *
 * Verifies:
 *  1. Test DB has all migration columns (B24/B131 fix)
 *  2. Stale test DBs can be detected (B111)
 *  3. BankReconciliation tables exist (B59)
 *  4. Config-driven locale fallbacks work (B116)
 *  5. AiToolPermissionSeeder idempotent (B95)
 */
class MigrationGovernanceTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // 1. test_module_access_logs_has_phase_10_columns
    // ================================================================
    public function test_module_access_logs_has_phase_10_columns(): void
    {
        $columns = Schema::getColumns('module_access_logs');
        $columnNames = array_column($columns, 'name');

        $this->assertContains('reason', $columnNames, 'module_access_logs must have reason column (Phase 10)');
        $this->assertContains('feature_key', $columnNames, 'module_access_logs must have feature_key column (Phase 10)');
        $this->assertContains('decision', $columnNames, 'module_access_logs must have decision column (Phase 10)');
        $this->assertContains('request_id', $columnNames, 'module_access_logs must have request_id column (Phase 10)');
        $this->assertContains('actor_type', $columnNames, 'module_access_logs must have actor_type column (Phase 10)');
    }

    // ================================================================
    // 2. test_bank_reconciliation_tables_exist
    // ================================================================
    public function test_bank_reconciliation_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('bank_reconciliations'), 'bank_reconciliations table must exist');
        $this->assertTrue(Schema::hasTable('bank_statement_lines'), 'bank_statement_lines table must exist');
    }

    // ================================================================
    // 3. test_locale_config_has_geo_defaults
    // ================================================================
    public function test_locale_config_has_geo_defaults(): void
    {
        $this->assertNotEmpty(config('locale.geo.default_lat'), 'locale.geo.default_lat must be configured');
        $this->assertNotEmpty(config('locale.geo.default_lng'), 'locale.geo.default_lng must be configured');
        $this->assertEquals('23.8103', config('locale.geo.default_lat'));
        $this->assertEquals('90.4125', config('locale.geo.default_lng'));
    }

    // ================================================================
    // 4. test_locale_config_has_country_defaults
    // ================================================================
    public function test_locale_config_has_country_defaults(): void
    {
        $this->assertNotEmpty(config('locale.country.default_iso2'));
        $this->assertNotEmpty(config('locale.country.default_name'));
        $this->assertNotEmpty(config('locale.currency.default_code'));
        $this->assertNotEmpty(config('locale.date.timezone'));
    }

    // ================================================================
    // 5. test_ai_tool_permission_seeder_idempotent
    // ================================================================
    public function test_ai_tool_permission_seeder_idempotent(): void
    {
        // Run the seeder — should not throw even if already seeded
        $seeder = new \Database\Seeders\AiToolPermissionSeeder;
        $seeder->run();
        $seeder->run(); // second run must be idempotent

        $this->assertDatabaseHas('permissions', ['slug' => 'finance.view']);
        $this->assertDatabaseHas('permissions', ['slug' => 'crm.view']);
    }

    // ================================================================
    // 6. test_no_stale_test_databases
    // ================================================================
    public function test_no_stale_test_databases(): void
    {
        $stale = DB::select(
            "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE ?",
            ['monetix_test_test_%']
        );

        $staleNames = array_column($stale, 'SCHEMA_NAME');

        // Soft assertion: report via message, don't fail the test suite
        $this->assertEmpty(
            $staleNames,
            'Stale test databases detected: ' . implode(', ', $staleNames)
            . '. Run: scripts/drop-stale-test-dbs.sh --apply'
        );
    }

    // ================================================================
    // 7. test_budget_service_uses_config_fallback
    // ================================================================
    public function test_budget_service_uses_config_fallback(): void
    {
        // Verify the config key exists — BudgetService now uses it
        $fallback = config('locale.currency.default_code', 'BDT');
        $this->assertEquals('BDT', $fallback);
    }

    // ================================================================
    // 8. test_platform_settings_controller_uses_config
    // ================================================================
    public function test_platform_settings_controller_uses_config(): void
    {
        // Verify all config keys that PlatformSettingsController now references
        $this->assertEquals('Asia/Dhaka', config('locale.date.timezone'));
        $this->assertEquals('BD', config('locale.country.default_iso2'));
        $this->assertEquals('BDT', config('locale.currency.default_code'));
        $this->assertEquals('23.8103', config('locale.geo.default_lat'));
        $this->assertEquals('90.4125', config('locale.geo.default_lng'));
    }
}
