<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\AmbulanceDriver;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\NumberSequence;
use App\Services\Medical\NumberSequenceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AmbulanceDriverController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.ambulance.view', only: ['index', 'show']),
            new Middleware('permission:medical.ambulance.driver.manage', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function __construct(
        private readonly NumberSequenceService $sequences,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = AmbulanceDriver::forInstitute($instituteId);
        $this->scopeBranch($query);

        if ($request->filled('status')) {
            $query->where('ambulance_drivers.status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ambulance_drivers.name', 'like', "%{$search}%")
                    ->orWhere('ambulance_drivers.driver_number', 'like', "%{$search}%")
                    ->orWhere('ambulance_drivers.phone', 'like', "%{$search}%");
            });
        }

        $drivers = $query->orderBy('ambulance_drivers.name')->paginate(25)->withQueryString();

        return view('medical.ambulance.drivers.index', compact('drivers'));
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $driverNumber = $this->sequences->peek(NumberSequence::TYPE_AMBULANCE_DRIVER, $instituteId);

        return view('medical.ambulance.drivers.create', [
            'driverNumber' => $driverNumber,
            'statuses' => AmbulanceDriver::STATUSES,
            'employeeTypes' => AmbulanceDriver::EMPLOYEE_TYPES,
            'licenseTypes' => AmbulanceDriver::LICENSE_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:150',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|max:10',
            'address' => 'nullable|string',
            'license_number' => 'nullable|string|max:50',
            'license_type' => 'nullable|string|in:' . implode(',', array_keys(AmbulanceDriver::LICENSE_TYPES)),
            'license_expiry' => 'nullable|date',
            'employee_type' => 'nullable|string|in:' . implode(',', array_keys(AmbulanceDriver::EMPLOYEE_TYPES)),
            'joined_date' => 'nullable|date',
            'medical_fitness_expiry' => 'nullable|date',
            'emergency_contact' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $instituteId = $this->instituteId();

        $driver = AmbulanceDriver::create([
            'institute_id' => $instituteId,
            'branch_id' => $this->resolveBranchId($request->branch_id),
            'driver_number' => $this->sequences->next(NumberSequence::TYPE_AMBULANCE_DRIVER, $instituteId),
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'address' => $request->address,
            'license_number' => $request->license_number,
            'license_type' => $request->license_type,
            'license_expiry' => $request->license_expiry,
            'employee_type' => $request->employee_type ?? 'full_time',
            'joined_date' => $request->joined_date,
            'status' => 'active',
            'medical_fitness_expiry' => $request->medical_fitness_expiry,
            'emergency_contact' => $request->emergency_contact,
            'notes' => $request->notes,
        ]);

        ClinicalAuditLog::record($driver, 'created');

        return redirect()
            ->route('medical.ambulance.drivers.show', $driver)
            ->with('status', "Driver registered: {$driver->driver_number}.");
    }

    public function show(AmbulanceDriver $driver)
    {
        $this->ensureSameInstitute($driver, 'driver');
        $this->ensureBranchAccess($driver, 'branch_id', 'driver');
        $driver->load(['trips' => fn ($q) => $q->orderByDesc('requested_at')->limit(10)]);

        return view('medical.ambulance.drivers.show', compact('driver'));
    }

    public function edit(AmbulanceDriver $driver)
    {
        $this->ensureSameInstitute($driver, 'driver');
        $this->ensureBranchAccess($driver, 'branch_id', 'driver');

        return view('medical.ambulance.drivers.edit', [
            'driver' => $driver,
            'statuses' => AmbulanceDriver::STATUSES,
            'employeeTypes' => AmbulanceDriver::EMPLOYEE_TYPES,
            'licenseTypes' => AmbulanceDriver::LICENSE_TYPES,
        ]);
    }

    public function update(Request $request, AmbulanceDriver $driver)
    {
        $this->ensureSameInstitute($driver, 'driver');
        $this->ensureBranchAccess($driver, 'branch_id', 'driver');

        $request->validate([
            'name' => 'required|string|max:200',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:150',
            'license_number' => 'nullable|string|max:50',
            'license_type' => 'nullable|string|in:' . implode(',', array_keys(AmbulanceDriver::LICENSE_TYPES)),
            'license_expiry' => 'nullable|date',
            'employee_type' => 'nullable|string|in:' . implode(',', array_keys(AmbulanceDriver::EMPLOYEE_TYPES)),
            'status' => 'nullable|string|in:' . implode(',', array_keys(AmbulanceDriver::STATUSES)),
            'medical_fitness_expiry' => 'nullable|date',
            'emergency_contact' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $original = ClinicalAuditLog::snapshot($driver);
        $driver->update($request->only([
            'name', 'phone', 'email', 'license_number', 'license_type',
            'license_expiry', 'employee_type', 'status',
            'medical_fitness_expiry', 'emergency_contact', 'notes',
        ]));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($driver->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($driver, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.ambulance.drivers.show', $driver)
            ->with('status', 'Driver updated.');
    }

    public function destroy(AmbulanceDriver $driver)
    {
        $this->ensureSameInstitute($driver, 'driver');
        $this->ensureBranchAccess($driver, 'branch_id', 'driver');

        ClinicalAuditLog::record($driver, 'deleted');
        $driver->delete();

        return redirect()
            ->route('medical.ambulance.drivers.index')
            ->with('status', 'Driver removed.');
    }
}
