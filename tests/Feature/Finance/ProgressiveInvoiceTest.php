<?php

namespace Tests\Feature\Finance;

use App\Models\Accounting\ProgressiveContract;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Role;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\ProgressiveInvoiceService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProgressiveInvoiceTest extends TestCase
{
    use DatabaseTransactions;

    protected ProgressiveInvoiceService $service;
    protected Institute $institute;
    protected Party $party;
    protected InstituteUser $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ProgressiveInvoiceService::class);

        $country = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'PI Test Inst ' . uniqid(),
            'slug' => 'pi-test-' . uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'retail',
            'status' => 'active',
        ]);

        app(AccountingSetupService::class)->setupForInstitute($this->institute->id);

        $role = Role::where('slug', 'institute-admin')->whereNull('institute_id')->first();
        $this->actor = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'pi-admin-' . uniqid() . '@example.test',
            'phone' => '01700' . rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->party = Party::create([
            'institute_id' => $this->institute->id,
            'type' => 'customer',
            'name' => 'PI Client ' . uniqid(),
            'phone' => '017' . rand(10000000, 99999999),
            'email' => 'pi-client-' . uniqid() . '@example.test',
            'is_active' => true,
            'credit_limit' => 0,
        ]);

        TenantContext::set($this->institute->id);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function createContract(array $overrides = []): ProgressiveContract
    {
        return $this->service->createContract(array_merge([
            'institute_id' => $this->institute->id,
            'party_id' => $this->party->id,
            'title' => 'Test Contract',
            'total_value' => 100000,
            'currency' => 'BDT',
            'retention_percentage' => 10,
            'start_date' => now()->toDateString(),
        ], $overrides));
    }

    // === Contract creation ===

    public function test_create_contract_with_basic_fields(): void
    {
        $contract = $this->createContract();

        $this->assertNotNull($contract->id);
        $this->assertEquals($this->institute->id, $contract->institute_id);
        $this->assertEquals($this->party->id, $contract->party_id);
        $this->assertEquals('Test Contract', $contract->title);
        $this->assertEquals(100000, $contract->total_value);
        $this->assertEquals('active', $contract->status);
    }

    public function test_create_contract_generates_contract_number(): void
    {
        $contract = $this->createContract();

        $this->assertNotNull($contract->contract_number);
        $this->assertStringStartsWith('PC-' . date('Y') . '-', $contract->contract_number);
    }

    public function test_create_contract_computes_retention_amount(): void
    {
        $contract = $this->createContract(['retention_percentage' => 10]);

        $this->assertEquals(10000, (float) $contract->retention_amount);
    }

    public function test_create_contract_sets_remaining_value(): void
    {
        $contract = $this->createContract(['total_value' => 50000]);

        $this->assertEquals(50000, (float) $contract->remaining_value);
    }

    public function test_create_contract_with_zero_retention(): void
    {
        $contract = $this->createContract(['retention_percentage' => 0]);

        $this->assertEquals(0, (float) $contract->retention_amount);
        $this->assertEquals(0, (float) $contract->retention_released);
    }

    // === Progress invoice ===

    public function test_create_progress_invoice_by_percentage(): void
    {
        $contract = $this->createContract(['total_value' => 100000, 'retention_percentage' => 0]);

        $invoice = $this->service->createProgressInvoice($contract, [
            'billing_method' => 'percentage',
            'progress_value' => 25,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertNotNull($invoice->id);
        $this->assertTrue($invoice->is_progressive);
        $this->assertEquals(25000, (float) $invoice->total_amount);
    }

    public function test_create_progress_invoice_by_amount(): void
    {
        $contract = $this->createContract(['total_value' => 100000, 'retention_percentage' => 0]);

        $invoice = $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 30000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertNotNull($invoice->id);
        $this->assertEquals(30000, (float) $invoice->total_amount);
    }

    public function test_create_progress_invoice_by_milestone(): void
    {
        $contract = $this->createContract(['total_value' => 100000, 'retention_percentage' => 0]);

        $invoice = $this->service->createProgressInvoice($contract, [
            'billing_method' => 'milestone',
            'progress_value' => 40000,
            'milestone_name' => 'Phase 1: Discovery',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertNotNull($invoice->id);
        $this->assertEquals(40000, (float) $invoice->total_amount);
        $this->assertEquals('Phase 1: Discovery', $invoice->milestone_name);
    }

    public function test_create_progress_invoice_computes_retention(): void
    {
        $contract = $this->createContract(['total_value' => 100000, 'retention_percentage' => 10]);

        $invoice = $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 50000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertEquals(5000, (float) $invoice->retention_amount);
    }

    public function test_create_progress_invoice_updates_cumulative(): void
    {
        $contract = $this->createContract(['total_value' => 100000, 'retention_percentage' => 0]);

        $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 30000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $contract->refresh();
        $this->assertEquals(30000, (float) $contract->total_billed);
        $this->assertEquals(70000, (float) $contract->remaining_value);
    }

    public function test_create_progress_invoice_updates_contract_progress(): void
    {
        $contract = $this->createContract(['total_value' => 100000, 'retention_percentage' => 0]);

        $this->service->createProgressInvoice($contract, [
            'billing_method' => 'percentage',
            'progress_value' => 50,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $contract->refresh();
        $this->assertEquals(50, (float) $contract->progress_percentage);
    }

    public function test_create_progress_invoice_validates_cumulative_overflow(): void
    {
        $contract = $this->createContract(['total_value' => 100000, 'retention_percentage' => 0]);

        $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 80000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds contract value');

        $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 30000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);
    }

    public function test_create_progress_invoice_zero_amount_rejected(): void
    {
        $contract = $this->createContract();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 0,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);
    }

    public function test_create_progress_invoice_on_inactive_contract_rejected(): void
    {
        $contract = $this->createContract();
        $contract->update(['status' => 'cancelled']);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 50000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);
    }

    // === Final invoice ===

    public function test_final_invoice_releases_retention(): void
    {
        $contract = $this->createContract(['total_value' => 100000, 'retention_percentage' => 10]);

        $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 50000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $finalInvoice = $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 50000,
            'is_final' => true,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $contract->refresh();
        $this->assertEquals(10000, (float) $contract->retention_released);
        $this->assertTrue($finalInvoice->is_final_progressive);
    }

    public function test_final_invoice_marks_contract_completed(): void
    {
        $contract = $this->createContract(['total_value' => 100000, 'retention_percentage' => 0]);

        $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 50000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 50000,
            'is_final' => true,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $contract->refresh();
        $this->assertEquals('completed', $contract->status);
        $this->assertNotNull($contract->actual_end_date);
    }

    // === Validation ===

    public function test_cannot_cancel_contract_with_active_invoices(): void
    {
        $contract = $this->createContract(['total_value' => 100000, 'retention_percentage' => 0]);

        $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 50000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->cancelContract($contract, 'Test cancellation');
    }

    public function test_can_cancel_empty_contract(): void
    {
        $contract = $this->createContract();
        $this->service->cancelContract($contract, 'Test cancellation');

        $contract->refresh();
        $this->assertEquals('cancelled', $contract->status);
    }

    // === Summary ===

    public function test_summary_reports_correct_totals(): void
    {
        $contract = $this->createContract(['total_value' => 100000, 'retention_percentage' => 10]);

        $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 40000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $summary = $this->service->getContractSummary($contract);

        $this->assertEquals(100000, $summary['total_value']);
        $this->assertEquals(40000, $summary['total_billed']);
        $this->assertEquals(60000, $summary['remaining_value']);
        $this->assertEquals(1, $summary['invoice_count']);
    }

    // === Recalculate ===

    public function test_recalculate_contract_fixes_totals(): void
    {
        $contract = $this->createContract(['total_value' => 100000, 'retention_percentage' => 0]);

        $this->service->createProgressInvoice($contract, [
            'billing_method' => 'amount',
            'progress_value' => 25000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $contract->update(['total_billed' => 999999]);

        $this->service->recalculateContract($contract);
        $contract->refresh();

        $this->assertEquals(25000, (float) $contract->total_billed);
        $this->assertEquals(75000, (float) $contract->remaining_value);
    }

    // === Multi-tenant ===

    public function test_contract_scoped_to_institute(): void
    {
        $contract = $this->createContract();

        $otherInstitute = Institute::create([
            'name' => 'Other Inst ' . uniqid(),
            'slug' => 'other-' . uniqid(),
            'country' => 'Bangladesh',
            'industry' => 'retail',
            'status' => 'active',
        ]);

        $count = ProgressiveContract::where('institute_id', $otherInstitute->id)->count();
        $this->assertEquals(0, $count);

        $count = ProgressiveContract::where('institute_id', $this->institute->id)->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }
}
