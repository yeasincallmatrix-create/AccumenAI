<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmLeadStatus;
use App\Models\CrmOrganization;
use App\Models\CrmTask;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRM dashboard (Step 31): headline counts, open follow-ups, and the most recent
 * activity feed for the acting institute (branch-scoped automatically).
 */
class CrmDashboardController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $leadsByStatus = CrmLead::query()
            ->selectRaw('status_id, COUNT(*) as total')
            ->groupBy('status_id')
            ->with('status')
            ->get();

        return view('institute.crm.dashboard', [
            'institute' => $institute,
            'contactsCount' => CrmContact::query()->count(),
            'customerContactsCount' => CrmContact::query()->where('is_customer', true)->count(),
            'organizationsCount' => CrmOrganization::query()->count(),
            'customerOrganizationsCount' => CrmOrganization::query()->where('is_customer', true)->count(),
            'leadsCount' => CrmLead::query()->count(),
            'openLeadsCount' => CrmLead::query()->whereHas('status', fn ($q) => $q->whereIn('slug', ['new', 'contacted', 'qualified', 'proposal']))->count(),
            'leadsByStatus' => $leadsByStatus,
            'openTasksCount' => CrmTask::query()->whereIn('status', ['open', 'in_progress'])->count(),
            'overdueTasksCount' => CrmTask::query()->whereIn('status', ['open', 'in_progress'])->where('due_at', '<', now())->count(),
            'openTasks' => CrmTask::query()->whereIn('status', ['open', 'in_progress'])->orderBy('due_at')->limit(10)->get(),
            'recentActivities' => CrmActivity::query()->orderByDesc('activity_at')->limit(15)->get(),
            'leadStatuses' => CrmLeadStatus::query()->where('status', 'active')->orderBy('display_order')->get(),
        ]);
    }
}
