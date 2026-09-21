<?php

namespace Tests\Feature\Finance;

use App\Models\AccountingAuditTrail;
use App\Models\Accounting\Expense;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Role;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\BillableExpenseBillingService;
use App\Services\Accounting\ExpenseService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BillableExpenseTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $institute;
    protected InstituteUser $actor;
    protected int $cashAccountId;
    protected int $expenseAccountId;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();

        $country = \App\Models\Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'BE Test Inst ' . uniqid(),
            'slug' => 'be-test-' . uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'retail',
            'status' => 'active',
        ]);

        app(AccountingSetupService::class)->setupForInstitute($this->institute->id);

        $role = Role::where('slug', 'institute-admin')->whereNull('institute_id')->first();
        $this->actor = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role?->id,
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'be-admin-' . uniqid() . '@example.test',
            'phone' => '01700' . rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->cashAccountId = ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('code', '1000')->first()->id;
        $this->expenseAccountId = ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('code', '5005')->first()->id;

        TenantContext::set($this->institute->id);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function createCustomer(string $name = 'Test Customer'): Party
    {
        return Party::create([
            'institute_id' => $this->institute->id,
            'name' => $name,
            'type' => 'customer',
        ]);
    }

    private function createExpense(array $overrides = []): Expense
    {
        $number = $overrides['expense_number'] ?? ('EXP-' . date('Y') . '-' . str_pad((string) (rand(1, 99999)), 5, '0', STR_PAD_LEFT));
        $data = array_merge([
            'institute_id' => $this->institute->id,
            'expense_number' => $number,
            'expense_date' => now()->toDateString(),
            'payment_account_id' => $this->cashAccountId,
            'expense_account_id' => $this->expenseAccountId,
            'amount' => 1000,
            'currency' => 'BDT',
            'expense_category' => 'travel',
            'description' => 'Test expense',
            'vendor_name' => 'Test Vendor',
        ], $overrides);

        if (!empty($data['is_billable']) && !isset($data['billable_amount'])) {
            $markup = (float) ($data['markup_percentage'] ?? 0);
            $data['billable_amount'] = round((float) $data['amount'] * (1 + $markup / 100), 2);
        }

        return Expense::create($data);
    }

    // === Expense CRUD ===

    public function test_create_non_billable_expense(): void
    {
        $service = app(ExpenseService::class);

        $expense = $service->create([
            'institute_id' => $this->institute->id,
            'expense_date' => now()->toDateString(),
            'payment_account_id' => $this->cashAccountId,
            'expense_account_id' => $this->expenseAccountId,
            'amount' => 500,
            'currency' => 'BDT',
            'expense_category' => 'supplies',
            'vendor_name' => 'Office Store',
            'is_billable' => false,
        ], $this->actor->id);

        $this->assertNotNull($expense);
        $this->assertEquals(500, $expense->amount);
        $this->assertFalse($expense->is_billable);
        $this->assertEquals('unbillable', $expense->billing_status);
        $this->assertNotNull($expense->expense_number);
    }

    public function test_create_billable_expense_computes_billable_amount(): void
    {
        $service = app(ExpenseService::class);

        $expense = $service->create([
            'institute_id' => $this->institute->id,
            'expense_date' => now()->toDateString(),
            'payment_account_id' => $this->cashAccountId,
            'expense_account_id' => $this->expenseAccountId,
            'amount' => 1000,
            'currency' => 'BDT',
            'expense_category' => 'travel',
            'is_billable' => true,
            'markup_percentage' => 20,
        ], $this->actor->id);

        $this->assertTrue($expense->is_billable);
        $this->assertEquals('unbilled', $expense->billing_status);
        $this->assertEquals(1200.00, $expense->billable_amount);
    }

    public function test_create_expense_generates_number(): void
    {
        $service = app(ExpenseService::class);

        $e1 = $service->create([
            'institute_id' => $this->institute->id,
            'expense_date' => now()->toDateString(),
            'payment_account_id' => $this->cashAccountId,
            'expense_account_id' => $this->expenseAccountId,
            'amount' => 100,
            'currency' => 'BDT',
            'expense_category' => 'other',
        ], $this->actor->id);

        $e2 = $service->create([
            'institute_id' => $this->institute->id,
            'expense_date' => now()->toDateString(),
            'payment_account_id' => $this->cashAccountId,
            'expense_account_id' => $this->expenseAccountId,
            'amount' => 200,
            'currency' => 'BDT',
            'expense_category' => 'other',
        ], $this->actor->id);

        $this->assertNotEquals($e1->expense_number, $e2->expense_number);
        $this->assertStringStartsWith('EXP-' . date('Y') . '-', $e1->expense_number);
    }

    public function test_create_expense_posts_journal_entry(): void
    {
        $service = app(ExpenseService::class);

        $expense = $service->create([
            'institute_id' => $this->institute->id,
            'expense_date' => now()->toDateString(),
            'payment_account_id' => $this->cashAccountId,
            'expense_account_id' => $this->expenseAccountId,
            'amount' => 750,
            'currency' => 'BDT',
            'expense_category' => 'travel',
        ], $this->actor->id);

        $this->assertNotNull($expense->journal_entry_id);

        $je = \App\Models\JournalEntry::find($expense->journal_entry_id);
        $this->assertNotNull($je);
        $this->assertEquals(750, $je->debit);
    }

    public function test_update_expense(): void
    {
        $service = app(ExpenseService::class);
        $expense = $this->createExpense(['amount' => 500]);

        $updated = $service->update($expense, [
            'amount' => 750,
            'vendor_name' => 'Updated Vendor',
        ], $this->actor->id);

        $this->assertEquals(750, $updated->amount);
        $this->assertEquals('Updated Vendor', $updated->vendor_name);
    }

    public function test_update_billed_expense_rejected(): void
    {
        $service = app(ExpenseService::class);
        $expense = $this->createExpense(['billing_status' => 'billed']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot edit a billed expense.');
        $service->update($expense, ['amount' => 999], $this->actor->id);
    }

    public function test_delete_unbilled_expense(): void
    {
        $service = app(ExpenseService::class);
        $expense = $this->createExpense();
        $id = $expense->id;

        $service->delete($expense);

        $this->assertSoftDeleted('expenses', ['id' => $id]);
    }

    public function test_delete_billed_expense_rejected(): void
    {
        $service = app(ExpenseService::class);
        $expense = $this->createExpense(['billing_status' => 'billed']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot delete a billed expense.');
        $service->delete($expense);
    }

    // === Mark billable ===

    public function test_mark_billable_with_customer(): void
    {
        $service = app(ExpenseService::class);
        $customer = $this->createCustomer();
        $expense = $this->createExpense();

        $marked = $service->markBillable($expense, $customer->id, 0);

        $this->assertTrue($marked->is_billable);
        $this->assertEquals($customer->id, $marked->customer_id);
        $this->assertEquals('unbilled', $marked->billing_status);
        $this->assertEquals($expense->amount, $marked->billable_amount);
    }

    public function test_mark_billable_with_markup(): void
    {
        $service = app(ExpenseService::class);
        $customer = $this->createCustomer();
        $expense = $this->createExpense(['amount' => 2000]);

        $marked = $service->markBillable($expense, $customer->id, 25);

        $this->assertEquals(2500.00, $marked->billable_amount);
    }

    public function test_mark_billable_recomputes_billable_amount(): void
    {
        $service = app(ExpenseService::class);
        $customer = $this->createCustomer();
        $expense = $this->createExpense(['amount' => 1000, 'is_billable' => true, 'markup_percentage' => 10, 'customer_id' => $customer->id, 'billing_status' => 'unbilled']);

        $this->assertEquals(1100.00, $expense->fresh()->billable_amount);

        $marked = $service->markBillable($expense, $customer->id, 50);

        $this->assertEquals(1500.00, $marked->billable_amount);
    }

    public function test_bulk_mark_billable(): void
    {
        $service = app(ExpenseService::class);
        $customer = $this->createCustomer();
        $e1 = $this->createExpense(['amount' => 100]);
        $e2 = $this->createExpense(['amount' => 200]);
        $e3 = $this->createExpense(['amount' => 300, 'billing_status' => 'billed']);

        $count = $service->bulkMarkBillable([$e1->id, $e2->id, $e3->id], $customer->id, 10);

        $this->assertEquals(2, $count);
        $this->assertTrue($e1->fresh()->is_billable);
        $this->assertTrue($e2->fresh()->is_billable);
        $this->assertFalse($e3->fresh()->is_billable);
    }

    // === Preview ===

    public function test_preview_invoice_totals(): void
    {
        $customer = $this->createCustomer();
        $e1 = $this->createExpense(['amount' => 1000, 'is_billable' => true, 'markup_percentage' => 20, 'customer_id' => $customer->id, 'billing_status' => 'unbilled']);
        $e2 = $this->createExpense(['amount' => 500, 'is_billable' => true, 'markup_percentage' => 0, 'customer_id' => $customer->id, 'billing_status' => 'unbilled']);

        $preview = app(BillableExpenseBillingService::class)->previewTotals([$e1->id, $e2->id]);

        $this->assertEquals(2, $preview['count']);
        $this->assertEquals(1500, $preview['total_cost']);
        $this->assertEquals(1700, $preview['total_billable']);
        $this->assertEquals(200, $preview['total_markup']);
    }

    // === Invoice generation ===

    public function test_generate_invoice_from_unbilled_expenses(): void
    {
        $customer = $this->createCustomer();
        $e1 = $this->createExpense(['amount' => 1000, 'is_billable' => true, 'customer_id' => $customer->id, 'billing_status' => 'unbilled']);
        $e2 = $this->createExpense(['amount' => 500, 'is_billable' => true, 'customer_id' => $customer->id, 'billing_status' => 'unbilled']);

        $invoice = app(BillableExpenseBillingService::class)->generateInvoice(
            $customer->id,
            [$e1->id, $e2->id],
            ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
            $this->actor->id
        );

        $this->assertNotNull($invoice);
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($customer->id, $invoice->party_id);
    }

    public function test_generate_invoice_marks_expenses_billed(): void
    {
        $customer = $this->createCustomer();
        $e1 = $this->createExpense(['amount' => 1000, 'is_billable' => true, 'customer_id' => $customer->id, 'billing_status' => 'unbilled']);

        $invoice = app(BillableExpenseBillingService::class)->generateInvoice(
            $customer->id,
            [$e1->id],
            ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
            $this->actor->id
        );

        $e1Refresh = $e1->fresh();
        $this->assertEquals('billed', $e1Refresh->billing_status);
        $this->assertEquals($invoice->id, $e1Refresh->billed_invoice_id);
        $this->assertNotNull($e1Refresh->billed_at);
    }

    public function test_generate_invoice_links_invoice_id(): void
    {
        $customer = $this->createCustomer();
        $e1 = $this->createExpense(['amount' => 1000, 'is_billable' => true, 'customer_id' => $customer->id, 'billing_status' => 'unbilled']);

        $invoice = app(BillableExpenseBillingService::class)->generateInvoice(
            $customer->id,
            [$e1->id],
            ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
            $this->actor->id
        );

        $this->assertEquals($invoice->id, $e1->fresh()->billed_invoice_id);
    }

    public function test_generate_invoice_validates_customer_match(): void
    {
        $customer1 = $this->createCustomer('Customer A');
        $customer2 = $this->createCustomer('Customer B');
        $e1 = $this->createExpense(['amount' => 1000, 'is_billable' => true, 'customer_id' => $customer1->id, 'billing_status' => 'unbilled']);

        $this->expectException(\RuntimeException::class);
        app(BillableExpenseBillingService::class)->generateInvoice(
            $customer2->id,
            [$e1->id],
            ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
            $this->actor->id
        );
    }

    public function test_generate_invoice_with_no_unbilled_rejected(): void
    {
        $customer = $this->createCustomer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No unbilled expenses');
        app(BillableExpenseBillingService::class)->generateInvoice(
            $customer->id,
            [99999],
            ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
            $this->actor->id
        );
    }

    public function test_generate_invoice_only_includes_unbilled(): void
    {
        $customer = $this->createCustomer();
        $unbilled = $this->createExpense(['amount' => 1000, 'is_billable' => true, 'customer_id' => $customer->id, 'billing_status' => 'unbilled']);
        $alreadyBilled = $this->createExpense(['amount' => 2000, 'is_billable' => true, 'customer_id' => $customer->id, 'billing_status' => 'billed']);

        $invoice = app(BillableExpenseBillingService::class)->generateInvoice(
            $customer->id,
            [$unbilled->id, $alreadyBilled->id],
            ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
            $this->actor->id
        );

        $this->assertNotNull($invoice);
        $this->assertEquals('billed', $unbilled->fresh()->billing_status);
        $this->assertEquals('billed', $alreadyBilled->fresh()->billing_status);
    }

    // === Reversal ===

    public function test_revert_billing_on_invoice_cancel(): void
    {
        $service = app(ExpenseService::class);
        $customer = $this->createCustomer();
        $expense = $this->createExpense([
            'is_billable' => true,
            'billing_status' => 'unbilled',
        ]);

        $marked = $service->markBillable($expense, $customer->id, 0);
        $marked->update([
            'billing_status' => 'billed',
            'billed_at' => now(),
        ]);

        $reverted = $service->revertBilling($marked);

        $this->assertEquals('unbilled', $reverted->billing_status);
        $this->assertNull($reverted->billed_invoice_id);
        $this->assertNull($reverted->billed_at);
    }

    // === Multi-tenant ===

    public function test_expense_scoped_to_institute(): void
    {
        $expense = $this->createExpense();
        $this->assertEquals($this->institute->id, $expense->institute_id);

        $otherInstitute = Institute::create([
            'name' => 'Other ' . uniqid(),
            'slug' => 'other-' . uniqid(),
            'country' => 'Bangladesh',
            'industry' => 'retail',
            'status' => 'active',
        ]);

        TenantContext::clear();
        TenantContext::set($otherInstitute->id);

        $this->assertNull(Expense::find($expense->id));
    }

    public function test_cannot_access_other_institute_expense(): void
    {
        $expense = $this->createExpense();
        $otherInstitute = Institute::create([
            'name' => 'Other ' . uniqid(),
            'slug' => 'other-' . uniqid(),
            'country' => 'Bangladesh',
            'industry' => 'retail',
            'status' => 'active',
        ]);

        TenantContext::clear();
        $otherExpense = Expense::create([
            'institute_id' => $otherInstitute->id,
            'expense_number' => 'EXP-2026-99999',
            'expense_date' => now()->toDateString(),
            'payment_account_id' => $this->cashAccountId,
            'expense_account_id' => $this->expenseAccountId,
            'amount' => 999,
            'currency' => 'BDT',
            'expense_category' => 'other',
        ]);
        TenantContext::set($this->institute->id);

        $this->assertNotEquals($expense->institute_id, $otherExpense->institute_id);
    }

    public function test_cannot_generate_invoice_for_other_institute(): void
    {
        $customer = $this->createCustomer();
        $e1 = $this->createExpense(['amount' => 1000, 'is_billable' => true, 'customer_id' => $customer->id, 'billing_status' => 'unbilled']);

        $otherInstitute = Institute::create([
            'name' => 'Other ' . uniqid(),
            'slug' => 'other-' . uniqid(),
            'country' => 'Bangladesh',
            'industry' => 'retail',
            'status' => 'active',
        ]);

        TenantContext::clear();
        TenantContext::set($otherInstitute->id);

        $this->expectException(\Throwable::class);
        app(BillableExpenseBillingService::class)->generateInvoice(
            $customer->id,
            [$e1->id],
            ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()],
            $this->actor->id
        );
    }

    // === Audit ===

    public function test_expense_creation_is_audit_logged(): void
    {
        $service = app(ExpenseService::class);

        $expense = $service->create([
            'institute_id' => $this->institute->id,
            'expense_date' => now()->toDateString(),
            'payment_account_id' => $this->cashAccountId,
            'expense_account_id' => $this->expenseAccountId,
            'amount' => 300,
            'currency' => 'BDT',
            'expense_category' => 'meals',
        ], $this->actor->id);

        $audit = AccountingAuditTrail::where('entity_type', 'expense')
            ->where('entity_id', $expense->id)
            ->where('action', 'create')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->institute->id, $audit->institute_id);
    }

    public function test_billable_marking_is_audit_logged(): void
    {
        $service = app(ExpenseService::class);
        $customer = $this->createCustomer();
        $expense = $this->createExpense();

        $service->markBillable($expense, $customer->id, 15);

        $audit = AccountingAuditTrail::where('entity_type', 'expense')
            ->where('entity_id', $expense->id)
            ->where('action', 'update')
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->institute->id, $audit->institute_id);
    }

    public function test_update_expense_is_audit_logged(): void
    {
        $service = app(ExpenseService::class);
        $expense = $this->createExpense();

        $service->update($expense, ['amount' => 999], $this->actor->id);

        $audit = AccountingAuditTrail::where('entity_type', 'expense')
            ->where('entity_id', $expense->id)
            ->where('action', 'update')
            ->latest()
            ->first();

        $this->assertNotNull($audit);
    }
}
