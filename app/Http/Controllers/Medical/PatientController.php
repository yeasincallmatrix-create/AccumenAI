<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\PatientRequest;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Models\Institute;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Patient;
use App\Services\Medical\MrNumberGenerator;
use App\Support\CountryCodes;
use App\Support\GeoHierarchy;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PatientController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_patients.view', only: ['index', 'show', 'history', 'lookup', 'reactIndex', 'reactData']),
            new Middleware('permission:medical_patients.create', only: ['create', 'store', 'quickStore']),
            new Middleware('permission:medical_patients.edit', only: ['edit', 'update']),
            new Middleware('permission:medical_patients.delete', only: ['destroy']),
        ];
    }

    protected MrNumberGenerator $mrGenerator;

    public const PATIENTS_COLUMNS = [
        'serial', 'mr', 'name', 'age', 'age_group',
        'gender', 'phone', 'blood', 'status', 'action',
    ];

    public const PER_PAGE_OPTIONS = [25, 50, 75, 100, 200, 500];

    public function __construct(MrNumberGenerator $mrGenerator)
    {
        $this->mrGenerator = $mrGenerator;
    }

    /**
     * List all patients with search — mirrors the admin institutes index
     * effects: filter-card, column visibility, per-page list, print table.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        // Back-compat: institutes page uses `q`, patients page used `search`.
        $search = $request->query('search', $request->query('q'));
        $gender = $request->query('gender');
        $bloodGroup = $request->query('blood_group');
        $status = $request->query('status');
        $category = $request->query('category');
        $categoryBounds = mawa_age_category_bounds();
        if (! is_string($category) || ! array_key_exists($category, $categoryBounds)) {
            $category = null;
        }

        $query = Patient::where('institute_id', $this->instituteId());

        // Fenced doctors list only their own patients.
        $query = $this->scopeOwnPatients($query);

        // Search by MR number, name, or phone.
        if (is_string($search) && trim($search) !== '') {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('mr_number', 'LIKE', "%{$search}%")
                    ->orWhere('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        // Filter by gender.
        if (is_string($gender) && in_array($gender, ['male', 'female', 'other'], true)) {
            $query->where('gender', $gender);
        } else {
            $gender = null;
        }

        // Filter by blood group.
        $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'UKN'];
        if (! (is_string($bloodGroup) && in_array($bloodGroup, $bloodGroups, true))) {
            $bloodGroup = null;
        }
        if ($bloodGroup) {
            $query->where('blood_group', $bloodGroup);
        }

        // Filter by active status.
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } else {
            $status = null;
        }

        // Filter by age category (computed from date_of_birth, not stored).
        if ($category !== null) {
            [$minYears, $maxYears] = $categoryBounds[$category];
            $today = \Carbon\Carbon::today();
            if ($minYears > 0) {
                $query->whereDate('date_of_birth', '<=', $today->copy()->subYears($minYears)->toDateString());
            }
            if ($maxYears !== null) {
                $query->whereDate('date_of_birth', '>', $today->copy()->subYears($maxYears)->toDateString());
            }
        }

        $patients = (clone $query)->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        // Full filtered set for the print-only table (same as institutes index).
        $allPatients = (clone $query)->orderBy('created_at', 'desc')->get();

        $visibleColumns = $request->user()?->preference('columns_patients', self::PATIENTS_COLUMNS)
            ?? self::PATIENTS_COLUMNS;
        $visibleColumns = array_values(array_intersect(self::PATIENTS_COLUMNS, (array) $visibleColumns));
        if (empty($visibleColumns)) {
            $visibleColumns = self::PATIENTS_COLUMNS;
        }

        // Country list + institute default for the Add Patient popup's
        // country-parameter phone validation.
        $countries = Country::where('status', true)->orderBy('name')->get(['id', 'name', 'phone_code']);
        $defaultCountryId = Institute::whereKey($this->instituteId())->value('country_id');

        // Auto Patient ID preview for the Add Patient popup (final ID is
        // assigned on save via MrNumberGenerator).
        $previewMr = $this->mrGenerator->generate($this->instituteId());

        return view('medical.patients.index', [
            'patients' => $patients,
            'allPatients' => $allPatients,
            'visibleColumns' => $visibleColumns,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'bloodGroups' => $bloodGroups,
            'filters' => [
                'search' => is_string($search) ? $search : null,
                'gender' => $gender,
                'blood_group' => $bloodGroup,
                'status' => $status,
                'category' => $category,
                'per_page' => $perPage,
            ],
            'categories' => array_keys($categoryBounds),
            'countries' => $countries,
            'defaultCountryId' => $defaultCountryId,
            'previewMr' => $previewMr,
        ]);
    }

    /**
     * Look up a patient by phone number (used by the Add Patient popup to
     * auto-fill the rest of the fields when the phone already exists).
     */
    public function lookup(Request $request)
    {
        $request->validate(['phone' => 'required|string|max:20']);

        $instituteId = $this->instituteId();
        $raw = trim((string) $request->input('phone'));

        $countryId = Institute::whereKey($instituteId)->value('country_id');
        $country = $countryId ? Country::whereKey($countryId)->value('name') : 'Bangladesh';

        $normalized = PhoneNormalizer::toE164($raw, $country);
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        $candidates = array_values(array_unique(array_filter([
            $raw,
            $normalized,
            $digits,
            $digits !== '' ? '+'.$digits : null,
        ])));

        // Also match legacy nationally-stored numbers against E.164 input.
        if ($normalized !== null && str_starts_with($normalized, '+')) {
            $int = ltrim($normalized, '+');
            $code = CountryCodes::matchPrefix($int);
            if ($code !== null) {
                $subscriber = substr($int, strlen($code));
                $candidates[] = $subscriber;
                $candidates[] = '0'.$subscriber;
            }
        }
        $candidates = array_values(array_unique(array_filter($candidates)));

        $patient = Patient::where('institute_id', $instituteId)
            ->whereIn('phone', $candidates)
            ->first();

        // Fenced doctors may only discover their own (or brand-new) patients;
        // anyone else's reads as not-found — no existence oracle.
        if ($patient && ! $this->mayActOnPatient($patient)) {
            return response()->json(['found' => false]);
        }

        if (! $patient) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'patient' => [
                'id' => $patient->id,
                'mr_number' => $patient->mr_number,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'date_of_birth' => $patient->date_of_birth?->format('Y-m-d'),
                'gender' => $patient->gender,
                'blood_group' => $patient->blood_group,
                'phone' => $patient->phone,
                'email' => $patient->email,
                'present_country_id' => $patient->present_country_id,
                'present_address' => $patient->present_address,
                'emergency_contact_name' => $patient->emergency_contact_name,
                'emergency_contact_phone' => $patient->emergency_contact_phone,
                'allergies' => $patient->allergies,
                'chronic_conditions' => $patient->chronic_conditions,
                'notes' => $patient->notes,
                'url' => route('medical.patients.show', $patient),
            ],
        ]);
    }

    /**
     * React-powered patient list page (Blade host for resources/js/medical/patients.js).
     *
     * The Blade patients index is untouched; React mounts only on this page
     * and polls reactData() every 10 seconds.
     */
    public function reactIndex()
    {
        $props = [
            'initialPatients' => $this->reactPatientPayload(),
            'refreshUrl' => route('medical.patients.react.data'),
        ];

        return view('medical.patients-react', compact('props'));
    }

    /**
     * JSON feed for the React patient list (polled every 10 seconds).
     */
    public function reactData()
    {
        return response()->json($this->reactPatientPayload());
    }

    /**
     * Patient rows for the React list (institute-scoped, doctor-fenced,
     * capped so the initial payload stays small; search happens client-side).
     */
    private function reactPatientPayload(): array
    {
        $query = Patient::where('institute_id', $this->instituteId());
        $query = $this->scopeOwnPatients($query);

        return $query->orderBy('first_name')
            ->limit(200)
            ->get()
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'mr_number' => $patient->mr_number,
                'name' => $patient->full_name,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'phone' => $patient->phone,
                'blood_group' => $patient->blood_group,
                'is_active' => (bool) $patient->is_active,
            ])
            ->all();
    }

    /**
     * Show patient registration form.
     */
    public function create()
    {
        $patient = new Patient(['is_active' => true]);
        $this->defaultAddressCountry($patient);
        $presentAddress = $this->addressData($patient);

        // Country-parameter phone meta for realtime length check (server: PhoneRule).
        $phoneCountry = $presentAddress['country']->name ?? 'Bangladesh';
        $phoneCode = CountryCodes::codeFor($phoneCountry);
        [$phoneMin, $phoneMax] = CountryCodes::nationalLengthFor($phoneCountry);
        $phoneExample = CountryCodes::phoneExampleFor($phoneCountry);
        $phoneCountries = Country::where('status', true)->orderBy('name')->get(['id', 'name', 'phone_code'])
            ->mapWithKeys(function ($c) {
                [$mn, $mx] = CountryCodes::nationalLengthFor($c->name);
                return [$c->id => [
                    'name' => $c->name,
                    'code' => $c->phone_code ?: CountryCodes::codeFor($c->name),
                    'min' => $mn,
                    'max' => $mx,
                    'example' => CountryCodes::phoneExampleFor($c->name),
                ]];
            })->all();

        return view('medical.patients.create', compact('patient', 'presentAddress', 'phoneCountry', 'phoneCode', 'phoneMin', 'phoneMax', 'phoneExample', 'phoneCountries'));
    }

    /**
     * AJAX quick-create used by nested modals (e.g. Book Appointment popup).
     * Same validation/normalization as store(), but returns JSON so the
     * caller can attach the new patient without leaving the page.
     */
    public function quickStore(PatientRequest $request)
    {
        $instituteId = $this->instituteId();

        $data = $request->validated();
        unset($data['age'], $data['age_unit']);
        $data['last_name'] = $data['last_name'] ?? '';
        $data['gender'] = $data['gender'] ?? 'other';
        if (empty($data['phone'])) {
            $data['phone'] = 'NA-'.uniqid();
        } else {
            $normalized = PhoneNormalizer::toE164($data['phone'], $request->phoneCountry());
            if ($normalized !== null) {
                $data['phone'] = $normalized;
            }
        }
        $data['institute_id'] = $instituteId;
        $data['mr_number'] = $this->mrGenerator->generate($instituteId);

        $patient = Patient::create($data);
        $patient->syncStructuredAllergies();

        return response()->json([
            'created' => true,
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->full_name,
                'mr_number' => $patient->mr_number,
                'phone' => $patient->phone,
            ],
        ], 201);
    }

    /**
      * Store a new patient.
      */
    public function store(PatientRequest $request)
    {
        $instituteId = $this->instituteId();

        $data = $request->validated();
        unset($data['age'], $data['age_unit']);
        // DB columns are still NOT NULL — supply safe defaults so that only
        // name + age are mandatory from the UI.
        $data['last_name'] = $data['last_name'] ?? '';
        $data['gender'] = $data['gender'] ?? 'other';
        if (empty($data['phone'])) {
            $data['phone'] = 'NA-'.uniqid();
        } else {
            // Store normalized E.164 using the same country parameter as validation.
            $normalized = PhoneNormalizer::toE164($data['phone'], $request->phoneCountry());
            if ($normalized !== null) {
                $data['phone'] = $normalized;
            }
        }
        $data['institute_id'] = $instituteId;
        $data['mr_number'] = $this->mrGenerator->generate($instituteId);

        $patient = Patient::create($data);
        $patient->syncStructuredAllergies();

        return redirect()->route('medical.patients.show', $patient)
            ->with('status', 'Patient registered successfully! MR: '.$patient->mr_number);
    }

    /**
     * Show patient profile with history.
     */
    public function show(Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');
        $this->ensurePatientVisible($patient);

        // Fenced doctors see only their own records in each history section
        // (shared patients must not leak another doctor's visits/fees).
        $fence = $this->doctorFenceId();
        $instituteId = $this->instituteId();

        $patient->load([
            'appointments' => function ($q) use ($fence) {
                $q->whereDate('appointment_date', '>=', now()->subDays(30))
                    ->orderBy('appointment_date', 'desc')
                    ->orderBy('appointment_time', 'desc');
                if ($fence !== null) {
                    $q->where('doctor_id', $fence);
                }
            },
            'admissions' => function ($q) use ($fence) {
                $q->orderBy('admission_date', 'desc');
                if ($fence !== null) {
                    $q->where('admitting_doctor_id', $fence);
                }
            },
            'prescriptions' => function ($q) use ($fence) {
                $q->orderBy('prescription_date', 'desc')->limit(5);
                if ($fence !== null) {
                    $q->where('doctor_id', $fence);
                }
            },
            'labOrders' => function ($q) use ($fence) {
                $q->orderBy('order_date', 'desc')->limit(5);
                if ($fence !== null) {
                    $q->where('doctor_id', $fence);
                }
            },
            'invoices' => function ($q) use ($fence, $instituteId) {
                $q->orderBy('invoice_date', 'desc')->limit(5);
                if ($fence !== null) {
                    $q->where(function ($qq) use ($fence, $instituteId) {
                        $qq->whereHas('admission', fn ($a) => $a
                                ->where('admitting_doctor_id', $fence))
                            ->orWhere(function ($qqq) use ($fence, $instituteId) {
                                $qqq->whereHas('patient.appointments', fn ($a) => $a
                                        ->where('institute_id', $instituteId)
                                        ->where('doctor_id', $fence))
                                    ->whereDoesntHave('patient.appointments', fn ($a) => $a
                                        ->where('institute_id', $instituteId)
                                        ->where('doctor_id', '!=', $fence));
                            });
                    });
                }
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
        $this->ensurePatientVisible($patient);
        $presentAddress = $this->addressData($patient);

        return view('medical.patients.edit', compact('patient', 'presentAddress'));
    }

    /**
     * Update patient.
     */
    public function update(PatientRequest $request, Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');
        $this->ensurePatientVisible($patient);
        $data = $request->validated();
        unset($data['age'], $data['age_unit']);
        if (array_key_exists('last_name', $data) && $data['last_name'] === null) {
            $data['last_name'] = '';
        }
        if (!empty($data['phone'])) {
            $normalized = PhoneNormalizer::toE164($data['phone'], $request->phoneCountry());
            if ($normalized !== null) {
                $data['phone'] = $normalized;
            }
        }
        // Phase 01: snapshot before mutation for the amendment audit.
        $original = ClinicalAuditLog::snapshot($patient);
        $patient->update($data);
        $patient->syncStructuredAllergies();

        // Phase 01: clinical amendments must stay attributable.
        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($patient->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($patient, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()->route('medical.patients.show', $patient)
            ->with('status', 'Patient updated successfully!');
    }

    /**
     * Delete patient (soft delete).
     */
    public function destroy(Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');
        $this->ensurePatientVisible($patient);
        // Phase 01: keep the deleted record attributable.
        $snapshot = ClinicalAuditLog::snapshot($patient);
        $patient->delete();
        ClinicalAuditLog::record($patient, 'deleted', ['old' => $snapshot]);

        return redirect()->route('medical.patients.index')
            ->with('status', 'Patient deleted successfully!');
    }

    /**
     * Show patient medical history.
     */
    public function history(Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');
        $this->ensurePatientVisible($patient);

        $fence = $this->doctorFenceId();

        $patient->load([
            'appointments' => function ($q) use ($fence) {
                $q->orderBy('appointment_date', 'desc');
                if ($fence !== null) {
                    $q->where('doctor_id', $fence);
                }
            },
            'admissions' => function ($q) use ($fence) {
                $q->orderBy('admission_date', 'desc');
                if ($fence !== null) {
                    $q->where('admitting_doctor_id', $fence);
                }
            },
            'prescriptions' => function ($q) use ($fence) {
                $q->orderBy('prescription_date', 'desc');
                if ($fence !== null) {
                    $q->where('doctor_id', $fence);
                }
            },
            'labOrders' => function ($q) use ($fence) {
                $q->orderBy('order_date', 'desc');
                if ($fence !== null) {
                    $q->where('doctor_id', $fence);
                }
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
