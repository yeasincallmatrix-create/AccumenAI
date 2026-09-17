<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\Ambulance;
use App\Models\Medical\ClinicalAuditLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AmbulanceController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.ambulance.view', only: ['index', 'show']),
            new Middleware('permission:medical.ambulance.fleet.manage', only: ['create', 'store', 'edit', 'update', 'destroy', 'updateStatus']),
        ];
    }

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = Ambulance::forInstitute($instituteId);
        $this->scopeBranch($query);

        if ($request->filled('status')) {
            $query->where('ambulances.status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('ambulances.type', $request->type);
        }
        if ($request->filled('search')) {
            $query->where('ambulances.vehicle_number', 'like', "%{$request->search}%");
        }

        $vehicles = $query->orderBy('ambulances.vehicle_number')->paginate(25)->withQueryString();
        $types = Ambulance::TYPES;
        $statuses = Ambulance::STATUSES;

        return view('medical.ambulance.vehicles.index', compact('vehicles', 'types', 'statuses'));
    }

    public function create()
    {
        $types = Ambulance::TYPES;
        $equipment = Ambulance::EQUIPMENT_ITEMS;

        return view('medical.ambulance.vehicles.create', compact('types', 'equipment'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vehicle_number' => 'required|string|max:50',
            'registration_number' => 'nullable|string|max:50',
            'make' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1990|max:' . (date('Y') + 1),
            'type' => 'required|string|in:' . implode(',', array_keys(Ambulance::TYPES)),
            'fuel_type' => 'nullable|string|max:30',
            'capacity_patients' => 'nullable|integer|min:1',
            'capacity_attendants' => 'nullable|integer|min:0',
            'equipment' => 'nullable|array',
            'last_service_date' => 'nullable|date',
            'next_service_date' => 'nullable|date',
            'insurance_expiry' => 'nullable|date',
            'fitness_expiry' => 'nullable|date',
            'odometer_km' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $instituteId = $this->instituteId();

        if (Ambulance::where('institute_id', $instituteId)->where('vehicle_number', $request->vehicle_number)->exists()) {
            return back()->withErrors(['vehicle_number' => 'This vehicle number already exists in your institute.'])->withInput();
        }

        $vehicle = Ambulance::create([
            'institute_id' => $instituteId,
            'branch_id' => $this->resolveBranchId($request->branch_id),
            'vehicle_number' => $request->vehicle_number,
            'registration_number' => $request->registration_number,
            'make' => $request->make,
            'model' => $request->model,
            'year' => $request->year,
            'type' => $request->type,
            'fuel_type' => $request->fuel_type,
            'capacity_patients' => $request->capacity_patients ?? 1,
            'capacity_attendants' => $request->capacity_attendants ?? 2,
            'equipment' => $request->equipment,
            'status' => 'available',
            'last_service_date' => $request->last_service_date,
            'next_service_date' => $request->next_service_date,
            'insurance_expiry' => $request->insurance_expiry,
            'fitness_expiry' => $request->fitness_expiry,
            'odometer_km' => $request->odometer_km,
            'notes' => $request->notes,
            'is_active' => true,
        ]);

        ClinicalAuditLog::record($vehicle, 'created');

        return redirect()
            ->route('medical.ambulance.vehicles.show', $vehicle)
            ->with('status', "Ambulance registered: {$vehicle->vehicle_number}.");
    }

    public function show(Ambulance $vehicle)
    {
        $this->ensureSameInstitute($vehicle, 'ambulance');
        $this->ensureBranchAccess($vehicle, 'branch_id', 'ambulance');
        $vehicle->load(['trips' => fn ($q) => $q->orderByDesc('requested_at')->limit(10)]);

        return view('medical.ambulance.vehicles.show', compact('vehicle'));
    }

    public function edit(Ambulance $vehicle)
    {
        $this->ensureSameInstitute($vehicle, 'ambulance');
        $this->ensureBranchAccess($vehicle, 'branch_id', 'ambulance');

        return view('medical.ambulance.vehicles.edit', [
            'vehicle' => $vehicle,
            'types' => Ambulance::TYPES,
            'statuses' => Ambulance::STATUSES,
            'equipment' => Ambulance::EQUIPMENT_ITEMS,
        ]);
    }

    public function update(Request $request, Ambulance $vehicle)
    {
        $this->ensureSameInstitute($vehicle, 'ambulance');
        $this->ensureBranchAccess($vehicle, 'branch_id', 'ambulance');

        $request->validate([
            'make' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1990|max:' . (date('Y') + 1),
            'type' => 'required|string|in:' . implode(',', array_keys(Ambulance::TYPES)),
            'fuel_type' => 'nullable|string|max:30',
            'equipment' => 'nullable|array',
            'last_service_date' => 'nullable|date',
            'next_service_date' => 'nullable|date',
            'insurance_expiry' => 'nullable|date',
            'fitness_expiry' => 'nullable|date',
            'odometer_km' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $original = ClinicalAuditLog::snapshot($vehicle);
        $vehicle->update($request->only([
            'make', 'model', 'year', 'type', 'fuel_type', 'equipment',
            'last_service_date', 'next_service_date', 'insurance_expiry',
            'fitness_expiry', 'odometer_km', 'notes',
        ]) + ['is_active' => $request->boolean('is_active', true)]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($vehicle->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($vehicle, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.ambulance.vehicles.show', $vehicle)
            ->with('status', 'Ambulance updated.');
    }

    public function destroy(Ambulance $vehicle)
    {
        $this->ensureSameInstitute($vehicle, 'ambulance');
        $this->ensureBranchAccess($vehicle, 'branch_id', 'ambulance');

        ClinicalAuditLog::record($vehicle, 'deleted');
        $vehicle->delete();

        return redirect()
            ->route('medical.ambulance.vehicles.index')
            ->with('status', 'Ambulance removed.');
    }

    public function updateStatus(Request $request, Ambulance $vehicle)
    {
        $this->ensureSameInstitute($vehicle, 'ambulance');
        $this->ensureBranchAccess($vehicle, 'branch_id', 'ambulance');

        $request->validate([
            'status' => 'required|string|in:' . implode(',', array_keys(Ambulance::STATUSES)),
        ]);

        $original = ClinicalAuditLog::snapshot($vehicle);
        $vehicle->update(['status' => $request->status]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($vehicle->refresh()));
        ClinicalAuditLog::record($vehicle, 'status_changed', ['old' => $old, 'new' => $new]);

        return back()->with('status', "Ambulance status: {$request->status}.");
    }
}
