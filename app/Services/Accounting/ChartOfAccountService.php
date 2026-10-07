<?php

namespace App\Services\Accounting;

use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Installs the base Chart of Accounts template for an institute.
 *
 * Idempotent: groups and accounts are created with firstOrCreate guarded on
 * (institute_id, branch_id, code) so repeated setup calls never duplicate rows.
 * Accounts reference their category group by code prefix (1=asset, 2=liability,
 * 3=equity, 4=income, 5=expense).
 */
class ChartOfAccountService
{
    public const CATEGORIES = [
        'asset' => ['code' => '1', 'name' => 'Assets', 'sort' => 10],
        'liability' => ['code' => '2', 'name' => 'Liabilities', 'sort' => 20],
        'equity' => ['code' => '3', 'name' => 'Equity', 'sort' => 30],
        'income' => ['code' => '4', 'name' => 'Income', 'sort' => 40],
        'expense' => ['code' => '5', 'name' => 'Expenses', 'sort' => 50],
    ];

    /**
     * [code, name, type, flags] - derived from the canonical CoaTemplate registry.
     *
     * @return array<int, array{0:string, 1:string, 2:string, 3?:array<string, bool>}>
     */
    public static function template(): array
    {
        return CoaTemplate::flatTyped();
    }

    public function __construct() {}

    /**
     * Create a new account. Guards:
     *  - duplicate code within (institute, branch) -> 422;
     *  - parent must belong to the same institute and share the type;
     *  - group must belong to the institute and its category must match type.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createAccount(
        int $instituteId,
        ?int $branchId,
        array $data,
        ?int $actorId = null,
    ): ChartOfAccount {
        $data = $this->validateAccountData($instituteId, $branchId, $data);

        $data['account_group_id'] = $data['account_group_id']
            ?? AccountGroup::query()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $branchId)
                ->where('category', $data['type'])
                ->value('id')
            ?? $this->ensureGroups($instituteId, $branchId, $actorId)[$data['type']]->id;

        $account = ChartOfAccount::create(array_merge($data, [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'is_active' => true,
            'is_system' => false,
            'created_by' => $actorId,
        ]));

        app(AccountingAuditService::class)->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'chart_of_account',
            'entity_id' => $account->id,
            'after_payload' => ['code' => $account->code, 'name' => $account->name],
        ]);

        return $account;
    }

    /**
     * Update an existing account. Duplicate-code guard excludes the row itself.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateAccount(
        ChartOfAccount $account,
        array $data,
        ?int $actorId = null,
    ): ChartOfAccount {
        $data = $this->validateAccountData($account->institute_id, $account->branch_id, $data, exceptAccountId: $account->id);

        $account->forceFill(array_merge($data, [
            'updated_by' => $actorId,
        ]))->save();

        app(AccountingAuditService::class)->log($account->institute_id, [
            'branch_id' => $account->branch_id,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'update',
            'entity_type' => 'chart_of_account',
            'entity_id' => $account->id,
            'after_payload' => ['code' => $account->code, 'name' => $account->name],
        ]);

        return $account;
    }

    /**
     * Toggle an account active/inactive. System template accounts can be
     * deactivated but never deleted.
     */
    public function toggleActive(ChartOfAccount $account, ?int $actorId = null): ChartOfAccount
    {
        $account->forceFill([
            'is_active' => ! $account->is_active,
            'updated_by' => $actorId,
        ])->save();

        app(AccountingAuditService::class)->log($account->institute_id, [
            'branch_id' => $account->branch_id,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'update',
            'entity_type' => 'chart_of_account',
            'entity_id' => $account->id,
            'after_payload' => ['is_active' => $account->is_active],
        ]);

        return $account;
    }

