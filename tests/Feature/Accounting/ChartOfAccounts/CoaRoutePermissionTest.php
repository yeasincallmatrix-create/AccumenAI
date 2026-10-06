<?php

namespace Tests\Feature\Accounting\ChartOfAccounts;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ModuleAccessService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A2.1 — the eight CoA routes carry permission:accounts.view|edit|delete
 * plus module_access:accounting on top of the inherited S1 stack.
 *
 * MAWA ACADEMY (education) keeps accounting enabled because accounting is a
 * core module; the module case disables it through a deny entitlement.
 */
class CoaRoutePermissionTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        TenantContext::set($this->institute->id);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_institute_owner_can_get_index(): void
    {
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('finance.chart-of-accounts.index'))
            ->assertOk();
    }

    public function test_trainer_without_accounts_view_is_forbidden_on_index(): void
    {
        // trainer (not teacher) and viewer (not receptionist/branch-manager):
        // DenyTeacherFromFinance 403s teachers and FinanceWriteGate 403s
        // receptionist|branch-manager on CoA writes, both BEFORE CheckPermission.
        // Using those roles would satisfy assertForbidden() for the wrong reason.
        //
        // 403 must originate from CheckPermission:accounts.view (late in the S1
        // stack) — NOT from DenyTeacherFromFinance or FinanceWriteGate.
        $trainer = $this->user('trainer');

        $response = $this->actingAs($trainer, 'institute_user')
            ->get(route('finance.chart-of-accounts.index'));

        $response->assertForbidden();
        $response->assertDontSee('Teachers do not have access');
        $response->assertDontSee('Finance manage access required');
    }

    public function test_institute_owner_can_post_store(): void
    {
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->post(route('finance.chart-of-accounts.store'), [
                'code' => '9811',
                'name' => 'A21 Owner Stored',
                'type' => 'expense',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => '9811',
            'institute_id' => $this->institute->id,
        ]);
    }

    public function test_accounts_view_without_accounts_edit_cannot_put_update(): void
    {
        // viewer holds accounts.view but not accounts.edit, and is absent from
        // FinanceWriteGate::$deniedRoles — so the PUT reaches
        // CheckPermission:accounts.edit instead of being short-circuited by the
        // upstream write gate (which would 403 receptionist|branch-manager for
        // the wrong reason).
        $this->grant('viewer', 'accounts.view');
        $viewer = $this->user('viewer');
        $account = $this->ownAccount();

        $this->actingAs($viewer, 'institute_user')
            ->get(route('finance.chart-of-accounts.index'))
            ->assertOk();

        $response = $this->actingAs($viewer, 'institute_user')
            ->put(route('finance.chart-of-accounts.update', $account), [
                'code' => $account->code,
                'name' => 'Renamed By View Only',
                'type' => $account->type,
            ]);

        $response->assertForbidden();
        $response->assertDontSee('Finance manage access required');
        $response->assertDontSee('Teachers do not have access');

        $this->assertSame('A21 Own Account', $account->fresh()->name);
    }

    public function test_accounts_edit_can_put_update(): void
    {
        $this->grant('accountant', 'accounts.view', 'accounts.edit');
        $accountant = $this->user('accountant');
        $account = $this->ownAccount();

        $this->actingAs($accountant, 'institute_user')
            ->put(route('finance.chart-of-accounts.update', $account), [
                'code' => $account->code,
                'name' => 'Renamed By Editor',
                'type' => $account->type,
            ])
            ->assertRedirect();

        $this->assertSame('Renamed By Editor', $account->fresh()->name);
    }

    public function test_all_routes_forbidden_without_accounting_module(): void
    {
        app(ModuleAccessService::class)->grantModule($this->institute, 'accounting', [
            'status' => 'active',
            'is_grant' => false,
        ]);

        $owner = $this->user('institute-owner');
        $account = $this->ownAccount();

        $this->actingAs($owner, 'institute_user')
            ->get(route('finance.chart-of-accounts.index'))
            ->assertForbidden();

        $this->actingAs($owner, 'institute_user')
            ->get(route('finance.chart-of-accounts.create'))
            ->assertForbidden();

        $this->actingAs($owner, 'institute_user')
            ->post(route('finance.chart-of-accounts.store'), [
                'code' => '9812',
                'name' => 'No Module',
                'type' => 'expense',
            ])
            ->assertForbidden();

        $this->actingAs($owner, 'institute_user')
            ->get(route('finance.chart-of-accounts.edit', $account))
            ->assertForbidden();

        $this->actingAs($owner, 'institute_user')
            ->put(route('finance.chart-of-accounts.update', $account), [
                'code' => $account->code,
                'name' => 'No Module',
                'type' => $account->type,
            ])
            ->assertForbidden();

        $this->actingAs($owner, 'institute_user')
            ->post(route('finance.chart-of-accounts.update.opening', $account), [
                'opening_balance' => 1,
                'opening_balance_date' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($owner, 'institute_user')
            ->post(route('finance.chart-of-accounts.toggle', $account))
            ->assertForbidden();

        $this->actingAs($owner, 'institute_user')
            ->delete(route('finance.chart-of-accounts.destroy', $account))
            ->assertForbidden();
    }

    public function test_view_only_cannot_update_opening_balance(): void
    {
        // receptionist holds accounts.view but not accounts.edit
        $receptionist = $this->user('receptionist');
        $account = $this->ownAccount();

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('finance.chart-of-accounts.update.opening', $account), [
                'opening_balance' => 1000,
                'opening_balance_date' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_edit_holder_can_reach_update_opening_gate(): void
    {
        $this->grant('accountant', 'accounts.edit');
        $accountant = $this->user('accountant');
        $account = $this->ownAccount();

        $response = $this->actingAs($accountant, 'institute_user')
            ->post(route('finance.chart-of-accounts.update.opening', $account), [
                'opening_balance' => 1000,
                'opening_balance_date' => now()->toDateString(),
            ]);

        // Not asserting 200 — permission layer passed (not 403)
        $this->assertNotSame(403, $response->status());
    }

    // ------------------------------------------------------------ Helpers

    private function user(string $roleSlug): InstituteUser
    {
        $roleId = Role::withoutGlobalScopes()->where('slug', $roleSlug)->firstOrFail()->id;

        return InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $roleId,
            'first_name' => 'CoaPerm',
            'last_name' => 'User',
            'email' => 'coa-perm-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    /**
     * Test-scoped grant to a global role; rolled back with the transaction.
     */
    private function grant(string $roleSlug, string ...$permissionSlugs): void
    {
        $roleId = Role::withoutGlobalScopes()->where('slug', $roleSlug)->firstOrFail()->id;

        foreach ($permissionSlugs as $slug) {
            $permissionId = Permission::where('slug', $slug)->firstOrFail()->id;

            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ], []);
        }
    }

    private function ownAccount(): ChartOfAccount
    {
        $groupId = AccountGroup::withoutGlobalScope('institute')
            ->where('institute_id', $this->institute->id)
            ->where('category', 'asset')
            ->value('id')
            ?? AccountGroup::withoutGlobalScope('institute')
                ->whereNull('institute_id')
                ->where('is_system', 1)
                ->where('category', 'asset')
                ->value('id');

        return ChartOfAccount::withoutGlobalScope('institute')->create([
            'institute_id' => $this->institute->id,
            'branch_id' => null,
            'account_group_id' => $groupId,
            'code' => '9876',
            'name' => 'A21 Own Account',
            'type' => 'asset',
            'is_system' => 0,
            'is_active' => 1,
        ]);
    }
}
