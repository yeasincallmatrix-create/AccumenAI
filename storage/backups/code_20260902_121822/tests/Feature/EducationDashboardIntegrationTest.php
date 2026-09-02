<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\CrmContact;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\InvoiceService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Step 34 — Education ↔ AccumenAI Core: the education dashboard surfaces CRM
 * and Finance summaries, gated by the exact permissions that gate those
 * modules (crm.view / finance.view).
 */
class EducationDashboardIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
    }

    // ------------------------------------------------------------ Fixtures

    protected function freshInstitute(string $industry = 'education'): Institute
    {
        $country = Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );

        $institute = Institute::create([
            'name' => 'Edu Dash '.mt_rand(1000, 9999),
            'slug' => 'edu-dash-'.mt_rand(1000, 9999),
            'industry' => $industry,
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        InstituteSetting::withoutGlobalScopes()->create([
            'institute_id' => $institute->id,
            'ai_config' => [
                'enabled' => true,
                'features' => ['assistant'],
                'daily_limit' => 0,
                'monthly_limit' => 0,
            ],
        ]);

        return $institute;
    }

    protected function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    protected function userFor(Institute $institute, string $roleSlug, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => $prefix,
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function admin(Institute $institute): InstituteUser
    {
        return $this->userFor($institute, 'institute-admin', 'admin');
    }

    // -------------------------------------------------------------- Tests

    public function test_dashboard_shows_crm_card_for_users_with_crm_view(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        TenantContext::set($institute->id);

        CrmContact::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'first_name' => 'Rahim',
            'last_name' => 'Ahmed',
            'is_customer' => true,
            'status' => 'active',
        ]);

        $admin = $this->admin($institute);

        $this->actingAs($admin, 'institute_user')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('CRM Contacts');
    }

    public function test_dashboard_shows_finance_card_for_users_with_finance_view(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        TenantContext::set($institute->id);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);
        app(AccountingSetupService::class)->setSetting($institute->id, 'invoice_auto_post', true, $branch->id);
        $owner = $this->userFor($institute, 'institute-owner', 'fin-dash');

        app(InvoiceService::class)->create($institute->id, $branch->id, [
            'invoice_type' => 'course_fee',
            'discount' => 0,
            'items' => [
                ['description' => 'Tuition', 'amount' => 7500],
            ],
        ], (int) $owner->id);

        $admin = $this->admin($institute);

        $this->actingAs($admin, 'institute_user')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Receivable')
            ->assertSee('7,500.00');
    }

    public function test_dashboard_hides_crm_and_finance_cards_for_teacher(): void
    {
        $institute = $this->freshInstitute();
        $teacher = $this->userFor($institute, 'teacher', 'teacher');

        $this->actingAs($teacher, 'institute_user')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('CRM Contacts')
            ->assertDontSee('Receivable');
    }
}
