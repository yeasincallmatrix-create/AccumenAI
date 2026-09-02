<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Country;
use App\Models\GradeScale;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\AcademicGradingService;
use App\Services\AcademicSetupService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AcademicSetupServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function country(string $iso2 = 'BD', string $name = 'Bangladesh'): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => $iso2],
            ['name' => $name, 'iso3' => strtoupper($iso2).'G', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(?Country $country = null, string $industry = 'education', ?string $sub = 'school'): Institute
    {
        $c = $country ?? $this->country();
        return Institute::create([
            'name' => 'AcadSetup '.uniqid(),
            'slug' => 'acad-setup-'.uniqid(),
            'country' => $c->name,
            'country_id' => $c->id,
            'industry' => $industry,
            'sub_industry' => $sub,
            'status' => 'active',
        ]);
    }

    private function owner(Institute $institute, string $roleSlug = 'institute-owner'): InstituteUser
    {
        $role = Role::where('slug', $roleSlug)->first();
        if (! $role) {
            $role = Role::withoutGlobalScopes()->where('slug', $roleSlug)->first();
        }
        if (! $role) {
            // Fallback for fresh test DB where roles not seeded yet: ensure they exist
            $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => ucwords(str_replace('-', ' ', $roleSlug)), 'is_system' => true]);
        }
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => $role->id,
            'first_name' => 'Owner',
            'last_name' => uniqid(),
            'email' => 'owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    public function test_new_institute_receives_academic_year(): void
    {
        $inst = $this->institute();
        $result = app(AcademicSetupService::class)->ensureDefaults($inst);

        $this->assertTrue($result['academic_year']['created']);
        $this->assertNotNull($result['academic_year']['id']);

        $year = AcademicYear::withoutGlobalScope('institute')->where('institute_id', $inst->id)->where('is_current', true)->first();
        $this->assertNotNull($year);
        $expectedYear = (int) now()->format('Y');
        $this->assertSame((string) $expectedYear, $year->code);
        $this->assertTrue((bool) $year->is_current);
    }

    public function test_academic_year_provisioning_is_idempotent(): void
    {
        $inst = $this->institute();
        $a = app(AcademicSetupService::class)->ensureDefaults($inst);
        $b = app(AcademicSetupService::class)->ensureDefaults($inst);

        $this->assertTrue($a['academic_year']['created']);
        $this->assertFalse($b['academic_year']['created']);
        $this->assertSame($a['academic_year']['id'], $b['academic_year']['id']);
        $this->assertSame(1, AcademicYear::withoutGlobalScope('institute')->where('institute_id', $inst->id)->where('is_current', true)->count());
    }

    public function test_grade_scale_resolvable_for_fresh_institute(): void
    {
        $inst = $this->institute();
        app(AcademicSetupService::class)->ensureDefaults($inst);

        $resolved = app(AcademicGradingService::class)->resolveScale($inst, $inst->country_id ?? 1);
        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->status);
        $this->assertGreaterThanOrEqual(1, $resolved->rows()->count());
    }

    public function test_existing_academic_year_not_duplicated(): void
    {
        $inst = $this->institute();
        $year = AcademicYear::withoutGlobalScope('institute')->create([
            'institute_id' => $inst->id,
            'name' => 'Custom Year',
            'code' => '2099',
            'is_current' => true,
            'status' => true,
            'start_date' => '2099-01-01',
            'end_date' => '2099-12-31',
        ]);

        $result = app(AcademicSetupService::class)->ensureDefaults($inst);
        $this->assertFalse($result['academic_year']['created']);
        $this->assertSame($year->id, $result['academic_year']['id']);
        $this->assertSame(1, AcademicYear::withoutGlobalScope('institute')->where('institute_id', $inst->id)->where('is_current', true)->count());
    }

    public function test_existing_grade_scale_not_overwritten(): void
    {
        // Ensure clean global scope for this test (isolate from previous tests that may have left a singleton)
        GradeScale::query()->whereNull('institute_id')->whereNull('country_id')->whereNull('education_system_id')->whereNull('academic_level_id')->delete();
        // Seed a global scale manually then ensureDefaults should not create second
        $scale = GradeScale::create([
            'institute_id' => null,
            'country_id' => null,
            'education_system_id' => null,
            'academic_level_id' => null,
            'name' => 'Existing Global',
            'gpa_mode' => 'equal_weight',
            'optional_subject_gpa' => 'included',
            'display_order' => 99,
            'status' => true,
        ]);
        // Need at least one row for resolution but not required for existence check
        $inst = $this->institute();
        $result = app(AcademicSetupService::class)->ensureDefaults($inst);

        $this->assertFalse($result['grade_scale']['created']);
        $this->assertSame($scale->id, $result['grade_scale']['id']);
        $this->assertSame(1, GradeScale::query()->whereNull('institute_id')->whereNull('country_id')->whereNull('education_system_id')->whereNull('academic_level_id')->count());
    }

    public function test_promotions_tab_visible_to_authorized_user(): void
    {
        $country = $this->country();
        $inst = $this->institute($country);
        $owner = $this->owner($inst, 'institute-owner');
        // Owner has promotion.manage via institute-owner role seeded in migrations
        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->get(route('settings.academic.promotions.index'))
            ->assertOk();
    }

    public function test_promotions_inaccessible_to_unauthorized_user(): void
    {
        $country = $this->country();
        $inst = $this->institute($country);
        $teacher = $this->owner($inst, 'teacher');
        TenantContext::set($inst->id);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.promotions.index'))
            ->assertStatus(403);

        // Also blocked at Academic Settings hub level (education.manage required)
        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.index'))
            ->assertStatus(403);
    }

    public function test_learning_structure_settings_reachable(): void
    {
        $this->seed(\Database\Seeders\LearningStructureSeeder::class);
        $inst = $this->institute();
        $owner = $this->owner($inst);
        // Assign template like InstituteCreation does
        $resolved = app(\App\Services\LearningStructureResolver::class)->resolveTemplate($inst);
        $tpl = $resolved['template'];
        if ($tpl) {
            \App\Models\InstituteSetting::withoutGlobalScope('institute')->updateOrCreate(
                ['institute_id' => $inst->id],
                ['structure_template_id' => $tpl->id]
            );
        }

        $this->actingAs($owner, 'institute_user')
            ->get(route('academic.structure.settings'))
            ->assertOk();
    }

    public function test_cross_tenant_grade_scale_not_leaked(): void
    {
        $instA = $this->institute();
        $instB = $this->institute();

        $scale = GradeScale::create([
            'institute_id' => $instA->id,
            'country_id' => null,
            'education_system_id' => null,
            'academic_level_id' => null,
            'name' => 'A Override '.uniqid(),
            'gpa_mode' => 'equal_weight',
            'optional_subject_gpa' => 'included',
            'display_order' => 0,
            'status' => true,
        ]);
        foreach (AcademicSetupService::DEFAULT_BANDS as $band) {
            \App\Models\GradeScaleRow::create(array_merge(['grade_scale_id' => $scale->id, 'status' => true], $band));
        }

        // B should NOT resolve A's institute override
        $resolvedForB = app(AcademicGradingService::class)->resolveScale($instB, $instB->country_id ?? 1);
        if ($resolvedForB !== null) {
            $this->assertNotSame($scale->id, $resolvedForB->id);
        }

        // A should resolve its own override when queried with level null? Actually override at institute level without academic_level_id is whole-institute
        $resolvedForA = app(AcademicGradingService::class)->resolveScale($instA, $instA->country_id ?? 1);
        $this->assertNotNull($resolvedForA);
        // Institute override (rank 2) beats global (rank 6), so A gets its override
        $this->assertSame($scale->id, $resolvedForA->id);
    }

    public function test_non_education_institute_gets_no_academic_year(): void
    {
        $inst = $this->institute(null, 'healthcare', null);
        $result = app(AcademicSetupService::class)->ensureDefaults($inst);
        $this->assertFalse($result['academic_year']['created']);
        $this->assertNull($result['academic_year']['id']);
        $this->assertSame(0, AcademicYear::withoutGlobalScope('institute')->where('institute_id', $inst->id)->count());
    }
}
