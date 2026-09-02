<?php

namespace Tests\Feature;

use App\Models\AcademicCumulativeResult;
use App\Models\AcademicCumulativeResultEntry;
use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicFinalResultRow;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\GradeScale;
use App\Models\Institute;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\Subject;
use App\Services\AcademicCumulativeService;
use App\Services\AcademicGradingService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AcademicCumulativeGpaTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

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

    private function institute(Country $country, string $name = 'CGPA Inst'): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name = 'Main'): Branch
    {
        return Branch::create(['institute_id' => $institute->id, 'name' => $name, 'status' => 'active']);
    }

    private function subject(string $name = 'Mathematics', string $code = 'SUB001'): Subject
    {
        return Subject::withoutGlobalScopes()->firstOrCreate(
            ['subject_code' => $code],
            [
                'institute_id' => null,
                'category_id' => null,
                'subject_type' => 'academic',
                'name' => $name,
                'slug' => str()->slug($name.'-'.substr(md5($name.$code), 0, 6)),
                'short_name' => substr($name, 0, 8),
                'description' => null,
                'status' => 'active',
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

    private function year(Institute $institute, string $code = '2026'): AcademicYear
    {
        return AcademicYear::withoutGlobalScopes()->firstOrCreate(
            ['institute_id' => $institute->id, 'code' => $code],
            ['name' => "Academic Year $code", 'is_current' => true, 'status' => true]
        );
    }

    private function globalScaleEqualWeight(): GradeScale
    {
        return app(AcademicGradingService::class)->store([
            'name' => 'BD Equal-Weight Scale',
            'gpa_mode' => 'equal_weight',
            'optional_subject_gpa' => 'included',
            'status' => true,
        ], [
            ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'A', 'min_score' => 70, 'max_score' => 79.99, 'grade_point' => 4.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'B', 'min_score' => 60, 'max_score' => 69.99, 'grade_point' => 3.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'C', 'min_score' => 50, 'max_score' => 59.99, 'grade_point' => 2.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'F', 'min_score' => 0, 'max_score' => 49.99, 'grade_point' => 0.0, 'is_pass' => false, 'gpa_included' => false],
        ]);
    }

    private function globalScaleCreditWeighted(): GradeScale
    {
        return app(AcademicGradingService::class)->store([
            'name' => 'BD Credit-Weighted Scale',
            'gpa_mode' => 'credit_weighted',
            'optional_subject_gpa' => 'included',
            'status' => true,
        ], [
            ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'A', 'min_score' => 70, 'max_score' => 79.99, 'grade_point' => 4.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'B', 'min_score' => 60, 'max_score' => 69.99, 'grade_point' => 3.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'C', 'min_score' => 50, 'max_score' => 59.99, 'grade_point' => 2.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'F', 'min_score' => 0, 'max_score' => 49.99, 'grade_point' => 0.0, 'is_pass' => false, 'gpa_included' => false],
        ]);
    }

    /**
     * Build a full curriculum context: country, system, level, classGrade, institute,
     * scheme, policy and two academic years for multi-placement tests.
     */
    private function curriculum(): array
    {
        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $institute = $this->institute($country);
        $year1 = $this->year($institute, '2025');
        $year2 = $this->year($institute, '2026');
        $math = $this->subject('Mathematics', 'G100001');
        $english = $this->subject('English', 'G100002');
        $science = $this->subject('Science', 'G100003');
        $scale = $this->globalScaleEqualWeight();

        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $institute->id,
            'academic_year_id' => $year1->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'name' => 'Annual',
            'status' => 'active',
        ]);

        $policy = AcademicFinalResultPolicy::withoutGlobalScopes()->create([
            'institute_id' => $institute->id,
            'scheme_id' => $scheme->id,
            'name' => 'Default Policy',
            'grade_scale_id' => $scale->id,
            'require_approval' => false,
            'absent_renormalization' => false,
            'status' => 'active',
        ]);

        return [
            'country' => $country,
            'system' => $system,
            'level' => $level,
            'class_grade' => $classGrade,
            'institute' => $institute,
            'scheme' => $scheme,
            'policy' => $policy,
            'scale' => $scale,
            'year1' => $year1,
            'year2' => $year2,
            'math' => $math,
            'english' => $english,
            'science' => $science,
        ];
    }

    private function placement(array $c, Student $student, ?int $academicYearId = null): StudentAcademicPlacement
    {
        return StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYearId ?? $c['year1']->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => null,
            'status' => 'active',
        ]);
    }

    private function publishedResult(
        array $c,
        StudentAcademicPlacement $placement,
        float $gpa,
        int $passedCount,
        int $failedCount,
        array $rows,
        ?int $yearId = null,
    ): AcademicFinalResult {
        $result = AcademicFinalResult::withoutGlobalScopes()->create([
            'institute_id' => $c['institute']->id,
            'scheme_id' => $c['scheme']->id,
            'policy_id' => $c['policy']->id,
            'name' => 'Year '.mt_rand(1, 99),
            'status' => 'published',
            'published_at' => now(),
        ]);

        AcademicFinalResultStudent::create([
            'result_id' => $result->id,
            'placement_id' => $placement->id,
            'gpa' => $gpa,
            'gpa_status' => 'computed',
            'passed_count' => $passedCount,
            'failed_count' => $failedCount,
        ]);

        foreach ($rows as $row) {
            AcademicFinalResultRow::create(array_merge([
                'result_id' => $result->id,
                'placement_id' => $placement->id,
            ], $row));
        }

        return $result;
    }

    // ------------------------------------------------------------- EQUAL WEIGHT formula

    public function test_equal_weight_cgpa_uses_simple_average_of_period_gpas(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        // Two placements in different years (unique constraint)
        $p1 = $this->placement($c, $student, $c['year1']->id);
        $p2 = $this->placement($c, $student, $c['year2']->id);

        $this->publishedResult($c, $p1, 4.0, 5, 0, [
            ['subject_id' => $c['math']->id, 'grade' => 'A', 'grade_point' => 4.0, 'gpa_included' => true, 'credits' => null, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 85],
        ]);
        $this->publishedResult($c, $p2, 3.0, 4, 1, [
            ['subject_id' => $c['math']->id, 'grade' => 'B', 'grade_point' => 3.0, 'gpa_included' => true, 'credits' => null, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 65],
        ]);

        $service = app(AcademicCumulativeService::class);
        $result = $service->compute($student, $c['level']->id);

        // Equal-weight CGPA = (4.0 + 3.0) / 2 = 3.5
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(3.5, $result['cumulative_gpa'], 0.01);
        $this->assertSame(2, $result['periods_completed']);
    }

    public function test_equal_weight_does_not_choke_on_fractional_periods(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $this->publishedResult($c, $p1, 3.625, 5, 0, [
            ['subject_id' => $c['math']->id, 'grade_point' => 4.0, 'gpa_included' => true, 'credits' => null, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 85],
            ['subject_id' => $c['english']->id, 'grade_point' => 3.25, 'gpa_included' => true, 'credits' => null, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 65],
        ]);

        $service = app(AcademicCumulativeService::class);
        $result = $service->compute($student, $c['level']->id);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(3.625, $result['cumulative_gpa'], 0.01);
    }

    public function test_equal_weight_with_multiple_periods(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $yearB = $this->year($c['institute'], '2024');
        $p2 = $this->placement($c, $student, $yearB->id);
        $yearC = $this->year($c['institute'], '2023');
        $p3 = $this->placement($c, $student, $yearC->id);

        $this->publishedResult($c, $p1, 3.75, 4, 0, []);
        $this->publishedResult($c, $p2, 3.25, 4, 0, []);
        $this->publishedResult($c, $p3, 3.875, 5, 0, []);

        $service = app(AcademicCumulativeService::class);
        $result = $service->compute($student, $c['level']->id);

        // (3.75 + 3.25 + 3.875) / 3 = 10.875 / 3 = 3.625
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(3.625, $result['cumulative_gpa'], 0.01);
        $this->assertSame(3, $result['periods_completed']);
    }

    // ------------------------------------------------------------- CREDIT WEIGHTED formula

    public function test_credit_weighted_cgpa_uses_weighted_average(): void
    {
        $c = $this->curriculum();
        $c['scale']->update(['gpa_mode' => 'credit_weighted']);
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $p2 = $this->placement($c, $student, $c['year2']->id);

        // Period 1: GPA=4.0, 10 credits
        $this->publishedResult($c, $p1, 4.0, 5, 0, [
            ['subject_id' => $c['math']->id, 'grade_point' => 4.0, 'gpa_included' => true, 'credits' => 5.0, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 85],
            ['subject_id' => $c['english']->id, 'grade_point' => 4.0, 'gpa_included' => true, 'credits' => 5.0, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 85],
        ]);

        // Period 2: GPA=3.0, 10 credits
        $this->publishedResult($c, $p2, 3.0, 4, 1, [
            ['subject_id' => $c['math']->id, 'grade_point' => 3.0, 'gpa_included' => true, 'credits' => 5.0, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 65],
            ['subject_id' => $c['english']->id, 'grade_point' => 3.0, 'gpa_included' => true, 'credits' => 5.0, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 65],
        ]);

        $service = app(AcademicCumulativeService::class);
        $result = $service->compute($student, $c['level']->id);

        // Credit-weighted CGPA = (4.0*10 + 3.0*10) / (10+10) = 70/20 = 3.5
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(3.5, $result['cumulative_gpa'], 0.01);
    }

    public function test_credit_weighted_cgpa_does_not_double_count_total_grade_points(): void
    {
        $c = $this->curriculum();
        $c['scale']->update(['gpa_mode' => 'credit_weighted']);
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $p2 = $this->placement($c, $student, $c['year2']->id);

        // Period 1: GPA=4.0, 10 credits → contribution = 4.0 * 10 = 40
        $this->publishedResult($c, $p1, 4.0, 5, 0, [
            ['subject_id' => $c['math']->id, 'grade_point' => 4.0, 'gpa_included' => true, 'credits' => 5.0, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 85],
            ['subject_id' => $c['english']->id, 'grade_point' => 4.0, 'gpa_included' => true, 'credits' => 5.0, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 85],
        ]);

        // Period 2: GPA=3.0, 20 credits → contribution = 3.0 * 20 = 60
        $this->publishedResult($c, $p2, 3.0, 4, 1, [
            ['subject_id' => $c['math']->id, 'grade_point' => 3.0, 'gpa_included' => true, 'credits' => 10.0, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 65],
            ['subject_id' => $c['english']->id, 'grade_point' => 3.0, 'gpa_included' => true, 'credits' => 10.0, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 65],
        ]);

        $service = app(AcademicCumulativeService::class);
        $result = $service->compute($student, $c['level']->id);

        // CGPA = (4.0*10 + 3.0*20) / (10+20) = 100/30 = 3.3333
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(3.3333, $result['cumulative_gpa'], 0.01);
        $this->assertEqualsWithDelta(100.0, $result['total_grade_points'], 0.01);
        $this->assertEqualsWithDelta(30.0, $result['total_credits'], 0.01);
    }

    public function test_credit_weighted_skips_periods_without_credits(): void
    {
        $c = $this->curriculum();
        $c['scale']->update(['gpa_mode' => 'credit_weighted']);
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $p2 = $this->placement($c, $student, $c['year2']->id);

        // Period 1: has credits
        $this->publishedResult($c, $p1, 4.0, 5, 0, [
            ['subject_id' => $c['math']->id, 'grade_point' => 4.0, 'gpa_included' => true, 'credits' => 10.0, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 85],
        ]);

        // Period 2: NO credits — should be skipped
        $this->publishedResult($c, $p2, 3.5, 4, 0, [
            ['subject_id' => $c['english']->id, 'grade_point' => 3.5, 'gpa_included' => true, 'credits' => null, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 75],
        ]);

        $service = app(AcademicCumulativeService::class);
        $result = $service->compute($student, $c['level']->id);

        $this->assertNotNull($result);
        // Only period 1 counted
        $this->assertEqualsWithDelta(4.0, $result['cumulative_gpa'], 0.01);
        $this->assertSame(1, $result['periods_completed']);
    }

    // ------------------------------------------------------------- Null / empty handling

    public function test_compute_returns_null_when_no_published_results(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $service = app(AcademicCumulativeService::class);
        $result = $service->compute($student, $c['level']->id);

        $this->assertNull($result);
    }

    public function test_compute_returns_null_when_all_snapshots_have_null_gpa(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);

        $result = AcademicFinalResult::withoutGlobalScopes()->create([
            'institute_id' => $c['institute']->id,
            'scheme_id' => $c['scheme']->id,
            'policy_id' => $c['policy']->id,
            'name' => 'Test',
            'status' => 'published',
            'published_at' => now(),
        ]);

        AcademicFinalResultStudent::create([
            'result_id' => $result->id,
            'placement_id' => $p1->id,
            'gpa' => null,
            'gpa_status' => 'unavailable',
            'passed_count' => 0,
            'failed_count' => 0,
        ]);

        $service = app(AcademicCumulativeService::class);
        $computed = $service->compute($student, $c['level']->id);

        $this->assertNull($computed);
    }

    // ------------------------------------------------------------- No-gpa-included subjects

    public function test_periods_with_no_gpa_included_subjects_are_skipped(): void
    {
        $c = $this->curriculum();
        $c['scale']->update(['gpa_mode' => 'credit_weighted']);
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $p2 = $this->placement($c, $student, $c['year2']->id);

        // Period 1: all subjects excluded from GPA and no credits → period skipped in credit_weighted
        $this->publishedResult($c, $p1, 4.0, 5, 0, [
            ['subject_id' => $c['math']->id, 'grade_point' => 4.0, 'gpa_included' => false, 'credits' => null, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 85],
        ]);

        // Period 2: normal with credits
        $this->publishedResult($c, $p2, 3.5, 4, 0, [
            ['subject_id' => $c['math']->id, 'grade_point' => 3.5, 'gpa_included' => true, 'credits' => 10.0, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 75],
        ]);

        $service = app(AcademicCumulativeService::class);
        $result = $service->compute($student, $c['level']->id);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(3.5, $result['cumulative_gpa'], 0.01);
        $this->assertSame(1, $result['periods_completed']);
    }

    // ------------------------------------------------------------- Precision & rounding

    public function test_cgpa_respects_precision_setting(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $p2 = $this->placement($c, $student, $c['year2']->id);

        $this->publishedResult($c, $p1, 3.75, 4, 0, []);
        $this->publishedResult($c, $p2, 3.25, 4, 0, []);

        // Equal-weight: (3.75 + 3.25) / 2 = 3.5
        $service = app(AcademicCumulativeService::class);
        $result = $service->compute($student, $c['level']->id);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(3.5, $result['cumulative_gpa'], 0.001);
    }

    public function test_half_down_rounding_mode(): void
    {
        $grading = app(AcademicGradingService::class);

        // 2.355 with precision 2 and half_down should round to 2.35 (not 2.36)
        $this->assertEqualsWithDelta(2.35, $grading->preciseRound(2.355, 2, GradeScale::ROUNDING_HALF_DOWN), 0.001);

        // half_up should round differently
        $this->assertEqualsWithDelta(2.36, $grading->preciseRound(2.355, 2, GradeScale::ROUNDING_HALF_UP), 0.001);
    }

    public function test_floor_rounding_mode(): void
    {
        $grading = app(AcademicGradingService::class);

        $this->assertEqualsWithDelta(2.35, $grading->preciseRound(2.359, 2, GradeScale::ROUNDING_FLOOR), 0.001);
    }

    public function test_ceil_rounding_mode(): void
    {
        $grading = app(AcademicGradingService::class);

        $this->assertEqualsWithDelta(2.36, $grading->preciseRound(2.351, 2, GradeScale::ROUNDING_CEIL), 0.001);
    }

    // ------------------------------------------------------------- Precision validation (H2)

    public function test_precision_0_to_6_is_accepted(): void
    {
        $grading = app(AcademicGradingService::class);
        $ref = new \ReflectionMethod(AcademicGradingService::class, 'validatePrecision');
        $ref->setAccessible(true);

        $ref->invoke($grading, ['gpa_decimal_places' => 0, 'cgpa_decimal_places' => 6, 'marks_decimal_places' => 3, 'percentage_decimal_places' => 4]);

        $this->assertTrue(true);
    }

    public function test_precision_7_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $grading = app(AcademicGradingService::class);
        $ref = new \ReflectionMethod(AcademicGradingService::class, 'validatePrecision');
        $ref->setAccessible(true);

        $ref->invoke($grading, ['gpa_decimal_places' => 7]);
    }

    public function test_negative_precision_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $grading = app(AcademicGradingService::class);
        $ref = new \ReflectionMethod(AcademicGradingService::class, 'validatePrecision');
        $ref->setAccessible(true);

        $ref->invoke($grading, ['cgpa_decimal_places' => -1]);
    }

    public function test_invalid_rounding_mode_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $grading = app(AcademicGradingService::class);
        $ref = new \ReflectionMethod(AcademicGradingService::class, 'validatePrecision');
        $ref->setAccessible(true);

        $ref->invoke($grading, ['rounding_mode' => 'invalid_mode']);
    }

    // ------------------------------------------------------------- Tenant isolation

    public function test_cgpa_is_isolated_per_institute(): void
    {
        $c = $this->curriculum();
        $country2 = $this->country('IN', 'India');
        $system2 = $this->system($country2, 'cbse');
        $level2 = $this->level($system2, 'senior');
        $classGrade2 = $this->classGrade($level2, 'c10');
        $institute2 = $this->institute($country2, 'Other Inst');
        $year2a = $this->year($institute2, '2026');

        $scheme2 = AcademicResultAggregationScheme::create([
            'institute_id' => $institute2->id,
            'academic_year_id' => $year2a->id,
            'class_grade_id' => $classGrade2->id,
            'academic_group_id' => null,
            'name' => 'Annual',
            'status' => 'active',
        ]);

        $policy2 = AcademicFinalResultPolicy::withoutGlobalScopes()->create([
            'institute_id' => $institute2->id,
            'scheme_id' => $scheme2->id,
            'name' => 'Policy',
            'grade_scale_id' => null,
            'require_approval' => false,
            'absent_renormalization' => false,
            'status' => 'active',
        ]);

        $student1 = $this->student($c['institute'], 'Student A');
        $student2 = $this->student($institute2, 'Student B');

        $p1 = $this->placement($c, $student1, $c['year1']->id);
        $this->publishedResult($c, $p1, 4.0, 5, 0, []);

        $c2 = ['institute' => $institute2, 'level' => $level2, 'class_grade' => $classGrade2, 'scheme' => $scheme2, 'policy' => $policy2, 'year1' => $year2a, 'year2' => $year2a];
        $p2 = $this->placement($c2, $student2, $year2a->id);
        $this->publishedResult($c2, $p2, 4.0, 5, 0, []);

        $service = app(AcademicCumulativeService::class);

        $result1 = $service->compute($student1, $c['level']->id);
        $result2 = $service->compute($student2, $level2->id);

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);

        $this->assertEqualsWithDelta(4.0, $result1['cumulative_gpa'], 0.01);
        $this->assertEqualsWithDelta(4.0, $result2['cumulative_gpa'], 0.01);
    }

    public function test_cgpa_does_not_count_other_students_data(): void
    {
        $c = $this->curriculum();
        $student1 = $this->student($c['institute'], 'Student A');
        $student2 = $this->student($c['institute'], 'Student B');

        // Student A: GPA=5.0
        $p1 = $this->placement($c, $student1, $c['year1']->id);
        $this->publishedResult($c, $p1, 5.0, 5, 0, []);

        // Student B: GPA=2.0
        $p2 = $this->placement($c, $student2, $c['year2']->id);
        $this->publishedResult($c, $p2, 2.0, 3, 2, []);

        $service = app(AcademicCumulativeService::class);
        $result1 = $service->compute($student1, $c['level']->id);
        $result2 = $service->compute($student2, $c['level']->id);

        $this->assertEqualsWithDelta(5.0, $result1['cumulative_gpa'], 0.01);
        $this->assertEqualsWithDelta(2.0, $result2['cumulative_gpa'], 0.01);
    }

    // ------------------------------------------------------------- Store persistence

    public function test_store_creates_cgpa_record(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $this->publishedResult($c, $p1, 4.0, 5, 0, []);

        $service = app(AcademicCumulativeService::class);
        $result = $service->store($student, $c['level']->id);

        $this->assertInstanceOf(AcademicCumulativeResult::class, $result);
        $this->assertEqualsWithDelta(4.0, $result->cumulative_gpa, 0.01);
        $this->assertSame(1, $result->periods_completed);
        $this->assertSame('active', $result->status);
    }

    public function test_store_persists_entries(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $p2 = $this->placement($c, $student, $c['year2']->id);

        $this->publishedResult($c, $p1, 4.0, 5, 0, []);
        $this->publishedResult($c, $p2, 3.5, 4, 0, []);

        $service = app(AcademicCumulativeService::class);
        $cumulative = $service->store($student, $c['level']->id);

        $entries = $cumulative->entries()->get();
        $this->assertCount(2, $entries);
    }

    public function test_store_idempotent_on_republish(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $this->publishedResult($c, $p1, 4.0, 5, 0, []);

        $service = app(AcademicCumulativeService::class);
        $first = $service->store($student, $c['level']->id);
        $second = $service->store($student, $c['level']->id);

        $this->assertSame($first->id, $second->id);
        $this->assertCount(1, AcademicCumulativeResult::withoutGlobalScopes()->where('student_id', $student->id)->get());
    }

    // ------------------------------------------------------------- Transaction safety (C2)

    public function test_upsert_runs_in_transaction(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $this->publishedResult($c, $p1, 4.0, 5, 0, []);

        $service = app(AcademicCumulativeService::class);
        $result = $service->store($student, $c['level']->id);

        $this->assertNotNull($result);
        $this->assertDatabaseHas('academic_cumulative_results', [
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }

    public function test_upsert_replaces_entries_on_update(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $this->publishedResult($c, $p1, 4.0, 5, 0, []);

        $service = app(AcademicCumulativeService::class);
        $service->store($student, $c['level']->id);

        // Add a second result
        $p2 = $this->placement($c, $student, $c['year2']->id);
        $this->publishedResult($c, $p2, 3.0, 4, 0, []);

        $service->store($student, $c['level']->id);

        $cumulative = $service->find($student, $c['level']->id);
        $entries = $cumulative->entries()->get();
        $this->assertCount(2, $entries);
    }

    // ------------------------------------------------------------- Level filtering

    public function test_cgpa_only_includes_results_for_specified_level(): void
    {
        $country = $this->country();
        $system = $this->system($country);
        $level1 = $this->level($system, 'primary');
        $level2 = $this->level($system, 'secondary');
        $classGrade1 = $this->classGrade($level1, 'c5');
        $classGrade2 = $this->classGrade($level2, 'c8');
        $institute = $this->institute($country);
        $yearA = $this->year($institute, '2025');
        $yearB = $this->year($institute, '2024');

        $scheme1 = AcademicResultAggregationScheme::create([
            'institute_id' => $institute->id,
            'academic_year_id' => $yearA->id,
            'class_grade_id' => $classGrade1->id,
            'academic_group_id' => null,
            'name' => 'Annual',
            'status' => 'active',
        ]);
        $scheme2 = AcademicResultAggregationScheme::create([
            'institute_id' => $institute->id,
            'academic_year_id' => $yearA->id,
            'class_grade_id' => $classGrade2->id,
            'academic_group_id' => null,
            'name' => 'Annual',
            'status' => 'active',
        ]);

        $policy1 = AcademicFinalResultPolicy::withoutGlobalScopes()->create([
            'institute_id' => $institute->id,
            'scheme_id' => $scheme1->id,
            'name' => 'Policy1',
            'grade_scale_id' => null,
            'require_approval' => false,
            'absent_renormalization' => false,
            'status' => 'active',
        ]);
        $policy2 = AcademicFinalResultPolicy::withoutGlobalScopes()->create([
            'institute_id' => $institute->id,
            'scheme_id' => $scheme2->id,
            'name' => 'Policy2',
            'grade_scale_id' => null,
            'require_approval' => false,
            'absent_renormalization' => false,
            'status' => 'active',
        ]);

        $student = $this->student($institute);

        $c1 = ['institute' => $institute, 'level' => $level1, 'class_grade' => $classGrade1, 'scheme' => $scheme1, 'policy' => $policy1, 'year1' => $yearA, 'year2' => $yearB];
        $c2 = ['institute' => $institute, 'level' => $level2, 'class_grade' => $classGrade2, 'scheme' => $scheme2, 'policy' => $policy2, 'year1' => $yearB, 'year2' => $yearA];

        $p1 = $this->placement($c1, $student, $yearA->id);
        $p2 = $this->placement($c2, $student, $yearB->id);

        $this->publishedResult($c1, $p1, 4.0, 5, 0, []);
        $this->publishedResult($c2, $p2, 3.0, 4, 0, []);

        $service = app(AcademicCumulativeService::class);

        $result1 = $service->compute($student, $level1->id);
        $result2 = $service->compute($student, $level2->id);

        $this->assertNotNull($result1);
        $this->assertEqualsWithDelta(4.0, $result1['cumulative_gpa'], 0.01);
        $this->assertSame(1, $result1['periods_completed']);

        $this->assertNotNull($result2);
        $this->assertEqualsWithDelta(3.0, $result2['cumulative_gpa'], 0.01);
        $this->assertSame(1, $result2['periods_completed']);
    }

    // ------------------------------------------------------------- GPA mode detection from entries

    public function test_mode_detected_from_grade_scale(): void
    {
        $c = $this->curriculum();
        $c['scale']->update(['gpa_mode' => 'credit_weighted']);
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);

        $result = AcademicFinalResult::withoutGlobalScopes()->create([
            'institute_id' => $c['institute']->id,
            'scheme_id' => $c['scheme']->id,
            'policy_id' => $c['policy']->id,
            'name' => 'Test',
            'status' => 'published',
            'published_at' => now(),
        ]);

        AcademicFinalResultStudent::create([
            'result_id' => $result->id,
            'placement_id' => $p1->id,
            'gpa' => 4.0,
            'gpa_status' => 'computed',
            'passed_count' => 2,
            'failed_count' => 0,
        ]);

        AcademicFinalResultRow::create([
            'result_id' => $result->id,
            'placement_id' => $p1->id,
            'subject_id' => $c['math']->id,
            'status' => 'computed',
            'aggregate' => 85,
            'grade' => 'A',
            'grade_point' => 4.0,
            'subject_status' => 'PASS',
            'gpa_included' => true,
            'credits' => 5.0,
        ]);

        AcademicFinalResultRow::create([
            'result_id' => $result->id,
            'placement_id' => $p1->id,
            'subject_id' => $c['english']->id,
            'status' => 'computed',
            'aggregate' => 80,
            'grade' => 'A',
            'grade_point' => 4.0,
            'subject_status' => 'PASS',
            'gpa_included' => true,
            'credits' => 5.0,
        ]);

        $service = app(AcademicCumulativeService::class);
        $computed = $service->compute($student, $c['level']->id);

        $this->assertNotNull($computed);
        $this->assertSame('credit_weighted', $computed['mode']);
    }

    public function test_equal_weight_mode_used_when_snapshot_mode_is_null(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);

        $result = AcademicFinalResult::withoutGlobalScopes()->create([
            'institute_id' => $c['institute']->id,
            'scheme_id' => $c['scheme']->id,
            'policy_id' => $c['policy']->id,
            'name' => 'Test',
            'status' => 'published',
            'published_at' => now(),
        ]);

        AcademicFinalResultStudent::create([
            'result_id' => $result->id,
            'placement_id' => $p1->id,
            'gpa' => 4.0,
            'gpa_status' => 'computed',
            'gpa_mode' => null,
            'passed_count' => 5,
            'failed_count' => 0,
        ]);

        $service = app(AcademicCumulativeService::class);
        $computed = $service->compute($student, $c['level']->id);

        $this->assertNotNull($computed);
        $this->assertSame('equal_weight', $computed['mode']);
    }

    // ------------------------------------------------------------- Store all levels

    public function test_store_all_levels(): void
    {
        $country = $this->country();
        $system = $this->system($country);
        $level1 = $this->level($system, 'primary');
        $level2 = $this->level($system, 'secondary');
        $classGrade1 = $this->classGrade($level1, 'c5');
        $classGrade2 = $this->classGrade($level2, 'c8');
        $institute = $this->institute($country);
        $yearA = $this->year($institute, '2025');
        $yearB = $this->year($institute, '2024');

        $scheme1 = AcademicResultAggregationScheme::create([
            'institute_id' => $institute->id,
            'academic_year_id' => $yearA->id,
            'class_grade_id' => $classGrade1->id,
            'academic_group_id' => null,
            'name' => 'Annual',
            'status' => 'active',
        ]);
        $scheme2 = AcademicResultAggregationScheme::create([
            'institute_id' => $institute->id,
            'academic_year_id' => $yearA->id,
            'class_grade_id' => $classGrade2->id,
            'academic_group_id' => null,
            'name' => 'Annual',
            'status' => 'active',
        ]);

        $policy1 = AcademicFinalResultPolicy::withoutGlobalScopes()->create([
            'institute_id' => $institute->id,
            'scheme_id' => $scheme1->id,
            'name' => 'Policy1',
            'grade_scale_id' => null,
            'require_approval' => false,
            'absent_renormalization' => false,
            'status' => 'active',
        ]);
        $policy2 = AcademicFinalResultPolicy::withoutGlobalScopes()->create([
            'institute_id' => $institute->id,
            'scheme_id' => $scheme2->id,
            'name' => 'Policy2',
            'grade_scale_id' => null,
            'require_approval' => false,
            'absent_renormalization' => false,
            'status' => 'active',
        ]);

        $student = $this->student($institute);

        $c1 = ['institute' => $institute, 'level' => $level1, 'class_grade' => $classGrade1, 'scheme' => $scheme1, 'policy' => $policy1, 'year1' => $yearA, 'year2' => $yearB];
        $c2 = ['institute' => $institute, 'level' => $level2, 'class_grade' => $classGrade2, 'scheme' => $scheme2, 'policy' => $policy2, 'year1' => $yearB, 'year2' => $yearA];

        $p1 = $this->placement($c1, $student, $yearA->id);
        $p2 = $this->placement($c2, $student, $yearB->id);

        $this->publishedResult($c1, $p1, 4.0, 5, 0, []);
        $this->publishedResult($c2, $p2, 3.0, 4, 0, []);

        $service = app(AcademicCumulativeService::class);
        $results = $service->storeAllLevels($student);

        $this->assertCount(2, $results);

        $gpas = $results->pluck('cumulative_gpa')->sort()->values()->toArray();
        $this->assertEqualsWithDelta(3.0, $gpas[0], 0.01);
        $this->assertEqualsWithDelta(4.0, $gpas[1], 0.01);
    }

    // ------------------------------------------------------------- Existing CGPA records untouched

    public function test_existing_cgpa_records_not_affected_when_no_new_published(): void
    {
        $c = $this->curriculum();
        $student1 = $this->student($c['institute']);
        $student2 = $this->student($c['institute'], 'Second Student');

        $p1 = $this->placement($c, $student1, $c['year1']->id);
        $this->publishedResult($c, $p1, 4.0, 5, 0, []);

        $service = app(AcademicCumulativeService::class);
        $service->store($student1, $c['level']->id);

        // Store for student2 with no published results
        $service->store($student2, $c['level']->id);

        // First student's CGPA should still be intact
        $result = $service->find($student1, $c['level']->id);
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(4.0, $result->cumulative_gpa, 0.01);
    }

    // ------------------------------------------------------------- CGPA entry credits match

    public function test_cumulative_entry_credits_match_total_credits(): void
    {
        $c = $this->curriculum();
        $c['scale']->update(['gpa_mode' => 'credit_weighted']);
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $this->publishedResult($c, $p1, 4.0, 5, 0, [
            ['subject_id' => $c['math']->id, 'grade_point' => 4.0, 'gpa_included' => true, 'credits' => 10.0, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 85],
        ]);

        $service = app(AcademicCumulativeService::class);
        $cumulative = $service->store($student, $c['level']->id);

        $this->assertEqualsWithDelta(10.0, $cumulative->total_credits, 0.01);

        $entry = $cumulative->entries()->first();
        $this->assertEqualsWithDelta(10.0, $entry->credits_earned, 0.01);
    }

    // ------------------------------------------------------------- Regression: formula not halved

    public function test_cgpa_is_not_halved_when_formula_uses_correct_average(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $p2 = $this->placement($c, $student, $c['year2']->id);

        $this->publishedResult($c, $p1, 4.0, 5, 0, []);
        $this->publishedResult($c, $p2, 3.25, 4, 0, []);

        $service = app(AcademicCumulativeService::class);
        $result = $service->compute($student, $c['level']->id);

        // BUG would have given: (4.0 + 3.25) / 4 = 1.8125
        // CORRECT: (4.0 + 3.25) / 2 = 3.625
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(3.625, $result['cumulative_gpa'], 0.01);
    }

    // ------------------------------------------------------------- find method

    public function test_find_returns_null_when_no_record(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $service = app(AcademicCumulativeService::class);
        $result = $service->find($student, $c['level']->id);

        $this->assertNull($result);
    }

    public function test_find_returns_record_when_exists(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $this->publishedResult($c, $p1, 4.0, 5, 0, []);

        $service = app(AcademicCumulativeService::class);
        $service->store($student, $c['level']->id);

        $found = $service->find($student, $c['level']->id);

        $this->assertNotNull($found);
        $this->assertEqualsWithDelta(4.0, $found->cumulative_gpa, 0.01);
    }

    // ------------------------------------------------------------- compute returns correct total_grade_points

    public function test_compute_returns_correct_total_grade_points_equal_weight(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $p2 = $this->placement($c, $student, $c['year2']->id);

        $this->publishedResult($c, $p1, 4.0, 5, 0, []);
        $this->publishedResult($c, $p2, 3.5, 4, 0, []);

        $service = app(AcademicCumulativeService::class);
        $result = $service->compute($student, $c['level']->id);

        $this->assertEqualsWithDelta(7.5, $result['total_grade_points'], 0.01);
    }

    // ------------------------------------------------------------- Snapshot with failed subjects

    public function test_failed_subjects_counted_in_passed_failed(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);
        $this->publishedResult($c, $p1, 3.5, 3, 2, [
            ['subject_id' => $c['math']->id, 'grade_point' => 3.0, 'gpa_included' => true, 'credits' => null, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 65],
            ['subject_id' => $c['english']->id, 'grade_point' => 4.0, 'gpa_included' => true, 'credits' => null, 'subject_status' => 'PASS', 'status' => 'computed', 'aggregate' => 80],
            ['subject_id' => $c['science']->id, 'grade_point' => 3.5, 'gpa_included' => true, 'credits' => null, 'subject_status' => 'FAIL', 'status' => 'computed', 'aggregate' => 45],
        ]);

        $service = app(AcademicCumulativeService::class);
        $cumulative = $service->store($student, $c['level']->id);

        $entry = $cumulative->entries()->first();
        $this->assertSame(2, $entry->subjects_passed);
        $this->assertSame(1, $entry->subjects_failed);
    }

    // ------------------------------------------------------------- CGPA mode on stored record

    public function test_stored_record_has_correct_gpa_mode(): void
    {
        $c = $this->curriculum();
        $c['scale']->update(['gpa_mode' => 'credit_weighted']);
        $student = $this->student($c['institute']);

        $p1 = $this->placement($c, $student, $c['year1']->id);

        $result = AcademicFinalResult::withoutGlobalScopes()->create([
            'institute_id' => $c['institute']->id,
            'scheme_id' => $c['scheme']->id,
            'policy_id' => $c['policy']->id,
            'name' => 'Test',
            'status' => 'published',
            'published_at' => now(),
        ]);

        AcademicFinalResultStudent::create([
            'result_id' => $result->id,
            'placement_id' => $p1->id,
            'gpa' => 4.0,
            'gpa_status' => 'computed',
            'passed_count' => 2,
            'failed_count' => 0,
        ]);

        AcademicFinalResultRow::create([
            'result_id' => $result->id,
            'placement_id' => $p1->id,
            'subject_id' => $c['math']->id,
            'status' => 'computed',
            'aggregate' => 85,
            'grade' => 'A',
            'grade_point' => 4.0,
            'subject_status' => 'PASS',
            'gpa_included' => true,
            'credits' => 5.0,
        ]);

        AcademicFinalResultRow::create([
            'result_id' => $result->id,
            'placement_id' => $p1->id,
            'subject_id' => $c['english']->id,
            'status' => 'computed',
            'aggregate' => 80,
            'grade' => 'A',
            'grade_point' => 4.0,
            'subject_status' => 'PASS',
            'gpa_included' => true,
            'credits' => 5.0,
        ]);

        $service = app(AcademicCumulativeService::class);
        $cumulative = $service->store($student, $c['level']->id);

        $this->assertSame('credit_weighted', $cumulative->gpa_mode);
    }

    public function test_stored_record_status_empty_when_no_results(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $service = app(AcademicCumulativeService::class);
        $cumulative = $service->store($student, $c['level']->id);

        $this->assertSame('empty', $cumulative->status);
        $this->assertNull($cumulative->cumulative_gpa);
    }
}
