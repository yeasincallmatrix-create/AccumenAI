<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\Department;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DepartmentController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_doctors.view', only: ['getSpecialties']),
        ];
    }

    /**
     * JSON list of active specialties for a department (tenant-scoped).
     * Used by the Add/Edit Doctor form for cascading Department → Specialty.
     */
    public function getSpecialties(Department $department)
    {
        $this->ensureSameInstitute($department, 'department');

        $specialties = $department->specialties()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);

        return response()->json([
            'success' => true,
            'data' => $specialties,
        ]);
    }
}
