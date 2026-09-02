<?php

namespace Tests\Feature;

use App\Services\System\SeedIntegrityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeedIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_required_seeds_are_present(): void
    {
        $service = app(SeedIntegrityService::class);
        $result = $service->check();

        $this->assertTrue($result['healthy'], 'Seed integrity should be healthy: '.implode(', ', $result['missing']));
        $this->assertEmpty($result['missing']);
    }

    public function test_detects_missing_role(): void
    {
        // Temporarily remove a role and check detection
        $role = DB::table('roles')->where('slug', 'institute-owner')->first();
        $this->assertNotNull($role);

        DB::table('roles')->where('slug', 'institute-owner')->delete();

        $service = app(SeedIntegrityService::class);
        $result = $service->check();

        $this->assertFalse($result['healthy']);
        $this->assertContains('role:institute-owner', $result['missing']);

        // Restore
        DB::table('roles')->insert((array)$role);
    }

    public function test_detects_missing_industry_settings(): void
    {
        DB::table('industry_settings')->delete();

        $service = app(SeedIntegrityService::class);
        $result = $service->check();

        $this->assertFalse($result['healthy']);
        $this->assertContains('industry_settings:all', $result['missing']);

        // Restore via seedDefaults
        $service->seedDefaults();
        $this->assertDatabaseHas('industry_settings', ['industry_key' => 'all']);
    }

    public function test_detects_missing_themes(): void
    {
        $count = DB::table('themes')->count();
        $this->assertGreaterThan(0, $count);
    }

    public function test_seed_defaults_creates_missing(): void
    {
        DB::table('industry_settings')->where('industry_key', 'all')->delete();
        $this->assertDatabaseMissing('industry_settings', ['industry_key' => 'all']);

        $service = app(SeedIntegrityService::class);
        $created = $service->seedDefaults();

        $this->assertContains('industry_settings:all', $created);
        $this->assertDatabaseHas('industry_settings', ['industry_key' => 'all']);
    }

    public function test_countries_seeded(): void
    {
        $count = DB::table('countries')->count();
        $this->assertGreaterThan(0, $count);

        $service = app(SeedIntegrityService::class);
        $result = $service->check();
        $this->assertNotContains('countries:empty', $result['missing']);
    }
}
