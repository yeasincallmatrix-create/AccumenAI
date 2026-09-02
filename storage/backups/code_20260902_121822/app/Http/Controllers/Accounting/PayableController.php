<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Services\Accounting\PayableService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STEP 79 — Accounts Payable UI Controller.
 */
class PayableController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly PayableService $payableSvc,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $asOf = $request->query('as_of_date') ?: now()->toDateString();

        $report = $this->payableSvc->payablesAging($institute->id, $branchId, $asOf);

        return view('institute.accounting.payables.index', [
            'institute' => $institute,
            'suppliers' => $report['suppliers'],
            'totals' => $report['totals'],
            'asOf' => $asOf,
        ]);
    }

    public function statement(Request $request, int $partyId): View
    {
        $institute = $this->requireInstitute($request);
        $asOf = $request->query('as_of_date') ?: now()->toDateString();

        $party = Party::where('institute_id', $institute->id)->where('id', $partyId)->firstOrFail();

        $statement = $this->payableSvc->supplierStatement($institute->id, $partyId, $asOf);

        return view('institute.accounting.payables.statement', [
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

        $report = $this->payableSvc->payablesAging($institute->id, $branchId, $asOf);

        return view('institute.accounting.payables.aging', [
            'institute' => $institute,
            'suppliers' => $report['suppliers'],
            'totals' => $report['totals'],
            'asOf' => $asOf,
        ]);
    }
}
