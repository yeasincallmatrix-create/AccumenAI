<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Requests\StoreTenantAccountRequest;
use App\Http\Requests\UpdateTenantAccountRequest;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Models\OpeningBalance;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\OpeningBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Chart of Accounts (Step 32): browse, create, update, activate/deactivate and
 * delete accounts. Account codes are unique per (institute, branch).
 */
class FinanceChartOfAccountController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly ChartOfAccountService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        // visible(): only universal + this tenant's industry globals (Phase F).
        $query = ChartOfAccount::visible((int) $institute->id)
            ->with('parent');

        if (filled($q = $request->query('q'))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        if (filled($request->query('type'))) {
            $query->where('type', $request->query('type'));
        }

        if (filled($request->query('status'))) {
            $query->where('is_active', $request->query('status') === 'active');
        }

        $accounts = $query->paginate(25)->withQueryString();

        $accounts->getCollection()->each(function ($a) use ($institute) {
            $a->is_editable = $a->isEditableBy((int) $institute->id);
            $a->is_global_flag = $a->isGlobal();
        });

        return view('institute.finance.chart-of-accounts.index', [
            'institute' => $institute,
            'accounts' => $accounts,
            'types' => ['asset', 'liability', 'equity', 'income', 'expense'],
            'filters' => $request->query(),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.chart-of-accounts.form', [
            'institute' => $institute,
            'account' => null,
            'groups' => $this->groups($institute->id),
            'parents' => $this->parents($institute->id),
            'types' => ['asset', 'liability', 'equity', 'income', 'expense'],
        ]);
    }

    public function store(StoreTenantAccountRequest $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $account = $this->service->createTenantAccount(
            (int) $institute->id,
            array_merge(
                $request->validated(),
                ['branch_id' => $this->actingBranchId($request)],
            ),
        );

        return redirect()
            ->route('finance.chart-of-accounts.index')
            ->with('status', 'Account "'.$account->code.' '.$account->name.'" created.');
    }

    public function edit(Request $request, ChartOfAccount $account): View
    {
        $institute = $this->requireInstitute($request);

        // Every visible row is openable: own rows are fully editable, shared
        // globals only expose this institute's own settings (opening balance).
        $editable = $account->isEditableBy((int) $institute->id);

        return view('institute.finance.chart-of-accounts.form', [
            'institute' => $institute,
            'account' => $account,
            'editable' => $editable,
            'opening' => $this->openingState($request, $institute, $account),
            'groups' => $this->groups($institute->id),
            'parents' => $this->parents($institute->id),
            'types' => ['asset', 'liability', 'equity', 'income', 'expense'],
        ]);
    }

    /**
     * Tenant-scoped opening balance for one account: opening_balances rows are
     * per-institute, so they stay editable even when the account definition
     * itself belongs to a shared global row.
     *
     * @return array{balance: float|null, date: string, year: string|null}|null
     */
    private function openingState(Request $request, Institute $institute, ChartOfAccount $account): ?array
    {
        if (! in_array($account->type, ['asset', 'liability', 'equity'], true)) {
            return null;
        }

        $branchId = $this->actingBranchId($request);
        $existing = OpeningBalance::query()
            ->where('institute_id', (int) $institute->id)
            ->where('coa_id', $account->id)
            ->when($branchId === null, fn ($query) => $query->whereNull('branch_id'), fn ($query) => $query->where('branch_id', $branchId))
            ->with('fiscalYear')
            ->get()
            ->sortByDesc(fn ($row) => $row->fiscalYear?->start_date?->getTimestamp() ?? 0)
            ->first();

        return [
            'balance' => $existing === null
                ? null
                : round((float) ($existing->debit > 0 ? $existing->debit : $existing->credit), 2),
            'date' => $existing?->fiscalYear?->start_date?->toDateString() ?? now()->toDateString(),
            'year' => $existing?->fiscalYear?->name,
        ];
    }

    /**
     * Save the tenant's opening position for this account (works for shared
     * global accounts too — the row referenced is tenant data).
     */
    public function updateOpening(Request $request, ChartOfAccount $account): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        Gate::authorize('view', $account);

        if (! in_array($account->type, ['asset', 'liability', 'equity'], true)) {
            return back()->with('error', 'Opening balances apply to balance sheet accounts (asset, liability, equity) only.');
        }

        $validated = $request->validate([
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_date' => ['required', 'date'],
        ]);

        $branchId = $this->actingBranchId($request);
        $actorId = $this->actorId($request);
        $amount = round((float) ($validated['opening_balance'] ?? 0), 4);
        $date = $validated['opening_balance_date'];

        // A ValidationException from inside the transaction rolls the write
        // back and surfaces as a form error (same contract as store()).
        $cleared = DB::transaction(function () use ($institute, $branchId, $actorId, $account, $amount, $date) {
            $year = app(AccountingPeriodService::class)->covering((int) $institute->id, $branchId, $date)['year'];

            if ($amount > 0.0) {
                if ($year === null) {
                    throw ValidationException::withMessages([
                        'opening_balance_date' => 'No fiscal year covers this date. Create a fiscal year first (Finance > Periods).',
                    ]);
                }

                if ($year->status !== 'open') {
                    throw ValidationException::withMessages([
                        'opening_balance_date' => 'The fiscal year covering this date is '.$year->status.'. Pick a date in an open fiscal year.',
                    ]);
                }

                $isDebit = in_array($account->type, ['asset', 'expense'], true);

                app(OpeningBalanceService::class)->upsertSingle(
                    (int) $institute->id,
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
                (int) $institute->id,
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

        return redirect()
            ->route('finance.chart-of-accounts.edit', $account)
            ->with('status', $message);
    }

    public function update(UpdateTenantAccountRequest $request, int $chartOfAccount): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $account = ChartOfAccount::withoutGlobalScope('institute')
            ->findOrFail($chartOfAccount);

        Gate::authorize('update', $account);

        $account = $this->service->updateTenantAccount(
            (int) $institute->id,
            $account->id,
            $request->validated(),
        );

        return redirect()
            ->route('finance.chart-of-accounts.index')
            ->with('status', 'Account "'.$account->code.'" updated.');
    }

    public function toggle(Request $request, ChartOfAccount $account): RedirectResponse
    {
        $this->requireInstitute($request);

        Gate::authorize('update', $account);

        $account = $this->service->toggleActive($account, (int) $this->actorId($request));

        return back()->with('status', 'Account "'.$account->code.'" '.($account->is_active ? 'activated' : 'deactivated').'.');
    }

    public function destroy(Request $request, int $chartOfAccount): RedirectResponse
    {
        $this->requireInstitute($request);

        $account = ChartOfAccount::withoutGlobalScope('institute')
            ->findOrFail($chartOfAccount);

        Gate::authorize('delete', $account);

        $code = $account->code;
        $this->service->deleteTenantAccount(
            (int) tenant_id(),
            $account->id,
        );

        return redirect()
            ->route('finance.chart-of-accounts.index')
            ->with('status', 'Account "'.$code.'" deleted.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'account_group_id' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'integer'],
            'is_cash' => ['nullable', 'boolean'],
            'is_bank' => ['nullable', 'boolean'],
            'is_receivable' => ['nullable', 'boolean'],
            'is_payable' => ['nullable', 'boolean'],
            'cash_flow_category' => ['nullable', Rule::in(['operating', 'investing', 'financing'])],
        ]);

        if (array_key_exists('cash_flow_category', $data) && $data['cash_flow_category'] === '') {
            $data['cash_flow_category'] = null;
        }

        return $data;
    }

    private function groups(int $instituteId): Collection
    {
        return AccountGroup::query()
            ->where(function ($q) use ($instituteId) {
                $q->where(function ($g) {
                    $g->whereNull('institute_id')->where('is_system', 1);
                })->orWhere('institute_id', $instituteId);
            })
            ->orderBy('sort_order')
            ->get(['id', 'name', 'category', 'code']);
    }

    private function parents(int $instituteId): Collection
    {
        // Post-C1 (reanchor): only tenant-owned rows may be offered. The
        // shared globals are read-only templates and assertValidParent()
        // rejects them, so showing them would only produce dead options.
        return ChartOfAccount::visible($instituteId)
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->ordered()
            ->get(['id', 'code', 'name', 'type']);
    }
}
