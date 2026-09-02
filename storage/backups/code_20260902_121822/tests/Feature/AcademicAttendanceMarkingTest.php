<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\Course;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentEnrollment;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Step 19 — Attendance marking workflow (AcademicAttendanceController +
 * AcademicAttendanceMarkingService). The write path stays in the legacy
 * `attendance` table; batch is always resolved server-side (never from the
 * frontend); exited students are excluded from new marks but historical rows
 * remain editable; tenant + branch scoping is enforced by `inScope()`.
 */
class AcademicAttendanceMarkingTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------- Fixtures

    private function country(): Country
    {
        return Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh',
            'iso3' => 'BGD',
            'phone_code' => '880',
            'status' => true,
        ]);
    }

    private function system(Country $country): EducationSystem
    {
        return EducationSystem::firstOrCreate(
            ['country_id' => $country->id, 'code' => 'general'],
            ['name' => 'General Education', 'display_order' => 0, 'status' => true]
        );
    }

    private function level(EducationSystem $system): AcademicLevel
    {
        return AcademicLevel::create([
            'country_id' => $system->country_id,
            'education_system_id' => $system->id,
            'name' => 'Secondary',
            'code' => 'secondary',
            'display_order' => 1,
            'status' => true,
        ]);
    }

    private function classGrade(AcademicLevel $level, string $code, string $name): ClassGrade
    {
        return ClassGrade::create([
            'country_id' => $level->country_id,
            'education_system_id' => $level->education_system_id,
            'academic_level_id' => $level->id,
            'name' => $name,
            'code' => $code,
            'display_order' => 0,
            'status' => true,
        ]);
    }

    private function group(ClassGrade $classGrade, string $name = 'General'): AcademicGroup
    {
        return AcademicGroup::create([
            'country_id' => $classGrade->country_id,
            'education_system_id' => $classGrade->education_system_id,
            'academic_level_id' => $classGrade->academic_level_id,
            'class_grade_id' => $classGrade->id,
            'name' => $name,
            'code' => 'g'.mt_rand(10, 99),
            'display_order' => 0,
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

    private function student(Institute $institute, string $name, ?Branch $branch = null): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => 'AM'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => '2026-01-01',
        ]);
    }

    private function year(Institute $institute): AcademicYear
    {
        return AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => 'Session 2026',
            'code' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
            'status' => true,
        ]);
    }

    private function batch(Institute $institute, string $code): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'course_id' => Course::create([
                'course_code' => 'C'.mt_rand(1000, 9999),
                'name' => 'Attendance Course',
            ])->id,
            'name' => 'Batch '.$code,
            'batch_code' => $code,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'ongoing',
        ]);
    }

    private function placement(Institute $institute, Student $student, AcademicYear $year, ClassGrade $class, ?AcademicGroup $group = null): StudentAcademicPlacement
    {
        return StudentAcademicPlacement::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'academic_group_id' => $group?->id,
            'status' => StudentAcademicPlacement::STATUS_ACTIVE,
        ]);
    }

    private function enroll(Student $student, Batch $batch): StudentEnrollment
    {
        return StudentEnrollment::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'roll_number' => 'R'.mt_rand(100, 999),
            'enrollment_date' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    private function base(Institute $institute): array
    {
        $owner = $this->user($institute, 'institute-owner', 'am-owner');
        $year = $this->year($institute);

        return compact('institute', 'owner', 'year');
    }

    private function indexUrl(int $yearId, int $classId, ?int $groupId = null, ?string $date = null): string
    {
        return route('academic-attendance.mark.index', [
            'academic_year_id' => $yearId,
            'class_grade_id' => $classId,
            'academic_group_id' => $groupId,
            'attendance_date' => $date ?? '2026-06-15',
        ]);
    }

    private function storeUrl(): string
    {
        return route('academic-attendance.mark.store');
    }

    // ---------------------------------------------------------------- Tests

    public function test_marking_page_requires_attendance_manage_permission(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Mark Perm Institute');
        $owner = $this->user($institute, 'institute-owner', 'perm-owner');
        $accountant = $this->user($institute, 'accountant', 'perm-acct');
        $year = $this->year($institute);

        $this->actingAs($owner, 'institute_user')
            ->get($this->indexUrl($year->id, $class->id))
            ->assertOk();

        $this->actingAs($accountant, 'institute_user')
            ->get($this->indexUrl($year->id, $class->id))
            ->assertForbidden();

        $this->actingAs($accountant, 'institute_user')
            ->post($this->storeUrl(), [
                'academic_year_id' => $year->id,
                'class_grade_id' => $class->id,
                'attendance_date' => '2026-06-15',
            ])
            ->assertForbidden();
    }

    public function test_index_shows_only_the_selected_class_roster(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class7 = $this->classGrade($level, 'c7', 'Class 7');
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Roster Institute');
        $b = $this->base($institute);
        $group = $this->group($class8);

        $student8a = $this->student($institute, 'Rana');
        $student8b = $this->student($institute, 'Sana');
        $student7 = $this->student($institute, 'OutOfClass');
        $this->placement($institute, $student8a, $b['year'], $class8, $group);
        $this->placement($institute, $student8b, $b['year'], $class8, $group);
        $this->placement($institute, $student7, $b['year'], $class7);

        $this->actingAs($b['owner'], 'institute_user')
            ->get($this->indexUrl($b['year']->id, $class8->id, $group->id))
            ->assertOk()
            ->assertSee('Rana')
            ->assertSee('Sana')
            ->assertDontSee('OutOfClass');
    }

    public function test_store_records_attendance_with_server_resolved_batch(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Save Institute');
        $b = $this->base($institute);
        $group = $this->group($class8);

        $batchA = $this->batch($institute, 'BA-01');
        $batchB = $this->batch($institute, 'BB-02');
        $studentA = $this->student($institute, 'Anowar');
        $studentB = $this->student($institute, 'Beauty');
        $this->placement($institute, $studentA, $b['year'], $class8, $group);
        $this->placement($institute, $studentB, $b['year'], $class8, $group);
        $this->enroll($studentA, $batchA);
        $this->enroll($studentB, $batchB);

        $date = '2026-06-15';
        $this->actingAs($b['owner'], 'institute_user')
            ->from($this->indexUrl($b['year']->id, $class8->id, $group->id, $date))
            ->post($this->storeUrl(), [
                'academic_year_id' => $b['year']->id,
                'class_grade_id' => $class8->id,
                'academic_group_id' => $group->id,
                'attendance_date' => $date,
                'statuses' => [
                    $studentA->id => 'present',
                    $studentB->id => 'absent',
                ],
            ])
            ->assertRedirect($this->indexUrl($b['year']->id, $class8->id, $group->id, $date))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('attendance', [
            'institute_id' => $institute->id,
            'batch_id' => $batchA->id,
            'student_id' => $studentA->id,
            'class_date' => $date,
            'status' => 'present',
            'marked_by' => $b['owner']->id,
        ]);

        $this->assertDatabaseHas('attendance', [
            'institute_id' => $institute->id,
            'batch_id' => $batchB->id,
            'student_id' => $studentB->id,
            'class_date' => $date,
            'status' => 'absent',
            'marked_by' => $b['owner']->id,
        ]);
    }

    public function test_resave_updates_instead_of_duplicating(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Update Institute');
        $b = $this->base($institute);
        $group = $this->group($class8);

        $batch = $this->batch($institute, 'BU-01');
        $student = $this->student($institute, 'Urmi');
        $this->placement($institute, $student, $b['year'], $class8, $group);
        $this->enroll($student, $batch);

        $date = '2026-06-15';
        $payload = [
            'academic_year_id' => $b['year']->id,
            'class_grade_id' => $class8->id,
            'academic_group_id' => $group->id,
            'attendance_date' => $date,
        ];

        $this->actingAs($b['owner'], 'institute_user')
            ->from($this->indexUrl($b['year']->id, $class8->id, $group->id, $date))
            ->post($this->storeUrl(), $payload + ['statuses' => [$student->id => 'present']])
            ->assertSessionHas('status');

        $this->actingAs($b['owner'], 'institute_user')
            ->from($this->indexUrl($b['year']->id, $class8->id, $group->id, $date))
            ->post($this->storeUrl(), $payload + ['statuses' => [$student->id => 'late']])
            ->assertSessionHas('status');

        $this->assertSame(1, Attendance::query()
            ->where('student_id', $student->id)
            ->where('class_date', $date)
            ->count());

        $this->assertDatabaseHas('attendance', [
            'student_id' => $student->id,
            'class_date' => $date,
            'status' => 'late',
        ]);
    }

    public function test_store_ignores_student_ids_outside_the_roster_and_tenant(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class7 = $this->classGrade($level, 'c7', 'Class 7');
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Roster Safety Institute');
        $b = $this->base($institute);
        $group = $this->group($class8);

        $included = $this->student($institute, 'Included');
        $this->placement($institute, $included, $b['year'], $class8, $group);
        $this->enroll($included, $this->batch($institute, 'IN-1'));

        // Same institute but placed in a different class.
        $otherClass = $this->student($institute, 'Other Class');
        $this->placement($institute, $otherClass, $b['year'], $class7);

        // Roster student with no enrollment (no resolvable batch).
        $noBatch = $this->student($institute, 'No Batch');
        $this->placement($institute, $noBatch, $b['year'], $class8, $group);

        // A student from a completely different institute.
        $otherInstitute = $this->institute($c, 'Alien Institute');
        $alien = $this->student($otherInstitute, 'Alien');
        $alienYear = $this->year($otherInstitute);
        $this->placement($otherInstitute, $alien, $alienYear, $class8);

        $date = '2026-06-15';
        $this->actingAs($b['owner'], 'institute_user')
            ->from($this->indexUrl($b['year']->id, $class8->id, $group->id, $date))
            ->post($this->storeUrl(), [
                'academic_year_id' => $b['year']->id,
                'class_grade_id' => $class8->id,
                'academic_group_id' => $group->id,
                'attendance_date' => $date,
                'statuses' => [
                    // Legit roster student (also has a batch) — must be written.
                    $included->id => 'present',
                    // Out-of-roster and out-of-tenant ids — must be ignored.
                    $otherClass->id => 'absent',
                    $alien->id => 'absent',
                    999999 => 'late',
                ],
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('attendance', [
            'student_id' => $included->id,
            'class_date' => $date,
            'status' => 'present',
        ]);

        $this->assertDatabaseMissing('attendance', ['student_id' => $otherClass->id, 'class_date' => $date]);
        $this->assertDatabaseMissing('attendance', ['student_id' => $alien->id, 'class_date' => $date]);
    }

    public function test_group_filter_scopes_the_roster(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Group Institute');
        $b = $this->base($institute);
        $groupA = $this->group($class8, 'Science');
        $groupB = $this->group($class8, 'Arts');

        $science = $this->student($institute, 'Science Kid');
        $arts = $this->student($institute, 'Arts Kid');
        $ungrouped = $this->student($institute, 'No Group Kid');
        $this->placement($institute, $science, $b['year'], $class8, $groupA);
        $this->placement($institute, $arts, $b['year'], $class8, $groupB);
        $this->placement($institute, $ungrouped, $b['year'], $class8);
        $this->enroll($science, $this->batch($institute, 'GR-A'));
        $this->enroll($arts, $this->batch($institute, 'GR-B'));
        $this->enroll($ungrouped, $this->batch($institute, 'GR-C'));

        $date = '2026-06-15';
        $this->actingAs($b['owner'], 'institute_user')
            ->from($this->indexUrl($b['year']->id, $class8->id, $groupA->id, $date))
            ->post($this->storeUrl(), [
                'academic_year_id' => $b['year']->id,
                'class_grade_id' => $class8->id,
                'academic_group_id' => $groupA->id,
                'attendance_date' => $date,
                'statuses' => [
                    $science->id => 'present',
                    $arts->id => 'absent',
                    $ungrouped->id => 'present',
                ],
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('attendance', ['student_id' => $science->id, 'class_date' => $date, 'status' => 'present']);
        $this->assertDatabaseMissing('attendance', ['student_id' => $arts->id, 'class_date' => $date]);
        $this->assertDatabaseMissing('attendance', ['student_id' => $ungrouped->id, 'class_date' => $date]);
    }

    public function test_student_without_resolvable_batch_is_skipped_with_reason(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Skip Institute');
        $b = $this->base($institute);
        $group = $this->group($class8);

        $withBatch = $this->student($institute, 'Has Batch');
        $noBatch = $this->student($institute, 'Missing Batch');
        $this->placement($institute, $withBatch, $b['year'], $class8, $group);
        $this->placement($institute, $noBatch, $b['year'], $class8, $group);
        $this->enroll($withBatch, $this->batch($institute, 'SB-1'));

        $date = '2026-06-15';
        $this->actingAs($b['owner'], 'institute_user')
            ->from($this->indexUrl($b['year']->id, $class8->id, $group->id, $date))
            ->post($this->storeUrl(), [
                'academic_year_id' => $b['year']->id,
                'class_grade_id' => $class8->id,
                'academic_group_id' => $group->id,
                'attendance_date' => $date,
                'statuses' => [
                    $withBatch->id => 'present',
                    $noBatch->id => 'present',
                ],
            ])
            ->assertSessionHas('status', function ($message) {
                return str_contains($message, 'skipped');
            });

        $this->assertDatabaseHas('attendance', ['student_id' => $withBatch->id, 'class_date' => $date, 'status' => 'present']);
        $this->assertDatabaseMissing('attendance', ['student_id' => $noBatch->id, 'class_date' => $date]);
    }

    public function test_exited_student_is_blocked_from_new_marks_but_historical_row_stays_editable(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Exit Institute');
        $b = $this->base($institute);
        $group = $this->group($class8);

        $batch = $this->batch($institute, 'EX-1');
        $student = $this->student($institute, 'Leaver');
        $this->placement($institute, $student, $b['year'], $class8, $group);
        $this->enroll($student, $batch);

        $historical = '2026-04-10';
        $future = '2026-10-15';

        // Historical row recorded while the student was still active.
        Attendance::create([
            'institute_id' => $institute->id,
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'class_date' => $historical,
            'status' => 'present',
        ]);

        // Official exit (simulates the exit workflow outcome: status becomes
        // dropped/transferred and updated_at is set to "today").
        $placement = StudentAcademicPlacement::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $b['year']->id)
            ->sole();
        $placement->update([
            'status' => StudentAcademicPlacement::STATUS_DROPPED,
            'updated_at' => Carbon::now(),
        ]);

        // 1) New mark for a date after the exit must NOT be written.
        $this->actingAs($b['owner'], 'institute_user')
            ->from($this->indexUrl($b['year']->id, $class8->id, $group->id, $future))
            ->post($this->storeUrl(), [
                'academic_year_id' => $b['year']->id,
                'class_grade_id' => $class8->id,
                'academic_group_id' => $group->id,
                'attendance_date' => $future,
                'statuses' => [$student->id => 'present'],
            ])
            ->assertSessionHas('status', function ($message) {
                return str_contains($message, 'skipped');
            });

        $this->assertDatabaseMissing('attendance', ['student_id' => $student->id, 'class_date' => $future]);

        // The marking roster for the future date reports the reason.
        $response = $this->actingAs($b['owner'], 'institute_user')
            ->get($this->indexUrl($b['year']->id, $class8->id, $group->id, $future));
        $response->assertOk()->assertSee('Officially exited before this date.');

        // 2) The historical row on a date before the exit remains editable.
        $this->actingAs($b['owner'], 'institute_user')
            ->from($this->indexUrl($b['year']->id, $class8->id, $group->id, $historical))
            ->post($this->storeUrl(), [
                'academic_year_id' => $b['year']->id,
                'class_grade_id' => $class8->id,
                'academic_group_id' => $group->id,
                'attendance_date' => $historical,
                'statuses' => [$student->id => 'leave'],
            ])
            ->assertSessionHas('status');

        $this->assertSame(1, Attendance::query()
            ->where('student_id', $student->id)
            ->where('class_date', $historical)
            ->count());
        $this->assertDatabaseHas('attendance', [
            'student_id' => $student->id,
            'class_date' => $historical,
            'status' => 'leave',
        ]);
    }

    public function test_date_outside_the_academic_year_is_rejected(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Window Institute');
        $b = $this->base($institute);
        $group = $this->group($class8);

        $student = $this->student($institute, 'Kiddo');
        $this->placement($institute, $student, $b['year'], $class8, $group);
        $this->enroll($student, $this->batch($institute, 'WI-1'));

        $badDate = '2027-02-01';
        $this->actingAs($b['owner'], 'institute_user')
            ->from($this->indexUrl($b['year']->id, $class8->id, $group->id, $badDate))
            ->post($this->storeUrl(), [
                'academic_year_id' => $b['year']->id,
                'class_grade_id' => $class8->id,
                'academic_group_id' => $group->id,
                'attendance_date' => $badDate,
                'statuses' => [$student->id => 'present'],
            ])
            ->assertSessionHasErrors('attendance_date');

        $this->assertDatabaseMissing('attendance', ['student_id' => $student->id, 'class_date' => $badDate]);
    }

    public function test_branch_manager_only_sees_and_marks_their_branch(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Branch Institute');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $owner = $this->user($institute, 'institute-owner', 'br-owner');
        $managerA = $this->user($institute, 'branch-manager', 'br-mgr-a', $branchA);
        $year = $this->year($institute);
        $group = $this->group($class8);

        $inBranchA = $this->student($institute, 'Lokal', $branchA);
        $inBranchB = $this->student($institute, 'Remote', $branchB);
        $this->placement($institute, $inBranchA, $year, $class8, $group);
        $this->placement($institute, $inBranchB, $year, $class8, $group);
        $this->enroll($inBranchA, $this->batch($institute, 'BA-A'));
        $this->enroll($inBranchB, $this->batch($institute, 'BA-B'));

        // Listing: only the manager's branch student shows up.
        $this->actingAs($managerA, 'institute_user')
            ->get($this->indexUrl($year->id, $class8->id, $group->id))
            ->assertOk()
            ->assertSee('Lokal')
            ->assertDontSee('Remote');

        // Saving: the other branch's id is ignored.
        $date = '2026-06-15';
        $this->actingAs($managerA, 'institute_user')
            ->from($this->indexUrl($year->id, $class8->id, $group->id, $date))
            ->post($this->storeUrl(), [
                'academic_year_id' => $year->id,
                'class_grade_id' => $class8->id,
                'academic_group_id' => $group->id,
                'attendance_date' => $date,
                'statuses' => [
                    $inBranchA->id => 'present',
                    $inBranchB->id => 'present',
                ],
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('attendance', [
            'student_id' => $inBranchA->id,
            'class_date' => $date,
        ]);
        $this->assertDatabaseMissing('attendance', [
            'student_id' => $inBranchB->id,
            'class_date' => $date,
        ]);

        // Sanity: the owner sees both branches.
        $this->actingAs($owner, 'institute_user')
            ->get($this->indexUrl($year->id, $class8->id, $group->id))
            ->assertOk()
            ->assertSee('Lokal')
            ->assertSee('Remote');
    }
}
