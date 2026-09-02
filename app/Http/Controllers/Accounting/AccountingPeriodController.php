<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STEP 12 — Accounting period lifecycle (/accounting/periods).
 *
 * Thin controller over AccountingPeriodService. Institute identity comes from
 * the authenticated user/workspace, never from request input. Routes are gated
 * by the existing settings.accounting.manage permission (owner/admin/accountant).
 */
class AccountingPeriodController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly AccountingPeriodService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $fiscalYears = FiscalYear::query()
            ->where('institute_id', $institute->id)
            ->with(['periods' => fn ($query) => $query->withCount('journals')])
            ->orderByDesc('start_date')
            ->paginate(10);

        return view('institute.accounting.periods.index', [
            'institute' => $institute,
            'fiscalYears' => $fiscalYears,
        ]);
    }

    public function close(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->closePeriod($period, $institute->id, $this->actorId($request));

        return back()->with('status', 'Period "'.$period->name.'" closed and locked against new postings.');
    }

    public function reopen(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->reopenPeriod($period, $institute->id, $this->actorId($request));

        return back()->with('status', 'Period "'.$period->name.'" reopened for postings.');
    }
}
