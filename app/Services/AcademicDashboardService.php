<?php

namespace App\Services;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Institute;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\Teacher;
use App\Support\IndustryRules;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Step 24 — Academic Operations Dashboard (read-only aggregation).
 *
 * A single-page overview of the institute's academic operations for the
 * CURRENT academic year (the `is_current = true` academic_years row). Every
 * figure is an aggregate over existing authoritative tables — nothing here
 * writes, and no metric is recomputed with new business logic:
 *
 *   - Students:  placement status counts for the current year plus terminal
 *                promotion outcomes (approved decision items) per the same
 *                derivation StudentAcademicLifecycleService uses.
 *   - Results:   published final-result count and the frozen per-student
 *                passed/failed snapshot sums of those published cycles.
 *   - Promotion: pending / in-review / approved decision counts.
 *   - Attendance: live status-grouped totals over the current year's date
 *                window, reusing the exact counting semantics of
 *                AcademicAttendanceReportService::summary() (unrecorded days
 *                are never treated as absent). Unavailable when the year has
 *                no reliable start/end dates.
 *   - Certificates: official issued/revoked counts plus the pending request
 *                count; "eligible" is derived solely from existing approved
 *                completed/graduated promotion outcomes — no new eligibility
 *                calculation is introduced.
 *
 * Tenant + branch isolation is inherited from the scoped models: placements
 * are reached through StudentAcademicPlacement::inScope() (branch inherited
 * from the owning Student), while result / decision rows are reached through
 * their tenant + branch scoped parents. The unscoped snapshot/decision-item
 * tables are only ever queried through those parents or a scoped placement id
 * subquery, never standalone.
 */
class AcademicDashboardService
{
    /** @return array{year: ?AcademicYear, students: array, results: array, promotion: array, attendance: array, certificates: array, overview: array, academics: array, teachers: array, batches: array, courses: array} */
    public function summary(): array
    {
        $year = AcademicYear::query()
            ->where('is_current', true)
            ->orderByDesc('id')
            ->first();

        $institute = $this->resolveInstitute();

        return [
            'year' => $year,
            'institute' => $institute,
            'industryLabel' => $this->industryLabel($institute),
            'subIndustryLabel' => $this->subIndustryLabel($institute),
            'isEducation' => $this->isEducation($institute),
            'students' => $this->students($year),
            'results' => $this->results($year),
            'promotion' => $this->promotion($year),
            'attendance' => $this->attendance($year),
            'certificates' => $this->certificates($year),
            'overview' => $this->overview($year),
            'academics' => $this->academics(),
            'teachers' => $this->teachers(),
            'batches' => $this->batches($year),
            'courses' => $this->courses(),
        ];
    }

    private function resolveInstitute(): ?Institute
    {
        $id = TenantContext::id();
        if ($id) {
            return Institute::withoutGlobalScopes()->find($id);
        }
        return null;
    }

    private function isEducation(?Institute $institute): bool
    {
        return \App\Support\InstituteDomain::isAcademic($institute);
    }

    private function industryLabel(?Institute $institute): string
    {
        if (! $institute) return '—';
        $label = IndustryRules::label($institute->country ?? '', $institute->industry ?? '', null);
        return $label ?? ucwords(str_replace('_', ' ', $institute->industry ?? 'Education'));
    }

    private function subIndustryLabel(?Institute $institute): string
    {
        if (! $institute || empty($institute->sub_industry)) return '';
        $label = IndustryRules::label($institute->country ?? '', $institute->industry ?? '', $institute->sub_industry);
        return $label ?? ucwords(str_replace('_', ' ', $institute->sub_industry));
    }

    /**
     * High-level overview counts for all education sub-industries.
     * @return array{students_total: int, teachers_total: int, batches_total: int, batches_running: int, courses_total: int, exams_total: int, assessments_total: int}
     */
    private function overview(?AcademicYear $year): array
    {
        return [
            'students_total' => $this->safeCount(Student::class),
            'teachers_total' => $this->safeCount(Teacher::class),
            'batches_total' => $this->safeCount(Batch::class),
            'batches_running' => $this->safeCountWhere(Batch::class, ['status' => 'running']),
            'courses_total' => $this->safeCount(Course::class),
            'exams_total' => $this->safeCount(Exam::class),
            'assessments_total' => $this->safeCount(\App\Models\AcademicAssessment::class),
        ];
    }

