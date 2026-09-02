<?php

namespace Tests\Feature;

use App\Services\System\SeedVersionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SeedVersionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_records_and_verifies_seeds(): void
    {
        $service = app(SeedVersionService::class);
        $service->recordAll();

        $result = $service->verifyAll();
        $this->assertTrue($result['healthy']);
        $this->assertEmpty($result['missing']);
    }

    public function test_detects_missing_seed(): void
    {
        $service = app(SeedVersionService::class);
        $service->recordAll();

        // Simulate drift by changing checksum
        \App\Models\SystemSeedVersion::where('seed_name', 'themes')->update(['checksum' => 'invalid']);

        $result = $service->verifyAll();
        $this->assertFalse($result['healthy']);
        $this->assertContains('themes', $result['missing']);
    }

    public function test_checksum_changes_when_data_changes(): void
    {
        $service = app(SeedVersionService::class);
        $before = $service->checksum('themes');

        \Illuminate\Support\Facades\DB::table('themes')->insert([
            'name' => 'Test Theme '.uniqid(),
            'slug' => 'test-'.uniqid(),
            'primary_color' => '#000000',
            'secondary_color' => '#FFFFFF',
            'is_default' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $after = $service->checksum('themes');
        $this->assertNotEquals($before, $after);
    }

    public function test_table_exists(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('system_seed_versions'));
    }

    public function test_artisan_command(): void
    {
        app(SeedVersionService::class)->recordAll();
        $this->artisan('system:verify-seeds')
            ->assertExitCode(0);
    }
}
