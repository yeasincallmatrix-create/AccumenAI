<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Student;
use App\Models\Workflow;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Step 51 — Education workflow automation.
 *
 * Server-rendered workflow list/detail pages plus transition endpoints. The
 * tenant is always derived from the authenticated user; the student and
 * workflow are tenant-verified before any action. Transitions are validated
 * server-side by the WorkflowService against the Workflow::TRANSITIONS map.
 */
class WorkflowController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly WorkflowService $workflows) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = Workflow::query()
            ->where('institute_id', $institute->id)
            ->with(['student', 'initiator', 'assignee', 'steps'])
            ->when($request->filled('workflow_type'), fn ($q) => $q->where('workflow_type', $request->string('workflow_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->integer('student_id')))
            ->orderByDesc('id');

        $workflows = $query->paginate(20)->withQueryString();

        return view('workflows.index', [
            'workflows' => $workflows,
            'types' => config('workflows.types', []),
            'statuses' => Workflow::STATUSES,
            'filters' => $request->only(['workflow_type', 'status', 'student_id']),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $students = Student::query()
            ->where('institute_id', $institute->id)
            ->orderBy('first_name')
            ->limit(500)
            ->get(['id', 'first_name', 'last_name', 'reg_no']);

        return view('workflows.create', [
            'types' => config('workflows.types', []),
            'students' => $students,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workflow_type' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'student_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $institute = $this->requireInstitute($request);

        $studentId = null;
        if (! empty($data['student_id'])) {
            $student = Student::query()
                ->where('institute_id', $institute->id)
                ->find($data['student_id']);
            abort_if($student === null, 422, 'The selected student is not valid for this institute.');
            $studentId = (int) $student->id;
        }

        $workflow = $this->workflows->create(
            instituteId: (int) $institute->id,
            workflowType: $data['workflow_type'],
            title: $data['title'],
            studentId: $studentId,
            entityType: $studentId !== null ? Student::class : null,
            entityId: $studentId,
            branchId: $this->actingBranchId($request),
            initiatedBy: $this->actorId($request),
            notes: $data['notes'] ?? null,
        );

        return redirect()->route('workflows.show', $workflow)->with('status', 'Workflow created.');
    }

    public function show(Request $request, Workflow $workflow): View
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $workflow->institute_id !== (int) $institute->id, 404);

        $workflow->load(['student', 'initiator', 'assignee', 'steps.actor', 'histories.actor']);

        return view('workflows.show', [
            'workflow' => $workflow,
            'nextStatuses' => Workflow::TRANSITIONS[$workflow->status] ?? [],
        ]);
    }

    public function transition(Request $request, Workflow $workflow): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $workflow->institute_id !== (int) $institute->id, 404);

        $data = $request->validate([
            'status' => ['required', 'string'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workflows->transition(
            $workflow,
            $data['status'],
            $this->actorId($request),
            $data['comment'] ?? null,
        );

        return redirect()->route('workflows.show', $workflow)->with('status', 'Workflow updated.');
    }

    public function approveStep(Request $request, Workflow $workflow): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $workflow->institute_id !== (int) $institute->id, 404);

        $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workflows->approveStep(
            $workflow,
            $this->actorId($request),
            $request->string('comment')->toString() ?: null,
        );

        return redirect()->route('workflows.show', $workflow)->with('status', 'Step approved.');
    }

    public function rejectStep(Request $request, Workflow $workflow): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $workflow->institute_id !== (int) $institute->id, 404);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $this->workflows->rejectStep(
            $workflow,
            $this->actorId($request),
            $data['comment'],
        );

        return redirect()->route('workflows.show', $workflow)->with('status', 'Workflow rejected.');
    }
}
