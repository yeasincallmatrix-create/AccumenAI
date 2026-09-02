<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicSelectionGroup;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StudentAcademicPlacementTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    // ------------------------------------------------------------- Setup data

    private function country(string $iso2 = 'BD', string $name = 'Bangladesh'): Country
    {
        return Country::firstOrCreate(['iso2' => $iso2], ['name' => $name,
            'iso3' => strtoupper($iso2).'D',
            'phone_code' => '880',
            'status' => true,
        ]);
    }

    private function system(Country $country, string $code = 'general'): EducationSystem
    {
        return EducationSystem::firstOrCreate(
            ['country_id' => $country->id, 'code' => $code],
            ['name' => 'General Education', 'display_order' => 0, 'status' => true]
        );
    }

    private function level(EducationSystem $system, string $code = 'secondary'): AcademicLevel
    {
        return AcademicLevel::create([
            'country_id' => $system->country_id,
            'education_system_id' => $system->id,
            'name' => 'Secondary',
            'code' => $code,
            'display_order' => 1,
            'status' => true,
        ]);
    }

    private function classGrade(AcademicLevel $level, string $code = 'c8'): ClassGrade
    {
        return ClassGrade::create([
            'country_id' => $level->country_id,
            'education_system_id' => $level->education_system_id,
            'academic_level_id' => $level->id,
            'name' => 'Class 8',
            'code' => $code,
            'display_order' => 0,
            'status' => true,
        ]);
    }

    private function group(ClassGrade $classGrade, string $code = 'sci'): AcademicGroup
    {
        return AcademicGroup::create([
            'country_id' => $classGrade->country_id,
            'education_system_id' => $classGrade->education_system_id,
            'academic_level_id' => $classGrade->academic_level_id,
            'class_grade_id' => $classGrade->id,
            'name' => 'Science',
            'code' => $code,
            'display_order' => 0,
            'status' => true,
        ]);
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

    private function assign(Subject $subject, ClassGrade $classGrade, string $requirementType, int $displayOrder, ?int $selectionGroupId = null, ?AcademicGroup $academicGroup = null): SubjectAcademicAssignment
    {
        return SubjectAcademicAssignment::create([
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => $academicGroup?->id,
            'requirement_type' => $requirementType,
            'selection_group_id' => $selectionGroupId,
            'display_order' => $displayOrder,
            'status' => 'active',
        ]);
    }

    private function selectionGroup(ClassGrade $classGrade, int $min, int $max, string $code = 'groupA'): AcademicSelectionGroup
    {
        return AcademicSelectionGroup::create([
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'name' => 'Group A',
            'code' => $code,
            'selection_type' => 'optional',
            'minimum_selection' => $min,
            'maximum_selection' => $max,
            'display_order' => 1,
            'status' => 'active',
        ]);
    }

    private function institute(Country $country, string $name = 'Placement Inst'): Institute
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
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
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

    private function year(Institute $institute, string $code = '2026', string $name = 'Academic Year 2026'): AcademicYear
    {
        return AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'code' => $code,
            'is_current' => true,
            'status' => true,
        ]);
    }

    /**
     * A fully-wired class-8 curriculum:
     * mandatory Bangla/English/Mathematics + optional group A {Biology, Higher Math} min 1 max 1.
     */
    private function curriculum(): array
    {
        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $group = $this->group($classGrade);
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'sap-owner');

        $bangla = $this->subject('Bangla', 'PL100001');
        $english = $this->subject('English', 'PL100002');
        $math = $this->subject('Mathematics', 'PL100003');
        $bio = $this->subject('Biology', 'PL100004');
        $hmath = $this->subject('Higher Mathematics', 'PL100005');

        $this->assign($bangla, $classGrade, 'mandatory', 1);
        $this->assign($english, $classGrade, 'mandatory', 2);
        $this->assign($math, $classGrade, 'mandatory', 3);
        $selGroup = $this->selectionGroup($classGrade, 1, 1);
        $this->assign($bio, $classGrade, 'optional', 4, $selGroup->id);
        $this->assign($hmath, $classGrade, 'optional', 5, $selGroup->id);

        return [
            'country' => $country,
            'class_grade' => $classGrade,
            'group' => $group,
            'institute' => $institute,
            'owner' => $owner,
            'subjects' => compact('bangla', 'english', 'math', 'bio', 'hmath'),
            'selection_group' => $selGroup,
        ];
    }

    // ------------------------------------------------------------- Placement

    public function test_owner_creates_academic_placement_with_mandatory_auto_selected(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'status' => 'active',
                'subject_ids' => [$c['subjects']['bio']->id],
            ])->assertRedirect();

        $placement = StudentAcademicPlacement::where('student_id', $student->id)->firstOrFail();

        $this->assertSame($c['institute']->id, $placement->institute_id);
        $this->assertSame($c['class_grade']->id, $placement->class_grade_id);
        $this->assertSame($c['group']->id, $placement->academic_group_id);
        $this->assertSame('active', $placement->status);

        $this->assertSame(4, $placement->selections()->count());
        $this->assertDatabaseHas('student_subject_selections', [
            'academic_placement_id' => $placement->id,
            'subject_id' => $c['subjects']['bangla']->id,
            'is_mandatory' => 1,
        ]);
        $this->assertDatabaseHas('student_subject_selections', [
            'academic_placement_id' => $placement->id,
            'subject_id' => $c['subjects']['bio']->id,
            'is_mandatory' => 0,
            'selection_group_id' => $c['selection_group']->id,
            'source' => 'inherited',
        ]);
        $this->assertDatabaseMissing('student_subject_selections', [
            'academic_placement_id' => $placement->id,
            'subject_id' => $c['subjects']['hmath']->id,
        ]);
    }

    public function test_optional_group_minimum_is_enforced(): void
    {
        $c = $this->curriculum();
        $c['selection_group']->update(['minimum_selection' => 2, 'maximum_selection' => 2]);
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
                'subject_ids' => [$c['subjects']['bio']->id],
            ])
            ->assertSessionHasErrors('subjects');

        $this->assertDatabaseMissing('student_academic_placements', ['student_id' => $student->id]);
    }

    public function test_optional_group_maximum_is_enforced(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
                'subject_ids' => [$c['subjects']['bio']->id, $c['subjects']['hmath']->id],
            ])
            ->assertSessionHasErrors('subjects');

        $this->assertDatabaseMissing('student_academic_placements', ['student_id' => $student->id]);
    }

    public function test_out_of_curriculum_subject_is_rejected(): void
    {
        $c = $this->curriculum();
        $foreign = $this->subject('Astronomy', 'PL100099');
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
                'subject_ids' => [$c['subjects']['bangla']->id, $foreign->id],
            ])
            ->assertSessionHasErrors('subjects');

        $this->assertDatabaseMissing('student_academic_placements', ['student_id' => $student->id]);
        $this->assertDatabaseMissing('student_subject_selections', ['subject_id' => $foreign->id]);
    }

    public function test_update_replaces_selection_and_keeps_validation(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute']);

        $placement = StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $c['class_grade']->id,
            'status' => 'active',
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->put(route('settings.academic.placements.update', $placement), [
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
                'subject_ids' => [$c['subjects']['hmath']->id],
            ])->assertRedirect();

        $this->assertDatabaseHas('student_subject_selections', [
            'academic_placement_id' => $placement->id,
            'subject_id' => $c['subjects']['hmath']->id,
            'is_mandatory' => 0,
        ]);
        $this->assertDatabaseMissing('student_subject_selections', [
            'academic_placement_id' => $placement->id,
            'subject_id' => $c['subjects']['bio']->id,
        ]);
        $this->assertDatabaseHas('student_subject_selections', [
            'academic_placement_id' => $placement->id,
            'subject_id' => $c['subjects']['bangla']->id,
            'is_mandatory' => 1,
        ]);
    }

    // ------------------------------------------------------------- Historical

    public function test_year_over_year_placements_are_preserved(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year2026 = $this->year($c['institute'], '2026');
        $year2027 = $this->year($c['institute'], '2027', 'Academic Year 2027');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $student->id,
                'academic_year_id' => $year2026->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
                'subject_ids' => [$c['subjects']['bio']->id],
            ])->assertRedirect();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $student->id,
                'academic_year_id' => $year2027->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
                'subject_ids' => [$c['subjects']['hmath']->id],
            ])->assertRedirect();

        $this->assertSame(2, StudentAcademicPlacement::where('student_id', $student->id)->count());

        $p2026 = StudentAcademicPlacement::where('academic_year_id', $year2026->id)->firstOrFail();
        $p2027 = StudentAcademicPlacement::where('academic_year_id', $year2027->id)->firstOrFail();

        $this->assertNotSame($p2026->id, $p2027->id);
        $this->assertDatabaseHas('student_subject_selections', ['academic_placement_id' => $p2026->id, 'subject_id' => $c['subjects']['bio']->id]);
        $this->assertDatabaseHas('student_subject_selections', ['academic_placement_id' => $p2027->id, 'subject_id' => $c['subjects']['hmath']->id]);
    }

    public function test_duplicate_year_placement_is_rejected(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
                'subject_ids' => [$c['subjects']['bio']->id],
            ])->assertRedirect();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
                'subject_ids' => [$c['subjects']['bio']->id],
            ])
            ->assertSessionHasErrors('academic_year_id');

        $this->assertSame(1, StudentAcademicPlacement::where('student_id', $student->id)->count());
    }

    public function test_configuration_change_does_not_rewrite_historical_selection(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
                'subject_ids' => [$c['subjects']['bio']->id],
            ])->assertRedirect();

        $placement = StudentAcademicPlacement::where('student_id', $student->id)->firstOrFail();

        SubjectAcademicAssignment::query()
            ->where('subject_id', $c['subjects']['bio']->id)
            ->delete();

        $this->assertDatabaseHas('student_subject_selections', [
            'academic_placement_id' => $placement->id,
            'subject_id' => $c['subjects']['bio']->id,
            'is_mandatory' => 0,
        ]);
    }

    // ------------------------------------------------------------- Security

    public function test_cross_tenant_student_is_rejected(): void
    {
        $c = $this->curriculum();
        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst');
        $otherStudent = $this->student($otherInstitute, 'Other');
        $year = $this->year($c['institute']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $otherStudent->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing('student_academic_placements', ['student_id' => $otherStudent->id]);
    }

    public function test_cross_tenant_academic_year_is_rejected(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst 2');
        $otherYear = $this->year($otherInstitute, '2026');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $student->id,
                'academic_year_id' => $otherYear->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('academic_year_id');

        $this->assertDatabaseMissing('student_academic_placements', ['student_id' => $student->id]);
    }

    public function test_class_outside_institute_structure_is_rejected(): void
    {
        $c = $this->curriculum();
        $foreignClass = $this->classGrade($this->level($this->system($this->country('IN', 'India'))), 'c8x');
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $foreignClass->id,
                'status' => 'active',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('student_academic_placements', ['student_id' => $student->id]);
    }

    public function test_group_of_another_class_is_rejected(): void
    {
        $c = $this->curriculum();
        $otherClass = $this->classGrade($this->level($this->system($this->country('IN', 'India'))), 'c8y');
        $rogueGroup = $this->group($otherClass, 'other');
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $rogueGroup->id,
                'status' => 'active',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('student_academic_placements', ['student_id' => $student->id]);
    }

    public function test_forged_institute_id_is_ignored(): void
    {
        $c = $this->curriculum();
        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst 3');
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.placements.store'), [
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
                'institute_id' => $otherInstitute->id,
                'subject_ids' => [$c['subjects']['bio']->id],
            ])->assertRedirect();

        $placement = StudentAcademicPlacement::where('student_id', $student->id)->firstOrFail();
        $this->assertSame($c['institute']->id, $placement->institute_id);
    }

    public function test_visibility_for_other_institute_admin(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute']);

        $placement = StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $c['class_grade']->id,
            'status' => 'active',
        ]);

        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst 4');
        $otherAdmin = $this->user($otherInstitute, 'institute-admin', 'sap-other');

        TenantContext::set($otherInstitute->id);

        $this->actingAs($otherAdmin, 'institute_user')
            ->get(route('settings.academic.placements.show', $placement))
            ->assertStatus(404);

        $this->actingAs($otherAdmin, 'institute_user')
            ->put(route('settings.academic.placements.update', $placement), [
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
            ])
            ->assertStatus(404);
    }

    public function test_visibility_for_branch_admin_outside_branch(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $student = $this->student($c['institute'], 'Rahim', $branchA);
        $year = $this->year($c['institute']);

        $placement = StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $c['class_grade']->id,
            'status' => 'active',
        ]);

        $branchB = $this->branch($c['institute'], 'Branch B');
        $adminB = $this->user($c['institute'], 'institute-admin', 'sap-admin-b', $branchB);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchB->id);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('settings.academic.placements.show', $placement))
            ->assertStatus(403);
    }

    public function test_branch_admin_within_branch_sees_placement(): void
    {
        $c = $this->curriculum();
        $branchB = $this->branch($c['institute'], 'Branch B');
        $student = $this->student($c['institute'], 'Karim', $branchB);
        $year = $this->year($c['institute']);

        $placement = StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $c['class_grade']->id,
            'status' => 'active',
        ]);

        $adminB = $this->user($c['institute'], 'institute-admin', 'sap-admin-b2', $branchB);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchB->id);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('settings.academic.placements.show', $placement))
            ->assertOk()
            ->assertSee('Karim');
    }

    public function test_admin_without_education_manage_permission_is_blocked(): void
    {
        $c = $this->curriculum();
        $teacher = $this->user($c['institute'], 'teacher', 'sap-teacher');

        TenantContext::set($c['institute']->id);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.placements.index'))
            ->assertForbidden();

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.placements.create'))
            ->assertForbidden();
    }

    public function test_auth_required_for_placement_routes(): void
    {
        $this->get(route('settings.academic.placements.index'))->assertRedirect();
        $this->get(route('settings.academic.placements.create'))->assertRedirect();
    }

    // ------------------------------------------------------------- Index search & filters

    public function test_index_search_finds_student_by_registration_number(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute']);

        $target = $this->student($c['institute'], 'Reg Target');
        $target->update(['registration_number' => 'REG-ABC-001']);
        $other = $this->student($c['institute'], 'Reg Other');

        StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $target->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $c['class_grade']->id,
            'status' => 'active',
        ]);
        StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $other->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $c['class_grade']->id,
            'status' => 'active',
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.placements.index', ['q' => 'REG-ABC-001']))
            ->assertOk()
            ->assertSee($target->full_name)
            ->assertDontSee($other->full_name);
    }

    public function test_index_filters_by_group(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute']);
        $humanities = $this->group($c['class_grade'], 'hum');

        $scienceStudent = $this->student($c['institute'], 'Science Kid');
        $humanitiesStudent = $this->student($c['institute'], 'Humanities Kid');

        StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $scienceStudent->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'status' => 'active',
        ]);
        StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $humanitiesStudent->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $humanities->id,
            'status' => 'active',
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.placements.index', ['academic_group_id' => $humanities->id]))
            ->assertOk()
            ->assertSee($humanitiesStudent->full_name)
            ->assertDontSee($scienceStudent->full_name);
    }

    public function test_index_filters_by_branch(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute']);
        $branchA = $this->branch($c['institute'], 'Branch A');
        $branchB = $this->branch($c['institute'], 'Branch B');

        $studentA = $this->student($c['institute'], 'Branch A Kid', $branchA);
        $studentB = $this->student($c['institute'], 'Branch B Kid', $branchB);

        foreach ([$studentA, $studentB] as $student) {
            StudentAcademicPlacement::create([
                'institute_id' => $c['institute']->id,
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
            ]);
        }

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.placements.index', ['branch_id' => $branchB->id]))
            ->assertOk()
            ->assertSee($studentB->full_name)
            ->assertDontSee($studentA->full_name);
    }

    public function test_branch_admin_index_only_lists_their_branch(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute']);
        $branchA = $this->branch($c['institute'], 'Branch A');
        $branchB = $this->branch($c['institute'], 'Branch B');

        $studentA = $this->student($c['institute'], 'Branch A Kid', $branchA);
        $studentB = $this->student($c['institute'], 'Branch B Kid', $branchB);

        foreach ([$studentA, $studentB] as $student) {
            StudentAcademicPlacement::create([
                'institute_id' => $c['institute']->id,
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $c['class_grade']->id,
                'status' => 'active',
            ]);
        }

        $adminB = $this->user($c['institute'], 'institute-admin', 'sap-admin-index-b', $branchB);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchB->id);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('settings.academic.placements.index'))
            ->assertOk()
            ->assertSee($studentB->full_name)
            ->assertDontSee($studentA->full_name);
    }

    // ------------------------------------------------------------- Academic-year deletion protection

    public function test_unused_academic_year_can_be_deleted(): void
    {
        $c = $this->curriculum();

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.academic-years.store'), [
                'name' => 'Academic Year 2026',
                'code' => '2026',
            ])->assertRedirect();

        $year = AcademicYear::where('institute_id', $c['institute']->id)->firstOrFail();

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('settings.academic.academic-years.destroy', $year))
            ->assertRedirect();

        $this->assertDatabaseMissing('academic_years', ['id' => $year->id]);
    }

    public function test_academic_year_with_placements_cannot_be_deleted(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute']);

        StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $c['class_grade']->id,
            'status' => 'active',
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('settings.academic.academic-years.destroy', $year))
            ->assertRedirect();

        $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
        $this->assertDatabaseHas('student_academic_placements', ['academic_year_id' => $year->id]);
    }

    public function test_academic_year_with_final_result_history_cannot_be_deleted(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute']);

        // An aggregation scheme binds this year to final-result configuration.
        AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $year->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => null,
            'name' => 'Annual Scheme',
            'status' => 'active',
            'display_order' => 1,
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('settings.academic.academic-years.destroy', $year))
            ->assertRedirect();

        $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
        $this->assertDatabaseHas('academic_result_aggregation_schemes', ['academic_year_id' => $year->id]);
    }

    public function test_cross_tenant_admin_cannot_delete_another_institutes_academic_year(): void
    {
        $c = $this->curriculum();
        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst Y1');
        $otherYear = $this->year($otherInstitute, '2030');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('settings.academic.academic-years.destroy', $otherYear))
            ->assertStatus(404);

        $this->assertDatabaseHas('academic_years', ['id' => $otherYear->id]);
    }

    public function test_cross_tenant_admin_cannot_delete_another_institutes_placement(): void
    {
        $c = $this->curriculum();
        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst Y2');
        $otherStudent = $this->student($otherInstitute, 'Other');
        $otherYear = $this->year($otherInstitute, '2030');

        $placement = StudentAcademicPlacement::create([
            'institute_id' => $otherInstitute->id,
            'student_id' => $otherStudent->id,
            'academic_year_id' => $otherYear->id,
            'class_grade_id' => null,
            'status' => 'active',
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('settings.academic.placements.destroy', $placement))
            ->assertStatus(404);

        $this->assertDatabaseHas('student_academic_placements', ['id' => $placement->id]);
    }

    // ------------------------------------------------------------- Current-year integrity

    public function test_first_current_year_is_set(): void
    {
        $c = $this->curriculum();

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.academic-years.store'), [
                'name' => 'Academic Year 2026',
                'code' => '2026',
                'is_current' => 1,
            ])->assertRedirect();

        $year = AcademicYear::withoutGlobalScopes()->where('institute_id', $c['institute']->id)->firstOrFail();
        $this->assertTrue($year->is_current);
        $this->assertSame(1, AcademicYear::withoutGlobalScopes()->where('institute_id', $c['institute']->id)->where('is_current', true)->count());
    }

    public function test_switching_current_year_unsets_previous(): void
    {
        $c = $this->curriculum();
        $previous = $this->year($c['institute'], '2026');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.academic-years.store'), [
                'name' => 'Academic Year 2027',
                'code' => '2027',
                'is_current' => 1,
            ])->assertRedirect();

        $this->assertFalse($previous->fresh()->is_current);
        $this->assertTrue(AcademicYear::withoutGlobalScopes()->where('institute_id', $c['institute']->id)->where('code', '2027')->firstOrFail()->is_current);
        $this->assertSame(1, AcademicYear::withoutGlobalScopes()->where('institute_id', $c['institute']->id)->where('is_current', true)->count());
    }

    public function test_multiple_current_years_cannot_coexist(): void
    {
        $c = $this->curriculum();
        $first = $this->year($c['institute'], '2026');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.academic-years.store'), [
                'name' => 'Academic Year 2027',
                'code' => '2027',
                'is_current' => 1,
            ])->assertRedirect();

        $this->assertSame(1, AcademicYear::withoutGlobalScopes()->where('institute_id', $c['institute']->id)->where('is_current', true)->count());

        // Update path also swaps exclusively: marking year 2026 current again
        // unsets 2027.
        $this->actingAs($c['owner'], 'institute_user')
            ->put(route('settings.academic.academic-years.update', $first), [
                'name' => $first->name,
                'code' => '2026',
                'is_current' => 1,
                'status' => 1,
            ])->assertRedirect();

        $this->assertTrue($first->fresh()->is_current);
        $this->assertFalse(AcademicYear::withoutGlobalScopes()->where('institute_id', $c['institute']->id)->where('code', '2027')->firstOrFail()->is_current);
        $this->assertSame(1, AcademicYear::withoutGlobalScopes()->where('institute_id', $c['institute']->id)->where('is_current', true)->count());
    }

    public function test_institutes_have_independent_current_years(): void
    {
        $c = $this->curriculum();
        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst Y3');
        $otherOwner = $this->user($otherInstitute, 'institute-owner', 'sap-other-owner');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.academic-years.store'), [
                'name' => 'Academic Year 2026',
                'code' => '2026',
                'is_current' => 1,
            ])->assertRedirect();

        TenantContext::set($otherInstitute->id);

        $this->actingAs($otherOwner, 'institute_user')
            ->post(route('settings.academic.academic-years.store'), [
                'name' => 'Academic Year 2030',
                'code' => '2030',
                'is_current' => 1,
            ])->assertRedirect();

        $this->assertSame(1, AcademicYear::withoutGlobalScopes()->where('institute_id', $c['institute']->id)->where('is_current', true)->count());
        $this->assertSame(1, AcademicYear::withoutGlobalScopes()->where('institute_id', $otherInstitute->id)->where('is_current', true)->count());
    }

    // ------------------------------------------------------------- Pages

    public function test_placement_index_and_create_pages_render(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute']);

        StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $c['class_grade']->id,
            'status' => 'active',
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.placements.index'))
            ->assertOk();

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.placements.create'))
            ->assertOk()
            ->assertSee('New Academic Placement');

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.placements.subjects', [
                'class_grade_id' => $c['class_grade']->id,
            ]))
            ->assertOk()
            ->assertSee('Bangla');
    }
}
