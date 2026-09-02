<?php

namespace Tests\Feature;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicFinalResultRow;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\PromotionPolicy;
use App\Models\PromotionPolicyRule;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Database\Seeders\AcademicAssessmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Step 25 — Promotion decision CSV export + printable promotion sheet.
 *
 * The export reads exclusively frozen data: the materialized per-student
 * verdicts (promotion_decision_items) and the PUBLISHED result's snapshot
 * (academic_final_result_students / _rows). It is strictly read-only,
 * tenant + branch isolated, and produces exactly the numbers shown on the
 * decision review page (same PromoterEvaluationService inputs).
 */
class AcademicPromotionDecisionExportTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAssessmentSeeder::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------ Fixtures

    private function country(string $iso2 = 'BD'): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => $iso2],
            ['name' => 'Bangladesh', 'iso3' => strtoupper($iso2).'P', 'phone_code' => '880', 'status' => true]
        );
    }

    private function system(Country $country): EducationSystem
    {
        return EducationSystem::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $country->id, 'code' => 'general'],
            ['name' => 'General Education', 'display_order' => 0, 'status' => true]
        );
    }

    private function level(EducationSystem $system): AcademicLevel
    {
        return AcademicLevel::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $system->country_id, 'education_system_id' => $system->id, 'code' => 'secondary'],
            ['name' => 'Secondary', 'display_order' => 1, 'status' => true]
        );
    }

    private function classGrade(AcademicLevel $level, string $code, string $name, int $order): ClassGrade
    {
        return ClassGrade::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $level->country_id, 'education_system_id' => $level->education_system_id, 'academic_level_id' => $level->id, 'code' => $code],
            ['name' => $name, 'display_order' => $order, 'status' => true]
        );
    }

    private function group(ClassGrade $classGrade, string $code, string $name): AcademicGroup
    {
        return AcademicGroup::withoutGlobalScopes()->firstOrCreate(
            ['class_grade_id' => $classGrade->id, 'code' => $code],
            [
                'country_id' => $classGrade->country_id,
                'education_system_id' => $classGrade->education_system_id,
                'academic_level_id' => $classGrade->academic_level_id,
                'name' => $name,
                'display_order' => 0,
                'status' => true,
            ]
        );
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

    private function assign(Subject $subject, ClassGrade $classGrade, int $displayOrder): SubjectAcademicAssignment
    {
        return SubjectAcademicAssignment::create([
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'requirement_type' => 'mandatory',
            'selection_group_id' => null,
            'display_order' => $displayOrder,
            'status' => 'active',
        ]);
    }

    private function institute(Country $country): Institute
    {
        return Institute::create([
            'name' => 'Export Promo Inst',
            'slug' => str()->slug('Export Promo Inst-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function user(Institute $institute, string $roleSlug, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => 'Export',
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function student(Institute $institute, string $name, ?Branch $branch = null): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => 'PX'.mt_rand(100000, 999999),
            'registration_number' => 'REX'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => '2026-01-01',
        ]);
    }

    private function year(Institute $institute, string $code, bool $current = false): AcademicYear
    {
        return AcademicYear::withoutGlobalScopes()->firstOrCreate(
            ['institute_id' => $institute->id, 'code' => $code],
            ['name' => 'Session '.$code, 'is_current' => $current, 'status' => true]
        );
    }

    private function placement(Institute $institute, Student $student, AcademicYear $academicYear, ClassGrade $class, ?AcademicGroup $group = null): StudentAcademicPlacement
    {
        $placement = StudentAcademicPlacement::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'class_grade_id' => $class->id,
            'academic_group_id' => $group?->id,
            'status' => 'active',
        ]);

        StudentSubjectSelection::create([
            'institute_id' => $institute->id,
            'academic_placement_id' => $placement->id,
            'subject_id' => $this->trackedSubject($institute, $class)->id,
            'is_selected' => true,
            'is_mandatory' => true,
        ]);

        return $placement;
    }

    private ?ClassGrade $trackedClass = null;

    private ?Subject $trackedSubjectRef = null;

    private function trackedSubject(Institute $institute, ClassGrade $class): Subject
    {
        if ($this->trackedSubjectRef === null || $this->trackedClass?->id !== $class->id) {
            $this->trackedClass = $class;
            $this->trackedSubjectRef = $this->subject('Mathematics', 'PXD'.mt_rand(100000, 999999));
            $this->assign($this->trackedSubjectRef, $class, 1);
        }

        return $this->trackedSubjectRef;
    }

    /**
     * Base context: institute + owner + class8 + Science group + 2026 year +
     * a mandatory subject assigned to the class.
     */
    private function context(): array
    {
        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'exp-owner');
        $class = $this->classGrade($level, 'exp-c8', 'Class 8', 0);
        $group = $this->group($class, 'exp-sci', 'Science');
        $year = $this->year($institute, '2026', true);
        $math = $this->trackedSubject($institute, $class);

        return compact('country', 'level', 'institute', 'owner', 'class', 'group', 'year', 'math');
    }

    /**
     * Directly published final result + frozen snapshot rows for the given
     * placements (no live marks involved — the snapshot IS the frozen data).
     *
     * @param  array<int, array<string, mixed>>  $snapshots  per placement:
     *                                                       placement, gpa (?float), failed (int), row (?array)
     * @param  Branch|null  $branch  optional owning branch
     */
    private function publishedResult(array $c, array $snapshots, string $name = 'Annual 2026', ?Branch $branch = null): AcademicFinalResult
    {
        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $branch?->id,
            'academic_year_id' => $c['year']->id,
            'class_grade_id' => $c['class']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Scheme '.$name,
            'status' => 'active',
            'display_order' => 1,
        ]);

        $policy = AcademicFinalResultPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $branch?->id,
            'scheme_id' => $scheme->id,
            'name' => $name.' Policy',
        ]);

        $result = AcademicFinalResult::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $branch?->id,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => $name,
            'status' => AcademicFinalResult::STATUS_PUBLISHED,
            'locked_at' => now(),
            'published_at' => now(),
        ]);

        foreach ($snapshots as $entry) {
            AcademicFinalResultStudent::create([
                'result_id' => $result->id,
                'placement_id' => $entry['placement']->id,
                'gpa' => $entry['gpa'] ?? null,
                'gpa_status' => ($entry['gpa'] ?? null) !== null
                    ? AcademicFinalResultStudent::GPA_COMPUTED
                    : AcademicFinalResultStudent::GPA_UNAVAILABLE,
                'passed_count' => count($entry['row'] ?? []) - (int) ($entry['failed'] ?? 0),
                'failed_count' => (int) ($entry['failed'] ?? 0),
            ]);

            if (isset($entry['row'])) {
                AcademicFinalResultRow::create([
                    'result_id' => $result->id,
                    'placement_id' => $entry['placement']->id,
                    'subject_id' => $c['math']->id,
                    'status' => 'computed',
                    'aggregate' => $entry['row']['aggregate'] ?? null,
                    'grade' => $entry['row']['grade'] ?? null,
                    'grade_point' => $entry['row']['grade_point'] ?? null,
                    'subject_status' => $entry['row']['subject_status'] ?? null,
                    'gpa_included' => $entry['row']['gpa_included'] ?? true,
                ]);
            }
        }

        return $result;
    }

    private function promotionPolicy(array $c, AcademicFinalResult $result, string $name = 'Class 8 Rules'): PromotionPolicy
    {
        return PromotionPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $result->branch_id,
            'name' => $name,
            'academic_year_id' => $c['year']->id,
            'class_grade_id' => $c['class']->id,
            'academic_group_id' => $c['group']->id,
            'status' => 'active',
            'created_by' => $c['owner']->id,
        ]);
    }

    private function rule(PromotionPolicy $policy, string $ruleType, ?string $operator, ?string $value, string $pass, string $fail, int $order): PromotionPolicyRule
    {
        return PromotionPolicyRule::create([
            'policy_id' => $policy->id,
            'rule_type' => $ruleType,
            'field' => match ($ruleType) {
                PromotionPolicyRule::RULE_GPA_THRESHOLD => PromotionPolicyRule::FIELD_GPA,
                PromotionPolicyRule::RULE_CONDITIONAL => PromotionPolicyRule::FIELD_FAILED_COUNT,
                PromotionPolicyRule::RULE_MAX_FAILED_SUBJECTS => PromotionPolicyRule::FIELD_FAILED_COUNT,
                default => null,
            },
            'operator' => $operator,
            'value' => $value,
            'pass_action' => $pass,
            'fail_action' => $fail,
            'display_order' => $order,
            'status' => true,
        ]);
    }

    private function decision(array $c, AcademicFinalResult $result, string $policyName = 'Class 8 Rules', ?PromotionPolicy $existing = null): PromotionDecision
    {
        $policy = $existing ?? $this->promotionPolicy($c, $result, $policyName);
        $this->rule($policy, PromotionPolicyRule::RULE_OVERALL_PASS, null, null, 'promoted', 'repeat', 1);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.promotions.decisions.store', $policy), ['result_id' => $result->id])
            ->assertRedirect();

        return PromotionDecision::query()->where('result_id', $result->id)->orderByDesc('id')->firstOrFail();
    }

    private function approve(array $c, PromotionDecision $decision, StudentAcademicPlacement $source, ClassGrade $targetClass, ?AcademicGroup $targetGroup, AcademicYear $targetYear): void
    {
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.promotions.decisions.approve', $decision), [
                'target_year_id' => $targetYear->id,
                'targets' => [
                    $source->id => ['class_grade_id' => $targetClass->id, 'academic_group_id' => $targetGroup?->id],
                ],
            ])
            ->assertRedirect();
    }

    private function exportUrl(PromotionDecision $decision): string
    {
        return route('settings.academic.promotions.decisions.export', $decision);
    }

    private function sheetUrl(PromotionDecision $decision): string
    {
        return route('settings.academic.promotions.decisions.sheet', $decision);
    }

    // ------------------------------------------------------------ CSV helpers

    private function csvContent(TestResponse $response): string
    {
        return $response->streamedContent();
    }

    private function parseCsv(string $content): array
    {
        $content = ltrim($content, "\xEF\xBB\xBF");

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function csvRowsFor(TestResponse $response): array
    {
        $all = $this->parseCsv($this->csvContent($response));
        array_shift($all);

        return $all;
    }

    // -------------------------------------------------------------- Tests

    public function test_authorized_promotion_decision_export_succeeds(): void
    {
        $c = $this->context();
        $promoted = $this->placement($c['institute'], $this->student($c['institute'], 'Rahim'), $c['year'], $c['class'], $c['group']);
        $repeater = $this->placement($c['institute'], $this->student($c['institute'], 'Karim'), $c['year'], $c['class'], $c['group']);

        $result = $this->publishedResult($c, [
            ['placement' => $promoted, 'gpa' => 4.75, 'failed' => 0, 'row' => ['aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS']],
            ['placement' => $repeater, 'gpa' => 1.5, 'failed' => 2, 'row' => ['aggregate' => 30.0, 'grade' => 'F', 'grade_point' => 0.0, 'subject_status' => 'FAIL']],
        ]);

        $decision = $this->decision($c, $result);

        $response = $this->actingAs($c['owner'], 'institute_user')
            ->get($this->exportUrl($decision))
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('promotion-decision-annual-2026', (string) $response->headers->get('content-disposition'));

        $rows = $this->csvRowsFor($response);

        $this->assertCount(2, $rows);

        $ra = collect($rows)->first(fn ($row) => $row[1] === 'Rahim Student');
        $this->assertNotNull($ra);
        $this->assertSame('Session 2026', $ra[4]);
        $this->assertSame('Class 8', $ra[5]);
        $this->assertSame('Science', $ra[6]);
        $this->assertSame('4.75', $ra[7]);
        $this->assertSame('0', $ra[8]);
        $this->assertSame('Promoted', $ra[10]);
        $this->assertStringContainsString('Overall result passed', $ra[11]);
        $this->assertSame('Yes', $ra[12]);
        $this->assertSame('—', $ra[13]);
        $this->assertSame('—', $ra[15]);

        $kr = collect($rows)->first(fn ($row) => $row[1] === 'Karim Student');
        $this->assertNotNull($kr);
        $this->assertSame('Repeat', $kr[10]);
        $this->assertStringContainsString('Overall result not passed', $kr[11]);
        $this->assertSame('No', $kr[12]);
    }

    public function test_promotion_decision_export_requires_promotion_manage_permission(): void
    {
        $c = $this->context();
        $placed = $this->placement($c['institute'], $this->student($c['institute'], 'Rahim'), $c['year'], $c['class'], $c['group']);
        $decision = $this->decision($c, $this->publishedResult($c, [
            ['placement' => $placed, 'gpa' => 4.75, 'failed' => 0, 'row' => ['aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS']],
        ]));

        $teacher = $this->user($c['institute'], 'teacher', 'exp-teacher');

        $this->actingAs($teacher, 'institute_user')
            ->get($this->exportUrl($decision))
            ->assertForbidden();

        $this->actingAs($teacher, 'institute_user')
            ->get($this->sheetUrl($decision))
            ->assertForbidden();
    }

    public function test_promotion_decision_export_guest_is_redirected_to_login(): void
    {
        $this->get(route('settings.academic.promotions.decisions.export', 1))
            ->assertRedirect();

        $this->get(route('settings.academic.promotions.decisions.sheet', 1))
            ->assertRedirect();
    }

    public function test_cross_tenant_promotion_decision_export_is_404(): void
    {
        $c = $this->context();
        $other = $this->context();

        $placed = $this->placement($other['institute'], $this->student($other['institute'], 'Alien'), $other['year'], $other['class'], $other['group']);
        $foreignDecision = $this->decision($other, $this->publishedResult($other, [
            ['placement' => $placed, 'gpa' => 4.75, 'failed' => 0, 'row' => ['aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS']],
        ]));

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->exportUrl($foreignDecision))
            ->assertNotFound();

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetUrl($foreignDecision))
            ->assertNotFound();

        $this->actingAs($other['owner'], 'institute_user')
            ->get($this->exportUrl($foreignDecision))
            ->assertOk();
    }

    public function test_cross_branch_promotion_decision_export_is_404(): void
    {
        $c = $this->context();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $branchB = $this->branch($c['institute'], 'Branch B');
        $managerA = $this->user($c['institute'], 'branch-manager', 'exp-bmgr', $branchA);

        $studentB = $this->student($c['institute'], 'Lokal', $branchB);
        $placedB = $this->placement($c['institute'], $studentB, $c['year'], $c['class'], $c['group']);

        $branchResult = $this->publishedResult($c, [
            ['placement' => $placedB, 'gpa' => 4.75, 'failed' => 0, 'row' => ['aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS']],
        ], name: 'Branch B Result', branch: $branchB);

        $decision = $this->decision($c, $branchResult);

        // The decision's branch was inherited from the published result.
        $this->assertSame((int) $branchB->id, (int) $decision->branch_id);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchA->id);

        $this->actingAs($managerA, 'institute_user')
            ->get($this->exportUrl($decision))
            ->assertNotFound();

        $this->actingAs($managerA, 'institute_user')
            ->get($this->sheetUrl($decision))
            ->assertNotFound();

        TenantContext::set($c['institute']->id);
        BranchContext::set(null);

        $exportResponse = $this->actingAs($c['owner'], 'institute_user')
            ->get($this->exportUrl($decision))
            ->assertOk();

        $this->assertStringContainsString('Lokal Student', $exportResponse->streamedContent());
    }

    public function test_export_reads_the_frozen_verdicts_of_that_decision_only(): void
    {
        $c = $this->context();
        $placed = $this->placement($c['institute'], $this->student($c['institute'], 'Rahim'), $c['year'], $c['class'], $c['group']);

        // Two published results over the SAME placement carry different frozen
        // snapshots and therefore different verdicts. Each decision exports its
        // own frozen verdict + snapshot — never the other result's numbers.
        $passing = $this->publishedResult($c, [
            ['placement' => $placed, 'gpa' => 4.75, 'failed' => 0, 'row' => ['aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS']],
        ], name: 'Term 1 2026');

        $failing = $this->publishedResult($c, [
            ['placement' => $placed, 'gpa' => 1.5, 'failed' => 2, 'row' => ['aggregate' => 30.0, 'grade' => 'F', 'grade_point' => 0.0, 'subject_status' => 'FAIL']],
        ], name: 'Annual 2026');

        $decisionA = $this->decision($c, $passing, 'Rules A');
        $decisionB = $this->decision($c, $failing, 'Rules B');

        $bodyA = $this->csvContent(
            $this->actingAs($c['owner'], 'institute_user')->get($this->exportUrl($decisionA))->assertOk(),
        );
        $bodyB = $this->csvContent(
            $this->actingAs($c['owner'], 'institute_user')->get($this->exportUrl($decisionB))->assertOk(),
        );

        $this->assertStringContainsString('Promoted', $bodyA);
        $this->assertStringContainsString('4.75', $bodyA);
        $this->assertStringNotContainsString('Repeat', $bodyA);

        $this->assertStringContainsString('Repeat', $bodyB);
        $this->assertStringContainsString('1.50', $bodyB);
        $this->assertStringNotContainsString('Promoted', $bodyB);
    }

    public function test_csv_headers_and_reasons_escaping_are_correct(): void
    {
        $c = $this->context();
        $placed = $this->placement($c['institute'], $this->student($c['institute'], 'Rahim'), $c['year'], $c['class'], $c['group']);
        $result = $this->publishedResult($c, [
            ['placement' => $placed, 'gpa' => 4.75, 'failed' => 0, 'row' => ['aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS']],
        ]);
        $decision = $this->decision($c, $result);

        // Multi-line reason with a quote to exercise fputcsv quoting.
        PromotionDecisionItem::query()->where('decision_id', $decision->id)->update([
            'reasons' => ["Overall result passed → promoted\nSecond line \"quoted\""],
        ]);

        $all = $this->parseCsv(
            $this->csvContent($this->actingAs($c['owner'], 'institute_user')->get($this->exportUrl($decision))->assertOk()),
        );

        $this->assertSame([
            '#', 'Student', 'Student ID', 'Registration Number', 'Source Academic Year',
            'Source Class / Grade', 'Source Group / Stream', 'GPA', 'Subjects Failed',
            'Subjects Incomplete', 'Verdict', 'Reasons', 'Placement Needed',
            'Target Class / Grade', 'Target Group / Stream', 'Next-Year Placement',
        ], $all[0]);

        $data = array_slice($all, 1);
        $this->assertCount(1, $data);
        $this->assertSame('Rahim Student', $data[0][1]);
        $this->assertSame("Overall result passed → promoted\nSecond line \"quoted\"", $data[0][11]);
    }

    public function test_approved_decision_export_lists_target_and_next_placement(): void
    {
        $c = $this->context();
        $promoted = $this->placement($c['institute'], $this->student($c['institute'], 'Rahim'), $c['year'], $c['class'], $c['group']);
        $repeater = $this->placement($c['institute'], $this->student($c['institute'], 'Karim'), $c['year'], $c['class'], $c['group']);

        $result = $this->publishedResult($c, [
            ['placement' => $promoted, 'gpa' => 4.75, 'failed' => 0, 'row' => ['aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS']],
            ['placement' => $repeater, 'gpa' => 1.5, 'failed' => 2, 'row' => ['aggregate' => 30.0, 'grade' => 'F', 'grade_point' => 0.0, 'subject_status' => 'FAIL']],
        ]);

        $decision = $this->decision($c, $result);

        $class9 = $this->classGrade($c['level'], 'exp-c9', 'Class 9', 1);
        $group9 = $this->group($class9, 'exp-sci9', 'Science 9');
        $this->trackedSubject($c['institute'], $class9);
        $year2027 = $this->year($c['institute'], '2027');

        $this->approve($c, $decision, $promoted, $class9, $group9, $year2027);

        $rows = $this->csvRowsFor(
            $this->actingAs($c['owner'], 'institute_user')->get($this->exportUrl($decision))->assertOk(),
        );

        $ra = collect($rows)->first(fn ($row) => $row[1] === 'Rahim Student');
        $this->assertNotNull($ra);
        $this->assertSame('Class 9', $ra[13]);
        $this->assertSame('Science 9', $ra[14]);
        $this->assertSame('Session 2027 · Class 9', $ra[15]);

        $kr = collect($rows)->first(fn ($row) => $row[1] === 'Karim Student');
        $this->assertNotNull($kr);
        $this->assertSame('—', $kr[13]);
        $this->assertSame('—', $kr[15]);
    }

    public function test_export_is_strictly_read_only(): void
    {
        $c = $this->context();
        $placed = $this->placement($c['institute'], $this->student($c['institute'], 'Rahim'), $c['year'], $c['class'], $c['group']);
        $decision = $this->decision($c, $this->publishedResult($c, [
            ['placement' => $placed, 'gpa' => 4.75, 'failed' => 0, 'row' => ['aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS']],
        ]));

        $before = [
            'decisions' => PromotionDecision::query()->count(),
            'items' => PromotionDecisionItem::query()->count(),
            'placements' => StudentAcademicPlacement::query()->count(),
            'snapshots' => AcademicFinalResultStudent::query()->count(),
            'rows' => AcademicFinalResultRow::query()->count(),
        ];

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->exportUrl($decision))
            ->assertOk();

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetUrl($decision))
            ->assertOk();

        $this->assertSame($before['decisions'], PromotionDecision::query()->count());
        $this->assertSame($before['items'], PromotionDecisionItem::query()->count());
        $this->assertSame($before['placements'], StudentAcademicPlacement::query()->count());
        $this->assertSame($before['snapshots'], AcademicFinalResultStudent::query()->count());
        $this->assertSame($before['rows'], AcademicFinalResultRow::query()->count());
    }

    public function test_promotion_sheet_renders_the_frozen_verdicts(): void
    {
        $c = $this->context();
        $promoted = $this->placement($c['institute'], $this->student($c['institute'], 'Rahim'), $c['year'], $c['class'], $c['group']);
        $repeater = $this->placement($c['institute'], $this->student($c['institute'], 'Karim'), $c['year'], $c['class'], $c['group']);

        $result = $this->publishedResult($c, [
            ['placement' => $promoted, 'gpa' => 4.75, 'failed' => 0, 'row' => ['aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS']],
            ['placement' => $repeater, 'gpa' => 1.5, 'failed' => 2, 'row' => ['aggregate' => 30.0, 'grade' => 'F', 'grade_point' => 0.0, 'subject_status' => 'FAIL']],
        ]);

        $decision = $this->decision($c, $result);

        $response = $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetUrl($decision))
            ->assertOk();

        $html = $response->getContent() ?? '';
        $this->assertStringContainsString('Promotion Sheet', $html);
        $this->assertStringContainsString('Rahim Student', $html);
        $this->assertStringContainsString('Karim Student', $html);
        $this->assertStringContainsString('Promoted', $html);
        $this->assertStringContainsString('Repeat', $html);
        $this->assertStringContainsString('4.75', $html);
        $this->assertStringContainsString('Overall result passed', $html);
        $this->assertStringContainsString('Overall result not passed', $html);
    }
}
