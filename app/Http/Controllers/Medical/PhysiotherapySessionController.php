<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\PhysiotherapyPlan;
use App\Models\Medical\PhysiotherapySession;
use App\Models\User;
use App\Services\Medical\NumberSequenceService;
use App\Services\Medical\PhysiotherapyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PhysiotherapySessionController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.physiotherapy.view', only: ['index', 'show']),
            new Middleware('permission:medical.physiotherapy.plan.create', only: ['create', 'store']),
            new Middleware('permission:medical.physiotherapy.plan.edit', only: ['edit', 'update', 'markAttended', 'markNoShow']),
            new Middleware('permission:medical.physiotherapy.plan.create', only: ['destroy']),
        ];
    }

    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly PhysiotherapyService $physioService,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = PhysiotherapySession::where('institute_id', $instituteId);
        $this->scopeBranch($query);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('therapist_id')) {
            $query->where('therapist_id', $request->therapist_id);
        }
        if ($request->filled('plan_id')) {
            $query->where('physiotherapy_plan_id', $request->plan_id);
        }
        if ($request->filled('from_date')) {
            $query->where('session_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('session_date', '<=', $request->to_date);
        }

        $sessions = $query->with(['plan.patient', 'therapist'])->orderByDesc('session_date')->paginate(25)->withQueryString();

        return view('medical.physiotherapy.sessions.index', compact('sessions'));
    }

    public function create(PhysiotherapyPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'physiotherapy_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'physiotherapy_plan');

        $instituteId = $this->instituteId();
        $sessionNumber = $this->sequences->peek(NumberSequence::TYPE_PHYSIO_SESSION, $instituteId);
        $sessionOrder = $this->physioService->nextSessionOrder($plan);
        $therapists = User::where('status', 'active')->orderBy('name')->get();

        return view('medical.physiotherapy.sessions.create', compact('plan', 'sessionNumber', 'sessionOrder', 'therapists'));
    }

    public function store(Request $request, PhysiotherapyPlan $plan)
    {
        $this->ensureSameInstitute($plan, 'physiotherapy_plan');
        $this->ensureBranchAccess($plan, 'branch_id', 'physiotherapy_plan');

        $request->validate([
            'therapist_id' => 'required|exists:users,id',
            'session_date' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:1|max:480',
            'fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $instituteId = $this->instituteId();
        $data = $request->all();
        $data['institute_id'] = $instituteId;
        $data['branch_id'] = $plan->branch_id;
        $data['physiotherapy_plan_id'] = $plan->getKey();
        $data['session_number'] = $this->sequences->next(NumberSequence::TYPE_PHYSIO_SESSION, $instituteId);
        $data['session_order'] = $this->physioService->nextSessionOrder($plan);
        $data['status'] = 'scheduled';
        $data['duration_minutes'] = $data['duration_minutes'] ?? 30;
        $data['fee'] = $data['fee'] ?? $plan->fee_per_session ?? 0;
        $data['payment_status'] = 'pending';

        $session = PhysiotherapySession::create($data);

        ClinicalAuditLog::record($session, 'created');

        return redirect()
            ->route('medical.physiotherapy.sessions.show', $session)
            ->with('status', 'Physiotherapy session created: ' . $session->session_number);
    }

    public function show(PhysiotherapySession $session)
    {
        $this->ensureSameInstitute($session, 'physiotherapy_session');
        $this->ensureBranchAccess($session, 'branch_id', 'physiotherapy_session');
        $session->load(['plan.patient', 'therapist']);

        return view('medical.physiotherapy.sessions.show', ['session' => $session]);
    }

    public function edit(PhysiotherapySession $session)
    {
        $this->ensureSameInstitute($session, 'physiotherapy_session');
        $this->ensureBranchAccess($session, 'branch_id', 'physiotherapy_session');
        $session->load('plan');

        $therapists = User::where('status', 'active')->orderBy('name')->get();
        $statuses = PhysiotherapySession::STATUSES;

        return view('medical.physiotherapy.sessions.edit', [
            'session' => $session,
            'therapists' => $therapists,
            'statuses' => $statuses,
        ]);
    }

    public function update(Request $request, PhysiotherapySession $session)
    {
        $this->ensureSameInstitute($session, 'physiotherapy_session');
        $this->ensureBranchAccess($session, 'branch_id', 'physiotherapy_session');

        $request->validate([
            'therapist_id' => 'required|exists:users,id',
            'session_date' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:1|max:480',
            'status' => 'nullable|string|in:' . implode(',', array_keys(PhysiotherapySession::STATUSES)),
            'fee' => 'nullable|numeric|min:0',
        ]);

        $original = ClinicalAuditLog::snapshot($session);

        $session->update($request->only([
            'therapist_id', 'session_date', 'duration_minutes', 'status', 'fee',
        ]));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($session->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($session, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.physiotherapy.sessions.show', $session)
            ->with('status', 'Physiotherapy session updated.');
    }

    public function destroy(PhysiotherapySession $session)
    {
        $this->ensureSameInstitute($session, 'physiotherapy_session');
        $this->ensureBranchAccess($session, 'branch_id', 'physiotherapy_session');

        if ($session->status !== 'scheduled') {
            return redirect()->back()->with('error', 'Only scheduled sessions can be deleted.');
        }

        ClinicalAuditLog::record($session, 'deleted');

        $session->delete();

        return redirect()
            ->route('medical.physiotherapy.sessions.index')
            ->with('status', 'Physiotherapy session deleted.');
    }

    public function markAttended(Request $request, PhysiotherapySession $session)
    {
        $this->ensureSameInstitute($session, 'physiotherapy_session');
        $this->ensureBranchAccess($session, 'branch_id', 'physiotherapy_session');

        $request->validate([
            'pain_score_before' => 'nullable|integer|min:0|max:10',
            'pain_score_after' => 'nullable|integer|min:0|max:10',
            'assessment_notes' => 'nullable|string|max:2000',
            'treatment_given' => 'nullable|string|max:2000',
            'exercises_done' => 'nullable|string|max:2000',
            'progress_notes' => 'nullable|string|max:2000',
            'next_session_focus' => 'nullable|string|max:1000',
        ]);

        $this->physioService->markAttended($session, $request->only([
            'pain_score_before', 'pain_score_after', 'assessment_notes',
            'treatment_given', 'exercises_done', 'progress_notes', 'next_session_focus',
        ]));

        return redirect()
            ->route('medical.physiotherapy.sessions.show', $session)
            ->with('status', 'Session marked as attended.');
    }

    public function markNoShow(PhysiotherapySession $session)
    {
        $this->ensureSameInstitute($session, 'physiotherapy_session');
        $this->ensureBranchAccess($session, 'branch_id', 'physiotherapy_session');

        $this->physioService->markNoShow($session);

        return redirect()
            ->route('medical.physiotherapy.sessions.show', $session)
            ->with('status', 'Session marked as no-show.');
    }
}
