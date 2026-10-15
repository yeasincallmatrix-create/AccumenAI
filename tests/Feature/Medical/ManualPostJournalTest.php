<?php

namespace Tests\Feature\Medical;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteFeatureOverride;
use App\Models\Journal;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Invoice;
use App\Models\Medical\Patient;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Medical\BillingService;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Database\Seeders\FeatureRegistrySeeder;
use Database\Seeders\MedicalSubModuleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Manual Post Journal (Task 8.5) — the Post Journal button + POST route that
 * posts an existing DRAFT sale journal (auto_post off creates drafts).
 *
 * Fixture mirrors InvoiceCancelTest's full billing gate stack, with
 * auto_post = 0 so BillingService::createInvoice leaves a draft journal.
 */
class ManualPostJournalTest extends TestCase
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
            'name' => 'Test Hospital '.uniqid(),
            'slug' => 'test-hospital-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'status' => 'active',
        ]);

        $this->owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'status' => 'active',
        ]);

        // Full billing gate stack: submodule registry row + feature
        // registry row + module override + feature override. Without these
        // the medical.module / feature middleware 403s every billing route.
        (new MedicalSubModuleSeeder)->run();
        (new FeatureRegistrySeeder)->run();
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical');
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.billing');
        InstituteFeatureOverride::updateOrCreate(
            ['institute_id' => $this->institute->id, 'feature_key' => 'medical.billing'],
            ['enabled' => true]
        );

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'phone' => '017'.rand(10000000, 99999999),
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
        ]);

        $this->createMedicalAccounts();

        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');
        // auto_post OFF: the pipeline creates DRAFT journals — exactly the
        // state the Post Journal button exists for.
        Setting::set("medical.billing.auto_post.{$this->institute->id}", '0');

        $this->billing = app(BillingService::class);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    protected function createMedicalAccounts(): void
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

        $assetGroup = AccountGroup::firstOrCreate([
            'institute_id' => $this->institute->id,
            'code' => '1',
        ], ['name' => 'Assets', 'category' => 'asset', 'is_system' => true]);

        $liabilityGroup = AccountGroup::firstOrCreate([
            'institute_id' => $this->institute->id,
            'code' => '2',
        ], ['name' => 'Liabilities', 'category' => 'liability', 'is_system' => true]);

        $incomeGroup = AccountGroup::firstOrCreate([
            'institute_id' => $this->institute->id,
            'code' => '4',
        ], ['name' => 'Income', 'category' => 'income', 'is_system' => true]);

        foreach ([
            ['code' => '1000.1', 'name' => 'Cash in Hand', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_cash' => true]],
            ['code' => '1200.1', 'name' => 'Trade Receivable', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_receivable' => true, 'cash_flow_category' => 'operating']],
            ['code' => '1200.4', 'name' => 'Insurance / TPA Receivable', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_receivable' => true, 'cash_flow_category' => 'operating']],
            ['code' => '2100.1', 'name' => 'VAT Output Payable', 'type' => 'liability', 'group' => $liabilityGroup, 'flags' => ['cash_flow_category' => 'operating']],
            ['code' => '4300.1', 'name' => 'Consultation Fees', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
            ['code' => '4300.4', 'name' => 'Surgery & IPD Room Revenue', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
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

    /**
     * Invoice with a linked DRAFT sale journal (auto_post is off in setUp).
     */
    protected function draftInvoice(string $type = 'opd', float $amount = 1000)
    {
        $invoice = $this->billing->createInvoice($this->patient, $type, [
            ['description' => 'Care', 'amount' => $amount, 'quantity' => 1, 'discount' => 0],
        ]);

        $this->assertNotNull($invoice->journal_id, 'fixture invoice must carry a journal');
        $this->assertSame('draft', Journal::find($invoice->journal_id)->status);

        return $invoice;
    }

    protected function makeReceptionistStaff(): User
    {
        $staff = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Membership::create([
            'user_id' => $staff->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'receptionist')->whereNull('institute_id')->value('id'),
            'status' => 'active',
        ]);

        return $staff;
    }

    public function test_owner_posts_draft_journal_with_audit(): void
    {
        $invoice = $this->draftInvoice();
        $journalId = $invoice->journal_id;

        $this->post(route('medical.billing.invoices.post-journal', $invoice))
            ->assertRedirect(route('medical.billing.invoices.show', $invoice))
            ->assertSessionHas('status');

        $journal = Journal::find($journalId);
        $this->assertSame('posted', $journal->status);
        $this->assertSame((int) $this->owner->id, (int) $journal->posted_by);
        $this->assertNotNull($journal->posted_at);

        $this->assertTrue(
            ClinicalAuditLog::where('institute_id', $this->institute->id)
                ->where('auditable_id', $invoice->id)
                ->where('action', 'journal_posted')
                ->exists(),
            'post must write a clinical audit row'
        );
    }

    public function test_post_denied_without_permission(): void
    {
        $invoice = $this->draftInvoice();
        $staff = $this->makeReceptionistStaff();

        $this->actingAs($staff, 'web');
        Workspace::set($this->institute->id);

        $this->post(route('medical.billing.invoices.post-journal', $invoice))
            ->assertForbidden();

        $this->assertSame('draft', Journal::find($invoice->journal_id)->status);
    }

    public function test_post_allowed_after_permission_grant(): void
    {
        $invoice = $this->draftInvoice();

        $permission = Permission::firstOrCreate(
            ['slug' => 'medical_billing.post'],
            ['module' => 'medical_billing', 'name' => 'Post Invoice Journals']
        );
        $receptionist = Role::where('slug', 'receptionist')->whereNull('institute_id')->firstOrFail();
        $receptionist->permissions()->syncWithoutDetaching([$permission->id]);

        $staff = $this->makeReceptionistStaff();

        $this->actingAs($staff, 'web');
        Workspace::set($this->institute->id);

        $this->post(route('medical.billing.invoices.post-journal', $invoice))
            ->assertRedirect(route('medical.billing.invoices.show', $invoice));

        $this->assertSame('posted', Journal::find($invoice->journal_id)->status);
    }

    public function test_button_visible_on_draft_and_hidden_after_post(): void
    {
        $invoice = $this->draftInvoice();
        $url = "invoices/{$invoice->id}/post-journal";

        $this->get(route('medical.billing.invoices.show', $invoice))
            ->assertOk()
            ->assertSee($url, false);

        $this->post(route('medical.billing.invoices.post-journal', $invoice))
            ->assertRedirect();

        $this->get(route('medical.billing.invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee($url, false);
    }

    public function test_already_posted_journal_is_rejected(): void
    {
        $invoice = $this->draftInvoice();
        $journalId = $invoice->journal_id;

        $this->post(route('medical.billing.invoices.post-journal', $invoice))
            ->assertRedirect(route('medical.billing.invoices.show', $invoice));

        $postedAt = Journal::find($journalId)->posted_at;

        $this->post(route('medical.billing.invoices.post-journal', $invoice->fresh()))
            ->assertRedirect()
            ->assertSessionHasErrors('journal');

        $journal = Journal::find($journalId);
        $this->assertSame('posted', $journal->status);
        $this->assertEquals($postedAt, $journal->posted_at);
    }

    public function test_invoice_without_journal_is_rejected(): void
    {
        $invoice = Invoice::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'type' => 'opd',
            'subtotal' => 500,
            'tax' => 0,
            'discount' => 0,
            'total' => 500,
            'paid_amount' => 0,
            'due_amount' => 500,
            'status' => 'pending',
        ]);
        $this->assertNull($invoice->journal_id);

        $this->post(route('medical.billing.invoices.post-journal', $invoice))
            ->assertRedirect()
            ->assertSessionHasErrors('journal');

        $this->assertNull($invoice->fresh()->journal_id);

        // Fail-soft invoice: no button either (nothing to post from the UI).
        $this->get(route('medical.billing.invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee("invoices/{$invoice->id}/post-journal", false);
    }

    public function test_post_requires_same_institute(): void
    {
        $other = Institute::create([
            'name' => 'Other Hospital '.uniqid(),
            'slug' => 'other-hospital-'.uniqid(),
            'country' => 'Bangladesh',
            'industry' => 'healthcare',
            'status' => 'active',
        ]);

        $foreign = Invoice::create([
            'institute_id' => $other->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'type' => 'opd',
            'subtotal' => 100,
            'tax' => 0,
            'discount' => 0,
            'total' => 100,
            'paid_amount' => 0,
            'due_amount' => 100,
            'status' => 'pending',
        ]);

        $this->post(route('medical.billing.invoices.post-journal', $foreign))
            ->assertForbidden();
        $this->assertNull($foreign->fresh()->journal_id);
    }
}
