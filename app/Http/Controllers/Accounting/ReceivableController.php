<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Services\Accounting\ReceivableService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STEP 79 — Accounts Receivable UI Controller.
 */
class ReceivableController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly ReceivableService $receivableSvc,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $asOf = $request->query('as_of_date') ?: now()->toDateString();

        $report = $this->receivableSvc->receivablesAging($institute->id, $branchId, $asOf);

        return view('institute.accounting.receivables.index', [
            'institute' => $institute,
            'customers' => $report['customers'],
            'totals' => $report['totals'],
            'asOf' => $asOf,
        ]);
    }

    public function statement(Request $request, int $partyId): View
    {
        $institute = $this->requireInstitute($request);
        $asOf = $request->query('as_of_date') ?: now()->toDateString();

        $party = Party::where('institute_id', $institute->id)->where('id', $partyId)->firstOrFail();

        $statement = $this->receivableSvc->customerStatement($institute->id, $partyId, $asOf);

        return view('institute.accounting.receivables.statement', [
            'institute' => $institute,
            'party' => $party,
            'statement' => $statement,
            'asOf' => $asOf,
        ]);
    }

    public function aging(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $asOf = $request->query('as_of_date') ?: now()->toDateString();

        $report = $this->receivableSvc->receivablesAging($institute->id, $branchId, $asOf);

        return view('institute.accounting.receivables.aging', [
            'institute' => $institute,
            'customers' => $report['customers'],
            'totals' => $report['totals'],
            'asOf' => $asOf,
        ]);
    }
}
