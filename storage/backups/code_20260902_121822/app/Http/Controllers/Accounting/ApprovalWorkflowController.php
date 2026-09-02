<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Role;
use App\Services\Accounting\ApprovalWorkflowService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STEP 80 — Approval Workflow UI Controller.
 */
class ApprovalWorkflowController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly ApprovalWorkflowService $approvalSvc,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $workflows = ApprovalWorkflow::where('institute_id', $institute->id)
            ->with('steps')
            ->get();

        return view('institute.accounting.approvals.index', [
            'institute' => $institute,
            'workflows' => $workflows,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $roles = Role::all();

        return view('institute.accounting.approvals.create', [
            'institute' => $institute,
            'roles' => $roles,
            'modules' => ['expense', 'purchase', 'payment', 'journal_adjustment'],
        ]);
    }

    public function store(Request $request)
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'module' => 'required|string|max:100',
            'amount_from' => 'required|numeric|min:0',
            'amount_to' => 'required|numeric|gte:amount_from',
            'approver_role_ids' => 'required|array|min:1',
        ]);

        $steps = collect($validated['approver_role_ids'])
            ->map(fn ($roleId) => ['approver_role_id' => (int) $roleId])
            ->toArray();

        $this->approvalSvc->createWorkflow(
            $institute->id,
            [
                'name' => $validated['name'],
                'module' => $validated['module'],
                'amount_from' => $validated['amount_from'],
                'amount_to' => $validated['amount_to'],
                'is_active' => true,
            ],
            $steps,
            $request->user()->id,
        );

        return redirect()->route('accounting.approvals.index')
            ->with('success', 'Workflow created.');
    }

    public function show(Request $request, int $workflowId): View
    {
        $institute = $this->requireInstitute($request);

        $workflow = ApprovalWorkflow::where('institute_id', $institute->id)
            ->with('steps', 'requests')
            ->findOrFail($workflowId);

        return view('institute.accounting.approvals.show', [
            'institute' => $institute,
            'workflow' => $workflow,
        ]);
    }

    public function inbox(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $pending = ApprovalRequest::where('institute_id', $institute->id)
            ->where('status', 'pending_approval')
            ->with('workflow', 'actions')
            ->get();

        return view('institute.accounting.approvals.inbox', [
            'institute' => $institute,
            'pending' => $pending,
        ]);
    }

    public function approve(Request $request, int $requestId)
    {
        $institute = $this->requireInstitute($request);

        $approvalRequest = ApprovalRequest::where('institute_id', $institute->id)
            ->findOrFail($requestId);

        $this->approvalSvc->approve(
            $approvalRequest,
            $request->user()->id,
            $request->input('notes'),
        );

        return back()->with('success', 'Request approved.');
    }

    public function reject(Request $request, int $requestId)
    {
        $institute = $this->requireInstitute($request);

        $approvalRequest = ApprovalRequest::where('institute_id', $institute->id)
            ->findOrFail($requestId);

        $this->approvalSvc->reject(
            $approvalRequest,
            $request->user()->id,
            $request->input('notes'),
        );

        return back()->with('success', 'Request rejected.');
    }
}
