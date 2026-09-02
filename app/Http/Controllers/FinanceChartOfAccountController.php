<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Services\Accounting\ChartOfAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $accounts = $query->orderBy('code')->paginate(25)->withQueryString();

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

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $account = $this->service->createAccount(
            $institute->id,
            $this->actingBranchId($request),
            $this->validated($request),
            (int) $this->actorId($request),
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

    public function update(Request $request, ChartOfAccount $account): RedirectResponse
    {
        $this->requireInstitute($request);

        $account = $this->service->updateAccount(
            $account,
            $this->validated($request),
            (int) $this->actorId($request),
        );

        return redirect()
            ->route('finance.chart-of-accounts.index')
            ->with('status', 'Account "'.$account->code.'" updated.');
    }

    public function toggle(Request $request, ChartOfAccount $account): RedirectResponse
    {
        $this->requireInstitute($request);

        $account = $this->service->toggleActive($account, (int) $this->actorId($request));

        return back()->with('status', 'Account "'.$account->code.'" '.($account->is_active ? 'activated' : 'deactivated').'.');
    }

    public function destroy(Request $request, ChartOfAccount $account): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->service->delete($account, (int) $this->actorId($request));

        return redirect()
            ->route('finance.chart-of-accounts.index')
            ->with('status', 'Account "'.$account->code.'" deleted.');
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
            ->where('institute_id', $instituteId)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'category', 'code']);
    }

    private function parents(int $instituteId): Collection
    {
        return ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);
    }
}
