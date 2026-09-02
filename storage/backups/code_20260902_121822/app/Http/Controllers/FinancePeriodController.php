<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fiscal years & accounting periods (Step 32): list, create fiscal years (with
 * monthly periods), close and reopen accounting periods.
 */
class FinancePeriodController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly AccountingPeriodService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $fiscalYears = FiscalYear::query()
            ->where('institute_id', $institute->id)
            ->with(['periods'])
            ->orderByDesc('start_date')
            ->paginate(10);

        return view('institute.finance.periods.index', [
            'institute' => $institute,
            'fiscalYears' => $fiscalYears,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $year = $this->service->createFiscalYear(
            $institute->id,
            $this->actingBranchId($request),
            $data,
            (int) $this->actorId($request),
        );

        return back()->with('status', 'Fiscal year "'.$year->name.'" created with '.$year->periods->count().' monthly periods.');
    }

    public function closePeriod(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->closePeriod($period, $institute->id, (int) $this->actorId($request));

        return back()->with('status', 'Period "'.$period->name.'" closed.');
    }

    public function reopenPeriod(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->reopenPeriod($period, $institute->id, (int) $this->actorId($request));

        return back()->with('status', 'Period "'.$period->name.'" reopened.');
    }
}
