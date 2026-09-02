<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminNavTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'nav-admin@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    protected function instituteStaff(): InstituteUser
    {
        $institute = Institute::firstOrCreate(
            ['name' => 'MAWA ACADEMY'],
            [
                'slug' => 'mawa-academy',
                'country' => 'Bangladesh',
                'country_id' => \App\Models\Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true])->id,
                'industry' => 'education',
                'sub_industry' => 'school',
                'status' => 'active',
            ]
        );

        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => 1,
            'email' => 'nav-staff@example.test',
            'phone' => '01700009999',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_platform_admin_views_every_navigation_page(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->get(route('admin.institutes.index'))->assertOk()->assertSee('Institutes');
        $this->get(route('admin.institutes.bin'))->assertOk()->assertSee('Recycle Bin');
        $this->get(route('admin.courses.index'))->assertOk()->assertSee('Courses')->assertSee('Classes &amp; Subjects', false);
        $this->get(route('admin.courses.batches'))->assertOk()->assertSee('Batches')->assertSee('filter-search-row', false);
        $this->get(route('admin.courses.assignment'))->assertOk()->assertSee('Course Assignment')->assertSee('filter-search-row', false);
        $this->get(route('admin.courses.requests'))->assertOk()->assertSee('Course Requests');
        $this->get(route('admin.students.index'))->assertOk()->assertSee('Student Registration');
        $this->get(route('admin.certificates.index'))->assertOk()->assertSee('Certificates');
        $this->get(route('admin.notifications.index'))->assertOk()->assertSee('Notifications');
        $this->get(route('admin.settings.index'))->assertOk()->assertSee('Staff Requests');
        $this->get(route('admin.settings.account'))->assertOk()->assertSee('Email');
        $this->get(route('admin.settings.password'))->assertOk()->assertSee('Current Password');
        $this->get(route('admin.settings.staff'))->assertOk()->assertSee('Approve');
    }

    public function test_course_pages_share_tab_navigation(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        foreach ([
            route('admin.courses.index'),
            route('admin.courses.batches'),
            route('admin.courses.subjects'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Courses')
                ->assertSee('Subjects')
                ->assertSee('filter-search-row', false);
        }

        $this->get(route('admin.courses.batches'))->assertDontSee('Course Assignment', false);
        $this->get(route('admin.courses.batches'))->assertDontSee('Course Requests', false);
    }

    public function test_institute_staff_views_new_navigation_pages(): void
    {
        TenantContext::clear();
        $this->actingAs($this->instituteStaff(), 'institute_user');

        $this->get(route('courses.manage.index'))->assertOk()->assertSee('Course Master');
        $this->get(route('certificates.index'))->assertOk()->assertSee('Certificates');
        $this->get(route('settings.index'))->assertOk()->assertSee('settings-tab-btn', false);
        $this->get(route('settings.account'))->assertOk()->assertSee('nav-staff@example.test');
        $this->get(route('settings.appearance'))->assertOk()->assertSee('form-control-color', false);
    }

    public function test_institute_courses_and_certificates_use_standard_list_view(): void
    {
        TenantContext::clear();
        $this->actingAs($this->instituteStaff(), 'institute_user');

        $this->get(route('courses.manage.index'))
            ->assertOk()
            ->assertSee('Course Master');

        $this->get(route('certificates.index'))
            ->assertOk()
            ->assertSeeLivewire('certificate-list');
    }

    public function test_platform_admin_sidebar_education_items_hidden_without_education_filter(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->get(route('dashboard', ['industry' => 'finance']))
            ->assertOk()
            ->assertDontSee('<span class="sidebar-label">Institutes</span>', false)
            ->assertDontSee('Classes &amp; Subjects', false)
            ->assertDontSee('Student Registration', false);
    }

    public function test_platform_admin_sidebar_shows_education_items_for_education_industry(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->get(route('dashboard', ['industry' => 'education']))
            ->assertOk()
            ->assertSee('<span class="sidebar-label">Institutes</span>', false)
            ->assertSee('Classes &amp; Subjects', false)
            ->assertSee('Student Registration', false);
    }

    public function test_platform_admin_sidebar_has_industry_button(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<span class="sidebar-label">All Industries Settings</span>', false)
            ->assertDontSee('<span class="sidebar-label">Settings</span>', false);

        $this->get(route('dashboard', ['industry' => 'education']))
            ->assertOk()
            ->assertSee('<span class="sidebar-label">Education Settings</span>', false);

        $this->get(route('dashboard', ['industry' => 'retail']))
            ->assertOk()
            ->assertSee('<span class="sidebar-label">Retail Settings</span>', false);
    }

    public function test_platform_admin_industry_settings_pages_render(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->get(route('admin.industry-settings'))
            ->assertOk()
            ->assertSee('All Industries Settings');

        $this->get(route('admin.industry-settings', ['industry' => 'education']))
            ->assertOk()
            ->assertSee('Education Settings');

        $this->get(route('admin.industry-settings', ['industry' => 'finance']))
            ->assertOk()
            ->assertSee('Finance &amp; Banking Settings', false);
    }

    public function test_platform_admin_sidebar_industry_button_links_to_industry_settings(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->get(route('dashboard', ['industry' => 'education']))
            ->assertOk()
            ->assertSee(route('admin.industry-settings', ['industry' => 'education']), false);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('admin.industry-settings'), false);
    }

    public function test_education_institute_sidebar_shows_classes_subjects_button(): void
    {
        TenantContext::clear();
        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $this->assertSame('education', $institute->industry);

        $this->actingAs($this->instituteStaff(), 'institute_user');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Courses &amp; Subjects', false);
    }

    public function test_platform_admin_sidebar_education_items_persist_after_navigation(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->get(route('admin.institutes.index', ['industry' => 'education']))
            ->assertOk()
            ->assertSee('<span class="sidebar-label">Institutes</span>', false)
            ->assertSee('Classes &amp; Subjects', false)
            ->assertSee('Student Registration', false);

        $this->get(route('admin.students.index', ['industry' => 'education']))
            ->assertOk()
            ->assertSee('<span class="sidebar-label">Institutes</span>', false)
            ->assertSee('Classes &amp; Subjects', false)
            ->assertSee('Student Registration', false);

        $this->get(route('admin.certificates.requests', ['industry' => 'education']))
            ->assertOk()
            ->assertSee('<span class="sidebar-label">Institutes</span>', false)
            ->assertSee('Classes &amp; Subjects', false)
            ->assertSee('Student Registration', false);
    }

    public function test_platform_admin_sidebar_education_items_persist_on_routes_without_industry_param(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        // Dashboard stat cards and in-page links navigate to these admin routes
        // without carrying ?industry=education. The education nav must not vanish.
        foreach ([
            route('admin.courses.index'),
            route('admin.courses.subjects'),
            route('admin.courses.batches'),
            route('admin.students.index'),
            route('admin.certificates.index'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<span class="sidebar-label">Institutes</span>', false)
                ->assertSee('Classes &amp; Subjects', false)
                ->assertSee('Student Registration', false);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
    }
}
