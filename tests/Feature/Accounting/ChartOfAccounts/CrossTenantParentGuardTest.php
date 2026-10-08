<?php

namespace Tests\Feature\Accounting\ChartOfAccounts;

use App\Livewire\ChartOfAccountList;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for the CoA parent-id guards:
 *  - F-001 cross-tenant parent_id on update (and the service layer behind it)
 *  - N-1   parent-flag hooks must never rewrite shared global rows
 *  - F-010 creating a child must flip a tenant parent to header
 *  - F-011 delete guard blocks every RESTRICT-referenced account
 *  - F-012 is_system cannot be mass-assigned over HTTP
 */
class CrossTenantParentGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Guard Owner',
            'first_name' => 'Guard',
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

    protected function institutes(): array
    {
        return [
            Institute::where('name', 'MAWA ACADEMY')->firstOrFail(),
            Institute::where('name', 'Tutu Center')->firstOrFail(),
        ];
    }

    protected function createTenantAccount(int $instituteId, array $overrides = []): ChartOfAccount
    {
        $type = $overrides['type'] ?? 'asset';
        $groupId = AccountGroup::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('category', $type)
            ->value('id')
            ?? AccountGroup::withoutGlobalScope('institute')
                ->whereNull('institute_id')
                ->where('is_system', 1)
                ->where('category', $type)
                ->value('id');

        return ChartOfAccount::withoutGlobalScope('institute')->create(array_merge([
            'institute_id' => $instituteId,
            'branch_id' => null,
            'account_group_id' => $groupId,
            'code' => '9101',
            'name' => 'Guard Account',
            'type' => $type,
            'is_active' => 1,
            'is_header' => 0,
            'is_postable' => 1,
        ], $overrides));
    }

    /**
     * Shared global anchor: seeder-owned, is_system = 1, no parent.
     */
    protected function globalAnchor(string $type): ChartOfAccount
    {
        $anchor = ChartOfAccount::withoutGlobalScopes()
            ->whereNull('institute_id')
            ->where('is_system', 1)
            ->whereNull('parent_id')
            ->where('type', $type)
            ->orderBy('id')
            ->first();

        $this->assertNotNull($anchor, "Expected a seeded global {$type} anchor.");

        return $anchor;
    }

    /**
     * Shared global leaf: seeder-owned, is_system = 1, already has a parent
     * and is postable — the shape the parent-flag hooks must never touch.
     */
    protected function globalLeaf(): ChartOfAccount
    {
        $leaf = ChartOfAccount::withoutGlobalScopes()
            ->whereNull('institute_id')
            ->where('is_system', 1)
            ->whereNotNull('parent_id')
            ->where('is_postable', 1)
            ->orderBy('id')
            ->first();

        $this->assertNotNull($leaf, 'Expected a seeded global postable leaf.');

        return $leaf;
    }

    // ------------------------------------------------------------ F-001

    public function test_update_rejects_parent_from_another_tenant(): void
    {
        [$a, $b] = $this->institutes();

        $own = $this->createTenantAccount($a->id, ['code' => '9501']);
        $otherTenantParent = $this->createTenantAccount($b->id, ['code' => '9502']);

        $owner = $this->owner('guard-upd-xparent@example.test');
        $this->assign($owner, $a);

        $this->asUser($owner, $a->id)
            ->put(route('finance.chart-of-accounts.update', $own->id), [
                'code' => $own->code,
                'name' => $own->name,
                'type' => $own->type,
                'parent_id' => $otherTenantParent->id,
            ])
            ->assertSessionHasErrors(['parent_id']);

        $this->assertNull($own->fresh()->parent_id, 'Cross-tenant parent must not be linked.');
    }

    public function test_service_layer_rejects_cross_tenant_parent_on_update(): void
    {
        [$a, $b] = $this->institutes();

        $own = $this->createTenantAccount($a->id, ['code' => '9503']);
        $otherTenantParent = $this->createTenantAccount($b->id, ['code' => '9504']);

        $this->expectException(ValidationException::class);

        app(ChartOfAccountService::class)->updateTenantAccount($a->id, $own->id, [
            'parent_id' => $otherTenantParent->id,
        ]);
    }

    public function test_create_rejects_parent_from_another_tenant(): void
    {
        [$a, $b] = $this->institutes();

        $otherTenantParent = $this->createTenantAccount($b->id, ['code' => '9505']);

        $owner = $this->owner('guard-cre-xparent@example.test');
        $this->assign($owner, $a);

        $this->asUser($owner, $a->id)
            ->post(route('finance.chart-of-accounts.store'), [
                'code' => '9505.1',
                'name' => 'Attempted sub',
                'type' => 'asset',
                'parent_id' => $otherTenantParent->id,
            ])
            ->assertSessionHasErrors(['parent_id']);

        $this->assertDatabaseMissing('chart_of_accounts', ['code' => '9505.1']);
    }

    public function test_create_rejects_sub_account_under_global_anchor(): void
    {
        [$a] = $this->institutes();

        $anchor = $this->globalAnchor('asset');

        $owner = $this->owner('guard-cre-global@example.test');
        $this->assign($owner, $a);

        // Post-C1 (reanchor): tenant accounts must have tenant-owned parents.
        $this->asUser($owner, $a->id)
            ->post(route('finance.chart-of-accounts.store'), [
                'code' => '9506',
                'name' => 'Anchor Child',
                'type' => $anchor->type,
                'parent_id' => $anchor->id,
            ])
            ->assertSessionHasErrors(['parent_id']);

        $this->assertDatabaseMissing('chart_of_accounts', ['code' => '9506']);
    }

    public function test_update_rejects_self_as_parent(): void
    {
        [$a] = $this->institutes();

        $own = $this->createTenantAccount($a->id, ['code' => '9507']);

        $owner = $this->owner('guard-self@example.test');
        $this->assign($owner, $a);

        $this->asUser($owner, $a->id)
            ->put(route('finance.chart-of-accounts.update', $own->id), [
                'code' => $own->code,
                'name' => $own->name,
                'type' => $own->type,
                'parent_id' => $own->id,
            ])
            ->assertSessionHasErrors(['parent_id']);

        $this->assertNull($own->fresh()->parent_id);
    }

    public function test_update_rejects_parent_below_max_depth(): void
    {
        [$a] = $this->institutes();

        $root = $this->createTenantAccount($a->id, ['code' => '9510']);
        $child = $this->createTenantAccount($a->id, ['code' => '9510.1', 'parent_id' => $root->id]);
        $sibling = $this->createTenantAccount($a->id, ['code' => '9511']);

        $owner = $this->owner('guard-depth@example.test');
        $this->assign($owner, $a);

        $this->asUser($owner, $a->id)
            ->put(route('finance.chart-of-accounts.update', $sibling->id), [
                'code' => $sibling->code,
                'name' => $sibling->name,
                'type' => $sibling->type,
                'parent_id' => $child->id,
            ])
            ->assertSessionHasErrors(['parent_id']);

        $this->assertNull($sibling->fresh()->parent_id);
    }

    public function test_update_rejects_parent_of_a_different_type(): void
    {
        [$a] = $this->institutes();

        $own = $this->createTenantAccount($a->id, ['code' => '9512', 'type' => 'asset']);
        $incomeAnchor = $this->globalAnchor('income');

        $owner = $this->owner('guard-type@example.test');
        $this->assign($owner, $a);

        $this->asUser($owner, $a->id)
            ->put(route('finance.chart-of-accounts.update', $own->id), [
                'code' => $own->code,
                'name' => $own->name,
                'type' => 'asset',
                'parent_id' => $incomeAnchor->id,
            ])
            ->assertSessionHasErrors(['parent_id']);

        $this->assertNull($own->fresh()->parent_id);
    }

    public function test_update_rejects_global_anchor_as_parent(): void
    {
        [$a] = $this->institutes();

        $own = $this->createTenantAccount($a->id, ['code' => '9513', 'type' => 'asset']);
        $anchor = $this->globalAnchor('asset');

        $owner = $this->owner('guard-ok@example.test');
        $this->assign($owner, $a);

        // Post-C1 (reanchor): tenant accounts must have tenant-owned parents.
        $this->asUser($owner, $a->id)
            ->put(route('finance.chart-of-accounts.update', $own->id), [
                'code' => $own->code,
                'name' => $own->name,
                'type' => 'asset',
                'parent_id' => $anchor->id,
            ])
            ->assertSessionHasErrors(['parent_id']);

        $this->assertNull($own->fresh()->parent_id, 'Global anchor must not become the parent.');
    }

    public function test_parent_dropdown_excludes_global_anchors(): void
    {
        [$a] = $this->institutes();

        $anchor = $this->globalAnchor('asset');

        $owner = $this->owner('guard-dropdown@example.test');
        $this->assign($owner, $a);

        // Post-C1 (reanchor): the parent dropdown offers tenant-owned rows
        // only — a global anchor must never be rendered as an option.
        $this->asUser($owner, $a->id)
            ->get(route('finance.chart-of-accounts.create'))
            ->assertOk()
            ->assertDontSee('value="'.$anchor->id.'" data-type=', false);
    }

    // ------------------------------------------------------------ N-1

    public function test_global_anchor_flags_unchanged_after_tenant_action(): void
    {
        [$a] = $this->institutes();

        $anchor = $this->globalAnchor('income');
        $this->assertSame(1, (int) $anchor->is_header, 'Global anchor baseline: is_header = 1');
        $this->assertSame(0, (int) $anchor->is_postable, 'Global anchor baseline: is_postable = 0');

        $child = $this->createTenantAccount($a->id, [
            'code' => '9520',
            'type' => 'income',
            'parent_id' => $anchor->id,
        ]);
        $child->update(['parent_id' => null]);
        $child->update(['parent_id' => $anchor->id]);

        $anchor->refresh();

        $this->assertSame(1, (int) $anchor->is_header, 'A tenant action must not rewrite a global anchor.');
        $this->assertSame(0, (int) $anchor->is_postable, 'A tenant action must not rewrite a global anchor.');
    }

    public function test_global_leaf_flags_unchanged_after_tenant_action(): void
    {
        [$a] = $this->institutes();

        $leaf = $this->globalLeaf();
        $this->assertSame(0, (int) $leaf->is_header, 'Global leaf baseline: is_header = 0');
        $this->assertSame(1, (int) $leaf->is_postable, 'Global leaf baseline: is_postable = 1');

        $own = $this->createTenantAccount($a->id, ['code' => '9521']);
        $own->update(['parent_id' => $leaf->id]);

        $leaf->refresh();

        $this->assertSame(0, (int) $leaf->is_header, 'A tenant child must not flip a global leaf to header.');
        $this->assertSame(1, (int) $leaf->is_postable, 'A tenant child must not un-post a global leaf.');
    }

    // ------------------------------------------------------------ F-010

    public function test_creating_a_child_flips_tenant_parent_to_header(): void
    {
        [$a] = $this->institutes();

        $parent = $this->createTenantAccount($a->id, [
            'code' => '9530',
            'is_header' => 0,
            'is_postable' => 1,
        ]);

        $this->createTenantAccount($a->id, [
            'code' => '9530.1',
            'parent_id' => $parent->id,
        ]);

        $parent->refresh();

        $this->assertTrue((bool) $parent->is_header, 'A parent that just gained a child becomes a header.');
        $this->assertFalse((bool) $parent->is_postable, 'A parent that just gained a child stops being postable.');
    }

    // ------------------------------------------------------------ F-012

    public function test_http_cannot_mass_assign_is_system(): void
    {
        [$a] = $this->institutes();

        $owner = $this->owner('guard-issys@example.test');
        $this->assign($owner, $a);

        $this->asUser($owner, $a->id)
            ->post(route('finance.chart-of-accounts.store'), [
                'code' => '9540',
                'name' => 'Mass Assigned System',
                'type' => 'expense',
                'is_system' => 1,
                'created_by' => 999999,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('finance.chart-of-accounts.index'));

        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => '9540',
            'institute_id' => $a->id,
            'is_system' => 0,
        ]);
    }

    // ------------------------------------------------------------ Livewire

    public function test_livewire_store_rejects_duplicate_code_and_creates_no_row(): void
    {
        [$a] = $this->institutes();

        // The component's manage gate resolves through the institute_user
        // guard (role permissions), the same way the modal's own tests do.
        $instituteUser = InstituteUser::create([
            'institute_id' => $a->id,
            'role_id' => Role::withoutGlobalScopes()->where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Guard',
            'last_name' => 'Modal',
            'email' => 'guard-livewire-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        TenantContext::set($a->id);
        $this->actingAs($instituteUser, 'institute_user');

        $globalCode = (string) ChartOfAccount::query()
            ->whereNull('institute_id')
            ->where('is_system', 1)
            ->value('code');

        $this->assertNotSame('', $globalCode, 'Expected a global system account in the fixture.');

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.code', $globalCode)
            ->set('form.name', 'Livewire Duplicate')
            ->call('store')
            ->assertHasErrors(['form.code']);

        $this->assertDatabaseMissing('chart_of_accounts', ['name' => 'Livewire Duplicate']);
    }

    // ------------------------------------------------------------ F-011

    public function test_delete_blocked_when_account_has_opening_balance(): void
    {
        [$a] = $this->institutes();

        $account = $this->createTenantAccount($a->id, ['code' => '9177']);

        $fiscalYearId = DB::table('fiscal_years')->insertGetId([
            'institute_id' => $a->id,
            'name' => 'Guard FY',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        DB::table('opening_balances')->insert([
            'institute_id' => $a->id,
            'fiscal_year_id' => $fiscalYearId,
            'coa_id' => $account->id,
            'debit' => 100,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Cannot delete account with opening balances: {$account->code}");

        $account->delete();
    }

    // ------------------------------------------------------------ F1 dedup

    /**
     * Universal (untagged) global asset the tenant has not adopted:
     * Phase-F gate passes it for any industry, so the fixture is stable.
     */
    protected function unownedUniversalGlobal(): ChartOfAccount
    {
        $global = ChartOfAccount::withoutGlobalScopes()
            ->whereNull('institute_id')
            ->where('is_system', 1)
            ->whereNull('industries')
            ->where('type', 'asset')
            ->orderBy('code')
            ->first();

        $this->assertNotNull($global, 'Expected a universal global asset account in the fixture.');

        return $global;
    }

    public function test_tenant_listing_dedupes_global_codes(): void
    {
        [$a] = $this->institutes();

        $code = (string) $this->unownedUniversalGlobal()->code;

        // Fixture precondition: the tenant owns no copy of this code yet.
        $this->assertSame(
            0,
            ChartOfAccount::withoutGlobalScope('institute')
                ->where('institute_id', $a->id)
                ->where('code', $code)
                ->count(),
        );

        // Unadopted global: exactly one visible row (the global itself).
        $this->assertSame(1, ChartOfAccount::visible($a->id)->where('code', $code)->count());

        $copy = $this->createTenantAccount($a->id, ['code' => $code, 'name' => 'Tenant Copy '.$code]);

        $visible = ChartOfAccount::visible($a->id)->where('code', $code)->get();

        $this->assertCount(1, $visible, 'A global and its tenant copy must never both be visible.');
        $this->assertSame($a->id, (int) $visible->first()->institute_id, 'The tenant-owned copy wins.');
        $this->assertTrue($visible->first()->is($copy));
    }

    public function test_global_code_visible_when_tenant_does_not_own_it(): void
    {
        [$a] = $this->institutes();

        $global = $this->unownedUniversalGlobal();
        $code = (string) $global->code;

        $visible = ChartOfAccount::visible($a->id)->where('code', $code)->get();

        $this->assertCount(1, $visible, 'A global the tenant has not adopted must stay visible.');
        $this->assertNull($visible->first()->institute_id, 'The unadopted row is still the shared global.');
        $this->assertTrue($visible->first()->is($global));
    }

    public function test_groups_listing_dedupes_global_codes(): void
    {
        [$a] = $this->institutes();

        $code = '1';
        $global = AccountGroup::withoutGlobalScope('institute')
            ->whereNull('institute_id')
            ->where('is_system', 1)
            ->where('code', $code)
            ->first();

        $this->assertNotNull($global, 'Expected the seeded global asset group in the fixture.');
        $this->assertSame(1, AccountGroup::visible($a->id)->where('code', $code)->count());

        AccountGroup::withoutGlobalScope('institute')->create([
            'institute_id' => $a->id,
            'code' => $code,
            'name' => 'Tenant Asset Group',
            'category' => 'asset',
            'is_system' => 0,
        ]);

        $visible = AccountGroup::visible($a->id)->get();
        $duplicated = $visible->groupBy('code')->filter(fn ($rows) => $rows->count() > 1);

        $this->assertCount(0, $duplicated, 'The account-group dropdown must never repeat a code.');

        $row = $visible->firstWhere('code', $code);
        $this->assertNotNull($row, 'The code must still be offered after the tenant adopts it.');
        $this->assertSame($a->id, (int) $row->institute_id, 'The tenant-owned group wins.');
    }

    public function test_listing_route_renders_no_duplicate_codes(): void
    {
        [$a] = $this->institutes();

        $global = $this->unownedUniversalGlobal();
        $code = (string) $global->code;
        $copy = $this->createTenantAccount($a->id, ['code' => $code, 'name' => 'Listing Copy '.$code]);

        $owner = $this->owner('guard-dedup-listing@example.test');
        $this->assign($owner, $a);

        $html = $this->asUser($owner, $a->id)
            ->get(route('finance.chart-of-accounts.index'))
            ->assertOk()
            ->getContent();

        preg_match_all('/<td class="text-muted">([^<]*)<\/td>/', $html, $matches);
        $codes = array_values(array_filter(array_map('trim', $matches[1]), fn ($cell) => $cell !== ''));

        $this->assertNotEmpty($codes, 'Expected rendered CoA code cells in the listing.');
        $this->assertSame($codes, array_values(array_unique($codes)), 'The CoA listing must not render a code twice.');
        $this->assertSame(
            1,
            count(array_filter($codes, fn ($cell) => $cell === $code)),
            "Code {$code} must render exactly once (the tenant copy, not both rows).",
        );

        // Row identity: the hidden global never renders, the tenant copy does.
        $this->assertStringNotContainsString(
            '<span class="fw-semibold">'.e($global->name).'</span>',
            $html,
            'The deduped global row must not be rendered.',
        );
        $this->assertStringContainsString(
            '<span class="fw-semibold">'.$copy->name.'</span>',
            $html,
            'The tenant-owned row must be rendered.',
        );
    }
}
