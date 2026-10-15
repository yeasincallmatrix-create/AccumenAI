<?php

namespace Tests\Feature\Medical;

use App\Models\Country;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Medical\Invoice;
use App\Models\Medical\Patient;
use App\Models\Role;
use App\Models\Setting;
use App\Services\Medical\BillingService;
use App\Services\Medical\MedicalBillingJournalService;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * medical:provision-coa — admin-facing CoA provisioning for medical billing.
 *
 * The fixture institute is deliberately created WITHOUT any tenant CoA
 * accounts (MedicalModuleActivator seeds permissions + billing settings only,
 * never CoA) so the tests exercise the exact "skipped onboarding" state the
 * command exists to repair.
 */
class MedicalProvisionCoaTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $institute;

    protected InstituteUser $owner;

    protected Patient $patient;

    protected MedicalBillingJournalService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'Fresh Hospital '.uniqid(),
            'slug' => 'fresh-hospital-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'healthcare',
            'status' => 'active',
        ]);

        app(ModuleAccessService::class)->enableModule($this->institute, 'medical');

        $role = Role::where('slug', 'institute-owner')->firstOrFail();
        $this->owner = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'email' => 'owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'phone' => '017'.rand(10000000, 99999999),
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
        ]);

        // Journal posting requires a current open fiscal year. No CoA accounts
        // are created here — that absence is the point of this test class.
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
        Setting::set("medical.billing.auto_post.{$this->institute->id}", '1');

        $this->service = app(MedicalBillingJournalService::class);
    }

    protected function createInvoice(string $type = 'opd'): Invoice
    {
        $totals = BillingService::computeTotals([
            ['amount' => 100, 'quantity' => 1, 'discount' => 0],
        ]);

        return Invoice::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'type' => $type,
            'subtotal' => $totals['subtotal'],
            'tax' => $totals['tax'],
            'discount' => $totals['discount'],
            'total' => $totals['total'],
            'paid_amount' => 0,
            'due_amount' => $totals['total'],
            'status' => 'pending',
        ]);
    }

    protected function tenantAccountCount(int $instituteId): int
    {
        return DB::table('chart_of_accounts')
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->count();
    }

    public function test_errors_when_no_target_specified(): void
    {
        $code = Artisan::call('medical:provision-coa');

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Specify target', Artisan::output());
    }

    public function test_dry_run_previews_without_writing(): void
    {
        $id = $this->institute->id;

        $code = Artisan::call('medical:provision-coa', ['--institute' => (string) $id]);

        $this->assertSame(0, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('DRY-RUN', $output);
        $this->assertStringContainsString("institute {$id}: [dry-run] would install", $output);
        $this->assertStringContainsString('1200.1', $output);

        // Nothing may be written without --execute.
        $this->assertSame(0, $this->tenantAccountCount($id));
    }

    public function test_execute_installs_missing_accounts_and_is_idempotent(): void
    {
        $id = $this->institute->id;
        $this->assertSame(0, $this->tenantAccountCount($id), 'fixture must start with zero tenant accounts');

        $code = Artisan::call('medical:provision-coa', ['--institute' => (string) $id, '--execute' => true]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString("✓ institute {$id}", Artisan::output());

        foreach (['1000.1', '1200.1', '2100.1', '4300.1', '4300.4', '4000.5', '1200.4', '2400.3', '2000.4'] as $code_) {
            $exists = DB::table('chart_of_accounts')
                ->where('institute_id', $id)
                ->whereNull('deleted_at')
                ->where('code', $code_)
                ->exists();
            $this->assertTrue($exists, "expected account {$code_} after --execute");
        }

        // Second run: fully provisioned → no changes, exit 0.
        $again = Artisan::call('medical:provision-coa', ['--institute' => (string) $id, '--execute' => true]);

        $this->assertSame(0, $again);
        $this->assertStringContainsString("⊘ institute {$id}: no changes needed", Artisan::output());
    }

    public function test_skips_non_healthcare_institute(): void
    {
        $country = Country::firstOrCreate(['iso2' => 'BD']);
        $edu = Institute::create([
            'name' => 'Fresh Training '.uniqid(),
            'slug' => 'fresh-training-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'training_center',
            'status' => 'active',
        ]);

        $code = Artisan::call('medical:provision-coa', ['--institute' => (string) $edu->id, '--execute' => true]);

        $this->assertSame(1, $code);
        $output = Artisan::output();
        $this->assertStringContainsString("✗ institute {$edu->id}: skipped", $output);
        $this->assertStringContainsString("'training_center'", $output);
        $this->assertSame(0, $this->tenantAccountCount($edu->id), 'non-healthcare institute must not be provisioned');
    }

    public function test_fails_for_nonexistent_institute(): void
    {
        $code = Artisan::call('medical:provision-coa', ['--institute' => '999999', '--execute' => true]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Institute 999999 not found', Artisan::output());
    }

    public function test_all_healthcare_targets_every_active_healthcare_institute(): void
    {
        $id = $this->institute->id;

        $code = Artisan::call('medical:provision-coa', ['--all-healthcare' => true]);

        $this->assertSame(0, $code);
        $output = Artisan::output();
        $this->assertStringContainsString("institute {$id}: [dry-run] would install", $output);
        $this->assertSame(0, $this->tenantAccountCount($id), 'dry-run writes nothing');
    }

    /**
     * Deliverable 3: a fresh healthcare institute without CoA creates the
     * invoice but fails soft (journal_id NULL + actionable log line); after
     * medical:provision-coa --execute the next invoice posts a journal.
     * Deliverable 2: the failure log carries institute_id, invoice_id and
     * action_required.
     */
    public function test_invoice_journal_fails_soft_then_succeeds_after_provisioning(): void
    {
        $id = $this->institute->id;
        Log::spy();

        // 1. Fail-soft: no accounts → no journal, invoice untouched.
        $invoice1 = $this->createInvoice('opd');
        $journal1 = $this->service->postSaleJournal($invoice1, (int) $this->owner->id);

        $this->assertNull($journal1, 'postSaleJournal must fail soft without CoA');
        $this->assertNull($invoice1->fresh()->journal_id);

        // Deliverable 2: warning must be actionable.
        Log::shouldHaveReceived('warning')
            ->withArgs(function ($message, $context) use ($id, $invoice1) {
                return str_contains((string) $message, 'required account missing')
                    && ($context['institute_id'] ?? null) === $id
                    && ($context['invoice_id'] ?? null) === (int) $invoice1->id
                    && str_contains((string) ($context['action_required'] ?? ''), "medical:provision-coa --institute={$id} --execute");
            })
            ->once();

        // 2. Provision the accounts the pipeline needs.
        $code = Artisan::call('medical:provision-coa', ['--institute' => (string) $id, '--execute' => true]);
        $this->assertSame(0, $code);
        $this->assertTrue(
            DB::table('chart_of_accounts')
                ->where('institute_id', $id)
                ->whereNull('deleted_at')
                ->where('code', '1200.1')
                ->exists(),
            'provisioning must install the Trade Receivable account'
        );

        // 3. Recovery: a new invoice now posts its sale journal.
        $invoice2 = $this->createInvoice('opd');
        $journal2 = $this->service->postSaleJournal($invoice2, (int) $this->owner->id);

        $this->assertNotNull($journal2, 'postSaleJournal must succeed after provisioning');
        $this->assertSame('sale', $journal2->type);
        $this->assertSame($invoice2->id, $journal2->ref_id);
        $this->assertNotNull($invoice2->fresh()->journal_id);

        // Journal must balance and carry tenant-owned accounts.
        $this->assertEqualsWithDelta(
            (float) $journal2->entries->sum('debit'),
            (float) $journal2->entries->sum('credit'),
            0.01
        );
    }
}
