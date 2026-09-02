<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Services\Accounting\AccountingAuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Audit trail (Step 9): read-only, tenant/branch-scoped listing of every
 * financial write. There is deliberately no write action on this screen.
 */
class FinanceAuditController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly AccountingAuditService $audit) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $entries = $this->audit->recent(
            $institute->id,
            $this->actingBranchId($request),
            (int) $request->query('per_page', 50),
        );

        return view('institute.finance.audit.index', [
            'institute' => $institute,
            'entries' => $entries,
            'filters' => $request->query(),
        ]);
    }
}
