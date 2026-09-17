<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\Ambulance;
use App\Models\Medical\AmbulanceDriver;
use App\Models\Medical\AmbulanceTrip;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\NumberSequence;
use App\Services\Medical\AmbulanceDispatchService;
use App\Services\Medical\NumberSequenceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AmbulanceTripController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.ambulance.view', only: ['index', 'show', 'fareEstimate']),
            new Middleware('permission:medical.ambulance.trip.create', only: ['create', 'store', 'edit', 'update', 'destroy']),
            new Middleware('permission:medical.ambulance.trip.dispatch', only: ['dispatch', 'updateStatus']),
            new Middleware('permission:medical.ambulance.trip.complete', only: ['cancel']),
        ];
    }

    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly AmbulanceDispatchService $dispatch,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = AmbulanceTrip::forInstitute($instituteId);
        $this->scopeBranch($query);

        if ($request->filled('status')) {
            $query->where('ambulance_trips.status', $request->status);
        }
        if ($request->filled('trip_type')) {
            $query->where('ambulance_trips.trip_type', $request->trip_type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ambulance_trips.trip_number', 'like', "%{$search}%")
                    ->orWhere('ambulance_trips.pickup_location', 'like', "%{$search}%")
                    ->orWhere('ambulance_trips.dropoff_location', 'like', "%{$search}%");
            });
        }

        $trips = $query->with(['ambulance', 'driver', 'patient'])
            ->orderByDesc('ambulance_trips.requested_at')->paginate(25)->withQueryString();

        return view('medical.ambulance.trips.index', [
            'trips' => $trips,
            'types' => AmbulanceTrip::TRIP_TYPES,
            'statuses' => AmbulanceTrip::STATUSES,
        ]);
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $tripNumber = $this->sequences->peek(NumberSequence::TYPE_AMBULANCE_TRIP, $instituteId);
        $patients = \App\Models\Medical\Patient::where('institute_id', $instituteId)->active()->patients()->orderBy('first_name')->get();
        $ambulances = $this->dispatch->findAvailableAmbulances($instituteId);
        $drivers = $this->dispatch->findAvailableDrivers($instituteId);

        return view('medical.ambulance.trips.create', [
            'tripNumber' => $tripNumber,
            'patients' => $patients,
            'ambulances' => $ambulances,
            'drivers' => $drivers,
            'types' => AmbulanceTrip::TRIP_TYPES,
            'priorities' => AmbulanceTrip::PRIORITIES,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'trip_type' => 'required|string|in:' . implode(',', array_keys(AmbulanceTrip::TRIP_TYPES)),
            'pickup_location' => 'required|string|max:255',
            'pickup_address' => 'nullable|string',
            'dropoff_location' => 'required|string|max:255',
            'dropoff_address' => 'nullable|string',
            'patient_id' => 'nullable|exists:patients,id',
            'priority' => 'nullable|string|in:' . implode(',', array_keys(AmbulanceTrip::PRIORITIES)),
            'patient_condition_at_pickup' => 'nullable|string',
            'dispatch_notes' => 'nullable|string',
            'base_fee' => 'nullable|numeric|min:0',
        ]);

        $instituteId = $this->instituteId();

        $trip = AmbulanceTrip::create([
            'institute_id' => $instituteId,
            'branch_id' => $this->resolveBranchId($request->branch_id),
            'trip_number' => $this->sequences->next(NumberSequence::TYPE_AMBULANCE_TRIP, $instituteId),
            'patient_id' => $request->patient_id,
            'emergency_visit_id' => $request->emergency_visit_id,
            'admission_id' => $request->admission_id,
            'trip_type' => $request->trip_type,
            'pickup_location' => $request->pickup_location,
            'pickup_address' => $request->pickup_address,
            'pickup_lat' => $request->pickup_lat,
            'pickup_lng' => $request->pickup_lng,
            'dropoff_location' => $request->dropoff_location,
            'dropoff_address' => $request->dropoff_address,
            'dropoff_lat' => $request->dropoff_lat,
            'dropoff_lng' => $request->dropoff_lng,
            'patient_condition_at_pickup' => $request->patient_condition_at_pickup,
            'requested_at' => now(),
            'status' => 'requested',
            'priority' => $request->priority ?? 'routine',
            'base_fee' => $request->base_fee ?? 0,
            'payment_status' => 'pending',
            'dispatch_notes' => $request->dispatch_notes,
        ]);

        ClinicalAuditLog::record($trip, 'created');

        return redirect()
            ->route('medical.ambulance.trips.show', $trip)
            ->with('status', "Trip requested: {$trip->trip_number}.");
    }

    public function show(AmbulanceTrip $trip)
    {
        $this->ensureSameInstitute($trip, 'trip');
        $this->ensureBranchAccess($trip, 'branch_id', 'trip');
        $trip->load(['ambulance', 'driver', 'patient', 'attendant']);

        $availableAmbulances = $trip->status === 'requested'
            ? $this->dispatch->findAvailableAmbulances($trip->institute_id)
            : collect();
        $availableDrivers = $trip->status === 'requested'
            ? $this->dispatch->findAvailableDrivers($trip->institute_id)
            : collect();

        return view('medical.ambulance.trips.show', compact('trip', 'availableAmbulances', 'availableDrivers'));
    }

    public function edit(AmbulanceTrip $trip)
    {
        $this->ensureSameInstitute($trip, 'trip');
        $this->ensureBranchAccess($trip, 'branch_id', 'trip');

        return view('medical.ambulance.trips.edit', [
            'trip' => $trip,
            'types' => AmbulanceTrip::TRIP_TYPES,
            'priorities' => AmbulanceTrip::PRIORITIES,
        ]);
    }

    public function update(Request $request, AmbulanceTrip $trip)
    {
        $this->ensureSameInstitute($trip, 'trip');
        $this->ensureBranchAccess($trip, 'branch_id', 'trip');

        $request->validate([
            'trip_type' => 'required|string|in:' . implode(',', array_keys(AmbulanceTrip::TRIP_TYPES)),
            'pickup_location' => 'required|string|max:255',
            'dropoff_location' => 'required|string|max:255',
            'priority' => 'nullable|string|in:' . implode(',', array_keys(AmbulanceTrip::PRIORITIES)),
            'odometer_start_km' => 'nullable|numeric|min:0',
            'odometer_end_km' => 'nullable|numeric|min:0',
            'distance_fee' => 'nullable|numeric|min:0',
            'waiting_fee' => 'nullable|numeric|min:0',
        ]);

        $original = ClinicalAuditLog::snapshot($trip);
        $trip->update($request->only([
            'trip_type', 'pickup_location', 'pickup_address', 'dropoff_location',
            'dropoff_address', 'priority', 'odometer_start_km', 'odometer_end_km',
            'distance_fee', 'waiting_fee', 'patient_condition_at_pickup',
            'treatment_en_route', 'driver_notes', 'dispatch_notes',
        ]));
        $trip->update(['total_fee' => $trip->calculateTotalFee()]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($trip->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($trip, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.ambulance.trips.show', $trip)
            ->with('status', 'Trip updated.');
    }

    public function destroy(AmbulanceTrip $trip)
    {
        $this->ensureSameInstitute($trip, 'trip');
        $this->ensureBranchAccess($trip, 'branch_id', 'trip');

        ClinicalAuditLog::record($trip, 'deleted');
        $trip->delete();

        return redirect()
            ->route('medical.ambulance.trips.index')
            ->with('status', 'Trip deleted.');
    }

    public function dispatch(Request $request, AmbulanceTrip $trip)
    {
        $this->ensureSameInstitute($trip, 'trip');
        $this->ensureBranchAccess($trip, 'branch_id', 'trip');

        $request->validate([
            'ambulance_id' => 'required|exists:ambulances,id',
            'driver_id' => 'required|exists:ambulance_drivers,id',
        ]);

        $ambulance = Ambulance::findOrFail($request->ambulance_id);
        $driver = AmbulanceDriver::findOrFail($request->driver_id);
        $this->ensureSameInstitute($ambulance, 'ambulance');
        $this->ensureSameInstitute($driver, 'driver');

        $original = ClinicalAuditLog::snapshot($trip);
        $this->dispatch->dispatch($trip, $ambulance, $driver);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($trip->refresh()));
        ClinicalAuditLog::record($trip, 'dispatched', ['old' => $old, 'new' => $new]);

        return redirect()
            ->route('medical.ambulance.trips.show', $trip)
            ->with('status', "Trip dispatched: {$ambulance->vehicle_number} + {$driver->name}.");
    }

    public function updateStatus(Request $request, AmbulanceTrip $trip)
    {
        $this->ensureSameInstitute($trip, 'trip');
        $this->ensureBranchAccess($trip, 'branch_id', 'trip');

        $request->validate([
            'status' => 'required|string|in:' . implode(',', array_keys(AmbulanceTrip::STATUSES)),
        ]);

        $original = ClinicalAuditLog::snapshot($trip);
        $this->dispatch->updateStatus($trip, $request->status);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($trip->refresh()));
        ClinicalAuditLog::record($trip, 'status_' . $request->status, ['old' => $old, 'new' => $new]);

        return back()->with('status', "Trip status: {$request->status}.");
    }

    public function cancel(Request $request, AmbulanceTrip $trip)
    {
        $this->ensureSameInstitute($trip, 'trip');
        $this->ensureBranchAccess($trip, 'branch_id', 'trip');

        $request->validate(['cancellation_reason' => 'required|string|max:2000']);

        $original = ClinicalAuditLog::snapshot($trip);
        $trip->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
        ]);
        $trip->ambulance?->update(['status' => 'available']);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($trip->refresh()));
        ClinicalAuditLog::record($trip, 'cancelled', ['old' => $old, 'new' => $new, 'reason' => $request->cancellation_reason]);

        return redirect()
            ->route('medical.ambulance.trips.show', $trip)
            ->with('status', 'Trip cancelled; ambulance released.');
    }

    public function fareEstimate(Request $request, AmbulanceTrip $trip)
    {
        $this->ensureSameInstitute($trip, 'trip');

        $request->validate([
            'ambulance_id' => 'required|exists:ambulances,id',
            'distance_km' => 'required|numeric|min:0',
            'waiting_minutes' => 'nullable|integer|min:0',
        ]);

        $ambulance = Ambulance::findOrFail($request->ambulance_id);
        $this->ensureSameInstitute($ambulance, 'ambulance');

        return response()->json(
            $this->dispatch->estimateFare($ambulance, (float) $request->distance_km, (int) ($request->waiting_minutes ?? 0))
        );
    }
}
