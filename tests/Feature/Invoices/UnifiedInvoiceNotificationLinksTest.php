<?php

namespace Tests\Feature\Invoices;

use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Role;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\InvoiceService;
use App\Services\Accounting\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Invoice/payment admin notifications must deep-link to the unified
 * /invoices/{id} route (InvoiceService:170, PaymentService:169) and the
 * payments list must link invoices through the unified show route.
 */
class UnifiedInvoiceNotificationLinksTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();
        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $this->seedMinimalCoa();
        app(AccountingSetupService::class)
            ->ensureCurrentFiscalYear($this->institute->id, null, null);
    }

    /**
     * MAWA's test fixture has no CoA — the invoice/payment services need
     * receivable + cash + income accounts to post their journals.
     */
    private function seedMinimalCoa(): void
    {
        $common = ['institute_id' => $this->institute->id, 'account_group_id' => 1, 'is_postable' => 1];

        ChartOfAccount::create($common + [
            'code' => '1200.1', 'name' => 'Accounts Receivable',
            'type' => 'asset', 'is_receivable' => 1,
        ]);
        ChartOfAccount::create($common + [
            'code' => '1000.1', 'name' => 'Cash in Hand',
            'type' => 'asset', 'is_cash' => 1, 'cash_flow_category' => 'operating',
        ]);
        ChartOfAccount::create($common + [
            'code' => '4000.1', 'name' => 'Service Income',
            'type' => 'income',
        ]);
    }

    private function owner(): InstituteUser
    {
        $roleId = Role::withoutGlobalScopes()->where('slug', 'institute-owner')->firstOrFail()->id;

        return InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $roleId,
            'first_name' => 'Unified',
            'last_name' => 'Notify',
            'email' => 'unified-notify-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    public function test_invoice_created_notification_links_to_unified_route(): void
    {
        $actor = $this->owner();

        $invoice = app(InvoiceService::class)->create($this->institute->id, null, [
            'invoice_type' => 'course_fee',
            'items' => [
                ['description' => 'Unified notification test item', 'amount' => 100],
            ],
        ], $actor->id);

        $notification = Notification::query()
            ->where('institute_id', $this->institute->id)
            ->where('category', 'finance_invoice')
            ->where('title', 'Invoice created')
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('/invoices/'.$invoice->id, (string) $notification->link_url);
    }

    public function test_payment_received_notification_links_to_unified_route(): void
    {
        $actor = $this->owner();
        $invoice = Invoice::factory()->create([
            'institute_id' => $this->institute->id,
            'sales_order_id' => null,
        ]);

        app(PaymentService::class)->record($this->institute->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 10,
            'payment_method' => 'cash',
        ], $actor->id);

        $notification = Notification::query()
            ->where('institute_id', $this->institute->id)
            ->where('category', 'finance_payment')
            ->where('title', 'Payment received')
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('/invoices/'.$invoice->id, (string) $notification->link_url);
    }

    public function test_payments_list_links_invoice_to_unified_route(): void
    {
        $invoice = Invoice::factory()->create([
            'institute_id' => $this->institute->id,
            'sales_order_id' => null,
        ]);
        Payment::create([
            'institute_id' => $this->institute->id,
            'invoice_id' => $invoice->id,
            'amount' => 25,
            'payment_method' => 'cash',
        ]);
        $actor = $this->owner();

        $this->actingAs($actor, 'institute_user')
            ->get(route('finance.payments.index'))
            ->assertOk()
            ->assertSee('href="'.route('invoices.show', $invoice).'"', false);
    }
}
