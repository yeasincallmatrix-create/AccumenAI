<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NavbarNavigationTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private InstituteUser $user;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();

        $country = Country::firstOrCreate(['iso2' => 'BD'], [
            'name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true,
        ]);

        $this->institute = Institute::create([
            'name' => 'Navbar Test Institute',
            'slug' => 'navbar-test-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'education',
            'sub_industry' => 'school',
            'status' => 'active',
        ]);

        $role = Role::where('slug', 'institute-owner')->first()
            ?? Role::firstOrCreate(['slug' => 'institute-owner'], ['name' => 'Institute Owner', 'is_system' => true]);

        $this->user = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'email' => 'navbar-owner-'.uniqid().'@test.com',
            'phone' => '017'.rand(10000000, 99999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        TenantContext::set($this->institute->id);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_dashboard_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/')
            ->assertStatus(200);
    }

    public function test_students_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/students')
            ->assertStatus(200);
    }

    public function test_academic_analytics_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/academic/analytics')
            ->assertStatus(200);
    }

    public function test_settings_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/settings')
            ->assertStatus(200);
    }

    public function test_hr_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/hr')
            ->assertStatus(200);
    }

    public function test_teachers_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/teachers')
            ->assertStatus(200);
    }

    public function test_admissions_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/admissions')
            ->assertStatus(200);
    }

    public function test_exams_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/exams')
            ->assertStatus(200);
    }

    public function test_certificates_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/certificates')
            ->assertStatus(200);
    }

    public function test_classes_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/classes')
            ->assertStatus(200);
    }

    public function test_courses_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/courses/manage')
            ->assertStatus(200);
    }

    public function test_crm_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/crm')
            ->assertStatus(200);
    }

    public function test_finance_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/finance')
            ->assertStatus(200);
    }

    public function test_purchase_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/purchase/orders')
            ->assertStatus(200);
    }

    public function test_sales_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/sales/settings')
            ->assertStatus(200);
    }

    public function test_recycle_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/recycle')
            ->assertStatus(200);
    }

    public function test_staff_invite_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/staff/invite')
            ->assertStatus(200);
    }

    public function test_alumni_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/alumni')
            ->assertStatus(200);
    }

    public function test_workflows_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/workflows')
            ->assertStatus(200);
    }

    public function test_academic_dashboard_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/academic-dashboard')
            ->assertStatus(200);
    }

    public function test_hr_employees_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/hr/employees')
            ->assertStatus(200);
    }

    public function test_hr_departments_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/hr/departments')
            ->assertStatus(200);
    }

    public function test_hr_designations_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/hr/designations')
            ->assertStatus(200);
    }

    public function test_hr_attendance_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/hr/attendance')
            ->assertStatus(200);
    }

    public function test_hr_leave_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/hr/leave')
            ->assertStatus(200);
    }

    public function test_hr_payroll_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/hr/payroll/periods')
            ->assertStatus(200);
    }

    public function test_finance_chart_of_accounts_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/finance/chart-of-accounts')
            ->assertStatus(200);
    }

    public function test_finance_journals_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/finance/journals')
            ->assertStatus(200);
    }

    public function test_finance_invoices_loads(): void
    {
        $this->actingAs($this->user, 'institute_user')
            ->get('/finance/invoices')
            ->assertStatus(200);
    }
}