    /**
     * Soft-delete an account. Blocked when posted journal entries reference it
     * or when it is a system template account — deactivation is the supported
     * alternative in those cases.
     *
     * @throws ValidationException
     */
    public function delete(ChartOfAccount $account, ?int $actorId = null): void
    {
        if ($account->is_system) {
            throw ValidationException::withMessages([
                'account' => 'System template accounts cannot be deleted; deactivate them instead.',
            ]);
        }

        $hasEntries = JournalEntry::query()
            ->where('institute_id', $account->institute_id)
            ->where('coa_id', $account->id)
            ->exists();

        if ($hasEntries) {
            throw ValidationException::withMessages([
                'account' => 'Accounts with posted journal entries cannot be deleted; deactivate them instead.',
            ]);
        }

        $account->delete();

        app(AccountingAuditService::class)->log($account->institute_id, [
            'branch_id' => $account->branch_id,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'delete',
            'entity_type' => 'chart_of_account',
            'entity_id' => $account->id,
            'after_payload' => ['code' => $account->code],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateAccountData(int $instituteId, ?int $branchId, array $data, ?int $exceptAccountId = null): array
    {
        $validator = validator($data, [
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:asset,liability,equity,income,expense'],
            'account_group_id' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'integer'],
            'is_cash' => ['nullable', 'boolean'],
            'is_bank' => ['nullable', 'boolean'],
            'is_receivable' => ['nullable', 'boolean'],
            'is_payable' => ['nullable', 'boolean'],
            'cash_flow_category' => ['nullable', Rule::in(['operating', 'investing', 'financing'])],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $codeQuery = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('code', $data['code'])
            ->whereNull('deleted_at');

        if ($exceptAccountId !== null) {
            $codeQuery->where('id', '!=', $exceptAccountId);
        }

        if ($codeQuery->exists()) {
            throw ValidationException::withMessages([
                'code' => 'An account with this code already exists in this scope.',
            ]);
        }

        if (isset($data['account_group_id'])) {
            $group = AccountGroup::query()
                ->where('institute_id', $instituteId)
                ->where('id', $data['account_group_id'])
                ->first();

            if ($group === null) {
                throw ValidationException::withMessages([
                    'account_group_id' => 'The selected group does not belong to this institute.',
                ]);
            }

            if ($group->category !== $data['type']) {
                throw ValidationException::withMessages([
                    'account_group_id' => 'The group category does not match the account type.',
                ]);
            }
        }

        if (isset($data['parent_id'])) {
            $parent = ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where('id', $data['parent_id'])
                ->first();

            if ($parent === null || $parent->type !== $data['type']) {
                throw ValidationException::withMessages([
                    'parent_id' => 'The parent account does not belong to this institute or has a different type.',
                ]);
            }
        }

        $data['is_cash'] = ! empty($data['is_cash']);
        $data['is_bank'] = ! empty($data['is_bank']);
        $data['is_receivable'] = ! empty($data['is_receivable']);
        $data['is_payable'] = ! empty($data['is_payable']);
        $data['cash_flow_category'] = $data['cash_flow_category'] ?? null;
        if ($data['cash_flow_category'] === '') {
            $data['cash_flow_category'] = null;
        }

        return $data;
    }

    /**
     * Install (or ensure) the category groups and base accounts.
     */
    public function installGroupsAndAccounts(int $instituteId, ?int $branchId = null, ?int $createdBy = null): void
    {
        $groups = $this->ensureGroups($instituteId, $branchId, $createdBy);

        foreach (self::template() as $row) {
            [$code, $name, $type] = $row;
            $flags = $row[3] ?? [];
            $category = $groups[$type];

            $account = ChartOfAccount::query()->firstOrNew([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'code' => $code,
            ]);
            $account->forceFill(array_merge([
                'account_group_id' => $category->id,
                'name' => $name,
                'type' => $type,
                'is_system' => true,
                'is_active' => true,
                'created_by' => $createdBy,
            ], $flags ?? []))->save();
        }
    }

    /**
     * Ensure the five category groups exist and return them keyed by category.
     *
     * @return array<string, AccountGroup>
     */
    public function ensureGroups(int $instituteId, ?int $branchId = null, ?int $createdBy = null): array
    {
        $groups = [];

        foreach (self::CATEGORIES as $category => $meta) {
            $group = AccountGroup::query()->firstOrNew([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'code' => $meta['code'],
            ]);
            $group->forceFill([
                'name' => $meta['name'],
                'category' => $category,
                'is_system' => true,
                'sort_order' => $meta['sort'],
                'created_by' => $createdBy,
            ])->save();

            $groups[$category] = $group;
        }

        return $groups;
    }

    /**
     * Create a tenant-owned account (Hybrid COA write isolation).
     * Globals stay read-only; ownership is forced to the tenant.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTenantAccount(int $instituteId, array $data): ChartOfAccount
    {
        // Force tenant ownership — ignore any incoming institute_id
        $data['institute_id'] = $instituteId;
        $data['is_system'] = false;

        // Validate branch (must belong to tenant)
        if (! empty($data['branch_id'])) {
            $branch = Branch::where('id', $data['branch_id'])
                ->where('institute_id', $instituteId)
                ->first();
            if ($branch === null) {
                throw ValidationException::withMessages([
                    'branch_id' => 'The selected branch does not belong to this institute.',
                ]);
            }
        }

        // Validate parent (must be global OR own tenant's)
        $this->assertValidParent(null, $data['parent_id'] ?? null, (string) ($data['type'] ?? ''), $instituteId);

        // Validate code uniqueness within visible namespace
        $exists = ChartOfAccount::visible($instituteId)
            ->where('code', $data['code'])
            ->exists();
        if ($exists) {
            throw new \InvalidArgumentException(
                "Account code {$data['code']} already exists."
            );
        }

        // Resolve group: explicit must be visible; otherwise prefer the
        // tenant's own group, falling back to the matching global group.
        if (! empty($data['account_group_id'])) {
            $group = AccountGroup::query()
                ->where('id', $data['account_group_id'])
                ->where(function ($q) use ($instituteId) {
                    $q->where(function ($g) {
                        $g->whereNull('institute_id')->where('is_system', 1);
                    })->orWhere('institute_id', $instituteId);
                })
                ->first();
            if ($group === null) {
                throw new \InvalidArgumentException(
                    'The selected group does not belong to this institute.'
                );
            }
            $data['account_group_id'] = $group->id;
        } else {
            $data['account_group_id'] = AccountGroup::query()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $data['branch_id'] ?? null)
                ->where('category', $data['type'])
                ->value('id')
                ?? AccountGroup::query()
                    ->whereNull('institute_id')
                    ->where('is_system', 1)
                    ->where('category', $data['type'])
                    ->value('id');
        }

        $account = ChartOfAccount::create($data);

        app(AccountingAuditService::class)->log($instituteId, [
            'branch_id' => $account->branch_id,
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'create',
            'entity_type' => 'chart_of_account',
            'entity_id' => $account->id,
            'after_payload' => ['code' => $account->code, 'name' => $account->name],
        ]);

        return $account;
    }

    /**
     * Update a tenant-owned account. Globals and other tenants' rows
     * throw AuthorizationException (HTTP 403).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTenantAccount(int $instituteId, int $accountId, array $data): ChartOfAccount
    {
        // Bypass scope to see if it exists at all
        $account = ChartOfAccount::withoutGlobalScope('institute')->findOrFail($accountId);

        // SECURITY: only own tenant's custom rows
        if (! $account->isEditableBy($instituteId)) {
            throw new AuthorizationException(
                'You can only modify your own custom accounts.'
            );
        }

        // Prevent changing ownership
        unset($data['institute_id'], $data['is_system'], $data['branch_id']);

        // Same parent checks as the create path: tenant-or-global ownership,
        // no self-reference, no cycle, max depth 2, same type.
        if (array_key_exists('parent_id', $data)) {
            $this->assertValidParent(
                $account,
                $data['parent_id'],
                (string) ($data['type'] ?? $account->type),
                $instituteId,
            );
        }

        // The type is structural: once the account carries journal entries it
        // is frozen, and we answer with a validation error rather than a throw.
        if (
            array_key_exists('type', $data)
            && $data['type'] !== $account->type
            && JournalEntry::query()->where('coa_id', $account->id)->exists()
        ) {
            throw ValidationException::withMessages([
                'type' => 'The account type cannot be changed because journal entries already reference this account.',
            ]);
        }

        // Validate code uniqueness if changing
        if (isset($data['code']) && $data['code'] !== $account->code) {
            $exists = ChartOfAccount::visible($instituteId)
                ->where('code', $data['code'])
                ->where('id', '!=', $account->id)
                ->exists();
            if ($exists) {
                throw new \InvalidArgumentException(
                    "Account code {$data['code']} already exists."
                );
            }
        }

        $before = ['code' => $account->code, 'name' => $account->name];

        $account->update($data);

        $account = $account->fresh();

        app(AccountingAuditService::class)->log($instituteId, [
            'branch_id' => $account->branch_id,
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'update',
            'entity_type' => 'chart_of_account',
            'entity_id' => $account->id,
            'before_payload' => $before,
            'after_payload' => ['code' => $account->code, 'name' => $account->name],
        ]);

        return $account;
    }

    /**
     * Parent checks shared by create and update so the two paths cannot drift:
     * tenant-or-global ownership (soft-deleted rows excluded), no self-parent,
     * no cycle, maximum depth of 2, and a parent of the same account type.
     *
     * @throws ValidationException|InvalidArgumentException
     */
    private function assertValidParent(?ChartOfAccount $account, mixed $parentId, string $type, ?int $instituteId): void
    {
        if ($parentId === null || $parentId === '' || (int) $parentId === 0) {
            return;
        }

        $parent = ChartOfAccount::withoutGlobalScopes()
            ->where('id', (int) $parentId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($instituteId) {
                $q->where(function ($g) {
                    $g->whereNull('institute_id')->where('is_system', 1);
                })->orWhere('institute_id', $instituteId);
            })
            ->first();

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => 'The parent account does not belong to this institute.',
            ]);
        }

        if ($account !== null) {
            if ($parent->id === $account->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'An account cannot be its own parent.',
                ]);
            }

            // Walking up from the candidate parent must never reach the account
            // being moved: that would close a loop in the tree.
            $cursor = $parent->parent_id;
            $depth = 0;
            while ($cursor !== null && $depth < 3) {
                if ((int) $cursor === $account->id) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'The parent account cannot be a descendant of this account.',
                    ]);
                }
                $cursor = ChartOfAccount::withoutGlobalScopes()
                    ->where('id', (int) $cursor)
                    ->value('parent_id');
                $depth++;
            }
        }

        // Enforce max 2 levels (parent cannot have a parent)
        if ($parent->parent_id !== null) {
            throw new \InvalidArgumentException(
                'Maximum sub-account depth is 2 levels.'
            );
        }

        if ($type !== '' && $parent->type !== $type) {
            throw ValidationException::withMessages([
                'parent_id' => 'The parent account must be of the same type.',
            ]);
        }
    }

    /**
     * Delete a tenant-owned account. Globals, other tenants' rows,
     * accounts with sub-accounts or journal entries are protected.
     */
    public function deleteTenantAccount(int $instituteId, int $accountId): void
    {
        $account = ChartOfAccount::withoutGlobalScope('institute')->findOrFail($accountId);

        if (! $account->isEditableBy($instituteId)) {
            throw new AuthorizationException(
                'You can only delete your own custom accounts.'
            );
        }

        if ($account->children()->exists()) {
            throw new \InvalidArgumentException(
                'Cannot delete account with sub-accounts.'
            );
        }

        if ($this->isAccountInUse($accountId)) {
            throw new \InvalidArgumentException(
                'Cannot delete account with journal entries.'
            );
        }

        $account->delete();

        app(AccountingAuditService::class)->log($instituteId, [
            'branch_id' => $account->branch_id,
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'delete',
            'entity_type' => 'chart_of_account',
            'entity_id' => $account->id,
            'before_payload' => ['code' => $account->code, 'name' => $account->name],
            'after_payload' => null,
        ]);
    }

    protected function isAccountInUse(int $accountId): bool
    {
        return JournalEntry::query()
            ->where('coa_id', $accountId)
            ->exists();
    }

    /**
     * Find an installed account by code within an institute.
     */
    public function accountByCode(int $instituteId, string $code, ?int $branchId = null): ?ChartOfAccount
    {
        return ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('code', $code)
            ->first();
    }
}
