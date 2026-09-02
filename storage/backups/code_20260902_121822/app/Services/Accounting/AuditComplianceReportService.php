<?php

namespace App\Services\Accounting;

use App\Models\AccountingAuditTrail;
use App\Models\ApprovalRequest;
use App\Models\ApprovalAction;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

/**
 * STEP 84 — Audit & Compliance Reports.
 *
 * Journal audit trail, user activity, financial change history, and approval
 * history. All read-only, tenant/branch-scoped, posted-journals-only.
 */
class AuditComplianceReportService
{
    public function __construct(
        private readonly AccountingAuditService $auditService,
    ) {}

    /**
     * Journal audit trail: all journal entries for posted journals in a date
     * range with before/after audit trail data.
     */
    public function journalAuditTrail(int $instituteId, ?int $branchId, string $from, string $to): array
    {
        $journals = Journal::query()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'posted')
            ->whereNull('reversal_of')
            ->whereDate('journal_date', '>=', $from)
            ->whereDate('journal_date', '<=', $to)
            ->with(['entries.coa'])
            ->orderByDesc('journal_date')
            ->get();

        $auditTrails = AccountingAuditTrail::query()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->where('entity_type', 'journal')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->orderByDesc('id')
            ->get()
            ->keyBy('entity_id');

        $trail = $journals->map(function ($journal) use ($auditTrails) {
            $audit = $auditTrails->get($journal->id);

            return [
                'journal_id' => $journal->id,
                'journal_no' => $journal->journal_no,
                'journal_date' => $journal->journal_date,
                'description' => $journal->description,
                'type' => $journal->type,
                'total_debit' => (float) $journal->entries->sum('debit'),
                'total_credit' => (float) $journal->entries->sum('credit'),
                'entries' => $journal->entries->map(fn ($e) => [
                    'account_code' => $e->coa?->code,
                    'account_name' => $e->coa?->name,
                    'debit' => (float) $e->debit,
                    'credit' => (float) $e->credit,
                    'description' => $e->description,
                ]),
                'audit_actor' => $audit?->actor_id,
                'audit_action' => $audit?->action,
                'audit_ip' => $audit?->ip,
                'audit_timestamp' => $audit?->created_at,
            ];
        });

        return [
            'trail' => $trail,
            'summary' => [
                'total_journals' => $journals->count(),
                'total_entries' => $journals->sum(fn ($j) => $j->entries->count()),
            ],
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * User activity report: audit trail grouped by actor_id.
     */
    public function userActivity(int $instituteId, ?int $branchId, string $from, string $to): array
    {
        $trails = AccountingAuditTrail::query()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->orderByDesc('id')
            ->get();

        $byUser = $trails->groupBy('actor_id')->map(function ($userTrails, $actorId) {
            return [
                'actor_id' => $actorId,
                'total_actions' => $userTrails->count(),
                'actions_by_type' => $userTrails->groupBy('action')->map(fn ($g) => $g->count()),
                'entities_touched' => $userTrails->groupBy('entity_type')->map(fn ($g) => $g->count()),
                'first_action' => $userTrails->last()?->created_at,
                'last_action' => $userTrails->first()?->created_at,
            ];
        })->values();

        return [
            'users' => $byUser,
            'summary' => [
                'total_users' => $byUser->count(),
                'total_actions' => $trails->count(),
            ],
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Financial change history: all audit trail entries for financial entities.
     */
    public function financialChangeHistory(int $instituteId, ?int $branchId, string $from, string $to): array
    {
        $trails = AccountingAuditTrail::query()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('entity_type', ['journal', 'payment', 'expense', 'invoice'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->orderByDesc('id')
            ->get();

        return [
            'changes' => $trails,
            'summary' => [
                'total_changes' => $trails->count(),
                'by_entity' => $trails->groupBy('entity_type')->map(fn ($g) => $g->count()),
                'by_action' => $trails->groupBy('action')->map(fn ($g) => $g->count()),
            ],
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Approval history: all approval requests with their actions.
     */
    public function approvalHistory(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null): array
    {
        $requests = ApprovalRequest::query()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from !== null, fn ($q) => $q->whereDate('requested_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('requested_at', '<=', $to))
            ->with(['workflow', 'requestedBy', 'resolvedBy', 'actions'])
            ->orderByDesc('requested_at')
            ->get();

        $summary = [
            'total_requests' => $requests->count(),
            'pending' => $requests->where('status', 'pending')->count(),
            'approved' => $requests->where('status', 'approved')->count(),
            'rejected' => $requests->where('status', 'rejected')->count(),
            'total_amount' => round((float) $requests->sum('amount'), 4),
        ];

        return [
            'requests' => $requests,
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
        ];
    }
}
