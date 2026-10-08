<?php

namespace App\Livewire;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\OpeningBalance;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\OpeningBalanceService;
use App\Services\ModuleAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChartOfAccountList extends DataTable
{
    use ResolvesInstitute;

    protected const VIEW = 'livewire.chart-of-accounts.list';

    public bool $canManage = false;

    public bool $showCreateModal = false;

    public array $form = [];

    public bool $showOpeningModal = false;

    public int $openingAccountId = 0;

    public array $openingForm = [];

    public function mount(): void
    {
        $request = request();
        $this->search = $request->query('q', '');
        $this->filters = [
            'type' => $request->query('type', ''),
            'status' => $request->query('status', ''),
        ];
        $this->perPage = 25;
        $this->form = $this->blankForm();
        $this->openingForm = $this->blankOpeningForm();

        $user = $request->user();
        if ($user !== null) {
            $institute = $this->resolveInstitute($request);
            $moduleService = app(ModuleAccessService::class);
            $hasPerm = $user->hasPermission('settings.accounting.manage');
            $this->canManage = $institute !== null
                && $moduleService->isEnabled($institute, 'finance')
                && $hasPerm;
        }
    }

    protected function baseQuery(): Builder
    {
        // Fail closed: the institute scope only exists while TenantContext is
        // bound. Never list anything than risk rendering another tenant's rows.
        abort_if(tenant_id() === null, 403, 'Tenant context is required.');

        // visible() adds the Phase-F industry gate: own rows + universal
        // globals + globals tagged for this tenant's industry only, so
        // education/training/medical/retail charts never cross-leak.
        return ChartOfAccount::visible((int) tenant_id())
            ->ordered()
            ->with('parent')
            ->withSum('journalEntries as total_debit', 'debit')
            ->withSum('journalEntries as total_credit', 'credit')
            ->withSum('openingBalances as opening_debit', 'debit')
            ->withSum('openingBalances as opening_credit', 'credit');
    }

    protected function searchableColumns(): array
    {
        return ['code', 'name'];
    }

    protected function filterableColumns(): array
    {
        return [
            'type' => ['type' => 'exact'],
            'status' => ['type' => 'exact', 'column' => 'is_active'],
        ];
    }

    protected function sortableColumns(): array
    {
        return ['code', 'name', 'type'];
    }

    protected function defaultSort(): ?string
    {
        return 'code';
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        match ($key) {
            'type' => $query->where('type', $value),
            'status' => $query->where('is_active', $value === 'active'),
            default => null,
        };
    }

    // ------------------------------------------------------------ Create popup

    public function openCreateModal(): void
    {
        if (! $this->canManage) {
            return;
        }

        $this->resetValidation();
        $this->form = $this->blankForm();
        $this->form['cash_flow_category'] = $this->deriveCashFlowCategory();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    // Livewire calls this as updatedForm($newValue, $subKey) — value first,
    // then the path after the first dot (e.g. 'type', 'opening_balance').
    public function updatedForm(mixed $value, ?string $key): void
    {
        // Group and parent options are type-scoped: switching the type must
        // not leave a stale selection behind.
        if ($key === 'type') {
            $this->form['account_group_id'] = null;
            $this->form['parent_id'] = null;
        }

        // The cash-flow category is read-only in this popup: it follows the
        // account type (and the Cash/Bank switches, which null it out). Any
        // direct write — even programmatic — snaps back to the derived value.
        if (in_array($key, ['type', 'is_cash', 'is_bank', 'cash_flow_category'], true)) {
            $this->form['cash_flow_category'] = $this->deriveCashFlowCategory();
        }

        // Livewire sends cleared inputs as empty strings, which the nullable
        // numeric/date rules would reject as non-null garbage.
        if (in_array($key, ['opening_balance', 'opening_balance_date'], true) && $value === '') {
            $this->form[$key] = null;
        }
    }

    public function store(): void
    {
        if (! $this->canManage) {
            session()->flash('error', 'Unauthorized action.');
            $this->showCreateModal = false;

            return;
        }

        $this->authorize('create', ChartOfAccount::class);

        foreach (['opening_balance', 'opening_balance_date'] as $field) {
            if (($this->form[$field] ?? null) === '') {
                $this->form[$field] = null;
            }
        }

        // Real-life rule: only balance sheet accounts (asset, liability,
        // equity — cash, bank, payables, capital ...) carry an opening
        // position; P&L accounts start fresh each period. The fields are
        // hidden for those types, and a stale or posted amount is stripped
        // before validation so nothing sneaks through.
        if (! in_array($this->form['type'] ?? null, ['asset', 'liability', 'equity'], true)) {
            $this->form['opening_balance'] = null;
        }

        $data = $this->validate($this->createRules(), [
            'form.code.regex' => 'Code must be 1-4 digits optionally followed by dot-separated subcodes (e.g., 1, 1000, 1000.1, 1000.1.1). Hyphen is NOT allowed — use dots.',
        ])['form'];

        $openingAmount = round((float) ($data['opening_balance'] ?? 0), 4);
        $openingDate = $data['opening_balance_date'] ?? now()->toDateString();
        unset($data['opening_balance'], $data['opening_balance_date']);

        // Read-only in the popup: never trust a posted value, always derive.
        $data['cash_flow_category'] = $this->deriveCashFlowCategory();

        $branchId = $this->actingBranchId(request());
        $actorId = $this->actorId(request());

        try {
            $account = DB::transaction(function () use ($data, $branchId, $actorId, $openingAmount, $openingDate) {
                $account = app(ChartOfAccountService::class)->createTenantAccount(
                    (int) tenant_id(),
                    array_merge($data, ['branch_id' => $branchId]),
                );

                if ($openingAmount > 0.0) {
                    $this->writeOpeningBalance($account, $openingAmount, $openingDate, $branchId, $actorId);
                }

                return $account;
            });
        } catch (\InvalidArgumentException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        session()->flash('status', 'Account "'.$account->code.' '.$account->name.'" created.');

        $this->showCreateModal = false;
        $this->form = $this->blankForm();
        $this->resetValidation();
        $this->resetPage();
    }

    /**
     * Side is the account's normal balance: debit for asset/expense, credit
     * for liability/equity/income. Runs inside store()'s DB transaction, so a
     * fiscal-year failure rolls the account creation back too.
     */
    private function writeOpeningBalance(ChartOfAccount $account, float $amount, string $date, ?int $branchId, ?int $actorId): void
    {
        $covered = app(AccountingPeriodService::class)->covering((int) tenant_id(), $branchId, $date);
        $year = $covered['year'];

        if ($year === null) {
            throw ValidationException::withMessages([
                'form.opening_balance_date' => 'No fiscal year covers this date. Create a fiscal year first (Finance > Periods).',
            ]);
        }

        if ($year->status !== 'open') {
            throw ValidationException::withMessages([
                'form.opening_balance_date' => 'The fiscal year covering this date is '.$year->status.'. Pick a date in an open fiscal year.',
            ]);
        }

        $isDebit = in_array($account->type, ['asset', 'expense'], true);

        app(OpeningBalanceService::class)->upsertSingle(
            (int) tenant_id(),
            $branchId,
            $year,
            (int) $account->id,
            $isDebit ? $amount : 0.0,
            $isDebit ? 0.0 : $amount,
            $actorId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createRules(): array
    {
        $instituteId = (int) tenant_id();
        $slug = ChartOfAccount::resolveIndustrySlug($instituteId);

        return [
            'form.code' => [
                'required',
                'string',
                'max:30',
                'regex:/^\d{1,4}(\.\d+){0,3}$/',
                // Unique within this tenant's visible namespace only: own rows
                // + universal globals + globals tagged for this industry.
                // Another industry's global code is not a conflict here.
                Rule::unique('chart_of_accounts', 'code')->where(function ($q) use ($instituteId, $slug) {
                    $q->where('institute_id', $instituteId)
                        ->orWhere(function ($g) use ($slug) {
                            $g->whereNull('institute_id')->where('is_system', 1);
                            if (ChartOfAccount::industriesTaggingAvailable()) {
                                $g->where(function ($i) use ($slug) {
                                    $i->whereNull('industries');
                                    if ($slug !== null) {
                                        $i->orWhereJsonContains('industries', $slug);
                                    }
                                });
                            }
                        });
                }),
            ],
            'form.name' => ['required', 'string', 'max:150'],
            'form.type' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'form.account_group_id' => ['nullable', 'integer', 'exists:account_groups,id'],
            'form.parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'form.is_cash' => ['boolean'],
            'form.is_bank' => ['boolean'],
            'form.is_receivable' => ['boolean'],
            'form.is_payable' => ['boolean'],
            'form.opening_balance' => ['nullable', 'numeric', 'min:0'],
            'form.opening_balance_date' => ['nullable', 'date', 'required_with:form.opening_balance'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        return [
            'code' => '',
            'name' => '',
            'type' => 'asset',
            'account_group_id' => null,
            'parent_id' => null,
            'cash_flow_category' => null,
            'is_cash' => false,
            'is_bank' => false,
            'is_receivable' => false,
            'is_payable' => false,
            'opening_balance' => null,
            'opening_balance_date' => now()->toDateString(),
        ];
    }

    /**
     * Cash-flow classification shown read-only in the popup: null for
     * cash/bank accounts (they are the cash side, not the counterpart),
     * financing for equity, operating for every other type — matching the
     * seeded chart's per-type default. Loans/PP&E-style exceptions can be
     * reclassified later on the account edit page.
     */
    private function deriveCashFlowCategory(): ?string
    {
        if (! empty($this->form['is_cash']) || ! empty($this->form['is_bank'])) {
            return null;
        }

        return match ($this->form['type'] ?? null) {
            'equity' => 'financing',
            'asset', 'liability', 'income', 'expense' => 'operating',
            default => null,
        };
    }

    // -------------------------------------------------- Opening balance popup

    /**
     * Open the Edit Opening Balance popup for one row. Works for global
     * accounts too: the account definition is shared and read-only, but the
     * opening balance itself is tenant data.
     */
    public function openOpeningModal(int $accountId): void
    {
        if (! $this->canManage) {
            return;
        }

        $this->resetValidation();

        // Same industry gate as the list: a cross-industry global or another
        // tenant's account must not even resolve (404).
        $account = ChartOfAccount::visible((int) tenant_id())->findOrFail($accountId);

        // Only balance sheet accounts (asset, liability, equity) carry an
        // opening position — P&L accounts start fresh each period.
        if (! in_array($account->type, ['asset', 'liability', 'equity'], true)) {
            session()->flash('error', 'Opening balances apply to balance sheet accounts (asset, liability, equity) only.');

            return;
        }

        $branchId = $this->actingBranchId(request());
        $existing = OpeningBalance::query()
            ->where('institute_id', (int) tenant_id())
            ->where('coa_id', $account->id)
            ->when($branchId === null, fn ($query) => $query->whereNull('branch_id'), fn ($query) => $query->where('branch_id', $branchId))
            ->with('fiscalYear')
            ->get()
            ->sortByDesc(fn ($row) => $row->fiscalYear?->start_date?->getTimestamp() ?? 0)
            ->first();

        $this->openingAccountId = (int) $account->id;
        $this->openingForm = [
            'label' => $account->code.' — '.$account->name,
            'balance' => $existing === null
                ? null
                : round((float) ($existing->debit > 0 ? $existing->debit : $existing->credit), 2),
            'date' => $existing?->fiscalYear?->start_date?->toDateString() ?? now()->toDateString(),
        ];
        $this->showOpeningModal = true;
    }

    public function closeOpeningModal(): void
    {
        $this->showOpeningModal = false;
        $this->resetValidation();
    }

    // Livewire calls this as updatedOpeningForm($newValue, $subKey) — value
    // first, then the path after the first dot ('balance' or 'date').
    public function updatedOpeningForm(mixed $value, ?string $key): void
    {
        // Livewire sends cleared inputs as empty strings, which the nullable
        // numeric/date rules would reject as non-null garbage.
        if (in_array($key, ['balance', 'date'], true) && $value === '') {
            $this->openingForm[$key] = null;
        }
    }

    public function storeOpening(): void
    {
        if (! $this->canManage) {
            session()->flash('error', 'Unauthorized action.');
            $this->showOpeningModal = false;

            return;
        }

        foreach (['balance', 'date'] as $field) {
            if (($this->openingForm[$field] ?? null) === '') {
                $this->openingForm[$field] = null;
            }
        }

        $validated = $this->validate([
            'openingForm.balance' => ['nullable', 'numeric', 'min:0'],
            'openingForm.date' => ['required', 'date'],
        ]);

        $amount = round((float) ($validated['openingForm']['balance'] ?? 0), 4);
        $date = $validated['openingForm']['date'];

        $account = ChartOfAccount::visible((int) tenant_id())->findOrFail($this->openingAccountId);

        // Same real-life rule as the popup: never write an opening balance
        // to a P&L account, even via a direct action call.
        if (! in_array($account->type, ['asset', 'liability', 'equity'], true)) {
            session()->flash('error', 'Opening balances apply to balance sheet accounts (asset, liability, equity) only.');
            $this->showOpeningModal = false;

            return;
        }

        $branchId = $this->actingBranchId(request());
        $actorId = $this->actorId(request());

        // A ValidationException from inside the transaction rolls the write
        // back and surfaces as a modal error (same contract as store()).
        $cleared = DB::transaction(function () use ($account, $amount, $date, $branchId, $actorId) {
            $year = app(AccountingPeriodService::class)->covering((int) tenant_id(), $branchId, $date)['year'];

            if ($amount > 0.0) {
                if ($year === null) {
                    throw ValidationException::withMessages([
                        'openingForm.date' => 'No fiscal year covers this date. Create a fiscal year first (Finance > Periods).',
                    ]);
                }

                if ($year->status !== 'open') {
                    throw ValidationException::withMessages([
                        'openingForm.date' => 'The fiscal year covering this date is '.$year->status.'. Pick a date in an open fiscal year.',
                    ]);
                }

                // Side follows the account's normal balance, like the popup.
                $isDebit = in_array($account->type, ['asset', 'expense'], true);

                app(OpeningBalanceService::class)->upsertSingle(
                    (int) tenant_id(),
                    $branchId,
                    $year,
                    (int) $account->id,
                    $isDebit ? $amount : 0.0,
                    $isDebit ? 0.0 : $amount,
                    $actorId,
                );

                return false;
            }

            // Blank amount clears the stored row for the date's fiscal year.
            if ($year === null) {
                return false;
            }

            return app(OpeningBalanceService::class)->removeSingle(
                (int) tenant_id(),
                $branchId,
                $year,
                (int) $account->id,
                $actorId,
            );
        });

        $label = $account->code.' '.$account->name;
        $message = $amount > 0.0
            ? 'Opening balance updated for "'.$label.'".'
            : ($cleared ? 'Opening balance cleared for "'.$label.'".' : 'Opening balance saved for "'.$label.'".');

        session()->flash('status', $message);
        $this->showOpeningModal = false;
        $this->resetValidation();
    }

    /**
     * @return array{balance: float|null, date: string, label: string}
     */
    private function blankOpeningForm(): array
    {
        return [
            'balance' => null,
            'date' => now()->toDateString(),
            'label' => '',
        ];
    }

    private function modalGroups()
    {
        $instituteId = (int) tenant_id();

        return AccountGroup::visible($instituteId)
            ->where('category', $this->form['type'] ?? 'asset')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'category', 'code']);
    }

    private function modalParents()
    {
        $instituteId = (int) tenant_id();

        // Post-C1 (reanchor): tenant-owned rows only — globals are read-only
        // templates that assertValidParent() rejects, so offering them would
        // only produce dead options. (visible() keeps the industry gate for
        // the tenant rows themselves.)
        return ChartOfAccount::visible($instituteId)
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->where('type', $this->form['type'] ?? 'asset')
            ->ordered()
            ->get(['id', 'code', 'name', 'type']);
    }

    public function toggle(int $accountId): void
    {
        if (! $this->canManage) {
            session()->flash('error', 'Unauthorized action.');

            return;
        }

        $user = auth()->user();
        // Own-tenant rows only: a foreign or global id must not even resolve
        // (404), so probes cannot confirm the existence of another tenant's row.
        $account = ChartOfAccount::tenantOnly((int) tenant_id())->findOrFail($accountId);

        try {
            $this->authorize('update', $account);
            app(ChartOfAccountService::class)->toggleActive($account, $user?->id);
            session()->flash('status', 'Account "'.$account->code.'" '.($account->is_active ? 'deactivated' : 'activated').'.');
        } catch (ValidationException $e) {
            session()->flash('error', $e->getMessage());
        } catch (AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function destroy(int $accountId): void
    {
        if (! $this->canManage) {
            session()->flash('error', 'Unauthorized action.');

            return;
        }

        $user = auth()->user();
        $account = ChartOfAccount::tenantOnly((int) tenant_id())->findOrFail($accountId);

        try {
            $this->authorize('delete', $account);
            app(ChartOfAccountService::class)->delete($account, $user?->id);
            session()->flash('status', 'Account "'.$account->code.'" deleted.');
        } catch (ValidationException $e) {
            session()->flash('error', $e->getMessage());
        } catch (AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $accounts = $this->getRows();

        $instituteId = (int) tenant_id();
        $collection = method_exists($accounts, 'getCollection')
            ? $accounts->getCollection()
            : $accounts;
        foreach ($collection as $account) {
            $account->is_global_flag = $account->isGlobal();
            $account->is_editable = $account->isEditableBy($instituteId);
            $account->balance = (($account->opening_debit ?? 0) - ($account->opening_credit ?? 0))
                + (($account->total_debit ?? 0) - ($account->total_credit ?? 0));
        }

        return view(self::VIEW, [
            'accounts' => $accounts,
            'canManage' => $this->canManage,
            'createGroups' => $this->showCreateModal ? $this->modalGroups() : collect(),
            'createParents' => $this->showCreateModal ? $this->modalParents() : collect(),
        ]);
    }
}
