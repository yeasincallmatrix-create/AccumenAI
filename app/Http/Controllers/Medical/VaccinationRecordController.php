<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\NumberSequence;
use App\Models\Medical\VaccinationRecord;
use App\Models\Medical\VaccineMaster;
use App\Services\Medical\NumberSequenceService;
use App\Services\Medical\VaccinationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class VaccinationRecordController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.vaccination.view', only: ['index', 'show']),
            new Middleware('permission:medical.vaccination.administer', only: ['create', 'store']),
        ];
    }

    public function __construct(
        private readonly VaccinationService $vaccinationService,
        private readonly NumberSequenceService $sequences,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = VaccinationRecord::forInstitute($instituteId);
        $this->scopeBranch($query);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('record_number', 'like', "%{$search}%")
                    ->orWhere('certificate_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($pq) => $pq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('administered_date')) {
            $query->whereDate('administered_date', $request->administered_date);
        }

        $records = $query->with(['patient', 'vaccineMaster', 'administeredBy'])
            ->orderByDesc('administered_date')
            ->paginate(25)->withQueryString();

        return view('medical.vaccination.records.index', compact('records'));
    }

    public function show(VaccinationRecord $record)
    {
        $record->load(['patient', 'vaccineMaster', 'administeredBy', 'schedule']);

        return view('medical.vaccination.records.show', ['record' => $record]);
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $recordNumber = $this->sequences->peek(NumberSequence::TYPE_VACCINATION_RECORD, $instituteId);
        $vaccines = VaccineMaster::forInstitute($instituteId)->active()->orderBy('name')->get();
        $sites = VaccineMaster::SITES;
        $routes = VaccineMaster::ROUTES;
        $adverseEvents = VaccineMaster::ADVERSE_EVENTS;

        return view('medical.vaccination.records.create', compact(
            'recordNumber', 'vaccines', 'sites', 'routes', 'adverseEvents',
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'vaccine_master_id' => 'required|exists:vaccine_masters,id',
            'dose_number' => 'required|integer|min:1',
            'administered_date' => 'required|date',
            'site' => 'nullable|string|max:50',
            'route' => 'nullable|string|max:30',
            'dose_volume' => 'nullable|string|max:30',
            'batch_number' => 'nullable|string|max:50',
            'batch_expiry' => 'nullable|date',
            'manufacturer' => 'nullable|string|max:200',
            'pre_vaccination_notes' => 'nullable|string|max:2000',
            'post_vaccination_notes' => 'nullable|string|max:2000',
            'adverse_event' => 'nullable|string|in:' . implode(',', array_keys(VaccineMaster::ADVERSE_EVENTS)),
            'adverse_event_details' => 'nullable|string|max:2000',
            'fee' => 'nullable|numeric|min:0',
        ]);

        $instituteId = $this->instituteId();
        $data = $request->all();
        $data['institute_id'] = $instituteId;
        $data['branch_id'] = $this->resolveBranchId($request->branch_id);
        $data['record_number'] = $this->sequences->next(NumberSequence::TYPE_VACCINATION_RECORD, $instituteId);
        $data['administered_at'] = now();
        $data['administered_by'] = auth()->id();
        $data['adverse_event'] = $data['adverse_event'] ?? 'none';
        $data['fee'] = $data['fee'] ?? 0;
        $data['payment_status'] = 'pending';

        $record = VaccinationRecord::create($data);

        $certNumber = $this->vaccinationService->generateCertificate($record);

        return redirect()
            ->route('medical.vaccination.records.show', $record)
            ->with('status', 'Vaccination recorded: ' . $record->record_number . '. Certificate: ' . $certNumber);
    }
}
