<?php

namespace Tests\Feature;

use App\Models\AcademicAssessment;
use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicStudentMark;
use App\Models\AcademicYear;
use App\Models\AssessmentSubject;
use App\Models\AssessmentSubjectComponent;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Component;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\GradeScale;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Services\AcademicFinalResultLifecycleService;
use App\Services\AcademicGradingService;
use App\Services\AcademicResultAggregationService as AggregationService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Database\Seeders\AcademicAssessmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Step 43 — per-assessment lock/unlock + education audit trail.
 *
 * A locked assessment refuses marks entry, configuration updates and deletion
 * until an explicitly permission-gated unlock. Every important mutation in the
 * assessment/result domain writes an audit_logs row (module=education) through
 * the shared AcademicAssessmentAuditService.
 */
class AcademicAssessmentLockAuditTest extends TestCase
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
            ['name' => 'Bangladesh', 'iso3' => strtoupper($iso2).'L', 'phone_code' => '880', 'status' => true]
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

    private function classGrade(AcademicLevel $level, string $code = 'lk-c8'): ClassGrade
    {
        return ClassGrade::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $level->country_id, 'education_system_id' => $level->education_system_id, 'academic_level_id' => $level->id, 'code' => $code],
            ['name' => 'Class 8', 'display_order' => 0, 'status' => true]
        );
    }

    private function group(ClassGrade $classGrade, string $code = 'lk-sci'): AcademicGroup
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

    private function assign(Subject $subject, ClassGrade $classGrade, string $requirementType, int $displayOrder): SubjectAcademicAssignment
    {
        return SubjectAcademicAssignment::create([
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'requirement_type' => $requirementType,
            'selection_group_id' => null,
            'display_order' => $displayOrder,
            'status' => 'active',
        ]);
    }

    private function institute(Country $country, string $name = 'LK Inst'): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
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
            'first_name' => 'LK',
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function student(Institute $institute, ?Branch $branch = null, string $name = 'Rahim'): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => 'LK'.mt_rand(100000, 999999),
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

    private function writtenComponent(): Component
    {
        return Component::where('slug', 'written')->firstOrFail();
    }

    private function curriculum(): array
    {
        $country = $this->country();
        $classGrade = $this->classGrade($this->level($this->system($country)));
        $institute = $this->institute($country);
        $math = $this->subject('Mathematics', 'LK100001');
        $english = $this->subject('English', 'LK100002');

        $this->assign($math, $classGrade, 'mandatory', 1);
        $this->assign($english, $classGrade, 'mandatory', 2);

        return [
            'country' => $country,
            'class_grade' => $classGrade,
            'group' => $this->group($classGrade),
            'institute' => $institute,
            'owner' => $this->user($institute, 'institute-owner', 'lk-owner'),
            'math' => $math,
            'english' => $english,
        ];
    }

    private function assessment(array $c, string $name, ?Branch $branch = null): AcademicAssessment
    {
        $assessment = AcademicAssessment::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $branch?->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => $name,
            'status' => 'scheduled',
            'display_order' => 1,
        ]);

        $written = $this->writtenComponent();

        foreach ([$c['math'], $c['english']] as $order => $subject) {
            $config = AssessmentSubject::create([
                'assessment_id' => $assessment->id,
                'subject_id' => $subject->id,
                'display_order' => $order + 1,
                'status' => 'active',
            ]);

            AssessmentSubjectComponent::create([
                'assessment_subject_id' => $config->id,
                'component_id' => $written->id,
                'full_mark' => 100,
                'pass_mark' => 33,
                'mandatory_pass' => false,
                'display_order' => 1,
                'status' => 'active',
            ]);
        }

        return $assessment;
    }

    private function placement(array $c, Student $student): StudentAcademicPlacement
    {
        $placement = StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'status' => 'active',
        ]);

        foreach ([$c['math'], $c['english']] as $subject) {
            StudentSubjectSelection::create([
                'institute_id' => $c['institute']->id,
                'academic_placement_id' => $placement->id,
                'subject_id' => $subject->id,
                'is_selected' => true,
                'is_mandatory' => false,
            ]);
        }

        return $placement;
    }

    private function componentFor(AssessmentSubject $config): AssessmentSubjectComponent
    {
        return AssessmentSubjectComponent::where('assessment_subject_id', $config->id)->firstOrFail();
    }

    private function mark(StudentAcademicPlacement $placement, AssessmentSubject $config, float $obtained, string $status = 'entered'): AcademicStudentMark
    {
        $assessmentComponent = $this->componentFor($config);

        return AcademicStudentMark::create([
            'institute_id' => $placement->institute_id,
            'academic_assessment_id' => $config->assessment_id,
            'assessment_subject_id' => $config->id,
            'assessment_component_id' => $assessmentComponent->id,
            'student_id' => $placement->student_id,
            'academic_placement_id' => $placement->id,
            'obtained_mark' => $status === 'absent' ? null : $obtained,
            'status' => $status,
        ]);
    }

    private function enterAll(StudentAcademicPlacement $placement, AcademicAssessment $assessment): void
    {
        foreach ($assessment->subjects()->with('components')->get() as $config) {
            $this->mark($placement, $config, 80);
        }
    }

    private function act(array $c, string $verb, string $routeName, mixed $params = [], array $data = [])
    {
        TenantContext::set($c['institute']->id);

        $params = is_array($params) ? $params : [$params];

        return $this->actingAs($c['owner'], 'institute_user')->{$verb}(route($routeName, $params), $data);
    }

    private function auditRows(int $instituteId, string $action): Collection
    {
        return AuditLog::query()
            ->where('institute_id', $instituteId)
            ->where('module', 'education')
            ->where('action', $action)
            ->orderBy('id')
            ->get();
    }

    private function marksPayload(StudentAcademicPlacement $placement, AssessmentSubject $config): array
    {
        $component = $this->componentFor($config);

        return [
            'rows' => [
                $placement->id => [
                    'status' => 'entered',
                    'marks' => [$component->id => 80],
                ],
            ],
        ];
    }

    // ---------------------------------------------------------- Lock behaviour

    public function test_locked_assessment_rejects_marks_entry(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');
        $config = $assessment->subjects()->firstOrFail();
        $placement = $this->placement($c, $this->student($c['institute']));

        $this->act($c, 'post', 'settings.academic.assessments.lock', [$assessment])->assertRedirect();
        $this->assertTrue($assessment->refresh()->isLocked());

        $this->act($c, 'post', 'settings.academic.assessments.marks.store', [$assessment, $config], $this->marksPayload($placement, $config))
            ->assertStatus(422);

        $this->assertDatabaseCount('academic_student_marks', 0);
    }

    public function test_locked_assessment_rejects_config_update_and_destroy(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');

        $this->act($c, 'post', 'settings.academic.assessments.lock', [$assessment])->assertRedirect();

        $payload = [
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Edited Name',
            'status' => 'open',
            'subjects' => [
                ['subject_id' => $c['math']->id, 'components' => [
                    ['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33],
                ]],
            ],
        ];

        $this->act($c, 'put', 'settings.academic.assessments.update', [$assessment], $payload)->assertStatus(422);
        $this->assertSame('Mid Term 2026', $assessment->refresh()->name);

        $this->act($c, 'delete', 'settings.academic.assessments.destroy', [$assessment])->assertStatus(422);
        $this->assertDatabaseHas('academic_assessments', ['id' => $assessment->id]);
    }

    public function test_unlock_restores_marks_entry_and_editing(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');
        $config = $assessment->subjects()->firstOrFail();
        $placement = $this->placement($c, $this->student($c['institute']));

        $this->act($c, 'post', 'settings.academic.assessments.lock', [$assessment])->assertRedirect();
        $this->act($c, 'post', 'settings.academic.assessments.unlock', [$assessment])->assertRedirect();
        $this->assertFalse($assessment->refresh()->isLocked());

        $this->act($c, 'post', 'settings.academic.assessments.marks.store', [$assessment, $config], $this->marksPayload($placement, $config))
            ->assertRedirect();

        $this->assertDatabaseHas('academic_student_marks', [
            'academic_assessment_id' => $assessment->id,
            'assessment_subject_id' => $config->id,
            'academic_placement_id' => $placement->id,
            'status' => 'entered',
            'obtained_mark' => 80,
        ]);
    }

    public function test_locked_assessment_remains_readable_for_sheet_and_readiness(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');
        $placement = $this->placement($c, $this->student($c['institute']));
        $this->enterAll($placement, $assessment);

        $this->act($c, 'post', 'settings.academic.assessments.lock', [$assessment])->assertRedirect();

        $this->act($c, 'get', 'settings.academic.assessments.marks-sheet', [$assessment])->assertOk();
        $this->act($c, 'get', 'settings.academic.assessments.readiness', [$assessment])->assertOk();
        $this->act($c, 'get', 'settings.academic.assessments.show', [$assessment])->assertOk();
    }

    public function test_lock_is_idempotent_and_does_not_duplicate_audit_rows(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');

        $this->act($c, 'post', 'settings.academic.assessments.lock', [$assessment])->assertRedirect();
        $this->act($c, 'post', 'settings.academic.assessments.lock', [$assessment])->assertRedirect();

        $this->assertCount(1, $this->auditRows($c['institute']->id, 'assessment.locked'));

        $this->act($c, 'post', 'settings.academic.assessments.unlock', [$assessment])->assertRedirect();
        $this->act($c, 'post', 'settings.academic.assessments.unlock', [$assessment])->assertRedirect();

        $this->assertCount(1, $this->auditRows($c['institute']->id, 'assessment.unlocked'));
    }

    // -------------------------------------------------------------- Security

    public function test_lock_unlock_require_education_manage_permission(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');
        $accountant = $this->user($c['institute'], 'accountant', 'lk-acct');

        TenantContext::set($c['institute']->id);

        $this->actingAs($accountant, 'institute_user')
            ->post(route('settings.academic.assessments.lock', $assessment))
            ->assertForbidden();

        $this->actingAs($accountant, 'institute_user')
            ->post(route('settings.academic.assessments.unlock', $assessment))
            ->assertForbidden();

        $this->assertFalse($assessment->refresh()->isLocked());
    }

    public function test_cross_tenant_lock_and_unlock_are_404(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');

        $otherCountry = $this->country('IN', 'India');
        $otherInstitute = $this->institute($otherCountry, 'Other LK Inst');
        $otherOwner = $this->user($otherInstitute, 'institute-owner', 'lk-other');

        TenantContext::set($otherInstitute->id);

        $this->actingAs($otherOwner, 'institute_user')
            ->post(route('settings.academic.assessments.lock', $assessment))
            ->assertStatus(404);

        $this->actingAs($otherOwner, 'institute_user')
            ->post(route('settings.academic.assessments.unlock', $assessment))
            ->assertStatus(404);
    }

    public function test_cross_branch_lock_and_unlock_are_404(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $branchB = $this->branch($c['institute'], 'Branch B');
        $adminB = $this->user($c['institute'], 'institute-admin', 'lk-admin-b', $branchB);

        $assessment = $this->assessment($c, 'Branch A Mid Term', $branchA);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchB->id);

        $this->actingAs($adminB, 'institute_user')
            ->post(route('settings.academic.assessments.lock', $assessment))
            ->assertStatus(404);

        $this->actingAs($adminB, 'institute_user')
            ->post(route('settings.academic.assessments.unlock', $assessment))
            ->assertStatus(404);

        $this->assertFalse($assessment->refresh()->isLocked());
    }

    public function test_branch_admin_can_lock_their_own_branch_assessment(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $adminA = $this->user($c['institute'], 'institute-admin', 'lk-admin-a', $branchA);

        $assessment = $this->assessment($c, 'Branch A Mid Term', $branchA);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchA->id);

        $this->actingAs($adminA, 'institute_user')
            ->post(route('settings.academic.assessments.lock', $assessment))
            ->assertRedirect();

        $this->assertTrue($assessment->refresh()->isLocked());
    }

    // ----------------------------------------------------------- Audit trail

    public function test_assessment_create_update_delete_are_audited(): void
    {
        $c = $this->curriculum();

        $this->act($c, 'post', 'settings.academic.assessments.store', [], [
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Audited Assessment',
            'status' => 'scheduled',
            'subjects' => [
                ['subject_id' => $c['math']->id, 'components' => [
                    ['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33],
                ]],
            ],
        ])->assertRedirect();

        $assessment = AcademicAssessment::where('name', 'Audited Assessment')->firstOrFail();

        $created = $this->auditRows($c['institute']->id, 'assessment.created');
        $this->assertCount(1, $created);
        $this->assertSame($assessment->id, $created[0]->record_id);
        $this->assertSame($c['owner']->id, $created[0]->user_id);

        $this->act($c, 'put', 'settings.academic.assessments.update', [$assessment], [
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Audited Assessment (edited)',
            'status' => 'open',
            'subjects' => [
                ['subject_id' => $c['math']->id, 'components' => [
                    ['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33],
                ]],
            ],
        ])->assertRedirect();

        $updated = $this->auditRows($c['institute']->id, 'assessment.updated');
        $this->assertCount(1, $updated);
        $this->assertStringContainsString('Audited Assessment (edited)', (string) $updated[0]->new_values);
        $this->assertStringContainsString('Audited Assessment', (string) $updated[0]->old_values);

        $this->act($c, 'delete', 'settings.academic.assessments.destroy', [$assessment])->assertRedirect();

        $deleted = $this->auditRows($c['institute']->id, 'assessment.deleted');
        $this->assertCount(1, $deleted);
        $this->assertSame($assessment->id, $deleted[0]->record_id);
        $this->assertDatabaseMissing('academic_assessments', ['id' => $assessment->id]);
    }

    public function test_marks_entry_is_audited(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');
        $config = $assessment->subjects()->firstOrFail();
        $placement = $this->placement($c, $this->student($c['institute']));

        $this->act($c, 'post', 'settings.academic.assessments.marks.store', [$assessment, $config], $this->marksPayload($placement, $config))
            ->assertRedirect();

        $rows = $this->auditRows($c['institute']->id, 'marks.entered');
        $this->assertCount(1, $rows);
        $this->assertSame($config->id, $rows[0]->record_id);
        $this->assertStringContainsString('"records_saved":1', (string) $rows[0]->new_values);
    }

    public function test_lock_and_unlock_are_audited(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');

        $this->act($c, 'post', 'settings.academic.assessments.lock', [$assessment])->assertRedirect();

        $locked = $this->auditRows($c['institute']->id, 'assessment.locked');
        $this->assertCount(1, $locked);
        $this->assertSame($assessment->id, $locked[0]->record_id);
        $this->assertSame($c['owner']->id, $locked[0]->user_id);

        $this->act($c, 'post', 'settings.academic.assessments.unlock', [$assessment])->assertRedirect();

        $unlocked = $this->auditRows($c['institute']->id, 'assessment.unlocked');
        $this->assertCount(1, $unlocked);
        $this->assertSame($assessment->id, $unlocked[0]->record_id);
    }

    public function test_final_result_lifecycle_transitions_are_audited(): void
    {
        $fx = $this->readyFixture();

        $result = $this->startCycle($fx['c'], $fx['policy']);

        $this->act($fx['c'], 'post', 'settings.academic.final-results.approve', $result)->assertRedirect();
        $this->act($fx['c'], 'post', 'settings.academic.final-results.lock', $result)->assertRedirect();
        $this->act($fx['c'], 'post', 'settings.academic.final-results.publish', $result)->assertRedirect();

        $this->assertCount(1, $this->auditRows($fx['c']['institute']->id, 'final_result.created'));
        $this->assertCount(1, $this->auditRows($fx['c']['institute']->id, 'final_result.approved'));
        $this->assertCount(1, $this->auditRows($fx['c']['institute']->id, 'final_result.locked'));
        $this->assertCount(1, $this->auditRows($fx['c']['institute']->id, 'final_result.published'));
        $this->assertSame('published', $result->refresh()->status);
    }

    // ----------------------------------------------- Final-result fixture set

    private function scheme(array $c, string $name, array $items): AcademicResultAggregationScheme
    {
        return app(AggregationService::class)->store(
            $c['institute'],
            null,
            (int) $c['owner']->id,
            [
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => $name,
                'status' => 'active',
                'display_order' => 1,
            ],
            $items
        );
    }

    private function gradeRows(): array
    {
        return [
            ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
            ['grade' => 'F', 'min_score' => 0, 'max_score' => 39, 'grade_point' => 0.0, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
        ];
    }

    private function instituteScale(array $c): GradeScale
    {
        return app(AcademicGradingService::class)->store([
            'name' => 'Lock Audit Scale',
            'institute_id' => (int) $c['institute']->id,
            'academic_level_id' => (int) $c['class_grade']->academic_level_id,
            'gpa_mode' => 'equal_weight',
            'optional_subject_gpa' => 'included',
            'display_order' => 1,
            'status' => true,
        ], $this->gradeRows());
    }

    private function lifecycle(): AcademicFinalResultLifecycleService
    {
        return app(AcademicFinalResultLifecycleService::class);
    }

    private function policy(array $c, AcademicResultAggregationScheme $scheme): AcademicFinalResultPolicy
    {
        return $this->lifecycle()->policyForScheme($c['institute'], $scheme);
    }

    private function readyFixture(string $name = 'Annual Result'): array
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $placement = $this->placement($c, $this->student($c['institute']));
        $this->enterAll($placement, $mid);
        $this->enterAll($placement, $final);
        $scheme = $this->scheme($c, $name, [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $policy = $this->policy($c, $scheme);
        $this->instituteScale($c);

        return [
            'c' => $c,
            'mid' => $mid,
            'final' => $final,
            'placement' => $placement,
            'scheme' => $scheme,
            'policy' => $policy,
        ];
    }

    private function startCycle(array $c, AcademicFinalResultPolicy $policy, string $name = 'Annual Final 2026'): AcademicFinalResult
    {
        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.final-results.store', $policy), ['name' => $name])
            ->assertRedirect();

        return AcademicFinalResult::query()->where('policy_id', $policy->id)->orderByDesc('id')->firstOrFail();
    }
}
