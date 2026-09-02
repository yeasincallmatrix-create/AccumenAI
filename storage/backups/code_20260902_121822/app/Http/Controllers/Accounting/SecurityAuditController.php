<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\AccountingAuditTrail;
use App\Services\SecurityAuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STEP 99 — Security Audit Dashboard Controller.
 *
 * Provides a security overview (permission count, audit events, failed logins,
 * active sessions) and the recent audit trail entries.
 */
class SecurityAuditController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly SecurityAuditService $securityAudit,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $summary = $this->securityAudit->getSecuritySummary((int) $institute->id);

        // Recent failed logins (last 24h)
        $recentFailedLogins = AccountingAuditTrail::query()
            ->where('institute_id', $institute->id)
            ->where('action', 'failed_login')
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Recent permission denials (last 24h)
        $recentPermissionDenials = AccountingAuditTrail::query()
            ->where('institute_id', $institute->id)
            ->where('action', 'permission_denied')
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('institute.accounting.security.index', [
            'institute' => $institute,
            'summary' => $summary,
            'recentFailedLogins' => $recentFailedLogins,
            'recentPermissionDenials' => $recentPermissionDenials,
        ]);
    }

    public function auditLogs(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $perPage = min((int) $request->query('per_page', 25), 100);

        $logs = AccountingAuditTrail::query()
            ->where('institute_id', $institute->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return view('institute.accounting.security.audit-logs', [
            'institute' => $institute,
            'logs' => $logs,
        ]);
    }
}
