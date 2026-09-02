<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Accounting\TaxReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STEP 82 — Tax Reporting Engine Controller.
 */
class TaxReportController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly TaxReportService $taxReport,
    ) {}

    public function vatSummary(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $report = $this->taxReport->vatSummary($institute->id, $branchId, $from, $to);

        return view('institute.accounting.reports.tax-vat-summary', array_merge($report, [
            'institute' => $institute,
        ]));
    }

    public function inputVat(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $transactions = $this->taxReport->inputVatDetail($institute->id, $branchId, $from, $to);

        return view('institute.accounting.reports.tax-input-vat', [
            'institute' => $institute,
            'transactions' => $transactions,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function outputVat(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $transactions = $this->taxReport->outputVatDetail($institute->id, $branchId, $from, $to);

        return view('institute.accounting.reports.tax-output-vat', [
            'institute' => $institute,
            'transactions' => $transactions,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function taxLiability(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $asOfDate = $request->query('as_of_date', now()->toDateString());

        $report = $this->taxReport->taxLiability($institute->id, $branchId, $asOfDate);

        return view('institute.accounting.reports.tax-liability', array_merge($report, [
            'institute' => $institute,
        ]));
    }

    public function taxTransactionDetail(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $transactions = $this->taxReport->taxTransactionDetail($institute->id, $branchId, $from, $to);

        return view('institute.accounting.reports.tax-transactions', [
            'institute' => $institute,
            'transactions' => $transactions,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
