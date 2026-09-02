<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\Training\Enrollment;
use Illuminate\Support\Collection;

/**
 * Step 47 — Guardian portal data access + authorization.
 *
 * The single gatekeeper for every portal page: a guardian may only ever reach
 * students linked to their account through an ACTIVE student_guardians row in
 * the same institute. Anything else 404s. Guardian queries never set a
 * BranchContext, so linked students are visible across every branch of the
 * guardian's institute.
 */
class GuardianService
{
    private const ACTIVE_STUDENT_SESSION = 'guardian.active_student_id';

    /**
     * Students linked to the guardian through active relationships, eager
     * loaded with their branch and (latest) enrollment context.
     */
    public function students(Guardian $guardian): Collection
    {
        return $guardian->linkedStudents()
            ->load(['branch']);
    }

    /**
     * Resolve a linked, active student or abort with 404 — never 403 — so
     * requests never leak whether a student exists.
     */
    public function requireStudent(Guardian $guardian, int $studentId): Student
    {
        $student = $guardian->linkedStudent($studentId);

        if ($student === null) {
            abort(404);
        }

        return $student;
    }

    /**
     * The currently selected student for the guardian's dashboard context,
     * validated against their active links (falls back to primary → first).
     */
    public function activeStudent(Guardian $guardian): ?Student
    {
        $sessionId = (int) session()->get(self::ACTIVE_STUDENT_SESSION);

        if ($sessionId > 0) {
            $student = $guardian->linkedStudent($sessionId);
            if ($student !== null) {
                return $student;
            }
        }

        $student = $guardian->primaryStudent();

        if ($student !== null) {
            return $student;
        }

        return $guardian->linkedStudents()->first();
    }

    /**
     * Switch the active student for the current session. Returns false when
     * the target is not an active link of the guardian.
     */
    public function switchActiveStudent(Guardian $guardian, int $studentId): bool
    {
        if ($guardian->linkedStudent($studentId) === null) {
            return false;
        }

        session()->put(self::ACTIVE_STUDENT_SESSION, $studentId);

        return true;
    }

    /**
     * The student's most recent enrollment, preferring active ones; eager
     * loads the batch → course + academic year chain.
     */
    public function currentEnrollment(Student $student): ?Enrollment
    {
        $active = $student->enrollments()
            ->where('status', 'active')
            ->with(['batch.course', 'batch.academicYear', 'batch.branch', 'course'])
            ->orderByDesc('id')
            ->first();

        if ($active !== null) {
            return $active;
        }

        return $student->enrollments()
            ->with(['batch.course', 'batch.academicYear', 'batch.branch', 'course'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * All enrollments of a student with batch context (academic history list).
     */
    public function enrollments(Student $student): Collection
    {
        return $student->enrollments()
            ->with(['batch.course', 'batch.academicYear', 'batch.branch'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The student's current (or most recent) academic placement.
     */
    public function currentPlacement(Student $student): ?StudentAcademicPlacement
    {
        return $student->currentAcademicPlacement();
    }

    /**
     * Create an active guardian→student link for the same institute.
     */
    public function linkStudent(Guardian $guardian, Student $student, array $data = []): GuardianStudent
    {
        return GuardianStudent::query()->updateOrCreate(
            [
                'institute_id' => $guardian->institute_id,
                'guardian_id' => $guardian->getKey(),
                'student_id' => $student->getKey(),
            ],
            [
                'relationship' => $data['relationship'] ?? GuardianStudent::RELATIONSHIP_GUARDIAN,
                'is_primary' => $data['is_primary'] ?? false,
                'status' => $data['status'] ?? GuardianStudent::STATUS_ACTIVE,
            ],
        );
    }
}
