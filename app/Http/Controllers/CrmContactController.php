<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Branch;
use App\Models\Country;
use App\Models\CrmContact;
use App\Models\CrmContactType;
use App\Models\CrmLeadSource;
use App\Models\CrmOrganization;
use App\Models\InstituteUser;
use App\Services\CrmContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRM contacts (Step 31).
 *
 * Security model mirrors the academic controllers:
 *   - institute_id / branch_id never come from request input;
 *   - tenant + branch visibility enforced by global scopes (implicit binding 404s
 *     cross-tenant / cross-branch records);
 *   - the route group is gated behind crm.* permissions.
 */
class CrmContactController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly CrmContactService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = CrmContact::query()
            ->with(['organization', 'contactType', 'source', 'assignedUser', 'branch']);

        if (filled($q = $request->query('q'))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        if (filled($request->query('contact_type_id'))) {
            $query->where('contact_type_id', (int) $request->query('contact_type_id'));
        }

        if (filled($request->query('organization_id'))) {
            $query->where('organization_id', (int) $request->query('organization_id'));
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

        $contacts = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.crm.contacts.index', [
            'institute' => $institute,
            'contacts' => $contacts,
            'contactTypes' => CrmContactType::query()->where('status', 'active')->orderBy('display_order')->get(),
            'sources' => CrmLeadSource::query()->where('status', 'active')->orderBy('display_order')->get(),
            'staff' => $this->instituteStaff($institute->id),
            'branches' => $this->instituteBranches($institute->id),
            'organizations' => $this->instituteOrganizations($institute->id),
            'filters' => $request->query(),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.crm.contacts.form', [
            'institute' => $institute,
            'contact' => null,
            'contactTypes' => CrmContactType::query()->where('status', 'active')->orderBy('display_order')->get(),
            'sources' => CrmLeadSource::query()->where('status', 'active')->orderBy('display_order')->get(),
            'staff' => $this->instituteStaff($institute->id),
            'organizations' => $this->instituteOrganizations($institute->id),
            'countries' => Country::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $contact = $this->service->create(
            $this->validated($request),
            $institute->id,
            $this->actingBranchId($request),
            (int) $this->actorId($request)
        );

        return redirect()
            ->route('crm.contacts.show', $contact)
            ->with('status', 'Contact "'.$contact->displayName().'" saved.');
    }

    public function show(Request $request, CrmContact $contact): View
    {
        $institute = $this->requireInstitute($request);

        $contact->load([
            'organization', 'contactType', 'source', 'country', 'assignedUser', 'branch', 'creator',
        ]);

        $timeline = $this->timeline($contact);

        return view('institute.crm.contacts.show', [
            'institute' => $institute,
            'contact' => $contact,
            'timeline' => $timeline,
            'openTasks' => $contact->tasks()->whereIn('status', ['open', 'in_progress'])->orderBy('due_at')->get(),
            'activities' => $contact->activities()->orderByDesc('activity_at')->limit(20)->get(),
            'staff' => $this->instituteStaff($institute->id),
        ]);
    }

    public function edit(Request $request, CrmContact $contact): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.crm.contacts.form', [
            'institute' => $institute,
            'contact' => $contact,
            'contactTypes' => CrmContactType::query()->where('status', 'active')->orderBy('display_order')->get(),
            'sources' => CrmLeadSource::query()->where('status', 'active')->orderBy('display_order')->get(),
            'staff' => $this->instituteStaff($institute->id),
            'organizations' => $this->instituteOrganizations($institute->id),
            'countries' => Country::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CrmContact $contact): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $contact = $this->service->update(
            $contact,
            $this->validated($request),
            $institute->id,
            (int) $this->actorId($request)
        );

        return redirect()
            ->route('crm.contacts.show', $contact)
            ->with('status', 'Contact updated.');
    }

    public function assign(Request $request, CrmContact $contact): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'assigned_user_id' => ['nullable', 'integer'],
        ]);

        $this->service->assign(
            $contact,
            $data['assigned_user_id'] !== '' ? (int) $data['assigned_user_id'] : null,
            $institute->id,
            (int) $this->actorId($request)
        );

        return back()->with('status', 'Contact reassigned.');
    }

    public function destroy(Request $request, CrmContact $contact): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->delete($contact, $institute->id, (int) $this->actorId($request));

        return redirect()
            ->route('crm.contacts.index')
            ->with('status', 'Contact moved to trash.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'contact_type_id' => ['nullable', 'integer'],
            'salutation' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone_alt' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'organization_id' => ['nullable', 'integer'],
            'designation' => ['nullable', 'string', 'max:120'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer'],
            'is_customer' => ['nullable', 'boolean'],
            'is_prospect' => ['nullable', 'boolean'],
            'customer_since' => ['nullable', 'date'],
            'source_id' => ['nullable', 'integer'],
            'assigned_user_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([CrmContact::STATUS_ACTIVE, CrmContact::STATUS_INACTIVE])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function timeline(CrmContact $contact): Collection
    {
        return collect()
            ->merge($contact->activities()->get()->map(fn ($item) => [
                'at' => $item->activity_at ?? $item->created_at,
                'kind' => 'activity',
                'label' => $item->type,
                'summary' => $item->summary,
                'meta' => $item->created_at?->format('Y-m-d H:i'),
            ]))
            ->merge($contact->notes()->get()->map(fn ($item) => [
                'at' => $item->created_at,
                'kind' => 'note',
                'label' => 'Note',
                'summary' => mb_strimwidth($item->body, 0, 200, '…'),
                'meta' => $item->created_at?->format('Y-m-d H:i'),
            ]))
            ->merge($contact->tasks()->get()->map(fn ($item) => [
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
