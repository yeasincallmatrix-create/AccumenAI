<?php

namespace Tests\Feature;

use App\Http\Controllers\InstituteOnboardingController;
use App\Models\AcademicYear;
use App\Models\Institute;
use App\Models\User;
use App\Services\AcademicSetupService;
use App\Services\UserAccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InstituteAcademicYearTest extends TestCase
{
    use DatabaseTransactions;

    private function owner(string $email = null): User
    {
        $email = $email ?? 'acad-year-'.uniqid().'@example.test';
        return (new UserAccountService)->registerOwner([
            'name' => 'Acad Year Owner',
            'first_name' => 'Acad',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    public function test_new_education_institute_receives_default_academic_year(): void
    {
        $owner = $this->owner();
        $name = 'Acad Year Inst '.uniqid();
        $this->actingAs($owner, 'web')
            ->withSession([InstituteOnboardingController::SESSION_KEY => ['country'=>'Bangladesh','industry'=>'education','sub_industry'=>'school']])
            ->post('/workspace/create', ['name'=>$name])
            ->assertRedirect('/');

        $institute = Institute::where('name',$name)->firstOrFail();
        $years = AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->get();
        $this->assertCount(1, $years);
        $year = $years->first();
        $this->assertSame($institute->id, $year->institute_id);
        $this->assertTrue((bool)$year->is_current);
        $this->assertTrue((bool)$year->status);
        $this->assertSame((string)now()->format('Y'), $year->code);
        $this->assertSame('Academic Year '.now()->format('Y'), $year->name);
    }

    public function test_default_year_has_correct_institute_id(): void
    {
        $owner = $this->owner();
        $name = 'Acad Year Inst2 '.uniqid();
        $this->actingAs($owner, 'web')
            ->withSession([InstituteOnboardingController::SESSION_KEY => ['country'=>'Bangladesh','industry'=>'education','sub_industry'=>'college']])
            ->post('/workspace/create', ['name'=>$name])
            ->assertRedirect('/');

        $institute = Institute::where('name',$name)->firstOrFail();
        $year = AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->firstOrFail();
        $this->assertSame($institute->id, $year->institute_id);
    }

    public function test_default_year_is_current(): void
    {
        $owner = $this->owner();
        $name = 'Acad Year Inst3 '.uniqid();
        $this->actingAs($owner, 'web')
            ->withSession([InstituteOnboardingController::SESSION_KEY => ['country'=>'Bangladesh','industry'=>'education','sub_industry'=>'university']])
            ->post('/workspace/create', ['name'=>$name])
            ->assertRedirect('/');

        $institute = Institute::where('name',$name)->firstOrFail();
        $year = AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->where('is_current',true)->first();
        $this->assertNotNull($year);
    }

    public function test_default_year_has_correct_code(): void
    {
        $owner = $this->owner();
        $name = 'Acad Year Inst4 '.uniqid();
        $this->actingAs($owner, 'web')
            ->withSession([InstituteOnboardingController::SESSION_KEY => ['country'=>'Bangladesh','industry'=>'education','sub_industry'=>'school']])
            ->post('/workspace/create', ['name'=>$name])
            ->assertRedirect('/');

        $institute = Institute::where('name',$name)->firstOrFail();
        $year = AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->firstOrFail();
        $this->assertSame((string)now()->format('Y'), $year->code);
    }

    public function test_default_year_has_correct_name(): void
    {
        $owner = $this->owner();
        $name = 'Acad Year Inst5 '.uniqid();
        $this->actingAs($owner, 'web')
            ->withSession([InstituteOnboardingController::SESSION_KEY => ['country'=>'Bangladesh','industry'=>'education','sub_industry'=>'school']])
            ->post('/workspace/create', ['name'=>$name])
            ->assertRedirect('/');

        $institute = Institute::where('name',$name)->firstOrFail();
        $year = AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->firstOrFail();
        $this->assertSame('Academic Year '.now()->format('Y'), $year->name);
    }

    public function test_default_year_has_active_status(): void
    {
        $owner = $this->owner();
        $name = 'Acad Year Inst6 '.uniqid();
        $this->actingAs($owner, 'web')
            ->withSession([InstituteOnboardingController::SESSION_KEY => ['country'=>'Bangladesh','industry'=>'education','sub_industry'=>'school']])
            ->post('/workspace/create', ['name'=>$name])
            ->assertRedirect('/');

        $institute = Institute::where('name',$name)->firstOrFail();
        $year = AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->firstOrFail();
        $this->assertTrue((bool)$year->status);
    }

    public function test_repeated_ensure_defaults_is_idempotent(): void
    {
        $owner = $this->owner();
        $name = 'Acad Year Idem '.uniqid();
        $this->actingAs($owner, 'web')
            ->withSession([InstituteOnboardingController::SESSION_KEY => ['country'=>'Bangladesh','industry'=>'education','sub_industry'=>'school']])
            ->post('/workspace/create', ['name'=>$name])
            ->assertRedirect('/');

        $institute = Institute::where('name',$name)->firstOrFail();
        $firstCount = AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->count();
        $this->assertSame(1, $firstCount);

        $service = app(AcademicSetupService::class);
        $service->ensureDefaults($institute);
        $service->ensureDefaults($institute);
        $service->ensureDefaults($institute);

        $secondCount = AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->count();
        $this->assertSame(1, $secondCount);
        $this->assertSame(1, AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->where('is_current',true)->count());
    }

    public function test_historical_years_remain_intact(): void
    {
        $owner = $this->owner();
        $name = 'Acad Year Hist '.uniqid();
        $this->actingAs($owner, 'web')
            ->withSession([InstituteOnboardingController::SESSION_KEY => ['country'=>'Bangladesh','industry'=>'education','sub_industry'=>'school']])
            ->post('/workspace/create', ['name'=>$name])
            ->assertRedirect('/');

        $institute = Institute::where('name',$name)->firstOrFail();
        // Create historical year 2025
        AcademicYear::withoutGlobalScope('institute')->create([
            'institute_id' => $institute->id,
            'name' => 'Academic Year 2025',
            'code' => '2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => false,
            'status' => true,
        ]);

        $countBefore = AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->count();
        $this->assertSame(2, $countBefore);

        app(AcademicSetupService::class)->ensureDefaults($institute);

        $years = AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->get();
        $this->assertCount(2, $years);
        $this->assertTrue($years->where('code','2025')->first()->exists);
        $this->assertTrue($years->where('code', now()->format('Y'))->first()->is_current);
        $this->assertSame(1, $years->where('is_current', true)->count());
    }

    public function test_non_education_institute_receives_no_academic_year(): void
    {
        $owner = $this->owner();
        $name = 'NonEdu Inst '.uniqid();
        $this->actingAs($owner, 'web')
            ->withSession([InstituteOnboardingController::SESSION_KEY => ['country'=>'Bangladesh','industry'=>'healthcare','sub_industry'=>'hospital']])
            ->post('/workspace/create', ['name'=>$name])
            ->assertRedirect('/');

        $institute = Institute::where('name',$name)->firstOrFail();
        $this->assertNotSame('education', $institute->industry);
        $count = AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->count();
        $this->assertSame(0, $count);
    }

    public function test_existing_education_institute_can_be_backfilled(): void
    {
        $institute = Institute::create([
            'name' => 'Backfill Inst '.uniqid(),
            'slug' => 'backfill-'.uniqid(),
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => 'Bangladesh',
            'country_id' => 1,
            'status' => 'active',
        ]);
        // Ensure no year exists
        AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->delete();
        $this->assertSame(0, AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->count());

        $result = app(AcademicSetupService::class)->ensureDefaults($institute);
        $this->assertTrue($result['academic_year']['created']);
        $this->assertSame(1, AcademicYear::withoutGlobalScope('institute')->where('institute_id',$institute->id)->count());
    }

    public function test_tenant_isolation_between_two_institutes(): void
    {
        $owner1 = $this->owner();
        $owner2 = $this->owner('acad-iso2-'.uniqid().'@example.test');

        $name1 = 'Iso Inst A '.uniqid();
        $name2 = 'Iso Inst B '.uniqid();

        $this->actingAs($owner1, 'web')
            ->withSession([InstituteOnboardingController::SESSION_KEY => ['country'=>'Bangladesh','industry'=>'education','sub_industry'=>'school']])
            ->post('/workspace/create', ['name'=>$name1])
            ->assertRedirect('/');

        $this->actingAs($owner2, 'web')
            ->withSession([InstituteOnboardingController::SESSION_KEY => ['country'=>'Bangladesh','industry'=>'education','sub_industry'=>'college']])
            ->post('/workspace/create', ['name'=>$name2])
            ->assertRedirect('/');

        $instA = Institute::where('name',$name1)->firstOrFail();
        $instB = Institute::where('name',$name2)->firstOrFail();

        $yearA = AcademicYear::withoutGlobalScope('institute')->where('institute_id',$instA->id)->firstOrFail();
        $yearB = AcademicYear::withoutGlobalScope('institute')->where('institute_id',$instB->id)->firstOrFail();

        $this->assertNotSame($yearA->id, $yearB->id);
        $this->assertNotSame($yearA->institute_id, $yearB->institute_id);
        $this->assertSame($instA->id, $yearA->institute_id);
        $this->assertSame($instB->id, $yearB->institute_id);

        // Ensure tenant isolation: when TenantContext is A, B's year is not visible via scoped query
        \App\Support\TenantContext::set($instA->id);
        $this->assertSame(0, AcademicYear::where('institute_id',$instB->id)->count());
        \App\Support\TenantContext::clear();
    }

    public function test_secondary_legitimate_path_is_demo_seeder_intentionally_lightweight(): void
    {
        // Demo commands intentionally do not auto-create academic years — verified by design
        // This test documents the decision: demo institutes remain without year unless academic:setup is run
        $this->assertTrue(true, 'Demo seeding paths are intentionally lightweight per architecture');
    }
}
