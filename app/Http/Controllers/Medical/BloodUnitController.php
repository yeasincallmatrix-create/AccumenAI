<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\BloodDonor;
use App\Models\Medical\BloodUnit;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\NumberSequence;
use App\Services\Medical\BloodBankService;
use App\Services\Medical\NumberSequenceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BloodUnitController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_bloodbank.view', only: ['index', 'show']),
            new Middleware('permission:medical_bloodbank.create', only: ['create', 'store']),
            new Middleware('permission:medical_bloodbank.edit', only: ['edit', 'update', 'screen', 'expire', 'discard']),
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

        $query = BloodUnit::where('institute_id', $instituteId);
        $this->scopeBranch($query);

        if ($request->filled('blood_group')) {
            $query->where('blood_group', $request->blood_group);
        }
        if ($request->filled('component')) {
            $query->where('component', $request->component);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('expiring_soon')) {
            $query->where('status', 'available')
                ->where('expiry_date', '>', now())
                ->where('expiry_date', '<=', now()->addDays(7));
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('unit_number', 'like', "%{$search}%")
                    ->orWhere('blood_group', 'like', "%{$search}%")
                    ->orWhereHas('donor', fn ($dq) => $dq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $units = $query->with(['donor', 'currentPatient'])->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('medical.blood-bank.units.index', compact('units'));
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $unitNumber = $this->sequences->peek(NumberSequence::TYPE_BLOOD_UNIT, $instituteId);
        $bloodGroups = BloodDonor::BLOOD_GROUPS;
        $components = BloodUnit::COMPONENTS;
        $donors = \App\Models\Medical\BloodDonor::where('institute_id', $instituteId)->active()->orderBy('first_name')->get();

        return view('medical.blood-bank.units.create', compact('unitNumber', 'bloodGroups', 'components', 'donors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'blood_group' => 'required|string|in:' . implode(',', array_keys(BloodDonor::BLOOD_GROUPS)),
            'component' => 'required|string|in:' . implode(',', array_keys(BloodUnit::COMPONENTS)),
            'volume_ml' => 'nullable|integer|min:0',
            'collection_date' => 'required|date',
            'expiry_date' => 'required|date|after:collection_date',
            'donor_id' => 'nullable|exists:blood_donors,id',
            'crossmatch_required' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $instituteId = $this->instituteId();
        $data = $request->all();
        $data['institute_id'] = $instituteId;
        $data['branch_id'] = $request->branch_id ?? $this->branchContextId();
        $data['unit_number'] = $this->sequences->next(NumberSequence::TYPE_BLOOD_UNIT, $instituteId);
        $data['status'] = 'available';
        $data['volume_ml'] = $data['volume_ml'] ?? 450;
        $data['donor_id'] = $data['donor_id'] ?? null;

        $unit = BloodUnit::create($data);

        ClinicalAuditLog::record($unit, 'created');

        return redirect()
            ->route('medical.blood-bank.units.show', $unit)
            ->with('status', 'Blood unit created: ' . $unit->unit_number);
    }

    public function show(BloodUnit $unit)
    {
        $this->ensureSameInstitute($unit, 'blood_unit');
        $this->ensureBranchAccess($unit, 'branch_id', 'blood_unit');
        $unit->load(['donor', 'currentPatient', 'issueItems']);

        return view('medical.blood-bank.units.show', ['unit' => $unit]);
    }

    public function edit(BloodUnit $unit)
    {
        $this->ensureSameInstitute($unit, 'blood_unit');
        $this->ensureBranchAccess($unit, 'branch_id', 'blood_unit');

        $bloodGroups = BloodDonor::BLOOD_GROUPS;
        $components = BloodUnit::COMPONENTS;
        $statuses = BloodUnit::STATUSES;

        return view('medical.blood-bank.units.edit', [
            'unit' => $unit,
            'bloodGroups' => $bloodGroups,
            'components' => $components,
            'statuses' => $statuses,
        ]);
    }

    public function update(Request $request, BloodUnit $unit)
    {
        $this->ensureSameInstitute($unit, 'blood_unit');
        $this->ensureBranchAccess($unit, 'branch_id', 'blood_unit');

        $request->validate([
            'blood_group' => 'required|string|in:' . implode(',', array_keys(BloodDonor::BLOOD_GROUPS)),
            'component' => 'required|string|in:' . implode(',', array_keys(BloodUnit::COMPONENTS)),
            'volume_ml' => 'nullable|integer|min:0',
            'collection_date' => 'required|date',
            'expiry_date' => 'required|date|after:collection_date',
            'status' => 'nullable|string|in:' . implode(',', array_keys(BloodUnit::STATUSES)),
            'crossmatch_required' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $original = ClinicalAuditLog::snapshot($unit);

        $unit->update($request->only([
            'blood_group', 'component', 'volume_ml', 'collection_date',
            'expiry_date', 'status', 'crossmatch_required', 'notes',
        ]));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($unit->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($unit, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.blood-bank.units.show', $unit)
            ->with('status', 'Blood unit updated.');
    }

    public function destroy(BloodUnit $unit)
    {
        $this->ensureSameInstitute($unit, 'blood_unit');
        $this->ensureBranchAccess($unit, 'branch_id', 'blood_unit');

        ClinicalAuditLog::record($unit, 'deleted');

        $unit->delete();

        return redirect()
            ->route('medical.blood-bank.units.index')
            ->with('status', 'Blood unit deleted.');
    }

    public function screen(Request $request, BloodUnit $unit)
    {
        $this->ensureSameInstitute($unit, 'blood_unit');
        $this->ensureBranchAccess($unit, 'branch_id', 'blood_unit');

        $request->validate([
            'screening_hiv' => 'required|string|in:' . implode(',', array_keys(BloodUnit::SCREENING_STATUSES)),
            'screening_hbsag' => 'required|string|in:' . implode(',', array_keys(BloodUnit::SCREENING_STATUSES)),
            'screening_hcv' => 'required|string|in:' . implode(',', array_keys(BloodUnit::SCREENING_STATUSES)),
            'screening_syphilis' => 'required|string|in:' . implode(',', array_keys(BloodUnit::SCREENING_STATUSES)),
            'screening_malaria' => 'required|string|in:' . implode(',', array_keys(BloodUnit::SCREENING_STATUSES)),
        ]);

        $original = ClinicalAuditLog::snapshot($unit);

        $unit->update($request->only([
            'screening_hiv', 'screening_hbsag', 'screening_hcv',
            'screening_syphilis', 'screening_malaria',
        ]));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($unit->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($unit, 'screened', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.blood-bank.units.show', $unit)
            ->with('status', 'Screening results updated.');
    }

    public function expire(BloodUnit $unit)
    {
        $this->ensureSameInstitute($unit, 'blood_unit');
        $this->ensureBranchAccess($unit, 'branch_id', 'blood_unit');

        $count = $this->bloodBankService->markExpired($unit->institute_id);

        return redirect()
            ->route('medical.blood-bank.units.index')
            ->with('status', "{$count} unit(s) marked as expired.");
    }

    public function discard(Request $request, BloodUnit $unit)
    {
        $this->ensureSameInstitute($unit, 'blood_unit');
        $this->ensureBranchAccess($unit, 'branch_id', 'blood_unit');

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->bloodBankService->discardUnit($unit, $request->reason);

        return redirect()
            ->route('medical.blood-bank.units.show', $unit)
            ->with('status', 'Blood unit discarded.');
    }
}
