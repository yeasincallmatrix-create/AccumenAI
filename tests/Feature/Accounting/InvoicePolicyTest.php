<?php

namespace Tests\Feature\Accounting;

use App\Models\Institute;
use App\Models\Invoice;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class InvoicePolicyTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(preg_replace('/[^a-z]/i', '', uniqid()));
        $email = str_replace('@', "+{$unique}@", $email);
        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Policy Owner',
            'first_name' => 'Policy',
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

    public function test_view_own_invoice(): void
    {
        [$institute, $owner] = $this->tenantOwner('policy-own@example.test');
        $invoice = Invoice::factory()->create(['institute_id' => $institute->id]);

        $this->asUser($owner, $institute->id)
            ->get(route('finance.invoices.show', $invoice))
            ->assertStatus(200);
    }

    public function test_cannot_view_other_tenant_invoice(): void
    {
        // TenantScoped binding hides foreign rows → 404 (existence not leaked).
        [$institute, $owner] = $this->tenantOwner('policy-cross@example.test');
        $other = Institute::where('name', 'Tutu Center')->firstOrFail();
        $otherInvoice = Invoice::factory()->create(['institute_id' => $other->id]);

        $this->asUser($owner, $institute->id)
            ->get(route('finance.invoices.show', $otherInvoice))
            ->assertStatus(404);
    }

    public function test_cannot_cancel_other_tenant_invoice(): void
    {
        // Same binding-level isolation on the cancel route.
        [$institute, $owner] = $this->tenantOwner('policy-cancel-cross@example.test');
        $other = Institute::where('name', 'Tutu Center')->firstOrFail();
        $otherInvoice = Invoice::factory()->create(['institute_id' => $other->id]);

        $this->asUser($owner, $institute->id)
            ->post(route('finance.invoices.cancel', $otherInvoice))
            ->assertStatus(404);
    }

    public function test_cannot_cancel_paid_invoice(): void
    {
        [$institute, $owner] = $this->tenantOwner('policy-paid@example.test');
        $invoice = Invoice::factory()->paid()->create(['institute_id' => $institute->id]);

        $this->asUser($owner, $institute->id)
            ->post(route('finance.invoices.cancel', $invoice))
            ->assertStatus(403);
    }
}
