<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Accounting\AuditComplianceReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STEP 84 — Audit & Compliance Reports Controller.
 */
class AuditComplianceReportController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly AuditComplianceReportService $auditReport,
    ) {}

    public function journalAuditTrail(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $report = $this->auditReport->journalAuditTrail($institute->id, $branchId, $from, $to);

        return view('institute.accounting.reports.audit-journal-trail', array_merge($report, [
            'institute' => $institute,
        ]));
    }

    public function userActivity(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $report = $this->auditReport->userActivity($institute->id, $branchId, $from, $to);

        return view('institute.accounting.reports.audit-user-activity', array_merge($report, [
            'institute' => $institute,
        ]));
    }

    public function financialChangeHistory(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $report = $this->auditReport->financialChangeHistory($institute->id, $branchId, $from, $to);

        return view('institute.accounting.reports.audit-financial-changes', array_merge($report, [
            'institute' => $institute,
        ]));
    }

    public function approvalHistory(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $report = $this->auditReport->approvalHistory($institute->id, $branchId, $from, $to);

        return view('institute.accounting.reports.audit-approval-history', array_merge($report, [
            'institute' => $institute,
        ]));
    }
}
