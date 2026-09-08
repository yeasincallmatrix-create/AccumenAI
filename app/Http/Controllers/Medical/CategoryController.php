<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\Department;
use App\Models\Medical\Specialty;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * On-the-fly Department / Specialty creation for the Doctor form.
 *
 * Lets staff add a missing category without leaving the doctor create/edit
 * page. Everything is tenant-scoped; creating a category requires the same
 * permission as creating a doctor.
 */
class CategoryController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_doctors.create', only: ['store', 'departments']),
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:department,specialty',
            'name' => 'required|string|max:100',
            'department_id' => 'required_if:type,specialty|nullable|exists:medical_departments,id',
        ]);

        $instituteId = $this->instituteId();
        $name = trim($validated['name']);

        if ($validated['type'] === 'department') {
            $exists = Department::where('institute_id', $instituteId)
                ->where('name', $name)
                ->exists();
            if ($exists) {
                return response()->json([
                    'errors' => ['name' => ['Department with this name already exists.']],
                ], 422);
            }

            $department = Department::create([
                'institute_id' => $instituteId,
                'name' => $name,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Department created successfully.',
                'data' => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'type' => 'department',
                ],
            ]);
        }

        // Specialty: parent department must belong to this institute.
        $department = Department::where('institute_id', $instituteId)
            ->whereKey($validated['department_id'])
            ->first();
        if (! $department) {
            return response()->json([
                'errors' => ['department_id' => ['Selected department is invalid.']],
            ], 422);
        }

        $exists = Specialty::where('institute_id', $instituteId)
            ->where('department_id', $department->id)
            ->where('name', $name)
            ->exists();
        if ($exists) {
            return response()->json([
                'errors' => ['name' => ['Specialty already exists in this department.']],
            ], 422);
        }

        $specialty = Specialty::create([
            'institute_id' => $instituteId,
            'department_id' => $department->id,
            'name' => $name,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Specialty created successfully.',
            'data' => [
                'id' => $specialty->id,
                'name' => $specialty->name,
                'department_id' => $specialty->department_id,
                'type' => 'specialty',
            ],
        ]);
    }

    /**
     * Active departments for the current institute (dropdown refresh).
     */
    public function departments()
    {
        $departments = Department::where('institute_id', $this->instituteId())
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['success' => true, 'data' => $departments]);
    }
}
