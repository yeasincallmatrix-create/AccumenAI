<?php

namespace App\Services\Accounting;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
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
     * [code, name, type, flags]
     *
     * @var array<int, array{0:string, 1:string, 2:string, 3?:array<string, bool>}>
     */
    public const TEMPLATE = [
        ['1001', 'Cash in Hand', 'asset', ['is_cash' => true]],
        ['1002', 'Bank Account', 'asset', ['is_bank' => true]],
        ['1100', 'Accounts Receivable', 'asset', ['is_receivable' => true, 'cash_flow_category' => 'operating']],
        ['1200', 'Inventory Asset', 'asset', ['cash_flow_category' => 'operating']],
        ['1201', 'Input VAT / Tax Receivable', 'asset', ['cash_flow_category' => 'operating']],
        ['1300', 'Fixed Assets', 'asset', ['cash_flow_category' => 'investing']],
        ['1301', 'Accumulated Depreciation', 'asset', ['cash_flow_category' => 'investing']],
        ['2001', 'Accounts Payable', 'liability', ['is_payable' => true, 'cash_flow_category' => 'operating']],
        ['2002', 'Unearned Revenue', 'liability', ['cash_flow_category' => 'operating']],
        ['2003', 'Loans Payable', 'liability', ['cash_flow_category' => 'financing']],
        ['2100', 'VAT Payable', 'liability', ['cash_flow_category' => 'operating']],
        ['2101', 'Withholding Tax Payable', 'liability', ['cash_flow_category' => 'operating']],
        ['2102', 'Tax Clearing', 'liability', ['cash_flow_category' => 'operating']],
        ['3001', "Owner's Capital", 'equity', ['cash_flow_category' => 'financing']],
        ['3002', 'Retained Earnings', 'equity', ['cash_flow_category' => 'financing']],
        ['3100', 'Revaluation Surplus', 'equity', ['cash_flow_category' => 'financing']],
        ['4001', 'Tuition Fees', 'income', ['cash_flow_category' => 'operating']],
        ['4002', 'Admission Fees', 'income', ['cash_flow_category' => 'operating']],
        ['4003', 'Merchandise Sales', 'income', ['cash_flow_category' => 'operating']],
        ['4004', 'Other Income', 'income', ['cash_flow_category' => 'operating']],
        ['4005', 'Inventory Adjustment Income', 'income', ['cash_flow_category' => 'operating']],
        ['4010', 'Gain on Disposal', 'income', ['cash_flow_category' => 'operating']],
        ['4900', 'Realized FX Gain', 'income', ['cash_flow_category' => 'operating']],
        ['4901', 'Unrealized FX Gain', 'income', ['cash_flow_category' => 'operating']],
        ['5001', 'Salary & Wages', 'expense', ['cash_flow_category' => 'operating']],
        ['5002', 'Rent', 'expense', ['cash_flow_category' => 'operating']],
        ['5003', 'Utilities', 'expense', ['cash_flow_category' => 'operating']],
        ['5004', 'Office Supplies', 'expense', ['cash_flow_category' => 'operating']],
        ['5005', 'Travel', 'expense', ['cash_flow_category' => 'operating']],
        ['5006', 'Miscellaneous Expense', 'expense', ['cash_flow_category' => 'operating']],
        ['5007', 'Cost of Goods Sold', 'expense', ['cash_flow_category' => 'operating']],
        ['5008', 'Inventory Adjustment Expense', 'expense', ['cash_flow_category' => 'operating']],
        ['5009', 'Inventory Wastage', 'expense', ['cash_flow_category' => 'operating']],
        ['5010', 'Depreciation Expense', 'expense', ['cash_flow_category' => 'operating']],
        ['5011', 'Loss on Disposal', 'expense', ['cash_flow_category' => 'operating']],
        ['5012', 'Impairment Expense', 'expense', ['cash_flow_category' => 'operating']],
        ['5900', 'Realized FX Loss', 'expense', ['cash_flow_category' => 'operating']],
        ['5901', 'Unrealized FX Loss', 'expense', ['cash_flow_category' => 'operating']],
    ];

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
            'cash_flow_category' => ['nullable', \Illuminate\Validation\Rule::in(['operating', 'investing', 'financing'])],
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

        foreach (self::TEMPLATE as $row) {
            [$code, $name, $type] = $row;
            $flags = $row[3] ?? [];
            $category = $groups[$type];

            ChartOfAccount::query()->firstOrCreate(
                [
                    'institute_id' => $instituteId,
                    'branch_id' => $branchId,
                    'code' => $code,
                ],
                array_merge([
                    'account_group_id' => $category->id,
                    'name' => $name,
                    'type' => $type,
                    'is_system' => true,
                    'is_active' => true,
                    'created_by' => $createdBy,
                ], $flags ?? []),
            );
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
            $groups[$category] = AccountGroup::query()->firstOrCreate(
                [
                    'institute_id' => $instituteId,
                    'branch_id' => $branchId,
                    'code' => $meta['code'],
                ],
                [
                    'name' => $meta['name'],
                    'category' => $category,
                    'is_system' => true,
                    'sort_order' => $meta['sort'],
                    'created_by' => $createdBy,
                ],
            );
        }

        return $groups;
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
