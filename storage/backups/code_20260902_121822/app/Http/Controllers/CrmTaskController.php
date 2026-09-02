<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Branch;
use App\Models\CrmTask;
use App\Models\InstituteUser;
use App\Services\CrmTaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRM follow-up tasks (Step 31). Same security model as CrmContactController.
 */
class CrmTaskController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly CrmTaskService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = CrmTask::query()
            ->with(['assignedUser', 'branch', 'creator']);

        if (filled($q = $request->query('q'))) {
            $query->where('title', 'like', "%{$q}%");
        }

        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        } else {
            $query->whereIn('status', ['open', 'in_progress']);
        }

        if (filled($request->query('priority'))) {
            $query->where('priority', $request->query('priority'));
        }

        if (filled($request->query('assigned_user_id'))) {
            $query->where('assigned_user_id', (int) $request->query('assigned_user_id'));
        }

        if ($request->boolean('my_tasks')) {
            $query->where('assigned_user_id', $this->actorId($request) ?? 0);
        }

        $tasks = $query->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->paginate(20)->withQueryString();

        return view('institute.crm.tasks.index', [
            'institute' => $institute,
            'tasks' => $tasks,
            'staff' => $this->instituteStaff($institute->id),
            'branches' => $this->instituteBranches($institute->id),
            'filters' => $request->query(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'subject_type' => ['nullable', Rule::in(['contact', 'organization', 'lead'])],
            'subject_id' => ['nullable', 'required_with:subject_type', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(CrmTask::PRIORITIES)],
            'status' => ['nullable', Rule::in(CrmTask::STATUSES)],
            'due_at' => ['nullable', 'date'],
            'assigned_user_id' => ['nullable', 'integer'],
        ]);

        $this->service->create(
            $data,
            $institute->id,
            $this->actingBranchId($request),
            (int) $this->actorId($request)
        );

        return back()->with('status', 'Task created.');
    }

    public function update(Request $request, CrmTask $task): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(CrmTask::PRIORITIES)],
            'status' => ['nullable', Rule::in(CrmTask::STATUSES)],
            'due_at' => ['nullable', 'date'],
            'assigned_user_id' => ['nullable', 'integer'],
        ]);

        $this->service->update($task, $data, $institute->id, (int) $this->actorId($request));

        return back()->with('status', 'Task updated.');
    }

    public function toggle(Request $request, CrmTask $task): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->toggleComplete($task, $institute->id, (int) $this->actorId($request));

        return back()->with('status', 'Task status updated.');
    }

    public function destroy(Request $request, CrmTask $task): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->delete($task, $institute->id, (int) $this->actorId($request));

        return back()->with('status', 'Task removed.');
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
