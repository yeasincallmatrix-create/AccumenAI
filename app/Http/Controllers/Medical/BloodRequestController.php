<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\BloodDonor;
use App\Models\Medical\BloodRequest;
use App\Models\Medical\BloodUnit;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\NumberSequence;
use App\Services\Medical\BloodBankService;
use App\Services\Medical\NumberSequenceService;
use App\Support\MedicalScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BloodRequestController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_bloodbank.view', only: ['index', 'show']),
            new Middleware('permission:medical_bloodbank.create', only: ['create', 'store']),
            new Middleware('permission:medical_bloodbank.edit', only: ['edit', 'update']),
            new Middleware('permission:medical_bloodbank.issue', only: ['approve', 'cancel', 'issue', 'return']),
            new Middleware('permission:medical_bloodbank.delete', only: ['destroy']),
        ];
    }

    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly BloodBankService $bloodBankService,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = BloodRequest::where('institute_id', $instituteId);
        $this->scopeBranch($query);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('blood_group')) {
            $query->where('blood_group', $request->blood_group);
        }
        if ($request->filled('urgency')) {
            $query->where('urgency', $request->urgency);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                    ->orWhere('blood_group', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($pq) => $pq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $requests = $query->with(['patient', 'requestedBy', 'doctor', 'approvedBy'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('medical.blood-bank.requests.index', ['requests' => $requests]);
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $requestNumber = $this->sequences->peek(NumberSequence::TYPE_BLOOD_REQUEST, $instituteId);
        $urgencyLevels = BloodRequest::URGENCY_LEVELS;
        $bloodGroups = BloodDonor::BLOOD_GROUPS;
        $patients = \App\Models\Medical\Patient::where('institute_id', $instituteId)->active()->patients()->orderBy('first_name')->get();
        $doctors = MedicalScope::instituteDoctors($instituteId);

        return view('medical.blood-bank.requests.create', compact('requestNumber', 'urgencyLevels', 'bloodGroups', 'patients', 'doctors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'blood_group' => 'required|string|in:' . implode(',', array_keys(BloodDonor::BLOOD_GROUPS)),
            'component' => 'nullable|string|in:' . implode(',', array_keys(BloodUnit::COMPONENTS)),
            'units_requested' => 'required|integer|min:1',
            'urgency' => 'required|string|in:' . implode(',', array_keys(BloodRequest::URGENCY_LEVELS)),
            'clinical_indication' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'doctor_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $instituteId = $this->instituteId();
        $data = $request->all();
        $data['institute_id'] = $instituteId;
        $data['branch_id'] = $request->branch_id ?? $this->branchContextId();
        $data['request_number'] = $this->sequences->next(NumberSequence::TYPE_BLOOD_REQUEST, $instituteId);
        $data['requested_by'] = MedicalScope::recorderId();
        $data['status'] = 'pending';
        $data['units_issued'] = 0;

        $bloodRequest = BloodRequest::create($data);

        ClinicalAuditLog::record($bloodRequest, 'created');

        return redirect()
            ->route('medical.blood-bank.requests.show', $bloodRequest)
            ->with('status', 'Blood request created: ' . $bloodRequest->request_number);
    }

    public function show(BloodRequest $bloodRequest)
    {
        $this->ensureSameInstitute($bloodRequest, 'blood_request');
        $this->ensureBranchAccess($bloodRequest, 'branch_id', 'blood_request');
        $bloodRequest->load(['patient', 'requestedBy', 'doctor', 'approvedBy', 'issueItems.unit', 'issueItems.issuedBy']);

        return view('medical.blood-bank.requests.show', ['bloodRequest' => $bloodRequest]);
    }

    public function edit(BloodRequest $bloodRequest)
    {
        $this->ensureSameInstitute($bloodRequest, 'blood_request');
        $this->ensureBranchAccess($bloodRequest, 'branch_id', 'blood_request');

        $instituteId = $this->instituteId();
        $urgencyLevels = BloodRequest::URGENCY_LEVELS;
        $bloodGroups = BloodDonor::BLOOD_GROUPS;
        $components = BloodUnit::COMPONENTS;
        $patients = \App\Models\Medical\Patient::where('institute_id', $instituteId)->active()->patients()->orderBy('first_name')->get();
        $doctors = MedicalScope::instituteDoctors($instituteId);
        $statuses = BloodRequest::STATUSES;

        return view('medical.blood-bank.requests.edit', [
            'bloodRequest' => $bloodRequest,
            'urgencyLevels' => $urgencyLevels,
            'bloodGroups' => $bloodGroups,
            'components' => $components,
            'patients' => $patients,
            'doctors' => $doctors,
            'statuses' => $statuses,
        ]);
    }

    public function update(Request $request, BloodRequest $bloodRequest)
    {
        $this->ensureSameInstitute($bloodRequest, 'blood_request');
        $this->ensureBranchAccess($bloodRequest, 'branch_id', 'blood_request');

        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'blood_group' => 'required|string|in:' . implode(',', array_keys(BloodDonor::BLOOD_GROUPS)),
            'component' => 'nullable|string|in:' . implode(',', array_keys(BloodUnit::COMPONENTS)),
            'units_requested' => 'required|integer|min:1',
            'urgency' => 'required|string|in:' . implode(',', array_keys(BloodRequest::URGENCY_LEVELS)),
            'clinical_indication' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'doctor_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string|in:' . implode(',', array_keys(BloodRequest::STATUSES)),
            'notes' => 'nullable|string',
        ]);

        $original = ClinicalAuditLog::snapshot($bloodRequest);

        $bloodRequest->update($request->only([
            'patient_id', 'blood_group', 'component', 'units_requested',
            'urgency', 'clinical_indication', 'diagnosis', 'doctor_id',
            'status', 'notes',
        ]));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($bloodRequest->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($bloodRequest, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.blood-bank.requests.show', $bloodRequest)
            ->with('status', 'Blood request updated.');
    }

    public function destroy(BloodRequest $bloodRequest)
    {
        $this->ensureSameInstitute($bloodRequest, 'blood_request');
        $this->ensureBranchAccess($bloodRequest, 'branch_id', 'blood_request');

        if ($bloodRequest->status !== 'pending') {
            abort(422, 'Only pending requests can be deleted.');
        }

        ClinicalAuditLog::record($bloodRequest, 'deleted');

        $bloodRequest->delete();

        return redirect()
            ->route('medical.blood-bank.requests.index')
            ->with('status', 'Blood request deleted.');
    }

    public function approve(BloodRequest $bloodRequest)
    {
        $this->ensureSameInstitute($bloodRequest, 'blood_request');
        $this->ensureBranchAccess($bloodRequest, 'branch_id', 'blood_request');

        $this->bloodBankService->approveRequest($bloodRequest);

        return redirect()
            ->route('medical.blood-bank.requests.show', $bloodRequest)
            ->with('status', 'Blood request approved.');
    }

    public function cancel(Request $request, BloodRequest $bloodRequest)
    {
        $this->ensureSameInstitute($bloodRequest, 'blood_request');
        $this->ensureBranchAccess($bloodRequest, 'branch_id', 'blood_request');

        $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ]);

        $this->bloodBankService->cancelRequest($bloodRequest, $request->cancel_reason);

        return redirect()
            ->route('medical.blood-bank.requests.show', $bloodRequest)
            ->with('status', 'Blood request cancelled.');
    }

    public function issue(Request $request, BloodRequest $bloodRequest)
    {
        $this->ensureSameInstitute($bloodRequest, 'blood_request');
        $this->ensureBranchAccess($bloodRequest, 'branch_id', 'blood_request');

        $request->validate([
            'blood_unit_id' => 'required|exists:blood_units,id',
        ]);

        $unit = BloodUnit::findOrFail($request->blood_unit_id);
        $this->ensureSameInstitute($unit, 'blood_unit');

        if ($unit->status !== 'available' || $unit->isExpired()) {
            abort(422, 'The selected blood unit is not available.');
        }

        $this->bloodBankService->issueUnit($bloodRequest, $unit);

        return redirect()
            ->route('medical.blood-bank.requests.show', $bloodRequest)
            ->with('status', 'Blood unit issued successfully.');
    }

    public function return(Request $request, BloodRequest $bloodRequest)
    {
        $this->ensureSameInstitute($bloodRequest, 'blood_request');
        $this->ensureBranchAccess($bloodRequest, 'branch_id', 'blood_request');

        $request->validate([
            'issue_item_id' => 'required|exists:blood_issue_items,id',
            'return_reason' => 'required|string|max:500',
        ]);

        $item = \App\Models\Medical\BloodIssueItem::findOrFail($request->issue_item_id);

        if ((int) $item->blood_request_id !== (int) $bloodRequest->getKey()) {
            abort(422, 'This issue item does not belong to the given request.');
        }

        if ($item->status === 'returned') {
            abort(422, 'This unit has already been returned.');
        }

        $this->bloodBankService->returnUnit($item, $request->return_reason);

        return redirect()
            ->route('medical.blood-bank.requests.show', $bloodRequest)
            ->with('status', 'Blood unit returned successfully.');
    }
}
