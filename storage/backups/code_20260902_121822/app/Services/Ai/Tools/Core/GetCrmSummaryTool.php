<?php

namespace App\Services\Ai\Tools\Core;

use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmLeadStatus;
use App\Models\CrmOrganization;
use App\Services\Ai\AiContext;
use App\Services\Ai\Tools\AbstractAiTool;
use Illuminate\Support\Carbon;

/**
 * Industry-neutral CRM summary backed by the Step 31 CRM Core.
 *
 * Reuses the tenant/branch-scoped Crm models — the same models the CRM UI reads
 * — so the AI inherits exactly the same visibility rules. Read-only: it only
 * counts and lists existing records and never mutates anything.
 */
class GetCrmSummaryTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'get_crm_summary';
    }

    public function description(): string
    {
        return 'Summarise CRM data for this organisation: contact counts (customers vs prospects), lead counts by '
            .'pipeline stage with total and won deal value, organisation counts, and recent activity volume by type '
            .'(calls, emails, meetings, follow-ups). Returns small bounded summaries and the most recent activity rows.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'branch_id' => ['type' => 'integer'],
                'days' => ['type' => 'integer', 'description' => 'Activity window in days, default 30'],
                'lead_status' => ['type' => 'string', 'description' => 'Pipeline stage slug to filter leads by'],
                'limit' => ['type' => 'integer', 'description' => 'Rows to return, 1-50'],
            ],
        ];
    }

    public function permission(): string
    {
        return 'crm.view';
    }

    public function handle(array $args, AiContext $context): array
    {
        $this->guard($context);

        $instituteId = $context->instituteId();
        $branchId = $this->branchId($context)
            ?? (($args['branch_id'] ?? null) !== null ? (int) $args['branch_id'] : null);

        // Mirrors BranchScopedOrShared: a branch-restricted actor sees
        // institute-wide rows plus their own branch, never other branches.
        $branchScope = fn ($query) => $query->when($branchId !== null, fn ($w) => $w->where(
            fn ($b) => $b->whereNull('branch_id')->orWhere('branch_id', $branchId)
        ));

        $contacts = CrmContact::query()->where('institute_id', $instituteId);
        $organizations = CrmOrganization::query()->where('institute_id', $instituteId);
        $leads = CrmLead::query()->where('institute_id', $instituteId);
        $activities = CrmActivity::query()->where('institute_id', $instituteId);

        $branchScope($contacts);
        $branchScope($organizations);
        $branchScope($leads);
        $branchScope($activities);

        if (($statusSlug = $args['lead_status'] ?? null) !== null) {
            $statusId = CrmLeadStatus::query()->where('slug', $statusSlug)->value('id');
            if ($statusId !== null) {
                $leads->where('status_id', $statusId);
            }
        }

        $summary = [
            'contacts' => [
                'total' => (clone $contacts)->count(),
                'customers' => (clone $contacts)->where('is_customer', true)->count(),
                'prospects' => (clone $contacts)->where('is_prospect', true)->count(),
            ],
            'organizations' => [
                'total' => (clone $organizations)->count(),
            ],
        ];

        $summary['leads'] = [
            'total' => (clone $leads)->count(),
            'by_status' => (clone $leads)
                ->join('crm_lead_statuses as st', 'st.id', '=', 'crm_leads.status_id')
                ->selectRaw('st.slug, st.name, COUNT(*) as total')
                ->groupBy('st.slug', 'st.name')
                ->orderBy('st.display_order')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->slug => ['name' => $row->name, 'total' => (int) $row->total]])
                ->toArray(),
            'open' => (clone $leads)
                ->join('crm_lead_statuses as st', 'st.id', '=', 'crm_leads.status_id')
                ->whereNotIn('st.slug', ['won', 'lost'])
                ->count(),
            'total_value' => round((float) (clone $leads)->sum('crm_leads.value_amount'), 2),
            'won_value' => round((float) (clone $leads)
                ->join('crm_lead_statuses as st', 'st.id', '=', 'crm_leads.status_id')
                ->where('st.slug', 'won')
                ->sum('crm_leads.value_amount'), 2),
        ];

        $days = max(1, min((int) ($args['days'] ?? 30), 365));
        $since = Carbon::today()->subDays($days)->startOfDay();

        $windowed = (clone $activities)->where('activity_at', '>=', $since);
        $summary['activities'] = [
            'window_days' => $days,
            'count' => (clone $windowed)->count(),
            'by_type' => (clone $windowed)
                ->selectRaw('type, COUNT(*) as total')
                ->groupBy('type')
                ->orderBy('type')
                ->pluck('total', 'type')
                ->toArray(),
        ];

        $rows = (clone $windowed)
            ->latest('activity_at')
            ->limit($this->limit($args))
            ->get(['id', 'type', 'summary', 'activity_at'])
            ->map(fn ($activity) => [
                'id' => $activity->id,
                'type' => $activity->type,
                'summary' => $activity->summary,
                'activity_at' => $activity->activity_at?->format('Y-m-d H:i'),
            ])
            ->all();

        return $this->result($summary, $rows);
    }
}
