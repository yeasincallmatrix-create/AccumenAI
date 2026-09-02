<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Services\GuardianService;

/**
 * Step 47 — Linked students list + read-only student profile.
 */
class GuardianStudentController extends Controller
{
    public function __construct(
        private readonly GuardianService $guardians,
    ) {}

    public function index()
    {
        /** @var Guardian $guardian */
        $guardian = auth('guardian')->user();

        $students = $this->guardians->students($guardian)
            ->map(fn ($student) => [
                'student' => $student,
                'enrollment' => $this->guardians->currentEnrollment($student),
            ]);

        return view('guardian.students', [
            'guardian' => $guardian,
            'rows' => $students,
        ]);
    }

    public function show(int $student)
    {
        /** @var Guardian $guardian */
        $guardian = auth('guardian')->user();

        $student = $this->guardians->requireStudent($guardian, $student);

        return view('guardian.students-show', [
            'guardian' => $guardian,
            'student' => $student,
            'enrollment' => $this->guardians->currentEnrollment($student),
            'enrollments' => $this->guardians->enrollments($student),
            'placement' => $this->guardians->currentPlacement($student),
        ]);
    }
}
