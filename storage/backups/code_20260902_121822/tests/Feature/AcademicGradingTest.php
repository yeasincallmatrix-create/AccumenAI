<?php

namespace Tests\Feature;

use App\Models\AcademicAssessment;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationItem;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicSelectionGroup;
use App\Models\AcademicStudentMark;
use App\Models\AcademicYear;
use App\Models\AssessmentSubject;
use App\Models\AssessmentSubjectComponent;
use App\Models\AssessmentType;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Component;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\GradeScale;
use App\Models\GradeScaleRow;
use App\Models\Institute;
use App\Models\InstituteSubject;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Services\AcademicFinalResultService;
use App\Services\AcademicGradingService;
use App\Support\TenantContext;
use Database\Seeders\AcademicAssessmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AcademicGradingTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAssessmentSeeder::class);
    }

    // ------------------------------------------------------------- Scaffolding

    private function country(string $iso2 = 'BD', string $name = 'Bangladesh'): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => $iso2],
            ['name' => $name, 'iso3' => strtoupper($iso2).'G', 'phone_code' => '880', 'status' => true]
        );
    }

    private function system(Country $country, string $code = 'general'): EducationSystem
    {
        return EducationSystem::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $country->id, 'code' => $code],
            ['name' => 'General Education', 'display_order' => 0, 'status' => true]
        );
    }

    private function level(EducationSystem $system, string $code = 'secondary'): AcademicLevel
    {
        return AcademicLevel::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $system->country_id, 'education_system_id' => $system->id, 'code' => $code],
            ['name' => 'Secondary', 'display_order' => 1, 'status' => true]
        );
    }

    private function classGrade(AcademicLevel $level, string $code = 'c8'): ClassGrade
    {
        return ClassGrade::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $level->country_id, 'education_system_id' => $level->education_system_id, 'academic_level_id' => $level->id, 'code' => $code],
            ['name' => 'Class 8', 'display_order' => 0, 'status' => true]
        );
    }

    private function institute(Country $country, string $name = 'Grading Inst'): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name = 'Main Branch'): Branch
    {
        return Branch::create(['institute_id' => $institute->id, 'name' => $name, 'status' => 'active']);
    }

    private function user(Institute $institute, string $roleSlug, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => 'Staff',
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function admin(string $prefix = 'grading-admin'): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'email' => $prefix.'-'.uniqid().'@platform.test',
            'first_name' => 'Platform',
            'last_name' => 'Admin',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    /** Bangladesh A+..F scale (A+ 80–100 inclusive, A 70–79.99, ... F 0–39). */
    private function bdRows(): array
    {
        return [
            ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'A', 'min_score' => 70, 'max_score' => 79.99, 'grade_point' => 4.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'B', 'min_score' => 60, 'max_score' => 69.99, 'grade_point' => 3.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'C', 'min_score' => 50, 'max_score' => 59.99, 'grade_point' => 2.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'E', 'min_score' => 40, 'max_score' => 49.99, 'grade_point' => 1.0, 'is_pass' => false, 'gpa_included' => false],
            ['grade' => 'F', 'min_score' => 0, 'max_score' => 39.99, 'grade_point' => 0.0, 'is_pass' => false, 'gpa_included' => false],
        ];
    }

    private function scale(array $scope, string $name = 'BD Scale', ?array $rows = null, array $flags = []): GradeScale
    {
        $data = array_merge([
            'name' => $name,
            'gpa_mode' => 'equal_weight',
            'optional_subject_gpa' => 'included',
            'status' => true,
        ], $scope, $flags);

        return app(AcademicGradingService::class)->store($data, $rows ?? $this->bdRows());
    }

    private function globalScale(string $name = 'Global Scale', array $rows = []): GradeScale
    {
        return $this->scale([], $name, $rows === [] ? $this->bdRows() : $rows);
    }

    // ------------------------------------------------------------- Scale CRUD + validation

    public function test_global_scale_can_be_created_with_bands(): void
    {
        TenantContext::clear();

        $this->actingAs($this->admin(), 'platform_admin')
            ->post(route('admin.academic.grading.store'), [
                'name' => 'Global 0-100',
                'gpa_mode' => 'equal_weight',
                'optional_subject_gpa' => 'included',
                'rows' => [
                    ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => '1', 'gpa_included' => '1', 'active' => '1'],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 79.99, 'grade_point' => 0.0, 'is_pass' => '0', 'gpa_included' => '0', 'active' => '1'],
                ],
            ])
            ->assertRedirect(route('admin.academic.grading.index'));

        $scale = GradeScale::query()->where('name', 'Global 0-100')->firstOrFail();
        $this->assertNull($scale->institute_id);
        $this->assertNull($scale->country_id);
        $this->assertSame(2, $scale->rows()->count());
        $this->assertSame(5.0, $scale->rows()->firstWhere('grade', 'A+')->grade_point);
    }

    public function test_overlapping_bands_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(AcademicGradingService::class)->validateRows([
            ['grade' => 'A', 'min_score' => 70, 'max_score' => 100, 'grade_point' => 5.0],
            ['grade' => 'B', 'min_score' => 60, 'max_score' => 90, 'grade_point' => 4.0],
        ]);
    }

    public function test_min_greater_than_max_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(AcademicGradingService::class)->validateRows([
            ['grade' => 'X', 'min_score' => 90, 'max_score' => 50, 'grade_point' => 1.0],
        ]);
    }

    public function test_negative_grade_point_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(AcademicGradingService::class)->validateRows([
            ['grade' => 'X', 'min_score' => 0, 'max_score' => 100, 'grade_point' => -1],
        ]);
    }

    public function test_out_of_range_bounds_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(AcademicGradingService::class)->validateRows([
            ['grade' => 'X', 'min_score' => 0, 'max_score' => 150, 'grade_point' => 1.0],
        ]);
    }

    public function test_empty_rows_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(AcademicGradingService::class)->validateRows([]);
    }

    public function test_admin_cannot_edit_or_delete_institute_override(): void
    {
        TenantContext::clear();
        $c = $this->curriculum();
        $override = $this->scale(['institute_id' => $c['institute']->id, 'academic_level_id' => $c['level']->id]);

        $this->actingAs($this->admin(), 'platform_admin')
            ->get(route('admin.academic.grading.edit', $override))
            ->assertNotFound();

        $this->actingAs($this->admin(), 'platform_admin')
            ->delete(route('admin.academic.grading.destroy', $override))
            ->assertNotFound();
    }

    public function test_scope_chain_is_enforced(): void
    {
        TenantContext::clear();
        $c = $this->curriculum();
        $this->actingAs($this->admin(), 'platform_admin');

        // level without system → rejected
        $this->post(route('admin.academic.grading.store'), [
            'name' => 'Bad',
            'gpa_mode' => 'equal_weight',
            'optional_subject_gpa' => 'included',
            'education_system_id' => null,
            'academic_level_id' => $c['level']->id,
            'country_id' => null,
            'rows' => [['grade' => 'A', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => '1', 'gpa_included' => '1']],
        ])->assertSessionHasNoErrors();

        $this->assertCount(0, GradeScale::query()->where('name', 'Bad')->get());
    }

    // ------------------------------------------------------------- Resolution ladder

    public function test_global_default_is_used_when_nothing_more_specific_exists(): void
    {
        TenantContext::clear();
        $this->globalScale('Global Fallback');

        $c = $this->curriculum();

        $resolved = app(AcademicGradingService::class)->resolveScaleForClass($c['institute'], $c['class_grade']);

        $this->assertNotNull($resolved);
        $this->assertSame('Global Fallback', $resolved->name);
    }

    public function test_country_default_beats_global_default(): void
    {
        TenantContext::clear();
        $this->globalScale('Global Fallback');
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Country Scale');

        $resolved = app(AcademicGradingService::class)->resolveScaleForClass($c['institute'], $c['class_grade']);

        $this->assertSame('BD Country Scale', $resolved->name);
    }

    public function test_system_default_beats_country_default(): void
    {
        TenantContext::clear();
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Country Scale');
        $this->scale(['country_id' => $c['country']->id, 'education_system_id' => $c['system']->id], 'BD System Scale');

        $resolved = app(AcademicGradingService::class)->resolveScaleForClass($c['institute'], $c['class_grade']);

        $this->assertSame('BD System Scale', $resolved->name);
    }

    public function test_level_default_beats_system_default(): void
    {
        TenantContext::clear();
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id, 'education_system_id' => $c['system']->id], 'BD System Scale');
        $this->scale(['country_id' => $c['country']->id, 'education_system_id' => $c['system']->id, 'academic_level_id' => $c['level']->id], 'BD Level Scale');

        $resolved = app(AcademicGradingService::class)->resolveScaleForClass($c['institute'], $c['class_grade']);

        $this->assertSame('BD Level Scale', $resolved->name);
    }

    public function test_institute_override_beats_all_defaults(): void
    {
        TenantContext::clear();
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id, 'education_system_id' => $c['system']->id, 'academic_level_id' => $c['level']->id], 'BD Level Scale');
        $this->scale(['institute_id' => $c['institute']->id, 'academic_level_id' => $c['level']->id], 'Inst Override');

        $resolved = app(AcademicGradingService::class)->resolveScaleForClass($c['institute'], $c['class_grade']);

        $this->assertSame('Inst Override', $resolved->name);
    }

    public function test_institute_override_does_not_leak_to_another_institute(): void
    {
        TenantContext::clear();
        $c = $this->curriculum();
        $otherInst = $this->institute($this->country('IN', 'India'), 'Other Grad Inst');
        $this->globalScale('Global Fallback');
        $this->scale(['institute_id' => $c['institute']->id, 'academic_level_id' => $c['level']->id], 'Inst Override');

        $resolved = app(AcademicGradingService::class)->resolveScaleForClass($otherInst, $c['class_grade']);

        $this->assertNotSame('Inst Override', $resolved->name);
        $this->assertSame('Global Fallback', $resolved->name);
    }

    public function test_inactive_scale_is_skipped(): void
    {
        TenantContext::clear();
        $c = $this->curriculum();
        $this->globalScale('Global Fallback');
        $this->scale(['country_id' => $c['country']->id], 'Inactive BD', null, ['status' => false]);

        $resolved = app(AcademicGradingService::class)->resolveScaleForClass($c['institute'], $c['class_grade']);

        $this->assertSame('Global Fallback', $resolved->name);
    }

    // ------------------------------------------------------------- Band boundaries

    public function test_inclusive_boundary_80_resolves_to_80_band(): void
    {
        TenantContext::clear();
        $scale = $this->globalScale();
        $service = app(AcademicGradingService::class);

        $this->assertSame('A+', $service->bandForScore($scale, 80.00)->grade);
        $this->assertSame('A', $service->bandForScore($scale, 79.99)->grade);
        $this->assertSame('A+', $service->bandForScore($scale, 100)->grade);
        $this->assertSame('F', $service->bandForScore($scale, 0)->grade);
        $this->assertSame('F', $service->bandForScore($scale, 39.99)->grade);
        $this->assertSame('E', $service->bandForScore($scale, 40.00)->grade);
    }

    public function test_score_in_a_gap_has_no_band(): void
    {
        TenantContext::clear();
        $scale = $this->globalScale('Gap Scale', [
            ['grade' => 'Pass', 'min_score' => 50, 'max_score' => 100, 'grade_point' => 3.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'Fail', 'min_score' => 0, 'max_score' => 39.99, 'grade_point' => 0.0, 'is_pass' => false, 'gpa_included' => false],
        ]);

        $this->assertNull(app(AcademicGradingService::class)->bandForScore($scale, 45));
    }

    public function test_inactive_band_is_ignored(): void
    {
        TenantContext::clear();
        $scale = app(AcademicGradingService::class)->store(['name' => 'Inactive Band Scale'], [
            ['grade' => 'Pass', 'min_score' => 0, 'max_score' => 100, 'grade_point' => 3.0, 'is_pass' => true, 'gpa_included' => true, 'status' => true],
        ]);
        $scale->rows()->first()->update(['status' => false]);

        $this->assertNull(app(AcademicGradingService::class)->bandForScore($scale->refresh(), 80));
    }

    // ------------------------------------------------------------- Subject level grading

    public function test_aggregate_is_graded_and_pass_fail_derived(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id, 'education_system_id' => $c['system']->id, 'academic_level_id' => $c['level']->id], 'BD Scale');

        $mid = $this->assessment($c, 'Mid', [[
            'subject_id' => $c['subjects']['math']->id,
            'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]],
        ]]);
        $final = $this->assessment($c, 'Final', [[
            'subject_id' => $c['subjects']['math']->id,
            'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 200, 'pass_mark' => 66]],
        ]]);
        $scheme = $this->schemeFor($c, [$mid->id => 40, $final->id => 60]);

        $ramim = $this->student($c['institute'], 'Ramim');
        $placement = $this->placement($c, $ramim, [$c['subjects']['math']->id]);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 72);
        $this->mark($placement, $this->mathConfig($final, $c['subjects']['math']->id), $this->writtenComponent(), 160);

        TenantContext::set($c['institute']->id);

        $result = app(AcademicFinalResultService::class)->subjectResult($scheme, $placement, $c['subjects']['math']->id);

        $this->assertSame(AcademicFinalResultService::SUBJECT_RESULT_GRADED, $result['status']);
        $this->assertEqualsWithDelta(76.8, $result['aggregate'], 0.01);
        $this->assertSame('A', $result['grade']);
        $this->assertSame(4.0, $result['grade_point']);
        $this->assertSame(AcademicFinalResultService::SUBJECT_STATUS_PASS, $result['subject_status']);
        $this->assertTrue($result['gpa']['included']);
    }

    public function test_zero_aggregate_is_graded_via_zero_band_not_fabricated(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id, 'education_system_id' => $c['system']->id, 'academic_level_id' => $c['level']->id], 'BD Scale');

        $mid = $this->assessment($c, 'Mid', [[
            'subject_id' => $c['subjects']['math']->id,
            'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]],
        ]]);
        $scheme = $this->schemeFor($c, [$mid->id => 100]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 0);

        TenantContext::set($c['institute']->id);

        $result = app(AcademicFinalResultService::class)->subjectResult($scheme, $placement, $c['subjects']['math']->id);

        $this->assertSame(AcademicFinalResultService::SUBJECT_RESULT_GRADED, $result['status']);
        $this->assertSame('F', $result['grade']);
        $this->assertSame(AcademicFinalResultService::SUBJECT_STATUS_FAIL, $result['subject_status']);
        $this->assertSame(0.0, $result['aggregate']);
    }

    public function test_not_entered_state_carries_through_without_grade(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale');

        $mid = $this->assessment($c, 'Mid', [[
            'subject_id' => $c['subjects']['math']->id,
            'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]],
        ]]);
        $scheme = $this->schemeFor($c, [$mid->id => 100]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);

        TenantContext::set($c['institute']->id);

        $result = app(AcademicFinalResultService::class)->subjectResult($scheme, $placement, $c['subjects']['math']->id);

        $this->assertSame(AcademicFinalResultService::SUBJECT_RESULT_INCOMPLETE, $result['status']);
        $this->assertNull($result['grade']);
        $this->assertNull($result['subject_status']);
        $this->assertNotNull($result['incomplete_reason']);
    }

    public function test_absent_only_carries_through_without_grade(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale');

        $mid = $this->assessment($c, 'Mid', [[
            'subject_id' => $c['subjects']['math']->id,
            'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]],
        ]]);
        $scheme = $this->schemeFor($c, [$mid->id => 100]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 0, 'absent');

        TenantContext::set($c['institute']->id);

        $result = app(AcademicFinalResultService::class)->subjectResult($scheme, $placement, $c['subjects']['math']->id);

        $this->assertSame(AcademicFinalResultService::SUBJECT_RESULT_ABSENT_ONLY, $result['status']);
        $this->assertNull($result['grade']);
        $this->assertNull($result['subject_status']);
    }

    public function test_unselected_optional_subject_is_not_eligible_and_no_grade(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale');

        $mid = $this->assessment($c, 'Mid', [
            ['subject_id' => $c['subjects']['bio']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
        ]);
        $scheme = $this->schemeFor($c, [$mid->id => 100]);

        $karim = $this->student($c['institute'], 'Karim');
        $placement = $this->placement($c, $karim, [$c['subjects']['hmath']->id]);

        TenantContext::set($c['institute']->id);

        $result = app(AcademicFinalResultService::class)->subjectResult($scheme, $placement, $c['subjects']['bio']->id);

        $this->assertSame(AcademicFinalResultService::SUBJECT_RESULT_NOT_ELIGIBLE, $result['status']);
        $this->assertNull($result['grade']);
        $this->assertFalse($result['gpa']['included']);
    }

    public function test_missing_scale_produces_no_grade(): void
    {
        GradeScale::query()->whereNull('institute_id')->whereNull('country_id')->whereNull('education_system_id')->whereNull('academic_level_id')->delete();
        $c = $this->curriculum(); // no scale configured anywhere

        $mid = $this->assessment($c, 'Mid', [[
            'subject_id' => $c['subjects']['math']->id,
            'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]],
        ]]);
        $scheme = $this->schemeFor($c, [$mid->id => 100]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 80);

        TenantContext::set($c['institute']->id);

        $result = app(AcademicFinalResultService::class)->subjectResult($scheme, $placement, $c['subjects']['math']->id);

        $this->assertSame(AcademicFinalResultService::SUBJECT_RESULT_NO_SCALE, $result['status']);
        $this->assertNull($result['grade']);
        $this->assertNull($result['subject_status']);
    }

    public function test_pass_and_fail_band_verdicts(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale');

        $a = $this->assessment($c, 'A', [[
            'subject_id' => $c['subjects']['math']->id,
            'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]],
        ]]);
        $scheme = $this->schemeFor($c, [$a->id => 100]);

        $passer = $this->placement($c, $this->student($c['institute'], 'Passer'), [$c['subjects']['math']->id]);
        $this->mark($passer, $this->mathConfig($a, $c['subjects']['math']->id), $this->writtenComponent(), 90);

        $failer = $this->placement($c, $this->student($c['institute'], 'Failer'), [$c['subjects']['math']->id]);
        $this->mark($failer, $this->mathConfig($a, $c['subjects']['math']->id), $this->writtenComponent(), 30);

        TenantContext::set($c['institute']->id);

        $service = app(AcademicFinalResultService::class);

        $passResult = $service->subjectResult($scheme, $passer, $c['subjects']['math']->id);
        $failResult = $service->subjectResult($scheme, $failer, $c['subjects']['math']->id);

        $this->assertSame(AcademicFinalResultService::SUBJECT_STATUS_PASS, $passResult['subject_status']);
        $this->assertSame(AcademicFinalResultService::SUBJECT_STATUS_FAIL, $failResult['subject_status']);
        $this->assertTrue($passResult['gpa']['included']);
        $this->assertFalse($failResult['gpa']['included']);
    }

    // ------------------------------------------------------------- GPA

    public function test_equal_weight_gpa(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale');

        $a = $this->assessment($c, 'A', [[
            'subject_id' => $c['subjects']['math']->id,
            'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]],
        ]]);
        $scheme = $this->schemeFor($c, [$a->id => 100]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id, $c['subjects']['english']->id]);
        // Math 90 → A+ (5.0). English 75 → A (4.0). Equal → 4.5
        $this->mark($placement, $this->mathConfig($a, $c['subjects']['math']->id), $this->writtenComponent(), 90);
        // English has no assessment in the scheme → it simply doesn't contribute.
        // (A single-assessment scheme only covers Mathematics; see note below.)

        TenantContext::set($c['institute']->id);

        $gpa = app(AcademicFinalResultService::class)->gpa($scheme, $placement);

        // Only Mathematics is covered by the scheme → single subject GPA == grade point.
        $this->assertSame('computed', $gpa['status']);
        $this->assertSame(5.0, $gpa['value']);
        $this->assertSame('equal_weight', $gpa['mode']);
    }

    public function test_equal_weight_gpa_averages_included_subjects(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale');

        $a = $this->assessment($c, 'A', [
            ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
            ['subject_id' => $c['subjects']['bangla']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
        ]);
        $scheme = $this->schemeFor($c, [$a->id => 100]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id, $c['subjects']['bangla']->id]);
        $this->mark($placement, $this->mathConfig($a, $c['subjects']['math']->id), $this->writtenComponent(), 90);    // 5.0
        $this->mark($placement, $this->mathConfig($a, $c['subjects']['bangla']->id), $this->writtenComponent(), 75);  // 4.0

        TenantContext::set($c['institute']->id);

        $gpa = app(AcademicFinalResultService::class)->gpa($scheme, $placement);

        $this->assertSame('computed', $gpa['status']);
        $this->assertSame(4.5, $gpa['value']);
        $this->assertCount(2, $gpa['subjects']);
    }

    public function test_credit_weighted_gpa_uses_credits(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale', null, ['gpa_mode' => 'credit_weighted']);

        $a = $this->assessment($c, 'A', [
            ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
            ['subject_id' => $c['subjects']['bangla']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
        ]);
        $scheme = $this->schemeFor($c, [$a->id => 100]);

        // Assign credits: Math = 2, Bangla = 1.
        SubjectAcademicAssignment::query()
            ->where('class_grade_id', $c['class_grade']->id)
            ->where('subject_id', $c['subjects']['math']->id)
            ->update(['credit_hours' => 2]);
        SubjectAcademicAssignment::query()
            ->where('class_grade_id', $c['class_grade']->id)
            ->where('subject_id', $c['subjects']['bangla']->id)
            ->update(['credit_hours' => 1]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id, $c['subjects']['bangla']->id]);
        $this->mark($placement, $this->mathConfig($a, $c['subjects']['math']->id), $this->writtenComponent(), 90);   // A+ 5.0 × 2
        $this->mark($placement, $this->mathConfig($a, $c['subjects']['bangla']->id), $this->writtenComponent(), 75); // A 4.0 × 1

        TenantContext::set($c['institute']->id);

        $gpa = app(AcademicFinalResultService::class)->gpa($scheme, $placement);

        // (5*2 + 4*1) / (2+1) = 14/3 = 4.67
        $this->assertSame('computed', $gpa['status']);
        $this->assertSame('credit_weighted', $gpa['mode']);
        $this->assertEqualsWithDelta(4.67, $gpa['value'], 0.01);
    }

    public function test_credit_weighted_gpa_never_invents_credits(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale', null, ['gpa_mode' => 'credit_weighted']);

        $a = $this->assessment($c, 'A', [
            ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
        ]);
        $scheme = $this->schemeFor($c, [$a->id => 100]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        $this->mark($placement, $this->mathConfig($a, $c['subjects']['math']->id), $this->writtenComponent(), 90);

        TenantContext::set($c['institute']->id);

        $gpa = app(AcademicFinalResultService::class)->gpa($scheme, $placement);

        $this->assertSame('unavailable', $gpa['status']);
        $this->assertNull($gpa['value']);
        $this->assertStringContainsString('credits', $gpa['reason']);
    }

    public function test_subject_level_gpa_exclusion_is_honoured(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale');

        $a = $this->assessment($c, 'A', [
            ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
            ['subject_id' => $c['subjects']['bangla']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
        ]);
        $scheme = $this->schemeFor($c, [$a->id => 100]);

        // Institute overrides Bangla to be excluded from GPA.
        InstituteSubject::updateOrCreate(
            ['institute_id' => $c['institute']->id, 'subject_id' => $c['subjects']['bangla']->id],
            ['gpa_included' => false]
        );

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id, $c['subjects']['bangla']->id]);
        $this->mark($placement, $this->mathConfig($a, $c['subjects']['math']->id), $this->writtenComponent(), 90);   // A+ 5.0
        $this->mark($placement, $this->mathConfig($a, $c['subjects']['bangla']->id), $this->writtenComponent(), 90); // excluded

        TenantContext::set($c['institute']->id);

        $gpa = app(AcademicFinalResultService::class)->gpa($scheme, $placement);

        $this->assertSame('computed', $gpa['status']);
        $this->assertSame(5.0, $gpa['value']);
        $this->assertCount(1, $gpa['subjects']);
    }

    public function test_optional_subjects_excluded_from_gpa_by_scale_policy(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale', null, ['optional_subject_gpa' => 'excluded']);

        $a = $this->assessment($c, 'A', [
            ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
            ['subject_id' => $c['subjects']['bio']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
        ]);
        $scheme = $this->schemeFor($c, [$a->id => 100]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id, $c['subjects']['bio']->id]);
        $this->mark($placement, $this->mathConfig($a, $c['subjects']['math']->id), $this->writtenComponent(), 90);  // A+ 5.0 (mandatory)
        $this->mark($placement, $this->mathConfig($a, $c['subjects']['bio']->id), $this->writtenComponent(), 95);  // A+ 5.0 (optional → excluded)

        TenantContext::set($c['institute']->id);

        $gpa = app(AcademicFinalResultService::class)->gpa($scheme, $placement);

        $this->assertSame('computed', $gpa['status']);
        $this->assertSame(5.0, $gpa['value']);
        $this->assertCount(1, $gpa['subjects']);
    }

    public function test_optional_subjects_included_in_gpa_when_policy_allows(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale', null, ['optional_subject_gpa' => 'included']);

        $a = $this->assessment($c, 'A', [
            ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
            ['subject_id' => $c['subjects']['bio']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
        ]);
        $scheme = $this->schemeFor($c, [$a->id => 100]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id, $c['subjects']['bio']->id]);
        $this->mark($placement, $this->mathConfig($a, $c['subjects']['math']->id), $this->writtenComponent(), 90);
        $this->mark($placement, $this->mathConfig($a, $c['subjects']['bio']->id), $this->writtenComponent(), 95);

        TenantContext::set($c['institute']->id);

        $gpa = app(AcademicFinalResultService::class)->gpa($scheme, $placement);

        $this->assertSame('computed', $gpa['status']);
        $this->assertSame(5.0, $gpa['value']);
        $this->assertCount(2, $gpa['subjects']);
    }

    // ------------------------------------------------------------- Security / pages

    public function test_owner_can_create_institute_override(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.grading.store'), [
                'institute_id' => implode('', array_filter([9999, '7'])), // forged → ignored
                'name' => 'Institute A+..F',
                'gpa_mode' => 'equal_weight',
                'optional_subject_gpa' => 'included',
                'academic_level_id' => $c['level']->id,
                'status' => '1',
                'rows' => array_map(fn ($r) => array_merge($r, ['active' => '1']), $this->bdRows()),
            ])
            ->assertRedirect();

        $scale = GradeScale::query()->where('name', 'Institute A+..F')->firstOrFail();
        $this->assertSame($c['institute']->id, $scale->institute_id);
        $this->assertSame($c['level']->id, $scale->academic_level_id);
        $this->assertNull($scale->country_id);
        $this->assertCount(6, $scale->rows);

        // The inherited BD default is untouched.
        $this->assertSame(1, GradeScale::query()->where('name', 'BD Scale')->count());
        $this->assertNull(GradeScale::query()->where('name', 'BD Scale')->first()->institute_id);
    }

    public function test_owner_cannot_fetch_another_institutes_override(): void
    {
        $c = $this->curriculum();
        $other = $this->institute($this->country('IN', 'India'), 'Other Inst');
        $override = $this->scale(['institute_id' => $c['institute']->id, 'academic_level_id' => $c['level']->id], 'Inst Override');
        $otherOwner = $this->user($other, 'institute-owner', 'g-other-owner');

        TenantContext::set($other->id);

        $this->actingAs($otherOwner, 'institute_user')
            ->get(route('settings.academic.grading.edit', $override->id))
            ->assertNotFound();

        $this->actingAs($otherOwner, 'institute_user')
            ->delete(route('settings.academic.grading.destroy', $override->id))
            ->assertNotFound();
    }

    public function test_teacher_without_permission_is_blocked(): void
    {
        $c = $this->curriculum();
        $teacher = $this->user($c['institute'], 'teacher', 'g-teacher');

        TenantContext::set($c['institute']->id);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.grading.index'))
            ->assertForbidden();

        $this->actingAs($teacher, 'institute_user')
            ->post(route('settings.academic.grading.store'), ['name' => 'X', 'gpa_mode' => 'equal_weight', 'optional_subject_gpa' => 'included'])
            ->assertForbidden();
    }

    public function test_pages_render(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale');
        $override = $this->scale(['institute_id' => $c['institute']->id, 'academic_level_id' => $c['level']->id], 'Inst Override');

        $mid = $this->assessment($c, 'Mid', [[
            'subject_id' => $c['subjects']['math']->id,
            'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]],
        ]]);
        $scheme = $this->schemeFor($c, [$mid->id => 100]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id], $c['group']);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 90);

        TenantContext::set($c['institute']->id);

        $owner = $this->actingAs($c['owner'], 'institute_user');

        // The effective scale for the class is the institute override; the
        // masked country default (BD Scale) does not appear on the owner page.
        $owner->get(route('settings.academic.grading.index'))->assertOk()->assertSee('Inst Override')->assertDontSee('BD Scale');
        $owner->get(route('settings.academic.grading.create'))->assertOk();
        $owner->get(route('settings.academic.grading.edit', $override->id))->assertOk();
        $owner->get(route('settings.academic.grading.preview'))->assertOk();
        $owner->get(route('settings.academic.grading.preview', ['scheme_id' => $scheme->id]))->assertOk()->assertSee('A+');

        TenantContext::clear();
        $this->actingAs($this->admin(), 'platform_admin')
            ->get(route('admin.academic.grading.index'))->assertOk()->assertSee('BD Scale');
    }

    public function test_preview_does_not_mutate_anything(): void
    {
        $c = $this->curriculum();
        $this->scale(['country_id' => $c['country']->id], 'BD Scale');

        $mid = $this->assessment($c, 'Mid', [[
            'subject_id' => $c['subjects']['math']->id,
            'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]],
        ]]);
        $scheme = $this->schemeFor($c, [$mid->id => 100]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 90);

        $marksBefore = AcademicStudentMark::count();
        $rowsBefore = GradeScaleRow::count();
        $scaleBefore = GradeScale::count();

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.grading.preview', ['scheme_id' => $scheme->id]))
            ->assertOk();

        $this->assertSame($marksBefore, AcademicStudentMark::count());
        $this->assertSame($rowsBefore, GradeScaleRow::count());
        $this->assertSame($scaleBefore, GradeScale::count());
    }

    // ------------------------------------------------------------- Aggregation helpers

    private function curriculum(): array
    {
        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $group = $this->group($classGrade);
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'g-owner');

        $bangla = $this->subject('Bangla', 'G100001');
        $english = $this->subject('English', 'G100002');
        $math = $this->subject('Mathematics', 'G100003');
        $bio = $this->subject('Biology', 'G100004');
        $hmath = $this->subject('Higher Mathematics', 'G100005');

        $this->assign($bangla, $classGrade, 'mandatory', 1);
        $this->assign($english, $classGrade, 'mandatory', 2);
        $this->assign($math, $classGrade, 'mandatory', 3);
        $selGroup = $this->selectionGroup($classGrade, 1, 1);
        $this->assign($bio, $classGrade, 'optional', 4, $selGroup->id);
        $this->assign($hmath, $classGrade, 'optional', 5, $selGroup->id);

        return [
            'country' => $country,
            'system' => $system,
            'level' => $level,
            'class_grade' => $classGrade,
            'group' => $group,
            'institute' => $institute,
            'owner' => $owner,
            'subjects' => compact('bangla', 'english', 'math', 'bio', 'hmath'),
        ];
    }

    private function subject(string $name, string $code): Subject
    {
        return Subject::create([
            'institute_id' => null,
            'category_id' => null,
            'subject_type' => 'academic',
            'subject_code' => $code,
            'name' => $name,
            'slug' => str()->slug($name.'-'.substr(md5($name.$code), 0, 6)),
            'short_name' => substr($name, 0, 8),
            'description' => null,
            'status' => 'active',
        ]);
    }

    private function assign(Subject $subject, ClassGrade $classGrade, string $requirementType, int $displayOrder, ?int $selectionGroupId = null): SubjectAcademicAssignment
    {
        return SubjectAcademicAssignment::create([
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'requirement_type' => $requirementType,
            'selection_group_id' => $selectionGroupId,
            'display_order' => $displayOrder,
            'status' => 'active',
        ]);
    }

    private function selectionGroup(ClassGrade $classGrade, int $min, int $max): AcademicSelectionGroup
    {
        return AcademicSelectionGroup::create([
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'name' => 'Group A',
            'code' => 'groupA'.uniqid(),
            'selection_type' => 'optional',
            'minimum_selection' => $min,
            'maximum_selection' => $max,
            'display_order' => 1,
            'status' => 'active',
        ]);
    }

    private function group(ClassGrade $classGrade, string $code = 'sci'): AcademicGroup
    {
        return AcademicGroup::withoutGlobalScopes()->firstOrCreate(
            ['class_grade_id' => $classGrade->id, 'code' => $code],
            [
                'country_id' => $classGrade->country_id,
                'education_system_id' => $classGrade->education_system_id,
                'academic_level_id' => $classGrade->academic_level_id,
                'name' => 'Science',
                'display_order' => 0,
                'status' => true,
            ]
        );
    }

    private function student(Institute $institute, string $name = 'Rahim', ?Branch $branch = null): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => 'SID'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => now()->toDateString(),
        ]);
    }

    private function defaultYear(Institute $institute): AcademicYear
    {
        return AcademicYear::withoutGlobalScopes()->firstOrCreate(
            ['institute_id' => $institute->id, 'code' => '2026'],
            ['name' => 'Academic Year 2026', 'is_current' => true, 'status' => true]
        );
    }

    protected function writtenComponent(string $slug = 'written'): Component
    {
        return Component::where('slug', $slug)->firstOrFail();
    }

    private function assessment(array $c, string $name, array $subjectConfigs): AcademicAssessment
    {
        $assessment = AcademicAssessment::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'assessment_type_id' => AssessmentType::where('slug', 'mid-term')->firstOrFail()->id,
            'name' => $name,
            'status' => 'scheduled',
            'display_order' => 1,
        ]);

        foreach ($subjectConfigs as $config) {
            $subjectConfig = AssessmentSubject::create([
                'assessment_id' => $assessment->id,
                'subject_id' => $config['subject_id'],
                'display_order' => $config['order'] ?? 1,
                'status' => 'active',
            ]);
            foreach ($config['components'] as $ci => $component) {
                AssessmentSubjectComponent::create([
                    'assessment_subject_id' => $subjectConfig->id,
                    'component_id' => $component['component_id'],
                    'full_mark' => $component['full_mark'],
                    'pass_mark' => $component['pass_mark'],
                    'mandatory_pass' => $component['mandatory_pass'] ?? false,
                    'display_order' => $ci + 1,
                    'status' => 'active',
                ]);
            }
        }

        return $assessment;
    }

    private function placement(array $c, Student $student, array $subjectIds, ?AcademicGroup $group = null): StudentAcademicPlacement
    {
        $placement = StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $group?->id,
            'status' => 'active',
        ]);

        foreach ($subjectIds as $subjectId) {
            StudentSubjectSelection::create([
                'institute_id' => $c['institute']->id,
                'academic_placement_id' => $placement->id,
                'subject_id' => $subjectId,
                'is_selected' => true,
                'is_mandatory' => false,
            ]);
        }

        return $placement;
    }

    private function mark(StudentAcademicPlacement $placement, AssessmentSubject $subjectConfig, Component $component, float $obtained, string $status = 'entered'): AcademicStudentMark
    {
        $assessmentComponent = AssessmentSubjectComponent::where('assessment_subject_id', $subjectConfig->id)
            ->where('component_id', $component->id)
            ->firstOrFail();

        return AcademicStudentMark::create([
            'institute_id' => $placement->institute_id,
            'academic_assessment_id' => $subjectConfig->assessment_id,
            'assessment_subject_id' => $subjectConfig->id,
            'assessment_component_id' => $assessmentComponent->id,
            'student_id' => $placement->student_id,
            'academic_placement_id' => $placement->id,
            'obtained_mark' => $status === 'absent' ? null : $obtained,
            'status' => $status,
        ]);
    }

    private function schemeFor(array $c, array $weights): AcademicResultAggregationScheme
    {
        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Annual',
            'status' => 'active',
        ]);

        foreach ($weights as $assessmentId => $weight) {
            AcademicResultAggregationItem::create([
                'scheme_id' => $scheme->id,
                'academic_assessment_id' => $assessmentId,
                'weight' => $weight,
            ]);
        }

        return $scheme->load('items');
    }

    private function mathConfig(AcademicAssessment $assessment, int $subjectId): AssessmentSubject
    {
        return AssessmentSubject::where('assessment_id', $assessment->id)->where('subject_id', $subjectId)->firstOrFail();
    }
}
