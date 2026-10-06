<?php

namespace Tests\Feature\Finance;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Permission;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A2.2a — the 35 finance routes carry permission:finance.view|manage
 * plus module_access:finance on top of the inherited S1 stack.
 *
 * DenyTeacherFromFinance and FinanceWriteGate run BEFORE CheckPermission,
 * so the fixture roles are chosen so the 403 provably originates from the
 * permission middleware under test:
 *
 *   - receptionist is blocked by CheckPermission:finance.view on GET (it is
 *     absent from FinanceWriteGate::$deniedRoles for this URI and is not in
 *     its gated patterns), not by the upstream write gate.
 *   - accountant is blocked by CheckPermission:finance.manage on POST store
 *     (finance/journals store is not a gated FinanceWriteGate URI).
 *   - teacher is blocked upstream by DenyTeacherFromFinance on every
 *     finance/* URI — asserted explicitly via its message.
 *
 * MAWA ACADEMY runs advanced_accounting_enabled=1 in the test DB, so
 * RequireAdvancedAccounting (also ahead of CheckPermission) does not 403.
 */
class FinanceRoutePermissionTest extends TestCase
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

    public function test_institute_owner_can_get_journals_index(): void
    {
        $owner = $this->user('institute-owner');

        $this->actingAs($owner, 'institute_user')
            ->get(route('finance.journals.index'))
            ->assertOk();
    }

    public function test_accountant_can_get_journals_index(): void
    {
        $accountant = $this->user('accountant');

        $this->actingAs($accountant, 'institute_user')
            ->get(route('finance.journals.index'))
            ->assertOk();
    }

    public function test_receptionist_cannot_get_journals_index(): void
    {
        $receptionist = $this->user('receptionist');

        $response = $this->actingAs($receptionist, 'institute_user')
            ->get(route('finance.journals.index'));

        $response->assertForbidden();

        // 403 must come from CheckPermission:finance.view, not from the
        // upstream middlewares that also run ahead of it.
        $response->assertDontSee('Teachers do not have access');
        $response->assertDontSee('Finance manage access required');
        $response->assertDontSee('Advanced Accounting mode is disabled');
    }

    public function test_institute_owner_can_post_journals_store(): void
    {
        $owner = $this->user('institute-owner');

        $response = $this->actingAs($owner, 'institute_user')
            ->post(route('finance.journals.store'), [
                'date' => now()->toDateString(),
                'description' => 'A22a Owner Journal',
            ]);

        // Owner holds finance.manage (and bypasses as super-user): the request
        // must clear CheckPermission:finance.manage. Any non-403 means it did;
        // a permission denial would abort(403) in the middleware.
        $this->assertNotSame(
            403,
            $response->getStatusCode(),
            'institute-owner must pass permission:finance.manage on journal store.'
        );
    }

    public function test_trainer_with_view_but_not_manage_cannot_post_journals_store(): void
    {
        // Reproduce the original test's intent ("holds finance.view but not
        // finance.manage") without relying on the global matrix, where the only
        // view-not-manage role (branch-manager) is intercepted upstream by
        // FinanceWriteGate. The in-test grant of finance.view to trainer isolates
        // the CheckPermission:finance.manage boundary:
        //   - GET journal index  (requires finance.view)   -> 200
        //   - POST journal store (requires finance.manage) -> 403 from CheckPermission
        $this->grant('trainer', 'finance.view');
        $trainer = $this->user('trainer');

        // Has view — the read path clears CheckPermission:finance.view.
        $this->actingAs($trainer, 'institute_user')
            ->get(route('finance.journals.index'))
            ->assertOk();

        // Lacks manage — the write path must 403 in CheckPermission:finance.manage.
        $response = $this->actingAs($trainer, 'institute_user')
            ->post(route('finance.journals.store'), [
                'date' => now()->toDateString(),
                'description' => 'A22a Accountant Journal',
            ]);

        $response->assertForbidden();

        // 403 must come from CheckPermission:finance.manage (which aborts with
        // "You are not authorized..."), not from any upstream middleware:
        // FinanceWriteGate (FinanceWriteGate.php:92), DenyTeacherFromFinance
        // (:38) or RequireAdvancedAccounting (:17).
        $response->assertDontSee('Finance manage access required');
        $response->assertDontSee('Teachers do not have access');
        $response->assertDontSee('Advanced Accounting mode is disabled');
    }

    public function test_teacher_is_blocked_on_all_finance_routes(): void
    {
        $teacher = $this->user('teacher');

        // Parameterless routes covering every in-scope finance group. All 35
        // URIs begin with "finance/", and DenyTeacherFromFinance matches on
        // $request->is('finance*') ahead of CheckPermission, so the gate that
        // applies here applies to the parameterised siblings too.
        $routes = [
            ['finance.journals.index', 'get'],
            ['finance.journals.create', 'get'],
            ['finance.journals.store', 'post'],
            ['finance.invoices.index', 'get'],
            ['finance.invoices.create', 'get'],
            ['finance.invoices.store', 'post'],
            ['finance.expenses.index', 'get'],
            ['finance.expenses.create', 'get'],
            ['finance.expenses.store', 'post'],
            ['finance.parties.index', 'get'],
            ['finance.parties.create', 'get'],
            ['finance.parties.store', 'post'],
            ['finance.payment-methods.index', 'get'],
            ['finance.payment-methods.create', 'get'],
            ['finance.payment-methods.store', 'post'],
            ['finance.payments.index', 'get'],
            ['finance.payments.store', 'post'],
        ];

        foreach ($routes as [$name, $method]) {
            $response = $this->actingAs($teacher, 'institute_user')
                ->{$method}(route($name));

            $response->assertForbidden();
        }

        // Prove the upstream middleware produced the 403, not the permission.
        $this->actingAs($teacher, 'institute_user')
            ->get(route('finance.journals.index'))
            ->assertSee('Teachers do not have access');
    }

    // ------------------------------------------------------------ Helpers

    private function user(string $roleSlug): InstituteUser
    {
        $roleId = Role::withoutGlobalScopes()->where('slug', $roleSlug)->firstOrFail()->id;

        return InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $roleId,
            'first_name' => 'FinPerm',
            'last_name' => 'User',
            'email' => 'fin-perm-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    /**
     * Test-scoped grant to a global role; rolled back with the transaction.
     * Mirrors CoaRoutePermissionTest::grant() (line 212).
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
}
