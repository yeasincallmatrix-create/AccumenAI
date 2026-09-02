<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\OpeningBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Opening balances (Step 9): set the opening position per account at the start
 * of a fiscal year. Entries are validated (balanced, tenant/branch-scoped) and
 * upserted via OpeningBalanceService; reports aggregate them automatically.
 */
class FinanceOpeningBalanceController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly OpeningBalanceService $service,
        private readonly AccountingPeriodService $periods,
    ) {}

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $fiscalYears = FiscalYear::query()
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($query) => $query->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'start_date', 'end_date', 'status', 'is_current']);

        $selectedYear = null;
        $accounts = new Collection;

        if ($fiscalYears->isNotEmpty()) {
            $current = $this->periods->current($institute->id, $branchId);
            $defaultYear = $current['year'] ?? $fiscalYears->first();
            $selectedYearId = $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : (int) $defaultYear->id;
            $selectedYear = $fiscalYears->firstWhere('id', $selectedYearId) ?? $fiscalYears->first();
            $accounts = $this->service->accountsWithBalances($institute->id, $branchId, $selectedYear);
        }

        return view('institute.finance.opening-balances.form', [
            'institute' => $institute,
            'fiscalYears' => $fiscalYears,
            'selectedYear' => $selectedYear,
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $year = FiscalYear::query()
            ->where('institute_id', $institute->id)
            ->where('id', (int) $request->input('fiscal_year_id'))
            ->when($branchId !== null, fn ($query) => $query->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->first();

        if ($year === null) {
            return back()->with('error', 'The selected fiscal year does not belong to this institute.');
        }

        $entries = [];
        foreach ((array) $request->input('entries', []) as $coaId => $amounts) {
            $entries[] = [
                'coa_id' => (int) $coaId,
                'debit' => (float) ($amounts['debit'] ?? 0),
                'credit' => (float) ($amounts['credit'] ?? 0),
            ];
        }

        $this->service->upsert($institute->id, $branchId, $year, $entries, $this->actorId($request));

        return redirect()
            ->route('finance.opening-balances.create', ['fiscal_year_id' => $year->id])
            ->with('status', 'Opening balances for "'.$year->name.'" saved.');
    }
}
