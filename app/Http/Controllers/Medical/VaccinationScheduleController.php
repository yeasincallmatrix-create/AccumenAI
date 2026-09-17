<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\VaccinationSchedule;
use App\Models\Medical\VaccineMaster;
use App\Models\Medical\Patient;
use App\Services\Medical\VaccinationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class VaccinationScheduleController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.vaccination.view', only: ['index', 'show']),
            new Middleware('permission:medical.vaccination.schedule', only: ['create', 'store']),
            new Middleware('permission:medical.vaccination.administer', only: ['administer', 'recordVaccination']),
        ];
    }

    public function __construct(
        private readonly VaccinationService $vaccinationService,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = VaccinationSchedule::forInstitute($instituteId);
        $this->scopeBranch($query);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('vaccine_master_id')) {
            $query->where('vaccine_master_id', $request->vaccine_master_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('patient', fn ($pq) => $pq->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('vaccineMaster', fn ($vq) => $vq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('filter') && $request->filter === 'due_today') {
            $query->dueToday();
        } elseif ($request->filled('filter') && $request->filter === 'overdue') {
            $query->overdue();
        }

        $schedules = $query->with(['patient', 'vaccineMaster'])->orderByDesc('due_date')->paginate(25)->withQueryString();
        $vaccines = VaccineMaster::forInstitute($instituteId)->active()->orderBy('name')->get();

        return view('medical.vaccination.schedules.index', compact('schedules', 'vaccines'));
    }

    public function show(VaccinationSchedule $schedule)
    {
        $schedule->load(['patient', 'vaccineMaster', 'records.administeredBy']);

        return view('medical.vaccination.schedules.show', ['schedule' => $schedule]);
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $patients = Patient::where('institute_id', $instituteId)->active()->patients()->orderBy('first_name')->get();
        $vaccines = VaccineMaster::forInstitute($instituteId)->active()->orderBy('name')->get();

        return view('medical.vaccination.schedules.create', compact('patients', 'vaccines'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'vaccine_master_id' => 'required|exists:vaccine_masters,id',
            'due_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $instituteId = $this->instituteId();
        $vaccine = VaccineMaster::findOrFail($request->vaccine_master_id);

        $created = $this->vaccinationService->generateEpiSchedule(
            $instituteId,
            $request->patient_id,
            \App\Models\Medical\Patient::find($request->patient_id)->date_of_birth ?? now()->subYears(5)
        );

        if ($created !== []) {
            return redirect()
                ->route('medical.vaccination.schedules.index')
                ->with('status', count($created) . ' vaccination schedules generated from EPI calendar.');
        }

        $nextDose = VaccinationSchedule::where('institute_id', $instituteId)
            ->where('patient_id', $request->patient_id)
            ->where('vaccine_master_id', $request->vaccine_master_id)
            ->max('dose_number');

        VaccinationSchedule::create([
            'institute_id' => $instituteId,
            'branch_id' => $this->resolveBranchId($request->branch_id),
            'patient_id' => $request->patient_id,
            'vaccine_master_id' => $request->vaccine_master_id,
            'dose_number' => ($nextDose ?? 0) + 1,
            'due_date' => $request->due_date,
            'status' => 'scheduled',
            'notes' => $request->notes,
        ]);

        return redirect()
            ->route('medical.vaccination.schedules.index')
            ->with('status', 'Vaccination schedule created.');
    }

    public function administer(VaccinationSchedule $schedule)
    {
        $schedule->load(['patient', 'vaccineMaster']);

        return view('medical.vaccination.schedules.administer', ['schedule' => $schedule]);
    }

    public function recordVaccination(Request $request, VaccinationSchedule $schedule)
    {
        $request->validate([
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

        $userId = auth()->id();
        $record = $this->vaccinationService->recordVaccination($schedule, $request->all(), $userId);

        return redirect()
            ->route('medical.vaccination.schedules.show', $schedule)
            ->with('status', 'Vaccination recorded. Record: ' . $record->record_number);
    }
}
