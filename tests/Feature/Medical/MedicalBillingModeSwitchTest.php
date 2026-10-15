<?php

namespace Tests\Feature\Medical;

use App\Models\AuditLog;
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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Task 9.3 — revenue-mode switching rules (no prior coverage existed).
 *
 * Guard under test: InstituteSettingController::updateMedicalBilling refuses
 * a revenue_mode change while ANY medical_invoice journal falls inside the
 * CURRENT fiscal year, and allows it once the current FY rolls over.
 */
class MedicalBillingModeSwitchTest extends TestCase
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
            'name' => 'Mode Switch Hospital '.uniqid(),
            'slug' => 'mode-switch-hospital-'.uniqid(),
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
        app(ModuleAccessService::class)->enableModule($this->institute, 'finance');
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

        $this->fy1 = FiscalYear::firstOrCreate(
            ['institute_id' => $this->institute->id, 'branch_id' => null, 'is_current' => true],
            [
                'name' => 'FY 1',
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31'),
                'status' => 'open',
            ]
        );

        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');
        Setting::set("medical.billing.auto_post.{$this->institute->id}", '1');

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

    protected function putBillingSettings(string $mode): TestResponse
    {
        return $this->put(route('settings.medical-billing.update'), [
            'revenue_mode' => $mode,
            'auto_post' => 1,
        ]);
    }

    protected function rollFiscalYear(): FiscalYear
    {
        $this->fy1->update(['is_current' => false]);

        return FiscalYear::create([
            'institute_id' => $this->institute->id,
            'branch_id' => null,
            'name' => 'FY 2 (rollover)',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'is_current' => true,
            'status' => 'open',
        ]);
    }

    public function test_mode_switch_blocked_while_current_fy_has_medical_journals(): void
    {
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 1000, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNotNull($invoice->journal_id, 'accrual invoice posts a sale journal in the current FY');

        $this->putBillingSettings('cash')
            ->assertSessionHasErrors('revenue_mode');

        // Nothing was written: guard throws before Setting::set().
        $this->assertSame(
            'accrual',
            Setting::get("medical.billing.revenue_mode.{$this->institute->id}")
        );
        $this->assertSame(
            '1',
            Setting::get("medical.billing.auto_post.{$this->institute->id}")
        );

        // The posted sale journal is untouched.
        $journal = Journal::find($invoice->journal_id);
        $this->assertSame('posted', $journal->status);
        $this->assertSame('sale', $journal->type);
    }

    public function test_mode_switch_allowed_after_fiscal_year_rollover(): void
    {
        // Journal exists in FY 1, which blocks switching now...
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 750, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNotNull($invoice->journal_id);
        $this->putBillingSettings('cash')->assertSessionHasErrors('revenue_mode');

        // ...but the FY rolls over (old journals stay in the old FY).
        $this->rollFiscalYear();

        $this->putBillingSettings('cash')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertSame(
            'cash',
            Setting::get("medical.billing.revenue_mode.{$this->institute->id}")
        );
        $this->assertSame(
            '1',
            Setting::get("medical.billing.auto_post.{$this->institute->id}")
        );

        $this->assertTrue(
            AuditLog::where('institute_id', $this->institute->id)
                ->where('action', 'medical_billing_settings_updated')
                ->exists(),
            'the mode switch must be audit-logged'
        );
    }

    public function test_old_accrual_invoices_remain_after_switch_to_cash(): void
    {
        $old = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 1000, 'quantity' => 1, 'discount' => 0],
        ]);
        $oldJournalId = $old->journal_id;
        $this->assertNotNull($oldJournalId);

        $tbBefore = $this->reports->trialBalance($this->institute->id, null);
        $arBefore = $tbBefore->firstWhere('code', '1200.1');
        $incomeBefore = $tbBefore->firstWhere('code', '4300.1');
        $this->assertEqualsWithDelta(1000.0, (float) $arBefore->debit, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $incomeBefore->credit, 0.01);

        $this->rollFiscalYear();
        $this->putBillingSettings('cash')->assertSessionHasNoErrors();
        $this->assertSame('cash', Setting::get("medical.billing.revenue_mode.{$this->institute->id}"));

        // Old accrual invoice is completely untouched by the switch.
        $old->refresh();
        $this->assertSame((int) $oldJournalId, (int) $old->journal_id);
        $this->assertSame('pending', $old->status);
        $this->assertEqualsWithDelta(1000.0, (float) $old->total, 0.01);

        $oldJournal = Journal::find($oldJournalId);
        $this->assertSame('posted', $oldJournal->status);
        $this->assertSame('sale', $oldJournal->type);

        $tbAfter = $this->reports->trialBalance($this->institute->id, null);
        $this->assertEqualsWithDelta(
            (float) $arBefore->debit,
            (float) $tbAfter->firstWhere('code', '1200.1')->debit,
            0.01
        );
        $this->assertEqualsWithDelta(
            (float) $incomeBefore->credit,
            (float) $tbAfter->firstWhere('code', '4300.1')->credit,
            0.01
        );

        // New invoices created after the switch follow cash mode: no sale journal.
        $new = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 500, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNotNull($new->id);
        $this->assertNull($new->journal_id, 'post-switch invoices must follow cash mode');
    }
}
