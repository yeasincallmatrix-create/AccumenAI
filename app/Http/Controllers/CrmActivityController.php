<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Branch;
use App\Models\CrmActivity;
use App\Models\InstituteUser;
use App\Services\CrmActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRM activity timeline (Step 31). Same security model as CrmContactController.
 */
class CrmActivityController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly CrmActivityService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = CrmActivity::query()
            ->with(['assignedUser', 'branch', 'creator']);

        if (filled($request->query('type'))) {
            $query->where('type', $request->query('type'));
        }

        if (filled($request->query('subject_type'))) {
            $query->where('subject_type', $request->query('subject_type'));
        }

        if (filled($request->query('assigned_user_id'))) {
            $query->where('assigned_user_id', (int) $request->query('assigned_user_id'));
        }

        $activities = $query->orderByDesc('activity_at')->paginate(20)->withQueryString();

        return view('institute.crm.activities.index', [
            'institute' => $institute,
            'activities' => $activities,
            'staff' => $this->instituteStaff($institute->id),
            'branches' => $this->instituteBranches($institute->id),
            'filters' => $request->query(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'subject_type' => ['required', Rule::in(['contact', 'organization', 'lead'])],
            'subject_id' => ['required', 'integer'],
            'type' => ['nullable', Rule::in(CrmActivity::TYPES)],
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'activity_at' => ['nullable', 'date'],
            'assigned_user_id' => ['nullable', 'integer'],
        ]);

        $activity = $this->service->create(
            $data,
            $institute->id,
            $this->actingBranchId($request),
            (int) $this->actorId($request)
        );

        return back()->with('status', 'Activity logged.');
    }

    public function destroy(Request $request, CrmActivity $activity): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->delete($activity, $institute->id, (int) $this->actorId($request));

        return back()->with('status', 'Activity removed.');
    }

    // ------------------------------------------------------------- Internals

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