    /**
     * Academic structure: levels, classes, groups, subjects, grading.
     * Works for every education sub-industry via the global Education Engine.
     */
    private function academics(): array
    {
        return [
            'levels' => $this->safeCount(\App\Models\AcademicLevel::class),
            'classes' => $this->safeCount(\App\Models\ClassGrade::class),
            'groups' => $this->safeCount(\App\Models\AcademicGroup::class),
            'subjects' => $this->safeCount(\App\Models\AcademicSubject::class),
            'systems' => $this->safeCount(\App\Models\EducationSystem::class),
            'grading_scales' => $this->safeCount(\App\Models\GradeScale::class),
            'institute_levels' => $this->safeCount(\App\Models\InstituteAcademicLevel::class),
            'institute_classes' => $this->safeCount(\App\Models\InstituteClassGrade::class),
        ];
    }

    private function teachers(): array
    {
        $active = 0;
        $total = 0;
        try {
            $total = Teacher::count();
            $active = Teacher::where('status', 'active')->count();
        } catch (\Throwable $e) {}
        return ['total' => $total, 'active' => $active, 'inactive' => max(0, $total - $active)];
    }

    private function batches(?AcademicYear $year): array
    {
        $byStatus = [];
        try {
            $byStatus = Batch::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();
        } catch (\Throwable $e) {}
        $recent = [];
        try {
            $recent = Batch::with(['course'])->latest('id')->limit(5)->get();
        } catch (\Throwable $e) {}
        return ['by_status' => $byStatus, 'recent' => $recent];
    }

    private function courses(): array
    {
        $total = $this->safeCount(Course::class);
        $assigned = 0;
        try { $assigned = \App\Models\InstituteCourse::count(); } catch (\Throwable $e) {}
        return ['total' => $total, 'assigned' => $assigned];
    }

    private function safeCount(string $model): int
    {
        try { return $model::count(); } catch (\Throwable $e) { return 0; }
    }

    private function safeCountWhere(string $model, array $where): int
    {
        try { return $model::where($where)->count(); } catch (\Throwable $e) { return 0; }
    }

    // ------------------------------------------------------------- Students

