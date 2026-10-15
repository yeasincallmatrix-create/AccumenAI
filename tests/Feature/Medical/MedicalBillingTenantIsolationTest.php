<?php

namespace Tests\Feature\Medical;

use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteFeatureOverride;
use App\Models\Journal;
use App\Models\Medical\Patient;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Accounting\AccountingReportService;
use App\Services\Medical\BillingService;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Database\Seeders\FeatureRegistrySeeder;
use Database\Seeders\MedicalSubModuleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Task 9.4 — tenant isolation across the medical billing pipeline: two fully
 * provisioned healthcare institutes side by side, read path, write path,
 * reports, and per-institute settings keys.
 */
class MedicalBillingTenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        (new MedicalSubModuleSeeder)->run();
        (new FeatureRegistrySeeder)->run();

        [$this->instituteA, $this->ownerA, $this->patientA] = $this->makeTenant('alpha', $country);
        [$this->instituteB, $this->ownerB, $this->patientB] = $this->makeTenant('beta', $country);

        $this->billing = app(BillingService::class);
        $this->reports = app(AccountingReportService::class);

        $this->actingAs($this->ownerA, 'web');
        Workspace::set($this->instituteA->id);
    }

    /** @return array{Institute, User, Patient} */
    protected function makeTenant(string $slug, Country $country): array
    {
        $institute = Institute::create([
            'name' => 'Isolation '.ucfirst($slug).' '.uniqid(),
            'slug' => 'isolation-'.$slug.'-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Membership::create([
            'user_id' => $owner->id,
            'institution_id' => $institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'status' => 'active',
        ]);

        app(ModuleAccessService::class)->enableModule($institute, 'medical');
        app(ModuleAccessService::class)->enableModule($institute, 'medical.billing');
        InstituteFeatureOverride::updateOrCreate(
            ['institute_id' => $institute->id, 'feature_key' => 'medical.billing'],
            ['enabled' => true]
        );

        $patient = Patient::create([
            'institute_id' => $institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => ucfirst($slug),
            'phone' => '017'.rand(10000000, 99999999),
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
        ]);

        FiscalYear::firstOrCreate(
            ['institute_id' => $institute->id, 'branch_id' => null, 'is_current' => true],
            [
                'name' => 'FY '.date('Y'),
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31'),
                'status' => 'open',
            ]
        );

        Setting::set("medical.billing.revenue_mode.{$institute->id}", 'accrual');
        Setting::set("medical.billing.auto_post.{$institute->id}", '1');

        $code = Artisan::call('medical:provision-coa', [
            '--institute' => (string) $institute->id,
            '--execute' => true,
        ]);
        $this->assertSame(0, $code, "CoA provisioning must succeed for {$slug}");

        return [$institute, $owner, $patient];
    }

    public function test_invoice_index_does_not_leak_foreign_tenant_invoices(): void
    {
        // ChartOfAccount carries the TenantScoped global scope, so each
        // side's service calls must run under that side's context
        // (production guarantees this via ensureSameInstitute).
        $own = $this->billing->createInvoice($this->patientA, 'opd', [
            ['description' => 'A care', 'amount' => 1000, 'quantity' => 1, 'discount' => 0],
        ]);
        Workspace::set($this->instituteB->id);
        $foreign = $this->billing->createInvoice($this->patientB, 'opd', [
            ['description' => 'B care', 'amount' => 700, 'quantity' => 1, 'discount' => 0],
        ]);
        Workspace::set($this->instituteA->id);

        $response = $this->get(route('medical.billing.invoices.index'));
        $response->assertOk();
        $response->assertViewHas('invoices', function ($invoices) use ($own, $foreign) {
            $ids = collect($invoices->items())->pluck('id')->all();

            return in_array($own->id, $ids, true) && ! in_array($foreign->id, $ids, true);
        });
    }

    public function test_show_invoice_blocked_cross_tenant(): void
    {
        Workspace::set($this->instituteB->id);
        $foreign = $this->billing->createInvoice($this->patientB, 'opd', [
            ['description' => 'B care', 'amount' => 400, 'quantity' => 1, 'discount' => 0],
        ]);
        Workspace::set($this->instituteA->id);

        $this->get(route('medical.billing.invoices.show', $foreign))
            ->assertForbidden();
    }

    public function test_cancel_blocked_cross_tenant_and_leaves_foreign_journal_intact(): void
    {
        Workspace::set($this->instituteB->id);
        $foreign = $this->billing->createInvoice($this->patientB, 'opd', [
            ['description' => 'B care', 'amount' => 650, 'quantity' => 1, 'discount' => 0],
        ]);
        $foreignJournalId = $foreign->journal_id;
        $this->assertNotNull($foreignJournalId);

        Workspace::set($this->instituteA->id);
        $this->post(route('medical.billing.invoices.cancel', $foreign))
            ->assertForbidden();

        // Verify B's state under B's scope so the assertions are meaningful.
        Workspace::set($this->instituteB->id);
        $foreign->refresh();
        $this->assertSame('pending', $foreign->status, 'foreign invoice must not be cancelled');
        $this->assertSame((int) $foreignJournalId, (int) $foreign->journal_id);

        $journal = Journal::find($foreignJournalId);
        $this->assertSame('posted', $journal->status, 'foreign sale journal must stay posted');

        $this->assertFalse(
            Journal::where('institute_id', $this->instituteB->id)
                ->where('reversal_of', $foreignJournalId)
                ->exists(),
            'no reversal journal may exist for the blocked cancel'
        );
    }

    public function test_trial_balance_and_general_ledger_are_tenant_scoped(): void
    {
        $invA = $this->billing->createInvoice($this->patientA, 'opd', [
            ['description' => 'A care', 'amount' => 1000, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNotNull($invA->journal_id);

        Workspace::set($this->instituteB->id);
        $invB = $this->billing->createInvoice($this->patientB, 'opd', [
            ['description' => 'B care', 'amount' => 700, 'quantity' => 1, 'discount' => 0],
        ]);
        $arIdB = ChartOfAccount::where('institute_id', $this->instituteB->id)->where('code', '1200.1')->value('id');
        $this->assertNotNull($invB->journal_id);
        $this->assertNotNull($arIdB);

        Workspace::set($this->instituteA->id);
        $arIdA = ChartOfAccount::where('institute_id', $this->instituteA->id)->where('code', '1200.1')->value('id');
        $this->assertNotNull($arIdA);

        // Trial balances: same account code per tenant, own activity only.
        $tbA = $this->reports->trialBalance($this->instituteA->id, null);
        $tbB = $this->reports->trialBalance($this->instituteB->id, null);

        $arA = $tbA->firstWhere('code', '1200.1');
        $arB = $tbB->firstWhere('code', '1200.1');
        $this->assertNotNull($arA);
        $this->assertNotNull($arB);
        $this->assertEqualsWithDelta(1000.0, (float) $arA->debit, 0.01, 'A must only see its own AR');
        $this->assertEqualsWithDelta(700.0, (float) $arB->debit, 0.01, 'B must only see its own AR');

        $incomeA = $tbA->firstWhere('code', '4300.1');
        $this->assertEqualsWithDelta(1000.0, (float) $incomeA->credit, 0.01);

        // General ledgers: no cross-tenant journal lines.
        $glA = $this->reports->generalLedger($this->instituteA->id, null, $arIdA);
        $glB = $this->reports->generalLedger($this->instituteB->id, null, $arIdB);

        $journalIdsA = collect($glA)->pluck('journal_id')->all();
        $journalIdsB = collect($glB)->pluck('journal_id')->all();

        $this->assertSame([(int) $invA->journal_id], array_map('intval', $journalIdsA));
        $this->assertSame([(int) $invB->journal_id], array_map('intval', $journalIdsB));
        $this->assertNotContains((int) $invB->journal_id, array_map('intval', $journalIdsA));
        $this->assertNotContains((int) $invA->journal_id, array_map('intval', $journalIdsB));
    }

    public function test_revenue_mode_setting_is_isolated_per_institute(): void
    {
        // B switches to cash; A stays accrual — keys are institute-suffixed.
        Setting::set("medical.billing.revenue_mode.{$this->instituteB->id}", 'cash');

        $invA = $this->billing->createInvoice($this->patientA, 'opd', [
            ['description' => 'A care', 'amount' => 300, 'quantity' => 1, 'discount' => 0],
        ]);
        $invB = $this->billing->createInvoice($this->patientB, 'opd', [
            ['description' => 'B care', 'amount' => 300, 'quantity' => 1, 'discount' => 0],
        ]);

        $this->assertSame('accrual', Setting::get("medical.billing.revenue_mode.{$this->instituteA->id}"));
        $this->assertSame('cash', Setting::get("medical.billing.revenue_mode.{$this->instituteB->id}"));

        $this->assertNotNull($invA->journal_id, 'accrual institute posts its sale journal');
        $this->assertNull($invB->journal_id, 'cash institute creates no sale journal');
    }
}
