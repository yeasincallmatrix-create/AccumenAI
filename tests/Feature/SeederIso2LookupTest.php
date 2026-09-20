<?php

namespace Tests\Feature;

use App\Console\Commands\DemoBusinessSeederCommand;
use App\Models\Country;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\GradeScaleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 9b-2 (B108): seeders look countries up by ISO2, so a country
 * rename no longer orphans seeded structures.
 *
 * Each test renames the shared row in-test (rolled back) — with the
 * old name-based lookup the seeder would skip; with ISO2 it proceeds.
 */
class SeederIso2LookupTest extends TestCase
{
    use DatabaseTransactions;

    private function bdId(): int
    {
        return (int) Country::where('iso2', 'BD')->firstOrFail()->id;
    }

    private function renameBd(string $name): void
    {
        DB::table('countries')->where('id', $this->bdId())->update(['name' => $name]);
    }

    public function test_academic_structure_seeder_uses_iso2_lookup(): void
    {
        $this->renameBd('Bangla (Renamed)');

        (new AcademicStructureSeeder)->run();

        $this->assertDatabaseHas('education_systems', ['country_id' => $this->bdId()]);
    }

    public function test_grade_scale_seeder_uses_iso2_lookup(): void
    {
        $this->renameBd('Bangla (Renamed)');

        (new GradeScaleSeeder)->run();

        $this->assertDatabaseHas('grade_scales', ['country_id' => $this->bdId()]);
    }

    public function test_demo_command_links_canonical_country_row(): void
    {
        $command = new DemoBusinessSeederCommand;
        $method = new \ReflectionMethod($command, 'createInstitute');
        $method->setAccessible(true);

        $inst = $method->invoke($command, 'education', 'school', 'Iso2 Demo '.uniqid(), 'Bangladesh');

        $this->assertSame($this->bdId(), (int) $inst->country_id);
    }

    public function test_demo_command_links_canonical_row_after_rename(): void
    {
        $this->renameBd('Bangla (Renamed)');

        $command = new DemoBusinessSeederCommand;
        $method = new \ReflectionMethod($command, 'createInstitute');
        $method->setAccessible(true);

        $inst = $method->invoke($command, 'education', 'school', 'Iso2 Demo '.uniqid(), 'Bangla (Renamed)');

        // Resolved by name, then converged onto the canonical BD row.
        $this->assertSame($this->bdId(), (int) $inst->country_id);
    }
}
