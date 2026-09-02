<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STEP 12 — Fiscal-year end closing (/accounting/fiscal-years).
 *
 * Thin controller over AccountingPeriodService. Closing a year posts the
 * closing journal (P&L swept to Retained Earnings), locks every period, closes
 * the year and carries balance-sheet balances into the next fiscal year.
 * Routes are gated by the existing settings.accounting.manage permission.
 */
class FiscalYearController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly AccountingPeriodService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $fiscalYears = FiscalYear::query()
            ->where('institute_id', $institute->id)
            ->withCount('periods')
            ->orderByDesc('start_date')
            ->paginate(10);

        $fiscalYears->getCollection()->transform(function (FiscalYear $year) use ($institute) {
            $year->net_income = $this->service->fiscalYearNetIncome($year, $institute->id, $year->branch_id);

            return $year;
        });

        return view('institute.accounting.fiscal-years.index', [
            'institute' => $institute,
            'fiscalYears' => $fiscalYears,
        ]);
    }

    public function close(Request $request, FiscalYear $year): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $result = $this->service->closeFiscalYear($year, $institute->id, $this->actorId($request));

        $status = sprintf(
            'Fiscal year "%s" closed. Net income %s. Closing journal %s posted and %d opening balance(s) carried forward.',
            $year->name,
            number_format($result['net_income'], 2),
            $result['closing_journal']->journal_no,
            $result['carried_forward'],
        );

        return back()->with('status', $status);
    }

    public function reopen(Request $request, FiscalYear $year): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->reopenFiscalYear($year, $institute->id, $this->actorId($request));

        return back()->with('status', 'Fiscal year "'.$year->name.'" reopened; its periods are open for postings again.');
    }
}
