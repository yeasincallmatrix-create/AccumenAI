<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Course;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\Institute;
use App\Models\InstituteCourse;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Education\FeeHeadService;
use App\Services\Education\FeeStructureService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EducationFeeCatalogTest extends TestCase
{
    use DatabaseTransactions;

    private string $password = 'password123';

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function context(Institute $institute): void
    {
        TenantContext::set($institute->id);
    }

    private function country(): Country
    {
        return Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh',
            'iso3' => 'BGD',
            'phone_code' => '+880',
            'status' => true,
        ]);
    }

    private function institute(Country $country, string $name): Institute
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
            'first_name' => 'Staff',
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function course(Institute $institute, string $name, array $fees = []): Course
    {
        $course = Course::create(array_merge([
            'course_code' => 'C'.mt_rand(1000, 9999),
            'name' => $name,
        ], $fees));

        InstituteCourse::create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
        ]);

        return $course;
    }

    private function batch(Institute $institute, Branch $branch, Course $course, string $name): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => $name,
            'batch_code' => 'B'.mt_rand(1000, 9999),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'ongoing',
        ]);
    }

    private function student(Institute $institute, Branch $branch, string $name): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'student_id_number' => 'ST'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => '2026-01-01',
        ]);
    }

    private function enroll(Student $student, Batch $batch): StudentEnrollment
    {
        return StudentEnrollment::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_id' => $batch->course_id,
            'enrollment_date' => '2026-01-05',
            'roll_number' => 'R'.mt_rand(100, 999),
            'status' => 'active',
        ]);
    }

    private function setupAccounting(Institute $institute, ?Branch $branch = null): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch?->id);
    }

    private function feeHead(Institute $institute, ?Branch $branch, string $type, string $name): FeeHead
    {
        return app(FeeHeadService::class)->create(
            $institute->id,
            $branch?->id,
            [
                'type' => $type,
                'name' => $name,
                'description' => 'Test head',
            ],
            null,
        );
    }

    public function test_fee_head_default_income_account_resolves_per_type(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Catalog Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->context($institute);

        $coa = app(ChartOfAccountService::class);

        $admission = $this->feeHead($institute, $branch, 'admission', 'Admission Fee');
        $this->assertSame((int) $coa->accountByCode($institute->id, '4002', $branch->id)->id, (int) $admission->income_coa_id);

        $tuition = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');
        $this->assertSame((int) $coa->accountByCode($institute->id, '4001', $branch->id)->id, (int) $tuition->income_coa_id);

        $other = $this->feeHead($institute, $branch, 'other', 'Library Fee');
        $this->assertSame((int) $coa->accountByCode($institute->id, '4004', $branch->id)->id, (int) $other->income_coa_id);

        $registration = $this->feeHead($institute, $branch, 'registration', 'Registration Fee');
        $this->assertSame((int) $coa->accountByCode($institute->id, '4004', $branch->id)->id, (int) $registration->income_coa_id);

        $exam = $this->feeHead($institute, $branch, 'exam', 'Exam Fee');
        $this->assertSame((int) $coa->accountByCode($institute->id, '4004', $branch->id)->id, (int) $exam->income_coa_id);

        $certificate = $this->feeHead($institute, $branch, 'certificate', 'Certificate Fee');
        $this->assertSame((int) $coa->accountByCode($institute->id, '4004', $branch->id)->id, (int) $certificate->income_coa_id);
    }

    public function test_fee_head_rejects_duplicate_name_in_scope_but_allows_other_branch(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Catalog Institute');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $this->setupAccounting($institute, $branchA);
        $this->setupAccounting($institute, $branchB);
        $this->context($institute);

        $this->feeHead($institute, $branchA, 'admission', 'Admission Fee');

        try {
            $this->feeHead($institute, $branchA, 'admission', 'Admission Fee');
            $this->fail('Duplicate fee head name in the same branch scope should fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors());
        }

        $second = $this->feeHead($institute, $branchB, 'admission', 'Admission Fee');
        $this->assertSame((int) $branchB->id, (int) $second->branch_id);
    }

    public function test_fee_head_scopes_are_exact_branch_or_shared(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Catalog Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->context($institute);

        $this->feeHead($institute, null, 'admission', 'Shared Admission');

        $branchHead = $this->feeHead($institute, $branch, 'admission', 'Shared Admission');
        $this->assertSame((int) $branch->id, (int) $branchHead->branch_id);

        try {
            $this->feeHead($institute, $branch, 'admission', 'Shared Admission');
            $this->fail('Duplicate fee head name in the exact same scope should fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors());
        }
    }

    public function test_fee_heads_are_tenant_isolated(): void
    {
        $country = $this->country();
        $instituteA = $this->institute($country, 'Isolation A');
        $instituteB = $this->institute($country, 'Isolation B');
        $this->setupAccounting($instituteA);
        $this->setupAccounting($instituteB);
        $this->context($instituteA);

        $this->feeHead($instituteA, null, 'admission', 'Admission Fee');

        $this->assertSame(1, FeeHead::query()->where('institute_id', $instituteA->id)->count());

        $this->context($instituteB);
        $this->assertSame(0, FeeHead::query()->where('institute_id', $instituteB->id)->count());
        $this->assertSame(1, FeeHead::withoutGlobalScopes()->where('institute_id', $instituteA->id)->count());
    }

    public function test_fee_structure_requires_assigned_course(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Catalog Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->context($institute);

        $unassigned = Course::create([
            'course_code' => 'X'.mt_rand(1000, 9999),
            'name' => 'Unassigned Course',
        ]);

        $head = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');

        try {
            app(FeeStructureService::class)->create(
                $institute->id,
                $branch->id,
                [
                    'name' => 'S1',
                    'academic_year_id' => null,
                    'course_id' => $unassigned->id,
                    'batch_id' => null,
                    'installments_count' => 1,
                    'installments_interval_days' => 30,
                    'status' => 'active',
                    'items' => [['fee_head_id' => $head->id, 'amount' => 5000]],
                ],
                null,
            );
            $this->fail('A fee structure must reference a course assigned to the institute.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('course_id', $e->errors());
        }
    }

    public function test_fee_structure_rejects_batch_from_other_branch_and_foreign_fee_head(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Catalog Institute');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $this->setupAccounting($institute, $branchA);
        $this->setupAccounting($institute, $branchB);
        $this->context($institute);

        $courseA = $this->course($institute, 'Course A');
        $batchB = $this->batch($institute, $branchB, $courseA, 'Batch in B');
        $headA = $this->feeHead($institute, $branchA, 'course_tuition', 'Tuition A');

        $service = app(FeeStructureService::class);

        try {
            $service->create(
                $institute->id,
                $branchA->id,
                [
                    'name' => 'S1',
                    'academic_year_id' => null,
                    'course_id' => $courseA->id,
                    'batch_id' => $batchB->id,
                    'installments_count' => 1,
                    'installments_interval_days' => 30,
                    'status' => 'active',
                    'items' => [['fee_head_id' => $headA->id, 'amount' => 5000]],
                ],
                null,
            );
            $this->fail('A structure on branch A cannot reference a batch on branch B.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('batch_id', $e->errors());
        }

        $headB = $this->feeHead($institute, $branchB, 'course_tuition', 'Tuition B');
        $batchA = $this->batch($institute, $branchA, $courseA, 'Batch in A');

        try {
            $service->create(
                $institute->id,
                $branchA->id,
                [
                    'name' => 'S2',
                    'academic_year_id' => null,
                    'course_id' => $courseA->id,
                    'batch_id' => $batchA->id,
                    'installments_count' => 1,
                    'installments_interval_days' => 30,
                    'status' => 'active',
                    'items' => [['fee_head_id' => $headB->id, 'amount' => 5000]],
                ],
                null,
            );
            $this->fail('A structure cannot reference a fee head owned by another branch.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.fee_head_id', $e->errors());
        }
    }

    public function test_fee_structure_rejects_duplicate_targets_and_duplicate_heads(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Catalog Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->context($institute);

        $course = $this->course($institute, 'Course A');
        $head = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');

        $service = app(FeeStructureService::class);

        $service->create(
            $institute->id,
            $branch->id,
            [
                'name' => 'S1',
                'academic_year_id' => null,
                'course_id' => $course->id,
                'batch_id' => null,
                'installments_count' => 1,
                'installments_interval_days' => 30,
                'status' => 'active',
                'items' => [['fee_head_id' => $head->id, 'amount' => 5000]],
            ],
            null,
        );

        try {
            $service->create(
                $institute->id,
                $branch->id,
                [
                    'name' => 'S1',
                    'academic_year_id' => null,
                    'course_id' => $course->id,
                    'batch_id' => null,
                    'installments_count' => 1,
                    'installments_interval_days' => 30,
                    'status' => 'active',
                    'items' => [['fee_head_id' => $head->id, 'amount' => 6000]],
                ],
                null,
            );
            $this->fail('Duplicate fee structure targets must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors());
        }

        try {
            $service->create(
                $institute->id,
                $branch->id,
                [
                    'name' => 'S3',
                    'academic_year_id' => null,
                    'course_id' => null,
                    'batch_id' => null,
                    'installments_count' => 1,
                    'installments_interval_days' => 30,
                    'status' => 'active',
                    'items' => [
                        ['fee_head_id' => $head->id, 'amount' => 5000],
                        ['fee_head_id' => $head->id, 'amount' => 1000],
                    ],
                ],
                null,
            );
            $this->fail('A structure cannot list the same fee head twice.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }
    }

    public function test_fee_structure_resolution_prefers_batch_then_course_then_branch_then_shared(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Catalog Institute');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $this->setupAccounting($institute, $branchA);
        $this->setupAccounting($institute, $branchB);
        $this->context($institute);

        $courseA = $this->course($institute, 'Course A');
        $courseB = $this->course($institute, 'Course B');
        $head = $this->feeHead($institute, $branchA, 'course_tuition', 'Tuition Fee');

        $service = app(FeeStructureService::class);

        $make = function (string $name, array $overrides, ?Branch $structureBranch = null) use ($institute, $head, $service) {
            return $service->create(
                $institute->id,
                $structureBranch?->id,
                array_merge([
                    'name' => $name,
                    'academic_year_id' => null,
                    'course_id' => null,
                    'batch_id' => null,
                    'installments_count' => 1,
                    'installments_interval_days' => 30,
                    'status' => 'active',
                    'items' => [['fee_head_id' => $head->id, 'amount' => 0]],
                ], $overrides),
                null,
            );
        };

        $shared = $make('Shared', ['items' => [['fee_head_id' => $head->id, 'amount' => 1000]]], null);
        $branchLevel = $make('Branch Level', ['items' => [['fee_head_id' => $head->id, 'amount' => 2000]]], $branchA);
        $courseLevel = $make('Course Level', ['course_id' => $courseA->id, 'items' => [['fee_head_id' => $head->id, 'amount' => 3000]]], $branchA);
        $batchA1 = $this->batch($institute, $branchA, $courseA, 'Batch A1');
        $batchLevel = $make('Batch Level', ['course_id' => $courseA->id, 'batch_id' => $batchA1->id, 'items' => [['fee_head_id' => $head->id, 'amount' => 4000]]], $branchA);

        $batchA2 = $this->batch($institute, $branchA, $courseA, 'Batch A2');
        $batchB1 = $this->batch($institute, $branchB, $courseB, 'Batch B1');
        $batchB2 = $this->batch($institute, $branchB, $courseA, 'Batch B2');

        $resolve = fn (StudentEnrollment $enrollment) => $service->resolveForEnrollment($enrollment);

        $sA1 = $this->student($institute, $branchA, 'S1');
        $this->assertSame((int) $batchLevel->id, (int) $resolve($this->enroll($sA1, $batchA1))->id);

        $sA2 = $this->student($institute, $branchA, 'S2');
        $this->assertSame((int) $courseLevel->id, (int) $resolve($this->enroll($sA2, $batchA2))->id);

        $sA3 = $this->student($institute, $branchA, 'S3');
        $sA3b = $this->batch($institute, $branchA, $courseB, 'Batch A Course B');
        $this->assertSame((int) $branchLevel->id, (int) $resolve($this->enroll($sA3, $sA3b))->id);

        $sB1 = $this->student($institute, $branchB, 'S4');
        $this->assertSame((int) $shared->id, (int) $resolve($this->enroll($sB1, $batchB2))->id);

        $sB2 = $this->student($institute, $branchB, 'S5');
        $this->assertSame((int) $shared->id, (int) $resolve($this->enroll($sB2, $batchB1))->id);

        $this->assertSame(4, FeeStructure::query()->count());
        $this->assertSame(4, FeeStructureItem::query()->count());
    }

    public function test_inactive_structures_are_excluded_from_resolution(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Catalog Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->context($institute);

        $course = $this->course($institute, 'Course A');
        $head = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');
        $service = app(FeeStructureService::class);

        $service->create(
            $institute->id,
            $branch->id,
            [
                'name' => 'Archived',
                'academic_year_id' => null,
                'course_id' => null,
                'batch_id' => null,
                'installments_count' => 1,
                'installments_interval_days' => 30,
                'status' => 'archived',
                'items' => [['fee_head_id' => $head->id, 'amount' => 1000]],
            ],
            null,
        );

        $batch = $this->batch($institute, $branch, $course, 'Batch A1');
        $student = $this->student($institute, $branch, 'S1');

        $this->assertNull($service->resolveForEnrollment($this->enroll($student, $batch)));
    }
}
