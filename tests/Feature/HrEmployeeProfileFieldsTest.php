<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckModuleAccess;
use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Branch;
use App\Models\Country;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrEmployee;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * HR employee profile fields: blood group, marital status, education
 * qualification, expertise chips and the cascading <x-address> selectors
 * (country → division → district → upazila) for present/permanent address.
 */
class HrEmployeeProfileFieldsTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private Branch $branch;

    private InstituteUser $owner;

    private int $departmentId;

    private int $designationId;

    private Country $country;

    private int $divisionId;

    private int $districtId;

    private int $upazilaId;

    protected function setUp(): void
    {
        parent::setUp();

        // The HR module gate is driven by the subscription plan modules and
        // is unrelated to this feature.
        $this->withoutMiddleware(CheckModuleAccess::class);

        $country = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );
        $this->country = $country;

        // Minimal geo hierarchy so the <x-address> cascades have options.
        $level1 = AdministrativeLevel::firstOrCreate(
            ['country_id' => $country->id, 'level_number' => 1],
            ['name' => 'Division', 'slug' => 'division', 'status' => true]
        );
        $level2 = AdministrativeLevel::firstOrCreate(
            ['country_id' => $country->id, 'level_number' => 2],
            ['name' => 'District', 'slug' => 'district', 'status' => true]
        );
        $level3 = AdministrativeLevel::firstOrCreate(
            ['country_id' => $country->id, 'level_number' => 3],
            ['name' => 'Upazila', 'slug' => 'upazila', 'status' => true]
        );

        $division = AdministrativeUnit::firstOrCreate(
            ['country_id' => $country->id, 'administrative_level_id' => $level1->id, 'parent_id' => null, 'name' => 'Dhaka '.uniqid()],
            ['status' => true]
        );
        $district = AdministrativeUnit::firstOrCreate(
            ['country_id' => $country->id, 'administrative_level_id' => $level2->id, 'parent_id' => $division->id, 'name' => 'Gazipur '.uniqid()],
            ['status' => true]
        );
        $upazila = AdministrativeUnit::firstOrCreate(
            ['country_id' => $country->id, 'administrative_level_id' => $level3->id, 'parent_id' => $district->id, 'name' => 'Sreepur '.uniqid()],
            ['status' => true]
        );

        $this->divisionId = $division->id;
        $this->districtId = $district->id;
        $this->upazilaId = $upazila->id;

        $this->institute = Institute::create([
            'name' => 'Profile Fields '.uniqid(),
            'slug' => 'profile-fields-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'institute_id' => $this->institute->id,
            'name' => 'Main '.uniqid(),
            'status' => 'active',
        ]);

        $this->departmentId = HrDepartment::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branch->id,
            'name' => 'Cardiology',
            'display_order' => 0,
            'is_active' => true,
        ])->id;

        $this->designationId = HrDesignation::create([
            'institute_id' => $this->institute->id,
            'department_id' => $this->departmentId,
            'name' => 'Consultant',
            'display_order' => 0,
            'is_active' => true,
        ])->id;

        $this->owner = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branch->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'first_name' => 'Profile',
            'last_name' => 'Owner',
            'email' => 'profile-owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        TenantContext::set($this->institute->id);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Rafi',
            'last_name' => 'Hasan',
            'phone' => '+8801711223344',
            'branch_id' => $this->branch->id,
            'department_id' => $this->departmentId,
            'designation_id' => $this->designationId,
            'employment_status' => 'active',
            'employment_type' => 'full_time',
            'joining_date' => now()->toDateString(),
        ], $overrides);
    }

    private function employee(): HrEmployee
    {
        return HrEmployee::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->orderBy('id')
            ->firstOrFail();
    }

    public function test_owner_can_create_employee_with_profile_fields(): void
    {
        $this->actingAs($this->owner, 'institute_user')
            ->post(route('hr.employees.store'), $this->payload([
                'blood_group' => 'O+',
                'marital_status' => 'married',
                'education_qualification' => 'MBBS, FCPS (Cardiology)',
                'expertise' => json_encode(['Cardiology', ' ECG ', 'Cardiology']),
                'present_country_id' => $this->country->id,
                'present_admin_1_id' => $this->divisionId,
                'present_admin_2_id' => $this->districtId,
                'present_admin_3_id' => $this->upazilaId,
                'present_zip_code' => '1704',
                'present_address' => 'House 12, Road 5, Dhanmondi',
                'permanent_country_id' => $this->country->id,
                'permanent_admin_1_id' => $this->divisionId,
                'permanent_admin_2_id' => $this->districtId,
                'permanent_admin_3_id' => $this->upazilaId,
                'permanent_zip_code' => '7000',
                'permanent_address' => 'Village Kushtia',
            ]))
            ->assertRedirect();

        $employee = $this->employee();

        $this->assertSame('O+', $employee->blood_group);
        $this->assertSame('married', $employee->marital_status);
        $this->assertSame('MBBS, FCPS (Cardiology)', $employee->education_qualification);
        $this->assertSame(['Cardiology', 'ECG'], $employee->expertise);
        $this->assertSame('House 12, Road 5, Dhanmondi', $employee->present_address);
        $this->assertSame('Village Kushtia', $employee->permanent_address);
        $this->assertEquals($this->country->id, $employee->present_country_id);
        $this->assertEquals($this->divisionId, $employee->present_admin_1_id);
        $this->assertEquals($this->districtId, $employee->present_admin_2_id);
        $this->assertEquals($this->upazilaId, $employee->present_admin_3_id);
        $this->assertSame('1704', $employee->present_zip_code);
        $this->assertEquals($this->country->id, $employee->permanent_country_id);
        $this->assertEquals($this->upazilaId, $employee->permanent_admin_3_id);
        $this->assertSame('7000', $employee->permanent_zip_code);
    }

    public function test_profile_fields_can_be_updated_and_cleared(): void
    {
        $this->actingAs($this->owner, 'institute_user')
            ->post(route('hr.employees.store'), $this->payload([
                'blood_group' => 'A-',
                'marital_status' => 'single',
                'education_qualification' => 'MBBS',
                'expertise' => json_encode(['Cardiology']),
                'present_country_id' => $this->country->id,
                'present_admin_1_id' => $this->divisionId,
                'present_zip_code' => '1215',
                'present_address' => 'Old present address',
                'permanent_address' => 'Old permanent address',
            ]))
            ->assertRedirect();

        $employee = $this->employee();

        $this->actingAs($this->owner, 'institute_user')
            ->put(route('hr.employees.update', $employee), $this->payload([
                'blood_group' => '',
                'marital_status' => '',
                'education_qualification' => '',
                'expertise' => json_encode(['ECG', 'Echo']),
                'present_country_id' => '',
                'present_admin_1_id' => '',
                'present_zip_code' => '',
                'present_address' => '',
                'permanent_address' => 'Village Kushtia',
            ]))
            ->assertRedirect();

        $employee->refresh();

        $this->assertNull($employee->blood_group);
        $this->assertNull($employee->marital_status);
        $this->assertNull($employee->education_qualification);
        $this->assertSame(['ECG', 'Echo'], $employee->expertise);
        $this->assertNull($employee->present_country_id);
        $this->assertNull($employee->present_admin_1_id);
        $this->assertNull($employee->present_zip_code);
        $this->assertNull($employee->present_address);
        $this->assertSame('Village Kushtia', $employee->permanent_address);
    }

    public function test_invalid_profile_values_are_rejected(): void
    {
        $this->actingAs($this->owner, 'institute_user')
            ->post(route('hr.employees.store'), $this->payload([
                'blood_group' => 'AB',
                'marital_status' => 'complicated',
                'education_qualification' => str_repeat('A', 501),
                'present_country_id' => 99999999,
                'present_admin_1_id' => 99999999,
            ]))
            ->assertSessionHasErrors([
                'blood_group',
                'marital_status',
                'education_qualification',
                'present_country_id',
                'present_admin_1_id',
            ]);

        $this->assertSame(
            0,
            HrEmployee::withoutGlobalScopes()->where('institute_id', $this->institute->id)->count()
        );
    }

    public function test_empty_expertise_is_stored_as_an_empty_list(): void
    {
        $this->actingAs($this->owner, 'institute_user')
            ->post(route('hr.employees.store'), $this->payload([
                'blood_group' => 'B+',
                'expertise' => '',
            ]))
            ->assertRedirect();

        $this->assertSame([], $this->employee()->expertise);
    }

    public function test_create_page_renders_profile_fields(): void
    {
        $response = $this->actingAs($this->owner, 'institute_user')->get(route('hr.employees.create'));

        $response->assertOk();
        $response->assertSee('Blood Group');
        $response->assertSee('Marital Status');
        $response->assertSee('Education Qualification');
        $response->assertSee('Expertise');
        $response->assertSee('Present Address');
        $response->assertSee('Permanent Address');
        $response->assertSee('Same as present address');
        $response->assertSee('name="blood_group"', false);
        $response->assertSee('name="present_address"', false);
        $response->assertSee('name="present_country_id"', false);
        $response->assertSee('name="present_admin_1_id"', false);
        $response->assertSee('name="permanent_country_id"', false);
        $response->assertSee('name="permanent_zip_code"', false);
        $response->assertSee('id="expertise_input"', false);
    }

    public function test_edit_and_show_pages_render_saved_profile_fields(): void
    {
        $divisionName = AdministrativeUnit::query()->whereKey($this->divisionId)->value('name');
        $districtName = AdministrativeUnit::query()->whereKey($this->districtId)->value('name');
        $upazilaName = AdministrativeUnit::query()->whereKey($this->upazilaId)->value('name');

        $this->actingAs($this->owner, 'institute_user')
            ->post(route('hr.employees.store'), $this->payload([
                'blood_group' => 'O+',
                'marital_status' => 'married',
                'education_qualification' => 'MBBS, FCPS (Cardiology)',
                'expertise' => json_encode(['Cardiology', 'ECG']),
                'present_country_id' => $this->country->id,
                'present_admin_1_id' => $this->divisionId,
                'present_admin_2_id' => $this->districtId,
                'present_admin_3_id' => $this->upazilaId,
                'present_zip_code' => '1704',
                'present_address' => 'House 12, Road 5, Dhanmondi',
                'permanent_country_id' => $this->country->id,
                'permanent_admin_1_id' => $this->divisionId,
                'permanent_admin_2_id' => $this->districtId,
                'permanent_admin_3_id' => $this->upazilaId,
                'permanent_zip_code' => '7000',
                'permanent_address' => 'Village Kushtia',
            ]))
            ->assertRedirect();

        $employee = $this->employee();

        $edit = $this->actingAs($this->owner, 'institute_user')->get(route('hr.employees.edit', $employee));
        $edit->assertOk();
        $edit->assertSee('name="blood_group"', false);
        $edit->assertSee('MBBS, FCPS (Cardiology)');
        $edit->assertSee('House 12, Road 5, Dhanmondi');
        $edit->assertSee('name="permanent_address"', false);
        $edit->assertSee('name="permanent_country_id"', false);
        $edit->assertSee('name="present_admin_3_id"', false);
        $edit->assertSee('value="1704"', false);

        $show = $this->actingAs($this->owner, 'institute_user')->get(route('hr.employees.show', $employee));
        $show->assertOk();
        $show->assertSee('Blood Group');
        $show->assertSee('O+');
        $show->assertSee('Marital Status');
        $show->assertSee('MBBS, FCPS (Cardiology)');
        $show->assertSee('Cardiology');
        $show->assertSee('House 12, Road 5, Dhanmondi');
        $show->assertSee('Village Kushtia');
        // Composed geo line: unit names + ZIP
        $show->assertSee($upazilaName);
        $show->assertSee($districtName);
        $show->assertSee($divisionName);
        $show->assertSee('ZIP 1704');
        $show->assertSee('ZIP 7000');
    }
}
