<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
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
            'name' => 'secondary',
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

    // ------------------------------------------------------------- Super Admin

    public function test_admin_index_requires_platform_admin(): void
    {
        TenantContext::clear();
        $this->get(route('admin.academic.index'))->assertRedirect('/admin/login');
    }

    public function test_platform_admin_lists_countries(): void
    {
        TenantContext::clear();
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'academic-admin@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
        $this->actingAs($admin, 'platform_admin');

        $this->country('BD', 'Bangladesh');

        $this->get(route('admin.academic.index'))
            ->assertOk()
            ->assertSee('Academic Structure')
            ->assertSee('Bangladesh');
    }

    public function test_platform_admin_creates_system_level_class_and_group(): void
    {
        TenantContext::clear();
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'academic-admin2@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
        $this->actingAs($admin, 'platform_admin');

        $bd = $this->country('BD', 'Bangladesh');

        $this->post(route('admin.academic.systems.store', $bd), [
            'name' => 'General Education',
            'code' => 'general',
            'display_order' => 0,
        ])->assertRedirect(route('admin.academic.country', $bd));

        $system = EducationSystem::where('code', 'general')->firstOrFail();

        $this->post(route('admin.academic.levels.store', $system), [
            'name' => 'secondary',
            'code' => 'secondary',
            'display_order' => 1,
        ])->assertRedirect(route('admin.academic.system', $system));

        $level = AcademicLevel::where('code', 'secondary')->firstOrFail();

        $this->post(route('admin.academic.classes.store', $level), [
            'name' => 'Class 8',
            'code' => 'c8',
            'display_order' => 0,
        ])->assertRedirect(route('admin.academic.level', $level));

        $classGrade = ClassGrade::where('code', 'c8')->firstOrFail();

        $this->post(route('admin.academic.groups.store', $classGrade), [
            'name' => 'Science',
            'code' => 'sci',
            'display_order' => 0,
        ])->assertRedirect(route('admin.academic.classGrade', $classGrade));

        $this->assertDatabaseHas('education_systems', ['code' => 'general']);
        $this->assertDatabaseHas('academic_levels', ['code' => 'secondary']);
        $this->assertDatabaseHas('class_grades', ['code' => 'c8']);
        $this->assertDatabaseHas('academic_groups', ['code' => 'sci']);
    }

    public function test_platform_admin_updates_country_unit_label(): void
    {
        TenantContext::clear();
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'academic-admin3@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
        $this->actingAs($admin, 'platform_admin');

        $bd = $this->country('BD', 'Bangladesh');

        $this->put(route('admin.academic.country.update', $bd), [
            'academic_unit_label' => 'School',
        ])->assertRedirect(route('admin.academic.country', $bd));

        $this->assertDatabaseHas('countries', ['id' => $bd->id, 'academic_unit_label' => 'School']);
    }

    // ------------------------------------------------------------- Institute

    private function institute(Country $country): Institute
    {
        return Institute::create([
            'name' => 'Academic Structure Inst',
            'slug' => 'academic-structure-inst-'.uniqid(),
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
            'email' => 'academic-owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    public function test_institute_owner_views_academic_structure_page(): void
    {
        $country = $this->country('BD', 'Bangladesh');
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $this->group($classGrade);

        $institute = $this->institute($country);
        $owner = $this->owner($institute);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->get(route('settings.academic.index'))
            ->assertOk()
            ->assertSee('General Education')
            ->assertSee('secondary')
            ->assertSee('Class 8')
            ->assertSee('Science');
    }

    public function test_institute_owner_can_save_custom_unit_label(): void
    {
        $country = $this->country('BD', 'Bangladesh');
        $this->system($country);
        $institute = $this->institute($country);
        $owner = $this->owner($institute);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('settings.academic.label'), [
                'academic_unit_label' => 'College',
            ])
            ->assertRedirect(route('settings.academic.index'));

        $this->assertDatabaseHas('institute_settings', [
            'institute_id' => $institute->id,
            'academic_unit_label' => 'College',
        ]);
    }

    public function test_institute_owner_can_disable_inherited_level(): void
    {
        $country = $this->country('BD', 'Bangladesh');
        $system = $this->system($country);
        $level = $this->level($system);
        $this->classGrade($level);

        $institute = $this->institute($country);
        $owner = $this->owner($institute);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->put(route('settings.academic.levels.update', $level), [
                'enabled' => 0,
            ])
            ->assertRedirect(route('settings.academic.index'));

        $this->assertDatabaseHas('institute_academic_levels', [
            'institute_id' => $institute->id,
            'academic_level_id' => $level->id,
            'status' => 0,
        ]);
    }

    public function test_institute_owner_can_add_custom_class(): void
    {
        $country = $this->country('BD', 'Bangladesh');
        $system = $this->system($country);
        $level = $this->level($system);

        $institute = $this->institute($country);
        $owner = $this->owner($institute);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('settings.academic.classes.store'), [
                'name' => 'Class 9',
                'academic_level_id' => $level->id,
                'display_order' => 0,
            ])
            ->assertRedirect(route('settings.academic.index'));

        $this->assertDatabaseHas('institute_class_grades', [
            'institute_id' => $institute->id,
            'academic_level_id' => $level->id,
            'name' => 'Class 9',
            'is_custom' => 1,
        ]);
    }

    public function test_teacher_cannot_access_academic_structure(): void
    {
        $country = $this->country('BD', 'Bangladesh');
        $this->system($country);
        $institute = $this->institute($country);

        $teacher = InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => Role::where('slug', 'teacher')->firstOrFail()->id,
            'first_name' => 'Teacher',
            'last_name' => 'User',
            'email' => 'academic-teacher-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        TenantContext::set($institute->id);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.index'))
            ->assertForbidden();
    }
}
