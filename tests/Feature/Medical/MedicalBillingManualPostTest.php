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
 * Task 9.2 — manual-post pipeline from the GL's point of view: auto_post off
 * yields a DRAFT sale journal that the trial balance / general ledger must
 * ignore (entryQuery filters journals.status='posted'), until the HTTP
 * post-journal endpoint flips it to posted.
 *
 * Deliberately overlaps ManualPostJournalTest only on the draft precondition;
 * permission/audit/already-posted rejections live there.
 */
class MedicalBillingManualPostTest extends TestCase
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
            'name' => 'Manual Post Hospital '.uniqid(),
            'slug' => 'manual-post-hospital-'.uniqid(),
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

        FiscalYear::firstOrCreate(
            ['institute_id' => $this->institute->id, 'branch_id' => null, 'is_current' => true],
            [
                'name' => 'FY '.date('Y'),
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31'),
                'status' => 'open',
            ]
        );

        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');
        Setting::set("medical.billing.auto_post.{$this->institute->id}", '0');

        $code = Artisan::call('medical:provision-coa', [
            '--institute' => (string) $this->institute->id,
            '--execute' => true,
        ]);
        $this->assertSame(0, $code, 'CoA provisioning must succeed');

        $this->billing = app(BillingService::class);
        $this->reports = app(AccountingReportService::class);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    protected function createDraftInvoice(float $amount = 1000.0): array
    {
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Consultation', 'amount' => $amount, 'quantity' => 1, 'discount' => 0],
        ]);

        return [$invoice, Journal::find($invoice->journal_id)];
    }

    public function test_auto_post_off_creates_draft_and_draft_is_excluded_from_trial_balance(): void
    {
        [$invoice, $journal] = $this->createDraftInvoice();

        // Invoice saved with its sale journal linked, but as a DRAFT.
        $this->assertNotNull($invoice->id);
        $this->assertNotNull($invoice->journal_id);
        $this->assertNotNull($journal, 'auto_post off still creates the journal');
        $this->assertSame('draft', $journal->status);
        $this->assertSame('sale', $journal->type);
        $this->assertSame((int) $invoice->id, (int) $journal->ref_id);

        // The draft must not reach the trial balance: no AR or income row.
        $tb = $this->reports->trialBalance($this->institute->id, null);
        $this->assertNull($tb->firstWhere('code', '1200.1'), 'draft AR must not hit trial balance');
        $this->assertNull($tb->firstWhere('code', '4300.1'), 'draft income must not hit trial balance');

        // Nor the general ledger.
        $arId = ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('code', '1200.1')
            ->value('id');
        $this->assertNotNull($arId);
        $gl = $this->reports->generalLedger($this->institute->id, null, $arId);
        $this->assertTrue($gl->isEmpty(), 'draft entries must not reach the general ledger');
    }

    public function test_manual_post_moves_draft_into_general_ledger(): void
    {
        [$invoice, $journal] = $this->createDraftInvoice();
        $this->assertSame('draft', $journal->status);

        // Precondition visible in reports before posting.
        $arId = ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('code', '1200.1')
            ->value('id');
        $this->assertTrue($this->reports->generalLedger($this->institute->id, null, $arId)->isEmpty());

        // Manual post via the Task 8.5 endpoint.
        $this->post(route('medical.billing.invoices.post-journal', $invoice))
            ->assertRedirect(route('medical.billing.invoices.show', $invoice))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $journal->refresh();
        $this->assertSame('posted', $journal->status);
        $this->assertNotNull($journal->posted_at);
        $this->assertSame($this->owner->id, (int) $journal->posted_by);
        $this->assertSame((int) $journal->id, (int) $invoice->fresh()->journal_id);

        // Posted sale now appears in the general ledger.
        $gl = $this->reports->generalLedger($this->institute->id, null, $arId);
        $this->assertCount(1, $gl);
        $this->assertSame((int) $journal->id, (int) $gl->first()->journal_id);
        $this->assertEqualsWithDelta(1000.0, (float) $gl->first()->debit, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $gl->first()->credit, 0.01);

        // ...and in the trial balance: Dr AR 1000 / Cr income 1000.
        $tb = $this->reports->trialBalance($this->institute->id, null);
        $arRow = $tb->firstWhere('code', '1200.1');
        $incomeRow = $tb->firstWhere('code', '4300.1');
        $this->assertNotNull($arRow, 'posted AR must appear in trial balance');
        $this->assertNotNull($incomeRow, 'posted income must appear in trial balance');
        $this->assertEqualsWithDelta(1000.0, (float) $arRow->debit, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $incomeRow->credit, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $incomeRow->debit, 0.01);
    }
}
