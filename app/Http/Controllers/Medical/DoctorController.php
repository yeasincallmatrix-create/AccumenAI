<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\Department;
use App\Models\Medical\Doctor;
use App\Models\Medical\Specialty;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DoctorController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_doctors.view', only: ['index', 'show', 'getSlots']),
            new Middleware('permission:medical_doctors.create', only: ['create', 'store']),
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

        return view('medical.doctors.create', compact('departments', 'specialties', 'users'));
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
