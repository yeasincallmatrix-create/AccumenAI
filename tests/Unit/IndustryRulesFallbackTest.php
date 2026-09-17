<?php

namespace Tests\Unit;

use App\Support\IndustryRules;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Config-fallback contract: when the taxonomy DB layer is unavailable
 * (tables missing — e.g. migration not yet run in an environment),
 * IndustryRules must fall back to config/industry_rules.php instead of
 * letting a QueryException propagate.
 *
 * The missing-table condition is simulated with an empty in-memory SQLite
 * connection (no DDL on the shared MySQL test database, which would
 * implicitly commit and break other tests). Production code is untouched —
 * only the default connection name is swapped for the duration of the test.
 */
class IndustryRulesFallbackTest extends TestCase
{
    protected string $originalConnection = 'mysql';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->originalConnection = config('database.default');
        config(['database.connections.taxonomy_missing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('taxonomy_missing');
        config(['database.default' => 'taxonomy_missing']);
    }

    protected function tearDown(): void
    {
        config(['database.default' => $this->originalConnection]);
        DB::purge('taxonomy_missing');
        Cache::flush();

        parent::tearDown();
    }

    public function test_industries_fall_back_to_config_when_tables_missing(): void
    {
        $this->assertSame(config('industry_rules.global.industries'), IndustryRules::industries('Bangladesh'));
        $this->assertSame(config('industry_rules.global.industries'), IndustryRules::industries('France'));
        $this->assertSame(config('industry_rules.global.industries'), IndustryRules::industries(null));
    }

    public function test_sub_industries_fall_back_to_config_when_tables_missing(): void
    {
        $this->assertSame(
            config('industry_rules.Bangladesh.education'),
            IndustryRules::subIndustries('Bangladesh', 'education')
        );
        $this->assertSame([], IndustryRules::subIndustries('Bangladesh', 'real_estate'));
        $this->assertSame([], IndustryRules::subIndustries('France', 'healthcare'));
    }

    public function test_has_sub_industries_falls_back_when_tables_missing(): void
    {
        $this->assertTrue(IndustryRules::hasSubIndustries('Bangladesh', 'education'));
        $this->assertFalse(IndustryRules::hasSubIndustries('Bangladesh', 'real_estate'));
        $this->assertFalse(IndustryRules::hasSubIndustries('France', 'education'));
    }

    public function test_label_falls_back_to_config_when_tables_missing(): void
    {
        $this->assertSame('Education', IndustryRules::label('Bangladesh', 'education'));
        $this->assertSame('Madrasha', IndustryRules::label('Bangladesh', 'education', 'madrasha'));
        $this->assertSame('Healthcare', IndustryRules::label('France', 'healthcare'));
        $this->assertSame('mystery', IndustryRules::label('Bangladesh', 'education', 'mystery'));
        $this->assertNull(IndustryRules::label('Bangladesh', 'unknown'));
    }
}
