<?php

namespace Tests\Feature\Settings;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Journal;
use App\Models\Medical\Patient;
use App\Models\Role;
use App\Models\Setting;
use App\Services\Medical\BillingService;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Settings → Medical Billing journal settings (revenue_mode / auto_post).
 *
 * Route: PUT settings/medical-billing (permission:settings.accounting.manage
 * + module_access:finance), backed by per-institute Setting keys
 * medical.billing.{revenue_mode,auto_post}.{institute_id}.
 */
class MedicalBillingSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $institute;

    protected InstituteUser $owner;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'Test Billing Hospital '.uniqid(),
            'slug' => 'test-billing-hospital-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'status' => 'active',
        ]);

        $role = Role::where('slug', 'institute-owner')->firstOrFail();
        $this->owner = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'Billing',
            'last_name' => 'Owner',
            'email' => 'billing-owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        // Route gate: module_access:finance.
        app(ModuleAccessService::class)->enableModule($this->institute, 'finance');

        $this->seedCurrentFiscalYear();
        $this->seedMedicalAccounts();

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'Settings',
            'last_name' => 'Patient',
            'phone' => '017'.rand(10000000, 99999999),
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
        ]);

        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');
        Setting::set("medical.billing.auto_post.{$this->institute->id}", '1');

        $this->actingAs($this->owner, 'institute_user');
    }

    protected function seedCurrentFiscalYear(): void
    {
        FiscalYear::firstOrCreate(
            ['institute_id' => $this->institute->id, 'branch_id' => null, 'is_current' => true],
            [
                'name' => 'FY '.date('Y'),
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31'),
                'status' => 'open',
            ]
        );
    }

    protected function seedMedicalAccounts(): void
    {
        $assetGroup = AccountGroup::firstOrCreate(
            ['institute_id' => $this->institute->id, 'code' => '1'],
            ['name' => 'Assets', 'category' => 'asset', 'is_system' => true]
        );
        $liabilityGroup = AccountGroup::firstOrCreate(
            ['institute_id' => $this->institute->id, 'code' => '2'],
            ['name' => 'Liabilities', 'category' => 'liability', 'is_system' => true]
        );
        $incomeGroup = AccountGroup::firstOrCreate(
            ['institute_id' => $this->institute->id, 'code' => '4'],
            ['name' => 'Income', 'category' => 'income', 'is_system' => true]
        );

        foreach ([
            ['code' => '1000.1', 'name' => 'Cash in Hand', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_cash' => true]],
            ['code' => '1200.1', 'name' => 'Trade Receivable', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_receivable' => true]],
            ['code' => '1200.4', 'name' => 'Insurance / TPA Receivable', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_receivable' => true]],
            ['code' => '2100.1', 'name' => 'VAT Output Payable', 'type' => 'liability', 'group' => $liabilityGroup, 'flags' => []],
            ['code' => '4300.1', 'name' => 'Consultation Fees', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
            ['code' => '4000.5', 'name' => 'Discount Allowed', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
        ] as $acc) {
            ChartOfAccount::firstOrCreate(
                ['institute_id' => $this->institute->id, 'code' => $acc['code']],
                [
                    'branch_id' => null,
                    'name' => $acc['name'],
                    'type' => $acc['type'],
                    'account_group_id' => $acc['group']->id,
                    'is_system' => true,
                    'is_active' => true,
                    'is_postable' => true,
                ] + $acc['flags']
            );
        }
    }

    protected function putSettings(array $overrides = [])
    {
        return $this->put(route('settings.medical-billing.update'), array_merge([
            'revenue_mode' => 'cash',
            'auto_post' => '1',
        ], $overrides));
    }

    public function test_can_view_medical_billing_section(): void
    {
        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Medical Billing', false)
            ->assertSee('settings/medical-billing', false)
            ->assertSee('name="revenue_mode"', false)
            ->assertSee('id="rm-accrual"', false)
            ->assertSee('id="rm-cash"', false)
            ->assertSee('id="auto-post"', false);
    }

    public function test_saves_revenue_mode_and_auto_post(): void
    {
        $this->putSettings(['revenue_mode' => 'cash', 'auto_post' => '1'])
            ->assertRedirect(route('settings.index', '#pane-medical'))
            ->assertSessionHas('status');

        $this->assertSame('cash', Setting::get("medical.billing.revenue_mode.{$this->institute->id}"));
        $this->assertSame('1', Setting::get("medical.billing.auto_post.{$this->institute->id}"));
    }

    public function test_rejects_invalid_revenue_mode(): void
    {
        $this->putSettings(['revenue_mode' => 'mixed'])
            ->assertSessionHasErrors('revenue_mode');

        $this->assertSame('accrual', Setting::get("medical.billing.revenue_mode.{$this->institute->id}"));
    }

    public function test_blocks_mode_change_when_journals_exist_in_current_fy(): void
    {
        // A real medical invoice in the current FY posts a sale journal.
        app(BillingService::class)->createInvoice($this->patient, 'opd', [
            ['description' => 'Consultation', 'amount' => 500, 'quantity' => 1, 'discount' => 0],
        ]);

        $this->assertGreaterThan(
            0,
            Journal::where('institute_id', $this->institute->id)
                ->where('ref_type', 'medical_invoice')
                ->count(),
            'Fixture must have produced a medical journal before testing the guard'
        );

        $this->putSettings(['revenue_mode' => 'cash'])
            ->assertSessionHasErrors('revenue_mode');

        $errors = session('errors')->get('revenue_mode');
        $this->assertStringContainsString('fiscal year', strtolower(implode(' ', $errors)));

        $this->assertSame('accrual', Setting::get("medical.billing.revenue_mode.{$this->institute->id}"));
    }

    public function test_allows_mode_change_when_no_journals_in_current_fy(): void
    {
        $this->putSettings(['revenue_mode' => 'cash'])
            ->assertRedirect(route('settings.index', '#pane-medical'));

        $this->assertSame('cash', Setting::get("medical.billing.revenue_mode.{$this->institute->id}"));
    }

    public function test_auto_post_can_be_toggled_independently(): void
    {
        // Journals exist, mode unchanged → the FY guard must not block the
        // auto_post toggle.
        app(BillingService::class)->createInvoice($this->patient, 'opd', [
            ['description' => 'Consultation', 'amount' => 400, 'quantity' => 1, 'discount' => 0],
        ]);

        $this->putSettings(['revenue_mode' => 'accrual', 'auto_post' => '0'])
            ->assertRedirect(route('settings.index', '#pane-medical'))
            ->assertSessionHasNoErrors();

        $this->assertSame('0', Setting::get("medical.billing.auto_post.{$this->institute->id}"));
        $this->assertSame('accrual', Setting::get("medical.billing.revenue_mode.{$this->institute->id}"));
    }

    public function test_requires_settings_accounting_manage_permission(): void
    {
        $emptyRole = Role::create([
            'name' => 'No Accounting '.uniqid(),
            'slug' => 'no-accounting-'.uniqid(),
            'status' => 'active',
        ]);

        $staff = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $emptyRole->id,
            'first_name' => 'Front',
            'last_name' => 'Desk',
            'email' => 'front-desk-'.uniqid().'@example.test',
            'phone' => '01800'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->actingAs($staff, 'institute_user')
            ->put(route('settings.medical-billing.update'), [
                'revenue_mode' => 'cash',
                'auto_post' => '1',
            ])
            ->assertForbidden();

        $this->assertSame('accrual', Setting::get("medical.billing.revenue_mode.{$this->institute->id}"));
    }

    public function test_tenant_isolation_settings_are_per_institute(): void
    {
        $country = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        $otherInstitute = Institute::create([
            'name' => 'Other Billing Hospital '.uniqid(),
            'slug' => 'other-billing-hospital-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'healthcare',
            'status' => 'active',
        ]);

        $this->putSettings(['revenue_mode' => 'cash'])
            ->assertRedirect(route('settings.index', '#pane-medical'));

        $this->assertSame(
            'cash',
            Setting::get("medical.billing.revenue_mode.{$this->institute->id}")
        );

        // A healthcare institute is auto-seeded with the accrual default by
        // the Institute::created observer — A's switch to cash must not leak.
        $this->assertSame(
            'accrual',
            Setting::get("medical.billing.revenue_mode.{$otherInstitute->id}")
        );
    }
}
