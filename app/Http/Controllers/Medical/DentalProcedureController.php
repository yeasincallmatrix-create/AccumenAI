<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\DentalChart;
use App\Models\Medical\DentalProcedure;
use App\Models\Medical\NumberSequence;
use App\Models\User;
use App\Services\Medical\DentalService;
use App\Services\Medical\NumberSequenceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DentalProcedureController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.dental.view', only: ['index', 'show']),
            new Middleware('permission:medical.dental.procedure.create', only: ['create', 'store']),
            new Middleware('permission:medical.dental.procedure.edit', only: ['edit', 'update', 'markFollowedUp']),
            new Middleware('permission:medical.dental.procedure.create', only: ['destroy']),
        ];
    }

    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly DentalService $dentalService,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = DentalProcedure::where('institute_id', $instituteId);
        $this->scopeBranch($query);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('procedure_number', 'like', "%{$search}%")
                    ->orWhere('procedure_name', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($pq) => $pq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $procedures = $query->with(['patient', 'dentist'])->orderByDesc('performed_at')->paginate(25)->withQueryString();

        return view('medical.dental.procedures.index', compact('procedures'));
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $procedureNumber = $this->sequences->peek(NumberSequence::TYPE_DENTAL_PROCEDURE, $instituteId);
        $patients = \App\Models\Medical\Patient::where('institute_id', $instituteId)->active()->patients()->orderBy('first_name')->get();
        $dentists = User::where('status', 'active')->orderBy('name')->get();
        $categories = DentalProcedure::CATEGORIES;
        $anesthesiaTypes = DentalProcedure::ANESTHESIA_TYPES;

        return view('medical.dental.procedures.create', compact(
            'procedureNumber', 'patients', 'dentists', 'categories', 'anesthesiaTypes',
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'dentist_id' => 'required|exists:users,id',
            'procedure_name' => 'required|string|max:200',
            'procedure_code' => 'nullable|string|max:30',
            'category' => 'nullable|string|in:' . implode(',', array_keys(DentalProcedure::CATEGORIES)),
            'tooth_number' => 'nullable|string|in:' . implode(',', DentalChart::TOOTH_NUMBERS),
            'tooth_surface' => 'nullable|string|max:50',
            'quadrant' => 'nullable|string|in:' . implode(',', array_keys(DentalProcedure::QUADRANTS)),
            'diagnosis' => 'nullable|string|max:2000',
            'procedure_notes' => 'nullable|string',
            'anesthesia_type' => 'nullable|string|in:' . implode(',', array_keys(DentalProcedure::ANESTHESIA_TYPES)),
            'anesthesia_agent' => 'nullable|string|max:100',
            'anesthesia_volume_ml' => 'nullable|numeric|min:0',
            'medications_prescribed' => 'nullable|string|max:2000',
            'materials_used' => 'nullable|array',
            'performed_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:1',
            'follow_up_date' => 'nullable|date|after_or_equal:performed_at',
            'follow_up_instructions' => 'nullable|string|max:2000',
            'fee' => 'nullable|numeric|min:0',
        ]);

        $instituteId = $this->instituteId();
        $data = $request->all();
        $data['institute_id'] = $instituteId;
        $data['branch_id'] = $this->resolveBranchId($request->branch_id);
        $data['procedure_number'] = $this->sequences->next(NumberSequence::TYPE_DENTAL_PROCEDURE, $instituteId);
        $data['status'] = 'completed';
        $data['fee'] = $data['fee'] ?? 0;
        $data['payment_status'] = 'pending';

        if ($request->filled('tooth_number') && ! $request->filled('quadrant')) {
            $data['quadrant'] = DentalService::quadrantFromTooth($request->tooth_number);
        }

        $procedure = DentalProcedure::create($data);

        ClinicalAuditLog::record($procedure, 'created');

        return redirect()
            ->route('medical.dental.procedures.show', $procedure)
            ->with('status', 'Dental procedure recorded: ' . $procedure->procedure_number);
    }

    public function show(DentalProcedure $procedure)
    {
        $this->ensureSameInstitute($procedure, 'dental_procedure');
        $this->ensureBranchAccess($procedure, 'branch_id', 'dental_procedure');
        $procedure->load(['patient', 'dentist', 'dentalChart']);

        return view('medical.dental.procedures.show', ['procedure' => $procedure]);
    }

    public function edit(DentalProcedure $procedure)
    {
        $this->ensureSameInstitute($procedure, 'dental_procedure');
        $this->ensureBranchAccess($procedure, 'branch_id', 'dental_procedure');

        $patients = \App\Models\Medical\Patient::where('institute_id', $procedure->institute_id)->active()->patients()->orderBy('first_name')->get();
        $dentists = User::where('status', 'active')->orderBy('name')->get();
        $categories = DentalProcedure::CATEGORIES;
        $anesthesiaTypes = DentalProcedure::ANESTHESIA_TYPES;
        $statuses = DentalProcedure::STATUSES;

        return view('medical.dental.procedures.edit', [
            'procedure' => $procedure,
            'patients' => $patients,
            'dentists' => $dentists,
            'categories' => $categories,
            'anesthesiaTypes' => $anesthesiaTypes,
            'statuses' => $statuses,
        ]);
    }

    public function update(Request $request, DentalProcedure $procedure)
    {
        $this->ensureSameInstitute($procedure, 'dental_procedure');
        $this->ensureBranchAccess($procedure, 'branch_id', 'dental_procedure');

        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'dentist_id' => 'required|exists:users,id',
            'procedure_name' => 'required|string|max:200',
            'procedure_code' => 'nullable|string|max:30',
            'category' => 'nullable|string|in:' . implode(',', array_keys(DentalProcedure::CATEGORIES)),
            'tooth_number' => 'nullable|string|in:' . implode(',', DentalChart::TOOTH_NUMBERS),
            'tooth_surface' => 'nullable|string|max:50',
            'quadrant' => 'nullable|string|in:' . implode(',', array_keys(DentalProcedure::QUADRANTS)),
            'diagnosis' => 'nullable|string|max:2000',
            'procedure_notes' => 'nullable|string',
            'anesthesia_type' => 'nullable|string|in:' . implode(',', array_keys(DentalProcedure::ANESTHESIA_TYPES)),
            'anesthesia_agent' => 'nullable|string|max:100',
            'anesthesia_volume_ml' => 'nullable|numeric|min:0',
            'medications_prescribed' => 'nullable|string|max:2000',
            'materials_used' => 'nullable|array',
            'performed_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:1',
            'follow_up_date' => 'nullable|date',
            'follow_up_instructions' => 'nullable|string|max:2000',
            'fee' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:' . implode(',', array_keys(DentalProcedure::STATUSES)),
        ]);

        $original = ClinicalAuditLog::snapshot($procedure);

        $procedure->update($request->only([
            'patient_id', 'dentist_id', 'procedure_name', 'procedure_code',
            'category', 'tooth_number', 'tooth_surface', 'quadrant',
            'diagnosis', 'procedure_notes', 'anesthesia_type', 'anesthesia_agent',
            'anesthesia_volume_ml', 'medications_prescribed', 'materials_used',
            'performed_at', 'duration_minutes', 'follow_up_date',
            'follow_up_instructions', 'fee', 'status',
        ]));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($procedure->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($procedure, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.dental.procedures.show', $procedure)
            ->with('status', 'Dental procedure updated.');
    }

    public function destroy(DentalProcedure $procedure)
    {
        $this->ensureSameInstitute($procedure, 'dental_procedure');
        $this->ensureBranchAccess($procedure, 'branch_id', 'dental_procedure');

        ClinicalAuditLog::record($procedure, 'deleted');
        $procedure->delete();

        return redirect()
            ->route('medical.dental.procedures.index')
            ->with('status', 'Dental procedure deleted.');
    }

    public function markFollowedUp(DentalProcedure $procedure)
    {
        $this->ensureSameInstitute($procedure, 'dental_procedure');
        $this->ensureBranchAccess($procedure, 'branch_id', 'dental_procedure');

        $original = ClinicalAuditLog::snapshot($procedure);
        $procedure->update(['status' => 'followed_up']);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($procedure->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($procedure, 'followed_up', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.dental.procedures.show', $procedure)
            ->with('status', 'Procedure marked as followed up.');
    }
}
