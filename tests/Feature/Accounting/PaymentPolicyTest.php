<?php

namespace Tests\Feature\Accounting;

use App\Models\Institute;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PaymentPolicyTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(preg_replace('/[^a-z]/i', '', uniqid()));
        $email = str_replace('@', "+{$unique}@", $email);
        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Pay Policy Owner',
            'first_name' => 'Pay',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
        $roleId = \App\Models\Role::where('slug', 'institute-owner')->firstOrFail()->id;
        (new MembershipService)->assign($owner, $institute->id, $roleId);

        return [$institute, $owner];
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([\App\Support\Workspace::SESSION_KEY => $workspaceId])
            ->actingAs($user, 'web');
    }

    protected function makePayment(int $instituteId): Payment
    {
        $invoice = Invoice::factory()->create(['institute_id' => $instituteId]);

        return Payment::create([
            'institute_id' => $instituteId,
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'payment_method' => 'cash',
            'paid_at' => now()->toDateString(),
        ]);
    }

    public function test_index_lists_own_payments(): void
    {
        [$institute, $owner] = $this->tenantOwner('pay-own@example.test');
        $this->makePayment($institute->id);

        $this->asUser($owner, $institute->id)
            ->get(route('finance.payments.index'))
            ->assertStatus(200);
    }

    public function test_index_hides_other_tenant_payments(): void
    {
        [$institute, $owner] = $this->tenantOwner('pay-hide@example.test');
        $other = Institute::where('name', 'Tutu Center')->firstOrFail();
        $foreign = $this->makePayment($other->id);

        $this->asUser($owner, $institute->id)
            ->get(route('finance.payments.index'))
            ->assertStatus(200)
            ->assertDontSee((string) $foreign->id);
    }

    public function test_cannot_reverse_other_tenant_payment(): void
    {
        // TenantScoped binding hides foreign rows → 404 (existence not leaked).
        [$institute, $owner] = $this->tenantOwner('pay-cross@example.test');
        $other = Institute::where('name', 'Tutu Center')->firstOrFail();
        $foreign = $this->makePayment($other->id);

        $this->asUser($owner, $institute->id)
            ->post(route('finance.payments.reverse', $foreign))
            ->assertStatus(404);
    }

    public function test_policy_allows_own_denies_foreign(): void
    {
        [$institute, $owner] = $this->tenantOwner('pay-gate@example.test');
        $other = Institute::where('name', 'Tutu Center')->firstOrFail();
        $own = $this->makePayment($institute->id);
        $foreign = $this->makePayment($other->id);

        \App\Support\TenantContext::set($institute->id);
        $this->assertTrue(Gate::forUser($owner)->allows('reverse', $own));
        $this->assertFalse(Gate::forUser($owner)->allows('reverse', $foreign));
    }
}
