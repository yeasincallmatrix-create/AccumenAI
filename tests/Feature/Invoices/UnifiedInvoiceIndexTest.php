<?php

namespace Tests\Feature\Invoices;

use App\Models\Currency;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Role;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unified /invoices list: auth, permissions, source filter, badges,
 * tenant isolation, search and status filter.
 *
 * Fixtures are created with TenantContext DISABLED (foreign-institute
 * rows would otherwise be rewritten to the context institute by
 * TenantScoped::creating). The HTTP middleware sets the context per
 * request from the acting InstituteUser.
 */
class UnifiedInvoiceIndexTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();
        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
    }

    private function user(string $roleSlug): InstituteUser
    {
        $roleId = Role::withoutGlobalScopes()->where('slug', $roleSlug)->firstOrFail()->id;

        return InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $roleId,
            'first_name' => 'Unified',
            'last_name' => 'List',
            'email' => 'unified-list-'.uniqid().'@example.test',
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

    private function salesInvoice(array $attributes = []): Invoice
    {
        $customer = Party::factory()->create([
            'institute_id' => $this->institute->id,
            'type' => 'customer',
        ]);
        $order = SalesOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'SO-U'.uniqid(),
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'currency_id' => Currency::firstOrFail()->id,
            'status' => 'approved',
        ]);

        return Invoice::factory()->create(array_merge(
            ['institute_id' => $this->institute->id, 'sales_order_id' => $order->id],
            $attributes,
        ));
    }

    public function test_guest_redirected_to_login(): void
    {
        $this->get(route('invoices.index'))->assertRedirect();
    }

    public function test_user_without_finance_view_gets_403(): void
    {
        $receptionist = $this->user('receptionist');

        $this->actingAs($receptionist, 'institute_user')
            ->get(route('invoices.index'))
            ->assertForbidden();
    }

    public function test_user_with_finance_view_can_access(): void
    {
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.index'))
            ->assertOk();
    }

    public function test_index_shows_all_invoices_by_default(): void
    {
        $finance = $this->financeInvoice();
        $sales = $this->salesInvoice();
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee($finance->invoice_number)
            ->assertSee($sales->invoice_number);
    }

    public function test_source_filter_finance_shows_only_finance_invoices(): void
    {
        $finance = $this->financeInvoice();
        $sales = $this->salesInvoice();
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.index').'?source=finance')
            ->assertOk()
            ->assertSee($finance->invoice_number)
            ->assertDontSee($sales->invoice_number);
    }

    public function test_source_filter_sales_shows_only_sales_invoices(): void
    {
        $finance = $this->financeInvoice();
        $sales = $this->salesInvoice();
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.index').'?source=sales')
            ->assertOk()
            ->assertSee($sales->invoice_number)
            ->assertDontSee($finance->invoice_number);
    }

    public function test_source_filter_all_shows_both(): void
    {
        $finance = $this->financeInvoice();
        $sales = $this->salesInvoice();
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.index').'?source=all')
            ->assertOk()
            ->assertSee($finance->invoice_number)
            ->assertSee($sales->invoice_number);
    }

    public function test_source_badge_renders_correctly_for_finance(): void
    {
        $this->financeInvoice();
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('<span class="badge text-bg-secondary">Finance</span>', false);
    }

    public function test_source_badge_renders_correctly_for_sales(): void
    {
        $this->salesInvoice();
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('<span class="badge text-bg-primary">Sales</span>', false);
    }

    public function test_tenant_isolation_tenant_a_cannot_see_tenant_b_invoices(): void
    {
        $other = Institute::where('name', 'Tutu Center')->firstOrFail();
        $foreign = Invoice::factory()->create(['institute_id' => $other->id]);
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertDontSee($foreign->invoice_number);
    }

    public function test_search_filters_by_invoice_number(): void
    {
        $wanted = $this->financeInvoice();
        $other = $this->financeInvoice();
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.index').'?q='.urlencode($wanted->invoice_number))
            ->assertOk()
            ->assertSee($wanted->invoice_number)
            ->assertDontSee($other->invoice_number);
    }

    public function test_status_filter_works(): void
    {
        $paid = Invoice::factory()->paid()->create([
            'institute_id' => $this->institute->id,
            'sales_order_id' => null,
        ]);
        $unpaid = $this->financeInvoice();
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('invoices.index').'?status=paid')
            ->assertOk()
            ->assertSee($paid->invoice_number)
            ->assertDontSee($unpaid->invoice_number);
    }
}
