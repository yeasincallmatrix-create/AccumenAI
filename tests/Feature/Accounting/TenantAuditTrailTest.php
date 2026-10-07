<?php

namespace Tests\Feature\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F-006 / N-2 — tenant CoA mutations must land in accounting_audit_trails.
 *
 * Covers the three previously unaudited service methods (createTenantAccount,
 * updateTenantAccount, deleteTenantAccount), actor attribution, institute
 * scoping, and the AuditActivityLog middleware now registered on the $tenant
 * route group.
 */
class TenantAuditTrailTest extends TestCase
{
    use DatabaseTransactions;

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Audit Owner',
            'first_name' => 'Audit',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    protected function assign(User $user, Institute $institute, string $role = 'institute-owner'): void
    {
        $roleId = Role::where('slug', $role)->firstOrFail()->id;
        (new MembershipService)->assign($user, $institute->id, $roleId);
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    /** @return array{0: Institute, 1: Institute} */
    protected function institutes(): array
    {
        return [
            Institute::where('name', 'MAWA ACADEMY')->firstOrFail(),
            Institute::where('name', 'Tutu Center')->firstOrFail(),
        ];
    }

    /** @return array<int, object> */
    private function auditRows(int $instituteId, string $action): array
    {
        return DB::table('accounting_audit_trails')
            ->where('institute_id', $instituteId)
            ->where('action', $action)
            ->where('entity_type', 'chart_of_account')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function test_create_tenant_account_writes_audit_trail(): void
    {
        [$a] = $this->institutes();
        $owner = $this->owner('audit-create@example.test');
        $this->assign($owner, $a);
        $this->asUser($owner, $a->id);

        $response = $this->post(route('finance.chart-of-accounts.store'), [
            'code' => '9701',
            'name' => 'Audit Created',
            'type' => 'asset',
        ]);

        $response->assertRedirect();

        $account = ChartOfAccount::withoutGlobalScope('institute')
            ->where('code', '9701')
            ->first();
        $this->assertNotNull($account, 'Account must be created before its audit row can be asserted.');

        $rows = $this->auditRows((int) $a->id, 'create');
        $this->assertCount(1, $rows, 'createTenantAccount must write exactly one create audit row.');

        $row = $rows[0];
        $this->assertSame('chart_of_account', $row->entity_type);
        $this->assertSame((int) $account->id, (int) $row->entity_id);
        $this->assertSame('user', $row->actor_type);
        $this->assertSame((int) $owner->id, (int) $row->actor_id);

        $payload = json_decode((string) $row->after_payload, true);
        $this->assertSame('9701', $payload['code'] ?? null);
        $this->assertSame('Audit Created', $payload['name'] ?? null);
        $this->assertNull($row->before_payload);
    }

    public function test_update_tenant_account_writes_audit_trail(): void
    {
        [$a] = $this->institutes();
        $owner = $this->owner('audit-update@example.test');
        $this->assign($owner, $a);
        $this->asUser($owner, $a->id);

        $this->post(route('finance.chart-of-accounts.store'), [
            'code' => '9702',
            'name' => 'Old Name',
            'type' => 'asset',
        ])->assertRedirect();

        $account = ChartOfAccount::withoutGlobalScope('institute')
            ->where('code', '9702')
            ->firstOrFail();

        $this->put(route('finance.chart-of-accounts.update', $account->id), [
            'code' => '9702',
            'name' => 'New Name',
            'type' => 'asset',
        ])->assertRedirect();

        $rows = $this->auditRows((int) $a->id, 'update');
        $this->assertCount(1, $rows, 'updateTenantAccount must write exactly one update audit row.');

        $row = $rows[0];
        $this->assertSame('chart_of_account', $row->entity_type);
        $this->assertSame((int) $account->id, (int) $row->entity_id);
        $this->assertSame((int) $owner->id, (int) $row->actor_id);

        $before = json_decode((string) $row->before_payload, true);
        $after = json_decode((string) $row->after_payload, true);
        $this->assertSame('Old Name', $before['name'] ?? null);
        $this->assertSame('New Name', $after['name'] ?? null);
        $this->assertSame('9702', $after['code'] ?? null);
    }

    public function test_delete_tenant_account_writes_audit_trail(): void
    {
        [$a] = $this->institutes();
        $owner = $this->owner('audit-delete@example.test');
        $this->assign($owner, $a);
        $this->asUser($owner, $a->id);

        $this->post(route('finance.chart-of-accounts.store'), [
            'code' => '9703',
            'name' => 'Doomed Account',
            'type' => 'asset',
        ])->assertRedirect();

        $account = ChartOfAccount::withoutGlobalScope('institute')
            ->where('code', '9703')
            ->firstOrFail();

        $this->delete(route('finance.chart-of-accounts.destroy', $account->id))
            ->assertRedirect();

        $rows = $this->auditRows((int) $a->id, 'delete');
        $this->assertCount(1, $rows, 'deleteTenantAccount must write exactly one delete audit row.');

        $row = $rows[0];
        $this->assertSame('chart_of_account', $row->entity_type);
        $this->assertSame((int) $account->id, (int) $row->entity_id);
        $this->assertSame((int) $owner->id, (int) $row->actor_id);
        $this->assertNull($row->after_payload);

        $before = json_decode((string) $row->before_payload, true);
        $this->assertSame('9703', $before['code'] ?? null);
        $this->assertSame('Doomed Account', $before['name'] ?? null);
    }

    public function test_audit_trail_records_correct_actor(): void
    {
        [$a] = $this->institutes();

        $creator = $this->owner('audit-actor-a@example.test');
        $this->assign($creator, $a);
        $editor = $this->owner('audit-actor-b@example.test');
        $this->assign($editor, $a);

        $this->asUser($creator, $a->id);
        $this->post(route('finance.chart-of-accounts.store'), [
            'code' => '9704',
            'name' => 'Acted On',
            'type' => 'asset',
        ])->assertRedirect();

        $account = ChartOfAccount::withoutGlobalScope('institute')
            ->where('code', '9704')
            ->firstOrFail();

        $this->asUser($editor, $a->id);
        $this->put(route('finance.chart-of-accounts.update', $account->id), [
            'code' => '9704',
            'name' => 'Acted On Again',
            'type' => 'asset',
        ])->assertRedirect();

        $createRows = $this->auditRows((int) $a->id, 'create');
        $updateRows = $this->auditRows((int) $a->id, 'update');

        $this->assertCount(1, $createRows);
        $this->assertCount(1, $updateRows);
        $this->assertSame((int) $creator->id, (int) $createRows[0]->actor_id);
        $this->assertSame((int) $editor->id, (int) $updateRows[0]->actor_id);
        $this->assertNotSame($createRows[0]->actor_id, $updateRows[0]->actor_id);
    }

    public function test_audit_trail_is_institute_scoped(): void
    {
        [$a, $b] = $this->institutes();

        $ownerA = $this->owner('audit-scope-a@example.test');
        $this->assign($ownerA, $a);
        $ownerB = $this->owner('audit-scope-b@example.test');
        $this->assign($ownerB, $b);

        $this->asUser($ownerA, $a->id);
        $this->post(route('finance.chart-of-accounts.store'), [
            'code' => '9705',
            'name' => 'Scoped A',
            'type' => 'asset',
        ])->assertRedirect();

        $this->asUser($ownerB, $b->id);
        $this->post(route('finance.chart-of-accounts.store'), [
            'code' => '9706',
            'name' => 'Scoped B',
            'type' => 'asset',
        ])->assertRedirect();

        $rowsA = $this->auditRows((int) $a->id, 'create');
        $rowsB = $this->auditRows((int) $b->id, 'create');

        $this->assertCount(1, $rowsA, 'Institute A must own exactly one create row.');
        $this->assertCount(1, $rowsB, 'Institute B must own exactly one create row.');
        $this->assertSame((int) $a->id, (int) $rowsA[0]->institute_id);
        $this->assertSame((int) $b->id, (int) $rowsB[0]->institute_id);
        $this->assertSame((int) $ownerA->id, (int) $rowsA[0]->actor_id);
        $this->assertSame((int) $ownerB->id, (int) $rowsB[0]->actor_id);
    }

    public function test_middleware_writes_module_access_row(): void
    {
        [$a] = $this->institutes();
        $owner = $this->owner('audit-middleware@example.test');
        $this->assign($owner, $a);
        $this->asUser($owner, $a->id);

        $this->get(route('finance.chart-of-accounts.create'))->assertOk();

        // Scoped to this request's path: InvoicePolicyTest commits without a
        // transaction and can leave module_access:finance rows behind.
        $rows = DB::table('accounting_audit_trails')
            ->where('institute_id', $a->id)
            ->where('action', 'module_access:finance')
            ->orderBy('id')
            ->get()
            ->filter(fn ($row) => (json_decode((string) $row->after_payload, true)['path'] ?? null) === 'finance/chart-of-accounts/create')
            ->values();

        $this->assertCount(1, $rows, 'AuditActivityLog must write one module_access:finance row.');

        $row = $rows[0];
        $this->assertSame('finance', $row->entity_type);
        $this->assertNull($row->entity_id, 'Middleware rows carry no entity id.');
        $this->assertSame((int) $owner->id, (int) $row->actor_id);

        $payload = json_decode((string) $row->after_payload, true);
        $this->assertSame('GET', $payload['method'] ?? null);
        $this->assertSame(200, $payload['status_code'] ?? null);
        $this->assertStringStartsWith('finance/', $payload['path'] ?? '');
    }
}
