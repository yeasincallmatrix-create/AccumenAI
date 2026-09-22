<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Requests\StoreTenantAccountRequest;
use App\Http\Requests\UpdateTenantAccountRequest;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Services\Accounting\ChartOfAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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

        $query = ChartOfAccount::query()
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

        $accounts = $query
            ->orderByRaw('CAST(SUBSTRING_INDEX(code, ".", 1) AS UNSIGNED)')
            ->orderByRaw('CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(code, ".", 2), ".", -1) AS UNSIGNED)')
            ->orderByRaw('CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(code, ".", 3), ".", -1) AS UNSIGNED)')
            ->orderByRaw('CAST(SUBSTRING_INDEX(code, ".", -1) AS UNSIGNED)')
            ->paginate(25)->withQueryString();

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

        return view('institute.finance.chart-of-accounts.form', [
            'institute' => $institute,
            'account' => $account,
            'groups' => $this->groups($institute->id),
            'parents' => $this->parents($institute->id),
            'types' => ['asset', 'liability', 'equity', 'income', 'expense'],
        ]);
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
        return ChartOfAccount::query()
            ->where(function ($q) use ($instituteId) {
                $q->where(function ($g) {
                    $g->whereNull('institute_id')->where('is_system', 1);
                })->orWhere('institute_id', $instituteId);
            })
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);
    }
}


