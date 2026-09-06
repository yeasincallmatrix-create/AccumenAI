<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\PatientRequest;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Models\Institute;
use App\Models\Medical\Patient;
use App\Services\Medical\MrNumberGenerator;
use App\Support\GeoHierarchy;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PatientController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_patients.view', only: ['index', 'show', 'history']),
            new Middleware('permission:medical_patients.create', only: ['create', 'store']),
            new Middleware('permission:medical_patients.edit', only: ['edit', 'update']),
            new Middleware('permission:medical_patients.delete', only: ['destroy']),
        ];
    }

    protected MrNumberGenerator $mrGenerator;

    public function __construct(MrNumberGenerator $mrGenerator)
    {
        $this->mrGenerator = $mrGenerator;
    }

    /**
     * List all patients with search.
     */
    public function index(Request $request)
    {
        $query = Patient::where('institute_id', $this->instituteId());

        // Search by MR number, name, or phone.
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('mr_number', 'LIKE', "%{$search}%")
                    ->orWhere('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        // Filter by active status.
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $patients = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return view('medical.patients.index', compact('patients'));
    }

    /**
     * Show patient registration form.
     */
    public function create()
    {
        $patient = new Patient(['is_active' => true]);
        $this->defaultAddressCountry($patient);
        $presentAddress = $this->addressData($patient);

        return view('medical.patients.create', compact('patient', 'presentAddress'));
    }

    /**
     * Store a new patient.
     */
    public function store(PatientRequest $request)
    {
        $instituteId = $this->instituteId();

        $data = $request->validated();
        $data['institute_id'] = $instituteId;
        $data['mr_number'] = $this->mrGenerator->generate($instituteId);

        $patient = Patient::create($data);

        return redirect()->route('medical.patients.show', $patient)
            ->with('status', 'Patient registered successfully! MR: '.$patient->mr_number);
    }

    /**
     * Show patient profile with history.
     */
    public function show(Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');

        $patient->load([
            'appointments' => function ($q) {
                $q->whereDate('appointment_date', '>=', now()->subDays(30))
                    ->orderBy('appointment_date', 'desc')
                    ->orderBy('appointment_time', 'desc');
            },
            'admissions' => function ($q) {
                $q->orderBy('admission_date', 'desc');
            },
            'prescriptions' => function ($q) {
                $q->orderBy('prescription_date', 'desc')->limit(5);
            },
            'labOrders' => function ($q) {
                $q->orderBy('order_date', 'desc')->limit(5);
            },
            'invoices' => function ($q) {
                $q->orderBy('invoice_date', 'desc')->limit(5);
            },
        ]);

        return view('medical.patients.show', compact('patient'));
    }

    /**
     * Show patient edit form.
     */
    public function edit(Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');
        $presentAddress = $this->addressData($patient);

        return view('medical.patients.edit', compact('patient', 'presentAddress'));
    }

    /**
     * Update patient.
     */
    public function update(PatientRequest $request, Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');
        $patient->update($request->validated());

        return redirect()->route('medical.patients.show', $patient)
            ->with('status', 'Patient updated successfully!');
    }

    /**
     * Delete patient (soft delete).
     */
    public function destroy(Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');
        $patient->delete();

        return redirect()->route('medical.patients.index')
            ->with('status', 'Patient deleted successfully!');
    }

    /**
     * Show patient medical history.
     */
    public function history(Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');

        $patient->load([
            'appointments' => function ($q) {
                $q->orderBy('appointment_date', 'desc');
            },
            'admissions' => function ($q) {
                $q->orderBy('admission_date', 'desc');
            },
            'prescriptions' => function ($q) {
                $q->orderBy('prescription_date', 'desc');
            },
            'labOrders' => function ($q) {
                $q->orderBy('order_date', 'desc');
            },
        ]);

        return view('medical.patients.history', compact('patient'));
    }

    /**
     * Data for the country-neutral <x-address> component (same pattern as
     * StudentController::addressData, present-address only for patients).
     */
    private function addressData(Patient $patient): array
    {
        $countryId = (int) ($patient->getAttribute('present_country_id') ?? 0) ?: 0;
        $country = $countryId ? Country::find($countryId) : null;

        $levelOptions = [1 => [], 2 => [], 3 => []];

        if ($country) {
            $levels = $country->selectableLevels()->orderBy('level_number')->get();
            foreach ($levels as $level) {
                $query = AdministrativeUnit::query()
                    ->where('country_id', $country->id)
                    ->where('administrative_level_id', $level->id)
                    ->where('status', true);

                if ($level->level_number > 1) {
                    $parentAttr = 'present_admin_'.($level->level_number - 1).'_id';
                    $query->where('parent_id', (int) ($patient->getAttribute($parentAttr) ?? 0));
                } else {
                    $query->whereNull('parent_id');
                }

                $levelOptions[$level->level_number] = $query
                    ->orderBy('name')
                    ->get()
                    ->pluck('name', 'id')
                    ->all();
            }
        }

        return [
            'country' => $country,
            'level_labels' => $country ? GeoHierarchy::levelLabels($country) : [],
            'level_options' => $levelOptions,
        ];
    }

    /**
     * Default a brand-new patient's address selection to the institute's
     * country so the address cascades render immediately.
     */
    private function defaultAddressCountry(Patient $patient): void
    {
        if ($patient->present_country_id || $patient->exists) {
            return;
        }

        $countryId = Institute::query()->where('id', $this->instituteId())->value('country_id');
        if ($countryId && Country::whereKey($countryId)->exists()) {
            $patient->present_country_id = $countryId;
        }
    }
}
