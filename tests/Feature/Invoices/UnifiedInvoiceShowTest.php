<?php

namespace Tests\Feature\Invoices;

use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Student;
use App\Services\Accounting\AccountingSetupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unified /invoices/{invoice} show: both sources render, conditional
 * sections (sales order / student / progressive / journal / payments),
 * cross-tenant 404 (TenantScoped binding), permission 403, and the
 * cancel button posting to the existing finance cancel route.
 */
class UnifiedInvoiceShowTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();
        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        app(AccountingSetupService::class)
            ->ensureCurrentFiscalYear($this->institute->id, null, null);
    }

    private function user(string $roleSlug): InstituteUser
    {
        $roleId = Role::withoutGlobalScopes()->where('slug', $roleSlug)->firstOrFail()->id;

        return InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $roleId,
            'first_name' => 'Unified',
            'last_name' => 'Show',
            'email' => 'unified-show-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    private function financeInvoice(array $attributes = []): Invoice
    {
        return Invoice::factory()->create(array_merge(
            ['institute_id' => $this->institute->id, 'sales_order_id' => null],
            $attributes,
        ));
    }

    private function makeOrder(): SalesOrder
    {
        $customer = Party::factory()->create([
            'institute_id' => $this->institute->id,
            'type' => 'customer',
        ]);

        return SalesOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'SO-S'.uniqid(),
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'currency_id' => Currency::firstOrFail()->id,
            'status' => 'approved',
        ]);
    }

    public function test_show_renders_finance_invoice(): void
    {
        $invoice = $this->financeInvoice();
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('<span class="badge text-bg-secondary">Finance</span>', false);
    }

    public function test_show_renders_sales_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'institute_id' => $this->institute->id,
            'sales_order_id' => $this->makeOrder()->id,
        ]);
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('<span class="badge text-bg-primary">Sales</span>', false);
    }

    public function test_show_displays_sales_order_link_when_present(): void
    {
        $order = $this->makeOrder();
        $invoice = Invoice::factory()->create([
            'institute_id' => $this->institute->id,
            'sales_order_id' => $order->id,
        ]);
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('<h6 class="card-title">Sales Order</h6>', false)
            ->assertSee($order->order_number)
            ->assertSee(route('sales.orders.show', $order), false);
    }

    public function test_show_displays_student_when_present(): void
    {
        $student = Student::create([
            'institute_id' => $this->institute->id,
            'reg_no' => 'R'.substr(uniqid(), -6),
            'student_id' => 'ST'.substr((string) rand(1, 9999), 0, 4),
            'student_id_number' => 'SID-'.uniqid(),
            'first_name' => 'Unified',
            'last_name' => 'Scholar',
            'admission_date' => now()->toDateString(),
        ]);
        $invoice = $this->financeInvoice(['student_id' => $student->id]);
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('<h6 class="card-title">Student</h6>', false)
            ->assertSee('Unified Scholar');
    }

    public function test_show_displays_progressive_section_when_applicable(): void
    {
        $invoice = $this->financeInvoice([
            'is_progressive' => true,
            'milestone_name' => 'Foundation Phase',
            'progress_percentage' => 40,
            'cumulative_billed' => 400,
            'retention_amount' => 0,
        ]);
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Progressive Billing')
            ->assertSee('Foundation Phase');
    }

    public function test_show_shows_journal_link_when_posted(): void
    {
        $fy = FiscalYear::where('institute_id', $this->institute->id)
            ->where('is_current', true)
            ->firstOrFail();
        $journal = Journal::create([
            'institute_id' => $this->institute->id,
            'journal_no' => 'UJ-'.substr(uniqid(), -8),
            'journal_date' => now()->toDateString(),
            'fiscal_year_id' => $fy->id,
            'currency_id' => Currency::firstOrFail()->id,
            'type' => 'sale',
            'status' => 'posted',
        ]);
        $invoice = $this->financeInvoice(['journal_id' => $journal->id]);
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Ledger posting')
            ->assertSee($journal->journal_no);
    }

    public function test_show_shows_payments_history(): void
    {
        $invoice = $this->financeInvoice();
        Payment::create([
            'institute_id' => $this->institute->id,
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'payment_method' => 'cash',
        ]);
        $invoice->refresh();
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('<h6 class="card-title">Payments</h6>', false)
            ->assertSee('50.00')
            ->assertDontSee('No payments yet.');
    }

    public function test_show_blocks_cross_tenant_via_policy_403(): void
    {
        // TenantScoped binding hides foreign rows before the policy runs,
        // so cross-tenant access is a 404 (existence not leaked) — same
        // behavior as finance.invoices.show (InvoicePolicyTest).
        $other = Institute::where('name', 'Tutu Center')->firstOrFail();
        $foreign = Invoice::factory()->create(['institute_id' => $other->id]);
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.show', $foreign))
            ->assertNotFound();
    }

    public function test_show_requires_finance_view_permission(): void
    {
        $invoice = $this->financeInvoice();
        $receptionist = $this->user('receptionist');

        $this->actingAs($receptionist, 'institute_user')
            ->get(route('invoices.show', $invoice))
            ->assertForbidden();
    }

    public function test_cancel_button_posts_to_existing_finance_cancel_route(): void
    {
        $invoice = $this->financeInvoice();
        $owner = $this->user('institute-owner');

        $response = $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Cancel invoice')
            ->assertSee(route('finance.invoices.cancel', $invoice), false);

        // Paid invoices must not offer cancel.
        $paid = Invoice::factory()->paid()->create([
            'institute_id' => $this->institute->id,
            'sales_order_id' => null,
        ]);

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.show', $paid))
            ->assertOk()
            ->assertDontSee('Cancel invoice');
    }
}
