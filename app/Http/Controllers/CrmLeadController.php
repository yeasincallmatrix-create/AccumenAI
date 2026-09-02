<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Branch;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmLeadSource;
use App\Models\CrmLeadStatus;
use App\Models\CrmOrganization;
use App\Models\InstituteUser;
use App\Services\CrmLeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * CRM leads (Step 31). Same security model as CrmContactController:
 * institute/branch never from input, tenant + branch scopes, crm.* permissions.
 */
class CrmLeadController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly CrmLeadService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = CrmLead::query()
            ->with(['status', 'source', 'contact', 'organization', 'assignedUser', 'branch']);

        if (filled($q = $request->query('q'))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        if (filled($request->query('status_id'))) {
            $query->where('status_id', (int) $request->query('status_id'));
        }

        if (filled($request->query('source_id'))) {
            $query->where('source_id', (int) $request->query('source_id'));
        }

        if (filled($request->query('branch_id'))) {
            $query->where('branch_id', (int) $request->query('branch_id'));
        }

        if (filled($request->query('assigned_user_id'))) {
            $query->where('assigned_user_id', (int) $request->query('assigned_user_id'));
        }

        $leads = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.crm.leads.index', [
            'institute' => $institute,
            'leads' => $leads,
            'statuses' => CrmLeadStatus::query()->where('status', 'active')->orderBy('display_order')->get(),
            'sources' => CrmLeadSource::query()->where('status', 'active')->orderBy('display_order')->get(),
            'staff' => $this->instituteStaff($institute->id),
            'branches' => $this->instituteBranches($institute->id),
            'filters' => $request->query(),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.crm.leads.form', [
            'institute' => $institute,
            'lead' => null,
            'statuses' => CrmLeadStatus::query()->where('status', 'active')->orderBy('display_order')->get(),
            'sources' => CrmLeadSource::query()->where('status', 'active')->orderBy('display_order')->get(),
            'staff' => $this->instituteStaff($institute->id),
            'contacts' => CrmContact::query()->orderBy('id')->get(['id', 'first_name', 'last_name']),
            'organizations' => $this->instituteOrganizations($institute->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $lead = $this->service->create(
            $this->validated($request),
            $institute->id,
            $this->actingBranchId($request),
            (int) $this->actorId($request)
        );

        return redirect()
            ->route('crm.leads.show', $lead)
            ->with('status', 'Lead "'.$lead->displayName().'" saved.');
    }

    public function show(Request $request, CrmLead $lead): View
    {
        $institute = $this->requireInstitute($request);

        $lead->load(['status', 'source', 'contact', 'organization', 'assignedUser', 'branch', 'creator', 'convertedContact']);

        return view('institute.crm.leads.show', [
            'institute' => $institute,
            'lead' => $lead,
            'timeline' => $this->timeline($lead),
            'openTasks' => $lead->tasks()->whereIn('status', ['open', 'in_progress'])->orderBy('due_at')->get(),
            'statuses' => CrmLeadStatus::query()->where('status', 'active')->orderBy('display_order')->get(),
            'staff' => $this->instituteStaff($institute->id),
        ]);
    }

    public function edit(Request $request, CrmLead $lead): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.crm.leads.form', [
            'institute' => $institute,
            'lead' => $lead,
            'statuses' => CrmLeadStatus::query()->where('status', 'active')->orderBy('display_order')->get(),
            'sources' => CrmLeadSource::query()->where('status', 'active')->orderBy('display_order')->get(),
            'staff' => $this->instituteStaff($institute->id),
            'contacts' => CrmContact::query()->orderBy('id')->get(['id', 'first_name', 'last_name']),
            'organizations' => $this->instituteOrganizations($institute->id),
        ]);
    }

    public function update(Request $request, CrmLead $lead): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $lead = $this->service->update(
            $lead,
            $this->validated($request),
            $institute->id,
            (int) $this->actorId($request)
        );

        return redirect()
            ->route('crm.leads.show', $lead)
            ->with('status', 'Lead updated.');
    }

    public function assign(Request $request, CrmLead $lead): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'assigned_user_id' => ['nullable', 'integer'],
        ]);

        $this->service->assign(
            $lead,
            $data['assigned_user_id'] !== '' ? (int) $data['assigned_user_id'] : null,
            $institute->id,
            (int) $this->actorId($request)
        );

        return back()->with('status', 'Lead reassigned.');
    }

    public function convert(Request $request, CrmLead $lead): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $contact = $this->service->convert($lead, $institute->id, (int) $this->actorId($request));

        return redirect()
            ->route('crm.contacts.show', $contact)
            ->with('status', 'Lead converted to contact.');
    }

    public function destroy(Request $request, CrmLead $lead): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->delete($lead, $institute->id, (int) $this->actorId($request));

        return redirect()
            ->route('crm.leads.index')
            ->with('status', 'Lead moved to trash.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'status_id' => ['nullable', 'integer'],
            'source_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'organization_id' => ['nullable', 'integer'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'interest_summary' => ['nullable', 'string', 'max:5000'],
            'value_amount' => ['nullable', 'numeric', 'min:0'],
            'assigned_user_id' => ['nullable', 'integer'],
        ]);
    }

    private function timeline(CrmLead $lead): Collection
    {
        return collect()
            ->merge($lead->activities()->get()->map(fn ($item) => [
                'at' => $item->activity_at ?? $item->created_at,
                'kind' => 'activity',
                'label' => $item->type,
                'summary' => $item->summary,
                'meta' => $item->created_at?->format('Y-m-d H:i'),
            ]))
            ->merge($lead->notes()->get()->map(fn ($item) => [
                'at' => $item->created_at,
                'kind' => 'note',
                'label' => 'Note',
                'summary' => mb_strimwidth($item->body, 0, 200, '…'),
                'meta' => $item->created_at?->format('Y-m-d H:i'),
            ]))
            ->merge($lead->tasks()->get()->map(fn ($item) => [
                'at' => $item->created_at,
                'kind' => 'task',
                'label' => $item->status,
                'summary' => $item->title,
                'meta' => $item->due_at?->format('Y-m-d H:i'),
            ]))
            ->sortByDesc('at')
            ->values();
    }

    private function instituteStaff(int $instituteId): Collection
    {
        return InstituteUser::query()
            ->where('institute_id', $instituteId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function instituteBranches(int $instituteId): Collection
    {
        return Branch::query()
            ->where('institute_id', $instituteId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function instituteOrganizations(int $instituteId): Collection
    {
        return CrmOrganization::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
