<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Branch;
use App\Models\Country;
use App\Models\CrmOrganization;
use App\Models\InstituteUser;
use App\Services\CrmOrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRM organizations (Step 31). Same security model as CrmContactController:
 * institute/branch never from input, tenant + branch scopes, crm.* permissions.
 */
class CrmOrganizationController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly CrmOrganizationService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = CrmOrganization::query()
            ->with(['assignedUser', 'branch'])
            ->withCount('contacts');

        if (filled($q = $request->query('q'))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        if ($request->boolean('is_customer')) {
            $query->where('is_customer', true);
        }

        if ($request->boolean('is_prospect')) {
            $query->where('is_prospect', true);
        }

        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        if (filled($request->query('branch_id'))) {
            $query->where('branch_id', (int) $request->query('branch_id'));
        }

        if (filled($request->query('assigned_user_id'))) {
            $query->where('assigned_user_id', (int) $request->query('assigned_user_id'));
        }

        $organizations = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.crm.organizations.index', [
            'institute' => $institute,
            'organizations' => $organizations,
            'staff' => $this->instituteStaff($institute->id),
            'branches' => $this->instituteBranches($institute->id),
            'filters' => $request->query(),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.crm.organizations.form', [
            'institute' => $institute,
            'organization' => null,
            'staff' => $this->instituteStaff($institute->id),
            'countries' => Country::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $organization = $this->service->create(
            $this->validated($request),
            $institute->id,
            $this->actingBranchId($request),
            (int) $this->actorId($request)
        );

        return redirect()
            ->route('crm.organizations.show', $organization)
            ->with('status', 'Organization "'.$organization->name.'" saved.');
    }

    public function show(Request $request, CrmOrganization $organization): View
    {
        $institute = $this->requireInstitute($request);

        $organization->load(['country', 'assignedUser', 'branch', 'creator']);

        return view('institute.crm.organizations.show', [
            'institute' => $institute,
            'organization' => $organization,
            'contacts' => $organization->contacts()->with('contactType')->orderByDesc('id')->get(),
            'leads' => $organization->leads()->with('status')->orderByDesc('id')->get(),
            'timeline' => $this->timeline($organization),
            'openTasks' => $organization->tasks()->whereIn('status', ['open', 'in_progress'])->orderBy('due_at')->get(),
            'staff' => $this->instituteStaff($institute->id),
        ]);
    }

    public function edit(Request $request, CrmOrganization $organization): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.crm.organizations.form', [
            'institute' => $institute,
            'organization' => $organization,
            'staff' => $this->instituteStaff($institute->id),
            'countries' => Country::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CrmOrganization $organization): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $organization = $this->service->update(
            $organization,
            $this->validated($request),
            $institute->id,
            (int) $this->actorId($request)
        );

        return redirect()
            ->route('crm.organizations.show', $organization)
            ->with('status', 'Organization updated.');
    }

    public function assign(Request $request, CrmOrganization $organization): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'assigned_user_id' => ['nullable', 'integer'],
        ]);

        $this->service->assign(
            $organization,
            $data['assigned_user_id'] !== '' ? (int) $data['assigned_user_id'] : null,
            $institute->id,
            (int) $this->actorId($request)
        );

        return back()->with('status', 'Organization reassigned.');
    }

    public function destroy(Request $request, CrmOrganization $organization): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->delete($organization, $institute->id, (int) $this->actorId($request));

        return redirect()
            ->route('crm.organizations.index')
            ->with('status', 'Organization moved to trash.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'max:191'],
            'industry' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer'],
            'is_customer' => ['nullable', 'boolean'],
            'is_prospect' => ['nullable', 'boolean'],
            'customer_since' => ['nullable', 'date'],
            'assigned_user_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([CrmOrganization::STATUS_ACTIVE, CrmOrganization::STATUS_INACTIVE])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function timeline(CrmOrganization $organization): Collection
    {
        return collect()
            ->merge($organization->activities()->get()->map(fn ($item) => [
                'at' => $item->activity_at ?? $item->created_at,
                'kind' => 'activity',
                'label' => $item->type,
                'summary' => $item->summary,
                'meta' => $item->created_at?->format('Y-m-d H:i'),
            ]))
            ->merge($organization->notes()->get()->map(fn ($item) => [
                'at' => $item->created_at,
                'kind' => 'note',
                'label' => 'Note',
                'summary' => mb_strimwidth($item->body, 0, 200, '…'),
                'meta' => $item->created_at?->format('Y-m-d H:i'),
            ]))
            ->merge($organization->tasks()->get()->map(fn ($item) => [
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
}
