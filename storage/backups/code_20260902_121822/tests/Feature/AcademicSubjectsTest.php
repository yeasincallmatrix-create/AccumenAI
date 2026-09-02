<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicSelectionGroup;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteSubject;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Models\SubjectRequest;
use App\Services\AcademicSubjectService;
use App\Services\StudentSubjectSelectionValidator;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AcademicSubjectsTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

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

    private function subject(string $name = 'Mathematics', string $code = 'SUB100001', string $status = 'active'): Subject
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
            'status' => $status,
        ]);
    }

    private function institute(Country $country, string $name = 'Subject Inst'): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function owner(Institute $institute): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'acad-subject-owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function admin(string $email = 'acad-subject-admin@example.test'): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    // ------------------------------------------------------------- Super Admin

    public function test_admin_subject_master_requires_platform_admin(): void
    {
        TenantContext::clear();
        $this->get(route('admin.academic.subjects.index'))->assertRedirect('/admin/login');
    }

    public function test_platform_admin_creates_and_toggles_subject(): void
    {
        TenantContext::clear();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin');

        $this->post(route('admin.academic.subjects.store'), [
            'name' => 'Robotics',
            'short_name' => 'ROB',
            'description' => 'Introduction to robotics',
        ])->assertRedirect(route('admin.academic.subjects.index'));

        $subject = Subject::where('name', 'Robotics')->firstOrFail();
        $this->assertSame('academic', $subject->subject_type);
        $this->assertNotNull($subject->subject_code);
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'status' => 'active']);

        $this->post(route('admin.academic.subjects.toggle', $subject))
            ->assertRedirect(route('admin.academic.subjects.index'));

        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'status' => 'inactive']);
    }

    public function test_platform_admin_updates_subject(): void
    {
        TenantContext::clear();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin');

        $subject = $this->subject('Mathematics', 'SUB200001');

        $this->put(route('admin.academic.subjects.update', $subject), [
            'name' => 'Pure Mathematics',
            'short_name' => 'PM',
        ])->assertRedirect(route('admin.academic.subjects.index'));

        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'name' => 'Pure Mathematics', 'short_name' => 'PM']);
    }

    public function test_platform_admin_assigns_updates_and_removes_assignment(): void
    {
        TenantContext::clear();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin');

        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $subject = $this->subject('Mathematics', 'SUB300001');

        $this->post(route('admin.academic.subjects.assignments.store'), [
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'requirement_type' => 'mandatory',
            'display_order' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subject_academic_assignments', [
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'status' => 'active',
        ]);

        $assignment = SubjectAcademicAssignment::where('subject_id', $subject->id)->firstOrFail();

        $this->put(route('admin.academic.subjects.assignments.update', $assignment), [
            'display_order' => 5,
            'requirement_type' => 'optional',
            'status' => 'inactive',
        ])->assertSessionHasNoErrors();

        $assignment->refresh();
        $this->assertSame(5, $assignment->display_order);
        $this->assertSame('inactive', $assignment->status);

        $this->delete(route('admin.academic.subjects.assignments.destroy', $assignment))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('subject_academic_assignments', ['id' => $assignment->id]);
    }

    public function test_assignment_is_scoped_to_group(): void
    {
        TenantContext::clear();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin');

        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $group = $this->group($classGrade);
        $subject = $this->subject('Physics', 'SUB400001');

        $this->post(route('admin.academic.subjects.assignments.store'), [
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => $group->id,
            'requirement_type' => 'optional',
            'display_order' => 2,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subject_academic_assignments', [
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => $group->id,
        ]);

        // Same subject may also be assigned to the whole class.
        $this->post(route('admin.academic.subjects.assignments.store'), [
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => '',
            'requirement_type' => 'elective',
            'display_order' => 3,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, SubjectAcademicAssignment::where('subject_id', $subject->id)->count());
    }

    public function test_duplicate_class_wide_assignment_is_rejected(): void
    {
        TenantContext::clear();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin');

        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $subject = $this->subject('Biology', 'SUB500001');

        SubjectAcademicAssignment::create([
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'display_order' => 1,
            'status' => 'active',
        ]);

        $this->post(route('admin.academic.subjects.assignments.store'), [
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'requirement_type' => 'mandatory',
            'display_order' => 4,
        ])->assertStatus(422);
    }

    public function test_subject_assignment_page_lists_assignments(): void
    {
        TenantContext::clear();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin');

        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $subject = $this->subject('Chemistry', 'SUB600001');
        $this->subject('Mathematics', 'SUB600002');

        SubjectAcademicAssignment::create([
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'display_order' => 1,
            'status' => 'active',
        ]);

        $this->get(route('admin.academic.subjects.assign', [
            'country_id' => $country->id,
            'system_id' => $system->id,
            'level_id' => $level->id,
            'class_id' => $classGrade->id,
        ]))
            ->assertOk()
            ->assertSee('Chemistry')
            ->assertSee('Mathematics');
    }

    // ------------------------------------------------------------- Resolver

    public function test_resolver_marks_inherited_customized_and_custom_sources(): void
    {
        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);

        $math = $this->subject('Mathematics', 'SUB110001');
        $art = $this->subject('Art & Design', 'SUB120001');
        $robotics = $this->subject('Robotics', 'SUB130001');

        SubjectAcademicAssignment::create([
            'subject_id' => $math->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'display_order' => 1,
            'status' => 'active',
        ]);
        SubjectAcademicAssignment::create([
            'subject_id' => $art->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'display_order' => 2,
            'status' => 'active',
        ]);

        $institute = $this->institute($country);

        TenantContext::set($institute->id);

        // Disable Art (customized) + disabled.
        InstituteSubject::create([
            'institute_id' => $institute->id,
            'subject_id' => $art->id,
            'status' => 'inactive',
            'is_custom' => false,
        ]);

        // Institute-created custom subject.
        InstituteSubject::create([
            'institute_id' => $institute->id,
            'subject_id' => $robotics->id,
            'status' => 'active',
            'is_custom' => true,
        ]);

        $service = app(AcademicSubjectService::class);
        $nodes = $service->resolveForClass($institute, $classGrade);

        $byName = [];
        foreach ($nodes as $node) {
            $byName[$node['subject']->name] = $node;
        }

        $this->assertArrayHasKey('Mathematics', $byName);
        $this->assertSame('inherited', $byName['Mathematics']['source']);
        $this->assertTrue($byName['Mathematics']['enabled']);

        $this->assertArrayHasKey('Art & Design', $byName);
        $this->assertSame('customized', $byName['Art & Design']['source']);
        $this->assertFalse($byName['Art & Design']['enabled']);

        $this->assertArrayHasKey('Robotics', $byName);
        $this->assertSame('custom', $byName['Robotics']['source']);
        $this->assertTrue($byName['Robotics']['enabled']);
        $this->assertNull($byName['Robotics']['assignment']);
    }

    public function test_resolver_applies_institute_name_and_order_overrides(): void
    {
        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $math = $this->subject('Mathematics', 'SUB140001');

        SubjectAcademicAssignment::create([
            'subject_id' => $math->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'display_order' => 1,
            'status' => 'active',
        ]);

        $institute = $this->institute($country);

        TenantContext::set($institute->id);

        InstituteSubject::create([
            'institute_id' => $institute->id,
            'subject_id' => $math->id,
            'name' => 'Advanced Math',
            'display_order' => 12,
            'status' => 'active',
            'is_custom' => false,
        ]);

        $service = app(AcademicSubjectService::class);
        $nodes = $service->resolveForClass($institute, $classGrade);

        $this->assertCount(1, $nodes);
        $this->assertSame('Advanced Math', $nodes[0]['name']);
        $this->assertSame(12, $nodes[0]['display_order']);
    }

    // ------------------------------------------------------------- Approval flow

    public function test_approved_academic_request_marks_subject_as_custom(): void
    {
        TenantContext::clear();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin');

        $country = $this->country();
        $this->system($country);
        $institute = $this->institute($country, 'Request Inst');

        $request = SubjectRequest::create([
            'institute_id' => $institute->id,
            'category_id' => null,
            'subject_type' => 'academic',
            'name' => 'Robotics',
            'requested_by' => null,
            'status' => 'pending',
        ]);

        $this->post(route('admin.courses.subjects-requests.action', $request), [
            'action' => 'approve',
        ])->assertSessionHasNoErrors();

        $subject = Subject::where('name', 'Robotics')->firstOrFail();

        $this->assertDatabaseHas('institute_subjects', [
            'institute_id' => $institute->id,
            'subject_id' => $subject->id,
            'is_custom' => 1,
        ]);
    }

    private function selectionGroup(ClassGrade $classGrade, string $code = 'group_a', int $min = 1, int $max = 1): AcademicSelectionGroup
    {
        return AcademicSelectionGroup::create([
            'class_grade_id' => $classGrade->id,
            'name' => 'Group A',
            'code' => $code,
            'selection_type' => 'optional',
            'minimum_selection' => $min,
            'maximum_selection' => $max,
            'display_order' => 1,
            'status' => 'active',
        ]);
    }

    // ------------------------------------------------------------- Selection groups (admin)

    public function test_admin_creates_updates_and_toggles_selection_group(): void
    {
        TenantContext::clear();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin');

        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);

        $this->post(route('admin.academic.subjects.selection-groups.store'), [
            'class_grade_id' => $classGrade->id,
            'name' => 'Optional Core',
            'code' => 'opt_core',
            'selection_type' => 'optional',
            'minimum_selection' => 1,
            'maximum_selection' => 2,
            'display_order' => 1,
        ])->assertSessionHasNoErrors();

        $group = AcademicSelectionGroup::where('class_grade_id', $classGrade->id)->firstOrFail();
        $this->assertDatabaseHas('academic_selection_groups', [
            'id' => $group->id,
            'name' => 'Optional Core',
            'minimum_selection' => 1,
            'maximum_selection' => 2,
            'status' => 'active',
        ]);

        $this->put(route('admin.academic.subjects.selection-groups.update', $group), [
            'class_grade_id' => $classGrade->id,
            'name' => 'Optional Core Plus',
            'code' => 'opt_core',
            'selection_type' => 'elective',
            'minimum_selection' => 1,
            'maximum_selection' => 3,
            'display_order' => 2,
        ])->assertSessionHasNoErrors();

        $group->refresh();
        $this->assertSame('Optional Core Plus', $group->name);
        $this->assertSame('elective', $group->selection_type);
        $this->assertSame(3, $group->maximum_selection);

        $this->post(route('admin.academic.subjects.selection-groups.toggle', $group))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $group->fresh()->status);

        $this->post(route('admin.academic.subjects.selection-groups.toggle', $group))->assertSessionHasNoErrors();
        $this->assertSame('active', $group->fresh()->status);
    }

    public function test_selection_group_code_must_be_unique_within_class(): void
    {
        TenantContext::clear();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin');

        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $this->selectionGroup($classGrade, 'opt_core');

        $this->post(route('admin.academic.subjects.selection-groups.store'), [
            'class_grade_id' => $classGrade->id,
            'name' => 'Duplicate',
            'code' => 'opt_core',
            'selection_type' => 'optional',
        ])->assertSessionHasErrors('code');
    }

    public function test_admin_assigns_optional_subjects_to_selection_group(): void
    {
        TenantContext::clear();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin');

        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $group = $this->selectionGroup($classGrade);
        $biology = $this->subject('Biology', 'SUB150001');
        $chemistry = $this->subject('Chemistry', 'SUB150002');

        foreach ([$biology, $chemistry] as $subject) {
            $this->post(route('admin.academic.subjects.assignments.store'), [
                'subject_id' => $subject->id,
                'class_grade_id' => $classGrade->id,
                'requirement_type' => 'optional',
                'selection_group_id' => $group->id,
                'display_order' => 1,
            ])->assertSessionHasNoErrors();
        }

        $assignments = SubjectAcademicAssignment::where('selection_group_id', $group->id)->get();
        $this->assertCount(2, $assignments);
        foreach ($assignments as $assignment) {
            $this->assertSame('optional', $assignment->requirement_type);
        }
    }

    public function test_mandatory_subject_cannot_join_selection_group(): void
    {
        TenantContext::clear();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin');

        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $group = $this->selectionGroup($classGrade);
        $biology = $this->subject('Biology', 'SUB160001');

        $this->post(route('admin.academic.subjects.assignments.store'), [
            'subject_id' => $biology->id,
            'class_grade_id' => $classGrade->id,
            'requirement_type' => 'mandatory',
            'selection_group_id' => $group->id,
            'display_order' => 1,
        ])->assertStatus(422);
    }

    public function test_assignment_page_lists_selection_groups_and_requirement_types(): void
    {
        TenantContext::clear();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin');

        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $group = $this->selectionGroup($classGrade, 'opt_core');
        $subject = $this->subject('Biology', 'SUB185001');

        SubjectAcademicAssignment::create([
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'requirement_type' => 'optional',
            'selection_group_id' => $group->id,
            'display_order' => 1,
            'status' => 'active',
        ]);

        $this->get(route('admin.academic.subjects.assign', [
            'country_id' => $country->id,
            'system_id' => $system->id,
            'level_id' => $level->id,
            'class_id' => $classGrade->id,
        ]))
            ->assertOk()
            ->assertSee('Biology')
            ->assertSee('Optional')
            ->assertSee('Group A')
            ->assertSee('Selection Groups');
    }

    // ------------------------------------------------------------- Selection resolver & validator

    private function selectionFixture(Country $country): array
    {
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $group = $this->selectionGroup($classGrade, 'group_a_sub', 1, 1);

        $math = $this->subject('Mathematics', 'SUB180001');
        $biology = $this->subject('Biology', 'SUB180002');
        $chemistry = $this->subject('Chemistry', 'SUB180003');
        $art = $this->subject('Art & Culture', 'SUB180004');

        $make = fn (Subject $subject, string $requirement, ?int $groupId, int $order) => SubjectAcademicAssignment::create([
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'requirement_type' => $requirement,
            'selection_group_id' => $groupId,
            'display_order' => $order,
            'status' => 'active',
        ]);

        $make($math, 'mandatory', null, 1);
        $make($biology, 'optional', $group->id, 2);
        $make($chemistry, 'elective', $group->id, 3);
        $make($art, 'elective', null, 4);

        return [
            'classGrade' => $classGrade,
            'group' => $group,
            'math' => $math,
            'biology' => $biology,
            'chemistry' => $chemistry,
            'art' => $art,
        ];
    }

    public function test_resolver_splits_selection_into_mandatory_groups_and_ungrouped(): void
    {
        $country = $this->country();
        $fixture = $this->selectionFixture($country);

        $institute = $this->institute($country);
        TenantContext::set($institute->id);

        $selection = app(AcademicSubjectService::class)->resolveForSelection($institute, $fixture['classGrade']);

        $this->assertCount(1, $selection['mandatory']);
        $this->assertSame('Mathematics', $selection['mandatory'][0]['name']);
        $this->assertSame('mandatory', $selection['mandatory'][0]['requirement_type']);

        $this->assertCount(1, $selection['groups']);
        $rules = $selection['groups'][0]['rules'];
        $this->assertSame(1, $rules['minimum']);
        $this->assertSame(1, $rules['maximum']);
        $this->assertSame(2, $rules['size']);
        $this->assertCount(2, $selection['groups'][0]['members']);

        $this->assertCount(1, $selection['ungrouped']);
        $this->assertSame('Art & Culture', $selection['ungrouped'][0]['name']);

        $this->assertEmpty($selection['config_errors']);
        $this->assertSame('Art & Culture', $selection['flat'][$fixture['art']->id]['subject']);
    }

    public function test_validator_enforces_mandatory_and_group_rules(): void
    {
        $country = $this->country();
        $fixture = $this->selectionFixture($country);

        $institute = $this->institute($country);
        TenantContext::set($institute->id);

        $validator = app(StudentSubjectSelectionValidator::class);

        // Pick one group member → mandatory auto-included, group rule satisfied.
        $result = $validator->validate($institute, $fixture['classGrade'], null, [$fixture['biology']->id]);
        $this->assertTrue($result['valid']);
        $this->assertCount(1, $result['auto_included']);
        $this->assertSame($fixture['math']->id, $result['auto_included'][0]['subject_id']);
        $this->assertContains($fixture['math']->id, $result['selected_ids']);

        // No group pick while mandatory included → group minimum violation.
        $result = $validator->validate($institute, $fixture['classGrade'], null, [$fixture['math']->id]);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('group_minimum', $codes);

        // Both group members picked → group maximum violation.
        $result = $validator->validate($institute, $fixture['classGrade'], null, [$fixture['math']->id, $fixture['biology']->id, $fixture['chemistry']->id]);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('group_maximum', $codes);

        // Mandatory missing when auto-include disabled.
        $result = $validator->validate($institute, $fixture['classGrade'], null, [$fixture['biology']->id], false);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('missing_mandatory', $codes);

        // Out-of-curriculum subject rejected.
        $result = $validator->validate($institute, $fixture['classGrade'], null, [$fixture['biology']->id, 999999]);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('subject_not_available', $codes);

        // Duplicate subject rejected.
        $result = $validator->validate($institute, $fixture['classGrade'], null, [$fixture['biology']->id, $fixture['biology']->id]);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('duplicate_subject', $codes);
    }
}
