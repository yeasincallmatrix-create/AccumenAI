<?php

namespace App\Services;

use App\Models\InstituteUser;
use App\Models\Training\Enrollment;
use App\Models\TeacherAcademicAssignment;

/**
 * Read-only teaching workload summary (Step 36).
 *
 * Every number here is CALCULATED from the teacher's assignments (aggregate
 * queries — no N+1). Stored values (joining date, designation, ...) are not
 * duplicated; the summary only reflects what the database actually holds.
 */
class TeacherWorkloadService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(InstituteUser $teacher): array
    {
        $activeIds = null;
        $active = TeacherAcademicAssignment::query()
            ->where('institute_user_id', $teacher->id)
            ->where('status', TeacherAcademicAssignment::STATUS_ACTIVE);

        $total = TeacherAcademicAssignment::query()
            ->where('institute_user_id', $teacher->id)
            ->count();

        $activeCount = (clone $active)->count();
        $completed = $total - $activeCount;

        $byResponsibility = (clone $active)
            ->select('responsibility')
            ->groupBy('responsibility')
            ->pluck('responsibility')
            ->mapWithKeys(fn ($responsibility) => [$responsibility => (clone $active)->where('responsibility', $responsibility)->count()])
            ->all();

        $activeAssignments = $active->get(['course_id', 'subject_id', 'batch_id']);

        $distinctCourses = $activeAssignments->pluck('course_id')->filter()->unique()->count();
        $distinctSubjects = $activeAssignments->pluck('subject_id')->filter()->unique()->count();
        $distinctBatches = $activeAssignments->pluck('batch_id')->filter()->unique()->count();

        $batchIds = $activeAssignments->pluck('batch_id')->filter()->unique()->values()->all();
        $studentCount = $batchIds === []
            ? 0
            : Enrollment::query()
                ->whereIn('batch_id', $batchIds)
                ->where('status', 'active')
                ->count();

        return [
            'total_assignments' => $total,
            'active_assignments' => $activeCount,
            'completed_assignments' => $completed,
            'distinct_courses' => $distinctCourses,
            'distinct_subjects' => $distinctSubjects,
            'distinct_batches' => $distinctBatches,
            'students_assigned' => $studentCount,
            'by_responsibility' => $byResponsibility,
            'calculated' => true,
        ];
    }
}
