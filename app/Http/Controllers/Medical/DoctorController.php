<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\Department;
use App\Models\Medical\Doctor;
use App\Models\Medical\Specialty;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class DoctorController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_doctors.view', only: ['index', 'show', 'getSlots']),
            new Middleware('permission:medical_doctors.create', only: ['create', 'store']),
            new Middleware('permission:staff.manage', only: ['quickUser']),
            new Middleware('permission:medical_doctors.edit', only: ['edit', 'update']),
            new Middleware('permission:medical_doctors.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = Doctor::where('institute_id', $instituteId)
            ->with(['user', 'department', 'specialty.department', 'availabilities']);

        if ($request->filled('department_id')) {
            $deptId = $request->department_id;
            $query->where(function ($q) use ($deptId) {
                $q->where('department_id', $deptId)
                    ->orWhereHas('specialty', function ($sq) use ($deptId) {
                        $sq->where('department_id', $deptId);
                    });
            });
        }

        if ($request->filled('specialty_id')) {
            $query->where('specialty_id', $request->specialty_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('registration_number', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    });
            });
        }

        $doctors = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $departments = Department::where('institute_id', $instituteId)->active()->orderBy('name')->get();
        $specialties = Specialty::where('institute_id', $instituteId)->active()->orderBy('name')->get();

        return view('medical.doctors.index', compact('doctors', 'departments', 'specialties'));
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $departments = Department::where('institute_id', $instituteId)->active()->orderBy('name')->get();
        $specialties = Specialty::where('institute_id', $instituteId)->active()->orderBy('name')->get();
        $users = User::where('status', 'active')->orderBy('name')->get();

        // Roles for the quick "Add Doctor Account" popup (same scope as staff invite).
        $inviteRoles = Role::query()
            ->where(function ($query) use ($instituteId) {
                $query->whereNull('institute_id');
                $query->orWhere('institute_id', $instituteId);
            })
            ->where('slug', '!=', 'institute-owner')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('medical.doctors.create', compact('departments', 'specialties', 'users', 'inviteRoles'));
    }

    /**
     * Create a staff user account from the "Add Doctor Account" popup.
     *
     * Same validation + provisioning as staff invite, but returns JSON so
     * the doctor form can select the new account without leaving the page.
     */
    public function quickUser(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\+?\d{4,20}$/', 'unique:users,phone'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'password' => \App\Support\PasswordPolicy::rules(),
        ]);

        $role = Role::query()->findOrFail($data['role_id']);
        abort_if($role->slug === 'institute-owner', 422, 'Owners cannot be invited as staff.');

        $instituteId = $this->instituteId();

        $user = app(\App\Services\UserAccountService::class)->createStaffFromInvitation([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'preferred_language' => mawa_current_lang(),
            'password_hash' => app(\App\Services\Auth\PasswordService::class)->hash($data['password']),
            'status' => 'active',
        ]);

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }

        app(\App\Services\MembershipService::class)->assign($user, $instituteId, $role->id);

        return response()->json([
            'success' => true,
            'message' => 'Doctor account created.',
            'data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'department_id' => 'nullable|exists:medical_departments,id',
            'specialty_id' => 'nullable|exists:medical_specialties,id',
            'registration_number' => 'required|string|max:50|unique:medical_doctors',
            'qualification' => 'nullable|string',
            'experience_years' => 'nullable|integer|min:0',
            'consultation_fee' => 'nullable|numeric|min:0',
            'first_visit_fee' => 'nullable|numeric|min:0',
            'follow_up_fee' => 'nullable|numeric|min:0',
            'follow_up_days' => 'nullable|integer|min:1|max:365',
            'chamber_address' => 'nullable|string',
            'room_no' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'bio' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'availabilities' => 'nullable|array',
            'availabilities.*.day' => 'required|in:sunday,monday,tuesday,wednesday,thursday,friday,saturday',
            'availabilities.*.start_time' => 'required|date_format:H:i',
            'availabilities.*.end_time' => 'required|date_format:H:i',
            'availabilities.*.slot_duration' => 'nullable|integer|min:5|max:60',
        ]);

        foreach ((array) ($validated['availabilities'] ?? []) as $avail) {
            if (isset($avail['start_time'], $avail['end_time']) && $avail['end_time'] <= $avail['start_time']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'availabilities' => 'End time must be after start time for each availability row.',
                ]);
            }
        }

        $this->assertNoDuplicateOrOverlappingAvailabilities((array) ($validated['availabilities'] ?? []));

        $validated['institute_id'] = $this->instituteId();
        $validated['is_active'] = $request->boolean('is_active', true);
        $availabilities = $validated['availabilities'] ?? null;
        unset($validated['availabilities']);

        // Ensure department/specialty belong to this institute (and to each other).
        if (! empty($validated['department_id'])) {
            Department::where('institute_id', $validated['institute_id'])
                ->whereKey($validated['department_id'])
                ->firstOrFail();
        }

        $specialty = null;
        if (! empty($validated['specialty_id'])) {
            $specialty = Specialty::where('institute_id', $validated['institute_id'])
                ->whereKey($validated['specialty_id'])
                ->firstOrFail();

            if (! empty($validated['department_id']) && (int) $specialty->department_id !== (int) $validated['department_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'specialty_id' => 'The selected specialty does not belong to the selected department.',
                ]);
            }

            // Department implied by specialty when not explicitly chosen.
            $validated['department_id'] ??= $specialty->department_id;
        }

        try {
            $doctor = DB::transaction(function () use ($validated, $availabilities) {
                $doctor = Doctor::create($validated);

                if (! empty($availabilities)) {
                    foreach ($availabilities as $avail) {
                        $doctor->availabilities()->create([
                            'day_of_week' => $avail['day'],
                            'start_time' => $avail['start_time'],
                            'end_time' => $avail['end_time'],
                            'slot_duration' => $avail['slot_duration'] ?? 10,
                            'is_available' => true,
                        ]);
                    }
                }

                return $doctor;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'availabilities' => 'Duplicate availability: the same day and start time was submitted more than once. Please remove the duplicate row.',
                ]);
            }

            throw $e;
        }

        return redirect()->route('medical.doctors.show', $doctor)
            ->with('status', 'Doctor added successfully!');
    }

    public function show(Doctor $doctor)
    {
        $this->ensureSameInstitute($doctor, 'doctor');
        $doctor->load(['user', 'department', 'specialty.department', 'availabilities']);

        return view('medical.doctors.show', compact('doctor'));
    }

    public function edit(Doctor $doctor)
    {
        $this->ensureSameInstitute($doctor, 'doctor');
        $instituteId = $this->instituteId();
        $departments = Department::where('institute_id', $instituteId)->active()->orderBy('name')->get();
        $specialties = Specialty::where('institute_id', $instituteId)->active()->orderBy('name')->get();
        $users = User::where('status', 'active')->orderBy('name')->get();
        $doctor->load('availabilities');

        return view('medical.doctors.edit', compact('doctor', 'departments', 'specialties', 'users'));
    }

    public function update(Request $request, Doctor $doctor)
    {
        $this->ensureSameInstitute($doctor, 'doctor');

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'department_id' => 'nullable|exists:medical_departments,id',
            'specialty_id' => 'nullable|exists:medical_specialties,id',
            'registration_number' => 'required|string|max:50|unique:medical_doctors,registration_number,'.$doctor->id,
            'qualification' => 'nullable|string',
            'experience_years' => 'nullable|integer|min:0',
            'consultation_fee' => 'nullable|numeric|min:0',
            'first_visit_fee' => 'nullable|numeric|min:0',
            'follow_up_fee' => 'nullable|numeric|min:0',
            'follow_up_days' => 'nullable|integer|min:1|max:365',
            'chamber_address' => 'nullable|string',
            'room_no' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'bio' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'availabilities' => 'nullable|array',
            'availabilities.*.day' => 'required|in:sunday,monday,tuesday,wednesday,thursday,friday,saturday',
            'availabilities.*.start_time' => 'required|date_format:H:i',
            'availabilities.*.end_time' => 'required|date_format:H:i',
            'availabilities.*.slot_duration' => 'nullable|integer|min:5|max:60',
        ]);

        foreach ((array) ($validated['availabilities'] ?? []) as $avail) {
            if (isset($avail['start_time'], $avail['end_time']) && $avail['end_time'] <= $avail['start_time']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'availabilities' => 'End time must be after start time for each availability row.',
                ]);
            }
        }

        $this->assertNoDuplicateOrOverlappingAvailabilities((array) ($validated['availabilities'] ?? []));

        $validated['is_active'] = $request->boolean('is_active', true);
        $availabilities = $validated['availabilities'] ?? null;
        unset($validated['availabilities']);

        if (! empty($validated['department_id'])) {
            Department::where('institute_id', $doctor->institute_id)
                ->whereKey($validated['department_id'])
                ->firstOrFail();
        }

        if (! empty($validated['specialty_id'])) {
            $specialty = Specialty::where('institute_id', $doctor->institute_id)
                ->whereKey($validated['specialty_id'])
                ->firstOrFail();

            if (! empty($validated['department_id']) && (int) $specialty->department_id !== (int) $validated['department_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'specialty_id' => 'The selected specialty does not belong to the selected department.',
                ]);
            }

            $validated['department_id'] ??= $specialty->department_id;
        }

        try {
            DB::transaction(function () use ($doctor, $validated, $availabilities) {
                $doctor->update($validated);

                $doctor->availabilities()->delete();
                if (! empty($availabilities)) {
                    foreach ($availabilities as $avail) {
                        $doctor->availabilities()->create([
                            'day_of_week' => $avail['day'],
                            'start_time' => $avail['start_time'],
                            'end_time' => $avail['end_time'],
                            'slot_duration' => $avail['slot_duration'] ?? 10,
                            'is_available' => true,
                        ]);
                    }
                }
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'availabilities' => 'Duplicate availability: the same day and start time was submitted more than once. Please remove the duplicate row.',
                ]);
            }

            throw $e;
        }

        return redirect()->route('medical.doctors.show', $doctor)
            ->with('status', 'Doctor updated successfully!');
    }

    public function destroy(Doctor $doctor)
    {
        $this->ensureSameInstitute($doctor, 'doctor');
        $doctor->delete();

        return redirect()->route('medical.doctors.index')
            ->with('status', 'Doctor removed successfully!');
    }

    /**
     * Reject duplicate (day + start_time) rows and overlapping intervals
     * on the same day before hitting the `unique_doctor_day_time` DB constraint.
     */
    private function assertNoDuplicateOrOverlappingAvailabilities(array $availabilities): void
    {
        $seen = [];
        $byDay = [];

        foreach ($availabilities as $index => $avail) {
            if (! isset($avail['day'], $avail['start_time'], $avail['end_time'])) {
                continue;
            }

            $day = strtolower((string) $avail['day']);
            $start = substr((string) $avail['start_time'], 0, 5);
            $end = substr((string) $avail['end_time'], 0, 5);
            $key = $day.'|'.$start;

            if (isset($seen[$key])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "availabilities.{$index}.day" => "Duplicate availability: {$day} at {$start} appears more than once. Remove or change the duplicate row.",
                ]);
            }
            $seen[$key] = true;

            $byDay[$day][] = ['start' => $start, 'end' => $end, 'index' => $index];
        }

        foreach ($byDay as $day => $ranges) {
            usort($ranges, fn ($a, $b) => strcmp($a['start'], $b['start']));
            for ($i = 1; $i < count($ranges); $i++) {
                if ($ranges[$i]['start'] < $ranges[$i - 1]['end']) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "availabilities.{$ranges[$i]['index']}.start_time" => "Overlapping availability on {$day}: {$ranges[$i]['start']}–{$ranges[$i]['end']} overlaps {$ranges[$i - 1]['start']}–{$ranges[$i - 1]['end']}.",
                    ]);
                }
            }
        }
    }

    /**
     * Get available slots for a doctor on a specific date (AJAX).
     */
    public function getSlots(Request $request, Doctor $doctor)
    {
        $this->ensureSameInstitute($doctor, 'doctor');

        $request->validate(['date' => 'required|date']);

        return response()->json([
            'success' => true,
            'data' => $doctor->getAvailableSlots($request->date),
        ]);
    }
}
