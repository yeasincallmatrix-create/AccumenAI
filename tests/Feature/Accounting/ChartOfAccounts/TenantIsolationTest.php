<?php

namespace Tests\Feature\Accounting\ChartOfAccounts;

use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Iso Owner',
            'first_name' => 'Iso',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    protected function assign(User $user, Institute $institute, string $role = 'institute-owner'): void
    {
        $roleId = \App\Models\Role::where('slug', $role)->firstOrFail()->id;
        (new MembershipService)->assign($user, $institute->id, $roleId);
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    protected function createTenantAccount(int $instituteId, array $overrides = []): ChartOfAccount
    {
        $type = $overrides['type'] ?? 'asset';
        $groupId = \App\Models\AccountGroup::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('category', $type)
            ->value('id')
            ?? \App\Models\AccountGroup::withoutGlobalScope('institute')
                ->whereNull('institute_id')
                ->where('is_system', 1)
                ->where('category', $type)
                ->value('id');

        return ChartOfAccount::withoutGlobalScope('institute')->create(array_merge([
            'institute_id' => $instituteId,
            'branch_id' => null,
            'account_group_id' => $groupId,
            'code' => '9101',
            'name' => 'Test Account',
            'type' => $type,
            'is_system' => 0,
            'is_active' => 1,
        ], $overrides));
    }

    protected function institutes(): array
    {
        return [
            Institute::where('name', 'MAWA ACADEMY')->firstOrFail(),
            Institute::where('name', 'Tutu Center')->firstOrFail(),
        ];
    }

    public function test_tenant_a_cannot_see_tenant_b_custom_accounts(): void
    {
        [$a, $b] = $this->institutes();

        $bAccount = $this->createTenantAccount($b->id, ['code' => '9991']);

        $owner = $this->owner('iso-a-see@example.test');
        $this->assign($owner, $a);
        $this->asUser($owner, $a->id);
        TenantContext::set($a->id);

        $visible = ChartOfAccount::pluck('id')->toArray();
        $this->assertNotContains($bAccount->id, $visible);
    }

    public function test_tenant_sees_globals_and_own_only(): void
    {
        [$a, $b] = $this->institutes();

        $global = ChartOfAccount::withoutGlobalScope('institute')
            ->whereNull('institute_id')->first();
        $this->assertNotNull($global, 'Global COA rows must be seeded');
        $aAccount = $this->createTenantAccount($a->id, ['code' => '9101']);
        $bAccount = $this->createTenantAccount($b->id, ['code' => '9201']);

        $owner = $this->owner('iso-a-both@example.test');
        $this->assign($owner, $a);
        $this->asUser($owner, $a->id);
        TenantContext::set($a->id);

        $visible = ChartOfAccount::pluck('id')->toArray();
        $this->assertContains($global->id, $visible);
        $this->assertContains($aAccount->id, $visible);
        $this->assertNotContains($bAccount->id, $visible);
    }

    public function test_tenant_cannot_update_other_tenants_account(): void
    {
        [$a, $b] = $this->institutes();

        $bAccount = $this->createTenantAccount($b->id, ['code' => '9301', 'name' => 'B Custom']);

        $owner = $this->owner('iso-a-upd@example.test');
        $this->assign($owner, $a);

        $response = $this->asUser($owner, $a->id)->put(
            route('finance.chart-of-accounts.update', $bAccount->id),
            ['name' => 'HACKED', 'code' => '9301', 'type' => 'asset']
        );

        $response->assertStatus(403);
        $this->assertEquals('B Custom', $bAccount->fresh()->name);
    }

    public function test_tenant_cannot_delete_other_tenants_account(): void
    {
        [$a, $b] = $this->institutes();

        $bAccount = $this->createTenantAccount($b->id, ['code' => '9401']);

        $owner = $this->owner('iso-a-del@example.test');
        $this->assign($owner, $a);

        $response = $this->asUser($owner, $a->id)->delete(
            route('finance.chart-of-accounts.destroy', $bAccount->id)
        );

        $response->assertStatus(403);
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $bAccount->id]);
    }

    public function test_tenant_cannot_modify_global_account(): void
    {
        [$a] = $this->institutes();
        $global = ChartOfAccount::withoutGlobalScope('institute')
            ->whereNull('institute_id')->first();
        $this->assertNotNull($global, 'Global COA rows must be seeded');

        $owner = $this->owner('iso-a-glob@example.test');
        $this->assign($owner, $a);

        $response = $this->asUser($owner, $a->id)->put(
            route('finance.chart-of-accounts.update', $global->id),
            ['name' => 'HACKED', 'code' => $global->code, 'type' => $global->type]
        );

        $response->assertStatus(403);
    }

    public function test_tenant_can_create_own_account(): void
    {
        [$a] = $this->institutes();

        $owner = $this->owner('iso-a-create@example.test');
        $this->assign($owner, $a);

        $response = $this->asUser($owner, $a->id)->post(
            route('finance.chart-of-accounts.store'),
            [
                'code' => '9001',
                'name' => 'My Custom',
                'type' => 'expense',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => '9001',
            'institute_id' => $a->id,
        ]);
    }

    public function test_sub_account_under_global_is_tenant_owned(): void
    {
        [$a] = $this->institutes();

        $parent = ChartOfAccount::withoutGlobalScope('institute')
            ->whereNull('institute_id')
            ->where('is_system', 1)
            ->whereNull('parent_id')
            ->orderBy('code')
            ->first();

        if (! $parent) {
            $this->markTestSkipped('Top-level global anchor not seeded');
        }

        $owner = $this->owner('iso-a-sub@example.test');
        $this->assign($owner, $a);

        $response = $this->asUser($owner, $a->id)->post(
            route('finance.chart-of-accounts.store'),
            [
                'code' => '6600.01',
                'name' => 'Transportation - Bike',
                'type' => $parent->type,
                'parent_id' => $parent->id,
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => '6600.01',
            'institute_id' => $a->id,
            'parent_id' => $parent->id,
        ]);
    }

    public function test_cross_tenant_parent_link_rejected(): void
    {
        [$a, $b] = $this->institutes();

        $bParent = $this->createTenantAccount($b->id, ['code' => '8888']);

        $owner = $this->owner('iso-a-parent@example.test');
        $this->assign($owner, $a);

        $response = $this->asUser($owner, $a->id)->post(
            route('finance.chart-of-accounts.store'),
            [
                'code' => '8888.01',
                'name' => 'Attempted sub',
                'type' => 'asset',
                'parent_id' => $bParent->id,
            ]
        );

        $response->assertSessionHasErrors(['parent_id']);
    }

    public function test_tenant_cannot_create_duplicate_global_code(): void
    {
        [$a] = $this->institutes();

        $global = ChartOfAccount::withoutGlobalScope('institute')
            ->whereNull('institute_id')->first();
        $this->assertNotNull($global, 'Global COA rows must be seeded');

        $owner = $this->owner('iso-a-dup@example.test');
        $this->assign($owner, $a);

        $response = $this->asUser($owner, $a->id)->post(
            route('finance.chart-of-accounts.store'),
            [
                'code' => $global->code,
                'name' => 'Duplicate',
                'type' => $global->type,
            ]
        );

        $response->assertSessionHasErrors(['code']);
    }
}
