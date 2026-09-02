<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Accounting\ReportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * STEP 85 — Enterprise Reporting Export Engine.
 *
 * CSV download endpoints for trial balance, income statement, and balance
 * sheet. Each export streams directly to the client for large datasets.
 */
class ReportExportController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly ReportExportService $exportService,
    ) {}

    public function exportTrialBalance(Request $request): StreamedResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        return $this->exportService->trialBalanceCsv($institute->id, $branchId, $from, $to);
    }

    public function exportIncomeStatement(Request $request): StreamedResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        return $this->exportService->incomeStatementCsv($institute->id, $branchId, $from, $to);
    }

    public function exportBalanceSheet(Request $request): StreamedResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $asOfDate = $request->query('as_of_date', now()->toDateString());

        return $this->exportService->balanceSheetCsv($institute->id, $branchId, $asOfDate);
    }
}
