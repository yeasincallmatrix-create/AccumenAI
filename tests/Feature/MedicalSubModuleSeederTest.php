<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleRegistry;
use Database\Seeders\MedicalSubModuleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * SEC-03: MedicalSubModuleSeeder must be idempotent and must not
 * mass-grant medical.* sub-modules to institutes. The per-institute
 * grant loop was removed entirely; only module_registry rows (and the
 * pre-existing advanced/premium package grants) are seeded.
 */
class MedicalSubModuleSeederTest extends TestCase
{
    use DatabaseTransactions;

    private const SUB_KEYS = [
        'medical.opd',
        'medical.ipd',
        'medical.pharmacy',
        'medical.laboratory',
        'medical.billing',
        'medical.emergency',
        'medical.radiology',
        'medical.bloodbank',
        'medical.physiotherapy',
        'medical.dental',
        'medical.vaccination',
        'medical.records',
        'medical.diet',
        'medical.ambulance',
    ];

    private function runSeeder(): void
    {
        (new MedicalSubModuleSeeder())->run();
    }

    private function freshInstitute(): Institute
    {
        return Institute::create([
            'name' => 'SEC03 '.uniqid(),
            'slug' => 'sec03-'.uniqid(),
            'status' => 'active',
            'package_id' => null,
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => 'Bangladesh',
        ]);
    }

    private function subOverrideCount(int $instituteId): int
    {
        return InstituteModuleOverride::where('institute_id', $instituteId)
            ->where('module_key', 'like', 'medical.%')
            ->count();
    }

    public function test_double_run_creates_no_duplicate_registry_rows(): void
    {
        $this->runSeeder();
        $firstCount = ModuleRegistry::where('parent_key', 'medical')->count();

        $this->runSeeder();
        $secondCount = ModuleRegistry::where('parent_key', 'medical')->count();

        $this->assertSame($firstCount, $secondCount);
        foreach (self::SUB_KEYS as $key) {
            $this->assertDatabaseHas('module_registry', [
                'key' => $key,
                'parent_key' => 'medical',
            ]);
        }
    }

    public function test_run_creates_no_sub_module_overrides_for_institutes(): void
    {
        // Force the exact trigger state of the old blast loop: no institute
        // holds a medical override/entitlement (the old code then fell back
        // to granting all 14 sub-modules to EVERY institute). Deleted rows
        // are rolled back with the test transaction.
        \Illuminate\Support\Facades\DB::table('institute_module_overrides')
            ->where('module_key', 'medical')
            ->delete();
        \Illuminate\Support\Facades\DB::table('institute_module_entitlements')
            ->where('module_key', 'medical')
            ->delete();

        // Institutes exist (seeded thousands + our fresh one).
        $institute = $this->freshInstitute();
        $this->assertSame(0, $this->subOverrideCount($institute->id));

        $this->runSeeder();

        $this->assertSame(0, $this->subOverrideCount($institute->id));
        foreach (self::SUB_KEYS as $key) {
            $this->assertDatabaseMissing('institute_module_overrides', [
                'institute_id' => $institute->id,
                'module_key' => $key,
            ]);
        }
    }

    public function test_run_leaves_existing_overrides_untouched(): void
    {
        $institute = $this->freshInstitute();
        // Put this institute in the old trigger set (medical override on;
        // syncIndustryModule already created it as disabled on insert).
        InstituteModuleOverride::updateOrCreate(
            ['institute_id' => $institute->id, 'module_key' => 'medical'],
            ['enabled' => true]
        );
        // ... and give it a pre-existing deny that must survive the run.
        InstituteModuleOverride::create([
            'institute_id' => $institute->id,
            'module_key' => 'medical.opd',
            'enabled' => false,
        ]);

        $this->runSeeder();
        $this->runSeeder();

        $this->assertSame(1, InstituteModuleOverride::where('institute_id', $institute->id)
            ->where('module_key', 'medical.opd')
            ->count());
        $this->assertDatabaseHas('institute_module_overrides', [
            'institute_id' => $institute->id,
            'module_key' => 'medical.opd',
            'enabled' => false,
        ]);
        $this->assertSame(1, $this->subOverrideCount($institute->id));
    }
}
