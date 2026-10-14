<?php

namespace Tests\Feature\Medical;

use App\Models\Country;
use App\Models\Institute;
use App\Models\Role;
use App\Models\ChartOfAccount;
use App\Models\AccountGroup;
use App\Services\Medical\MedicalBillingJournalService;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MedicalBillingJournalServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'Test Hospital ' . uniqid(),
            'slug' => 'test-hospital-' . uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'healthcare',
            'status' => 'active',
        ]);

        // Enable medical module
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical');

        $role = Role::where('slug', 'institute-owner')->firstOrFail();
        $this->owner = \App\Models\InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'email' => 'test-owner-' . uniqid() . '@example.test',
            'phone' => '01700' . rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->patient = \App\Models\Medical\Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-' . uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'phone' => '017' . rand(10000000, 99999999),
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
        ]);

        // Manually create required accounts for medical billing
        $this->createMedicalAccounts();

        $this->service = app(MedicalBillingJournalService::class);
    }

    protected function createMedicalAccounts(): void
    {
        // Create fiscal year first (required for journal posting)
        $this->createFiscalYear();

        // Create account groups if not exist
        $assetGroup = AccountGroup::firstOrCreate([
            'institute_id' => $this->institute->id,
            'code' => '1',
        ], [
            'name' => 'Assets',
            'category' => 'asset',
            'is_system' => true,
        ]);

        $liabilityGroup = AccountGroup::firstOrCreate([
            'institute_id' => $this->institute->id,
            'code' => '2',
        ], [
            'name' => 'Liabilities',
            'category' => 'liability',
            'is_system' => true,
        ]);

        $incomeGroup = AccountGroup::firstOrCreate([
            'institute_id' => $this->institute->id,
            'code' => '4',
        ], [
            'name' => 'Income',
            'category' => 'income',
            'is_system' => true,
        ]);

        // Create required accounts
        $accounts = [
            ['code' => '1000.1', 'name' => 'Cash in Hand', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_cash' => true]],
            ['code' => '1200.1', 'name' => 'Trade Receivable', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_receivable' => true, 'cash_flow_category' => 'operating']],
            ['code' => '2100.1', 'name' => 'VAT Output Payable', 'type' => 'liability', 'group' => $liabilityGroup, 'flags' => ['cash_flow_category' => 'operating']],
            ['code' => '4300.1', 'name' => 'Consultation Fees', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
        ];

        foreach ($accounts as $acc) {
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

    protected function createFiscalYear(): void
    {
        \App\Models\FiscalYear::firstOrCreate(
            ['institute_id' => $this->institute->id, 'branch_id' => null, 'is_current' => true],
            [
                'name' => 'FY ' . date('Y'),
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31'),
                'status' => 'open',
            ]
        );
    }

    public function test_service_instantiates_and_reads_settings(): void
    {
        // Activate medical module (seeds billing settings)
        app(\App\Services\MedicalModuleActivator::class)->activateForHealthcare($this->institute);

        // Verify settings are readable
        $revenueMode = \App\Models\Setting::get("medical.billing.revenue_mode.{$this->institute->id}");
        $autoPost = \App\Models\Setting::get("medical.billing.auto_post.{$this->institute->id}");

        $this->assertEquals('accrual', $revenueMode);
        $this->assertEquals('1', $autoPost);

        // Service should be instantiable
        $this->assertInstanceOf(MedicalBillingJournalService::class, $this->service);
    }

    public function test_post_salejournal_returns_null_on_cash_mode(): void
    {
        // Force cash mode
        \App\Models\Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');
        \App\Models\Setting::set("medical.billing.auto_post.{$this->institute->id}", '1');

        $invoice = \App\Services\Medical\BillingService::computeTotals([
            ['amount' => 100, 'quantity' => 1, 'discount' => 0],
        ]);

        $medicalInvoice = \App\Models\Medical\Invoice::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'type' => 'opd',
            'subtotal' => $invoice['subtotal'],
            'tax' => $invoice['tax'],
            'discount' => $invoice['discount'],
            'total' => $invoice['total'],
            'paid_amount' => 0,
            'due_amount' => $invoice['total'],
            'status' => 'pending',
        ]);

        $result = $this->service->postSaleJournal($medicalInvoice, (int) $this->owner->id);
        $this->assertNull($result, 'Sale journal should be null in cash mode');
    }

    public function test_post_salejournal_creates_journal_in_accrual_mode(): void
    {
        // Ensure accrual mode
        \App\Models\Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');
        \App\Models\Setting::set("medical.billing.auto_post.{$this->institute->id}", '1');

        $invoice = \App\Services\Medical\BillingService::computeTotals([
            ['amount' => 100, 'quantity' => 1, 'discount' => 0],
        ]);

        $medicalInvoice = \App\Models\Medical\Invoice::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'type' => 'opd',
            'subtotal' => $invoice['subtotal'],
            'tax' => $invoice['tax'],
            'discount' => $invoice['discount'],
            'total' => $invoice['total'],
            'paid_amount' => 0,
            'due_amount' => $invoice['total'],
            'status' => 'pending',
        ]);

        $result = $this->service->postSaleJournal($medicalInvoice, (int) $this->owner->id);
        $this->assertNotNull($result, 'Sale journal should be created in accrual mode');
        $this->assertEquals('sale', $result->type);
        $this->assertEquals($medicalInvoice->id, $result->ref_id);
    }
}