    /**
     * @return array{cohort: int, active: int, completed: int, graduated: int, withdrawn: int, transferred: int}
     */
    private function students(?AcademicYear $year): array
    {
        if ($year === null) {
            return ['cohort' => 0, 'active' => 0, 'completed' => 0, 'graduated' => 0, 'withdrawn' => 0, 'transferred' => 0];
        }

        $statusCounts = $this->yearPlacements($year)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'cohort' => $this->yearPlacements($year)->distinct()->count('student_id'),
            'active' => (int) ($statusCounts[StudentAcademicPlacement::STATUS_ACTIVE] ?? 0),
            'completed' => $this->approvedDecisionCount($year, PromotionDecisionItem::DECISION_COMPLETED),
            'graduated' => $this->approvedDecisionCount($year, PromotionDecisionItem::DECISION_GRADUATED),
            'withdrawn' => (int) ($statusCounts[StudentAcademicPlacement::STATUS_DROPPED] ?? 0),
            'transferred' => (int) ($statusCounts[StudentAcademicPlacement::STATUS_TRANSFERRED] ?? 0),
        ];
    }

    /**
     * Placements of the current year visible in the tenant + branch scope.
     */
    private function yearPlacements(AcademicYear $year): Builder
    {
        return StudentAcademicPlacement::query()
            ->inScope()
            ->where('academic_year_id', $year->id);
    }

    /**
     * Placements of the current year with an APPROVED promotion decision item
     * carrying the given terminal outcome. One placement per student per year,
     * so this is the cohort that officially completed / graduated.
     */
    private function approvedDecisionCount(AcademicYear $year, string $decision): int
    {
        return PromotionDecisionItem::query()
            ->where('decision', $decision)
            ->whereHas('decision', fn (Builder $query) => $query->where('status', PromotionDecision::STATUS_APPROVED))
            ->whereIn('placement_id', $this->yearPlacements($year)->select('id'))
            ->distinct()
            ->count('placement_id');
    }

    // -------------------------------------------------------------- Results

    /**
     * @return array{published_results: int, passed_students: int, failed_students: int}
     */
    private function results(?AcademicYear $year): array
    {
        if ($year === null) {
            return ['published_results' => 0, 'passed_students' => 0, 'failed_students' => 0];
        }

        $published = fn (Builder $query) => $query
            ->where('status', AcademicFinalResult::STATUS_PUBLISHED)
            ->whereHas('scheme', fn (Builder $scheme) => $scheme->where('academic_year_id', $year->id));

        $passed = (int) AcademicFinalResultStudent::query()
            ->whereHas('result', $published)
            ->sum('passed_count');

        $failed = (int) AcademicFinalResultStudent::query()
            ->whereHas('result', $published)
            ->sum('failed_count');

        return [
            'published_results' => AcademicFinalResult::query()
                ->where('status', AcademicFinalResult::STATUS_PUBLISHED)
                ->whereHas('scheme', fn (Builder $scheme) => $scheme->where('academic_year_id', $year->id))
                ->count(),
            'passed_students' => $passed,
            'failed_students' => $failed,
        ];
    }

    // ------------------------------------------------------------ Promotion

    /**
     * @return array{pending: int, review: int, approved: int}
     */
    private function promotion(?AcademicYear $year): array
    {
        if ($year === null) {
            return ['pending' => 0, 'review' => 0, 'approved' => 0];
        }

        $count = fn (string $status) => PromotionDecision::query()
            ->where('status', $status)
            ->where('academic_year_id', $year->id)
            ->count();

        return [
            'pending' => $count(PromotionDecision::STATUS_PENDING),
            'review' => $count(PromotionDecision::STATUS_REVIEW),
            'approved' => $count(PromotionDecision::STATUS_APPROVED),
        ];
    }

    // ----------------------------------------------------------- Attendance

    /**
     * @return array{available: bool, message: ?string, total: int, present: int, absent: int, late: int, leave: int, present_percent: ?float}
     */
    private function attendance(?AcademicYear $year): array
    {
        if ($year === null || $year->start_date === null || $year->end_date === null) {
            return [
                'available' => false,
                'message' => 'The current academic year has no reliable start/end dates, so attendance totals cannot be computed.',
                'total' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'leave' => 0,
                'present_percent' => null,
            ];
        }

        // Same status-grouped semantics as AcademicAttendanceReportService::summary():
        // only recorded rows count; unrecorded days are never treated as absent.
        $counts = Attendance::query()
            ->whereBetween('class_date', [$year->start_date->toDateString(), $year->end_date->toDateString()])
            ->whereIn('student_id', $this->yearPlacements($year)->select('student_id'))
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $present = (int) ($counts[Attendance::STATUS_PRESENT] ?? 0);
        $absent = (int) ($counts[Attendance::STATUS_ABSENT] ?? 0);
        $late = (int) ($counts[Attendance::STATUS_LATE] ?? 0);
        $leave = (int) ($counts[Attendance::STATUS_LEAVE] ?? 0);
        $total = $present + $absent + $late + $leave;

        return [
            'available' => true,
            'message' => null,
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'leave' => $leave,
            'present_percent' => $total > 0 ? round($present / $total * 100, 1) : null,
        ];
    }

    // --------------------------------------------------------- Certificates

    /**
     * @return array{eligible: int, issued: int, revoked: int, pending: int}
     */
    private function certificates(?AcademicYear $year): array
    {
        $eligible = 0;

        if ($year !== null) {
            $eligible = PromotionDecisionItem::query()
                ->whereIn('decision', [PromotionDecisionItem::DECISION_COMPLETED, PromotionDecisionItem::DECISION_GRADUATED])
                ->whereHas('decision', fn (Builder $query) => $query->where('status', PromotionDecision::STATUS_APPROVED))
                ->whereIn('placement_id', $this->yearPlacements($year)->select('id'))
                ->distinct()
                ->count('placement_id');
        }

        return [
            'eligible' => $eligible,
            'issued' => Certificate::query()->where('status', 'active')->count(),
            'revoked' => Certificate::query()->where('status', 'revoked')->count(),
            'pending' => Certificate::query()->where('status', 'pending')->count(),
        ];
    }
}
