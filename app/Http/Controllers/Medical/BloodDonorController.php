<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\BloodDonor;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\NumberSequence;
use App\Services\Medical\NumberSequenceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BloodDonorController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_bloodbank.view', only: ['index', 'show']),
            new Middleware('permission:medical_bloodbank.create', only: ['create', 'store']),
            new Middleware('permission:medical_bloodbank.edit', only: ['edit', 'update']),
            new Middleware('permission:medical_bloodbank.delete', only: ['destroy']),
        ];
    }

    public function __construct(private readonly NumberSequenceService $sequences) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = BloodDonor::where('institute_id', $instituteId);
        $this->scopeBranch($query);

        if ($request->filled('blood_group')) {
            $query->where('blood_group', $request->blood_group);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('donor_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $donors = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('medical.blood-bank.donors.index', compact('donors'));
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $donorNumber = $this->sequences->peek(NumberSequence::TYPE_BLOOD_DONOR, $instituteId);
        $bloodGroups = BloodDonor::BLOOD_GROUPS;
        $genders = BloodDonor::GENDERS;

        return view('medical.blood-bank.donors.create', compact('donorNumber', 'bloodGroups', 'genders'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'required|string|in:' . implode(',', array_keys(BloodDonor::GENDERS)),
            'blood_group' => 'required|string|in:' . implode(',', array_keys(BloodDonor::BLOOD_GROUPS)),
            'weight_kg' => 'nullable|numeric|min:30',
            'hemoglobin' => 'nullable|numeric|min:0|max:20',
            'medical_history' => 'nullable|string',
            'is_eligible' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $instituteId = $this->instituteId();
        $data = $request->all();
        $data['institute_id'] = $instituteId;
        $data['branch_id'] = $request->branch_id ?? $this->branchContextId();
        $data['donor_number'] = $this->sequences->next(NumberSequence::TYPE_BLOOD_DONOR, $instituteId);
        $data['status'] = $data['status'] ?? 'active';
        $data['is_eligible'] = $request->boolean('is_eligible', true);

        $donor = BloodDonor::create($data);

        ClinicalAuditLog::record($donor, 'created');

        return redirect()
            ->route('medical.blood-bank.donors.show', $donor)
            ->with('status', 'Blood donor registered: ' . $donor->donor_number);
    }

    public function show(BloodDonor $donor)
    {
        $this->ensureSameInstitute($donor, 'blood_donor');
        $this->ensureBranchAccess($donor, 'branch_id', 'blood_donor');
        $donor->load(['bloodUnits']);

        return view('medical.blood-bank.donors.show', ['donor' => $donor]);
    }

    public function edit(BloodDonor $donor)
    {
        $this->ensureSameInstitute($donor, 'blood_donor');
        $this->ensureBranchAccess($donor, 'branch_id', 'blood_donor');

        $bloodGroups = BloodDonor::BLOOD_GROUPS;
        $genders = BloodDonor::GENDERS;
        $statuses = BloodDonor::STATUSES;

        return view('medical.blood-bank.donors.edit', [
            'donor' => $donor,
            'bloodGroups' => $bloodGroups,
            'genders' => $genders,
            'statuses' => $statuses,
        ]);
    }

    public function update(Request $request, BloodDonor $donor)
    {
        $this->ensureSameInstitute($donor, 'blood_donor');
        $this->ensureBranchAccess($donor, 'branch_id', 'blood_donor');

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'required|string|in:' . implode(',', array_keys(BloodDonor::GENDERS)),
            'blood_group' => 'required|string|in:' . implode(',', array_keys(BloodDonor::BLOOD_GROUPS)),
            'weight_kg' => 'nullable|numeric|min:30',
            'hemoglobin' => 'nullable|numeric|min:0|max:20',
            'medical_history' => 'nullable|string',
            'is_eligible' => 'nullable|boolean',
            'status' => 'nullable|string|in:' . implode(',', array_keys(BloodDonor::STATUSES)),
            'notes' => 'nullable|string',
        ]);

        $original = ClinicalAuditLog::snapshot($donor);

        $donor->update($request->only([
            'first_name', 'last_name', 'phone', 'email', 'date_of_birth',
            'gender', 'blood_group', 'weight_kg', 'hemoglobin',
            'medical_history', 'is_eligible', 'status', 'notes',
        ]));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($donor->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($donor, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.blood-bank.donors.show', $donor)
            ->with('status', 'Blood donor updated.');
    }

    public function destroy(BloodDonor $donor)
    {
        $this->ensureSameInstitute($donor, 'blood_donor');
        $this->ensureBranchAccess($donor, 'branch_id', 'blood_donor');

        ClinicalAuditLog::record($donor, 'deleted');

        $donor->delete();

        return redirect()
            ->route('medical.blood-bank.donors.index')
            ->with('status', 'Blood donor deleted.');
    }
}
