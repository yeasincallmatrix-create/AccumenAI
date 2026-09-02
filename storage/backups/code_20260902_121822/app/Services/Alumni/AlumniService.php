<?php

namespace App\Services\Alumni;

use App\Models\AcademicFinalResult;
use App\Models\Alumni;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\Student;
use App\Services\StudentAcademicLifecycleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Step 48 — Alumni Management.
 *
 * Eligibility is NOT invented here: a student is eligible exactly when the
 * existing promotion engine has an APPROVED decision item with a
 * `completed` / `graduated` outcome whose source result is PUBLISHED — the
 * same rule the certificate request service already uses
 * (StudentAcademicCertificateRequestService::TERMINAL_OUTCOMES).
 *
 * The alumni profile stores only alumni/career fields. Academic provenance
 * (graduation date, completion academic year, completed course/batch) is
 * DERIVED from the approved decision item + the student's latest enrollment
 * and stored as foreign keys to existing records — it is never a new source
 * of truth and never editable here. CRM contact is reused from the student
 * (never duplicated, never auto-created).
 */
class AlumniService
{
    /**
     * Terminal outcomes that make a student eligible. Same values as the
     * certificate request service's TERMINAL_OUTCOMES.
     *
     * @var string[]
     */
    public const TERMINAL_OUTCOMES = [
        PromotionDecisionItem::DECISION_COMPLETED,
        PromotionDecisionItem::DECISION_GRADUATED,
    ];

    /**
     * Alumni-specific fields staff may edit. Academic provenance is excluded
     * on purpose — it is derived from the approved promotion decision.
     *
     * @var string[]
     */
    public const PROFILE_FIELDS = [
        'alumni_reference_number',
        'graduation_date',
        'current_occupation',
        'job_title',
        'employer',
        'employment_sector',
        'higher_education',
        'career_notes',
        'current_city',
        'current_country',
        'public_contact_preference',
        'profile_visibility',
        'notes',
    ];

    public function __construct(
        private readonly StudentAcademicLifecycleService $lifecycle,
        private readonly AlumniAuditService $audit,
    ) {}

    /**
     * Read-only eligibility summary for a student (used by the create UI and
     * the student search endpoint).
     *
     * @return array{
     *     eligible: bool,
     *     outcome: string,
     *     isCompletion: bool,
     *     isGraduation: bool,
     *     isTerminal: bool,
     *     item: ?PromotionDecisionItem,
     *     graduationDate: ?string,
     *     completionAcademicYearId: ?int,
     *     completionAcademicYear: mixed,
     *     completedCourseId: ?int,
     *     completedBatchId: ?int,
     * }
     */
    public function eligibility(Student $student): array
    {
        $lifecycle = $this->lifecycle->forStudent($student);
        $item = $this->eligibleItem($student);
        $latestEnrollment = $student->enrollments()->latest('id')->first();

        return [
            'eligible' => $item !== null,
            'outcome' => $lifecycle['outcome'],
            'isCompletion' => $lifecycle['isCompletion'],
            'isGraduation' => $lifecycle['isGraduation'],
            'isTerminal' => $lifecycle['isTerminal'],
            'item' => $item,
            'graduationDate' => $item?->approved_at?->toDateString(),
            'completionAcademicYearId' => $item?->placement?->academic_year_id,
            'completionAcademicYear' => $item?->placement?->academicYear,
            'completedCourseId' => $latestEnrollment?->course_id,
            'completedBatchId' => $latestEnrollment?->batch_id,
        ];
    }

    /**
     * Create (activate) the alumni profile for a student. Idempotent: if a
     * profile already exists it is returned unchanged. Throws when the
     * student has no approved completed/graduated outcome (eligibility is
     * always enforced server-side).
     */
    public function createForStudent(Student $student, int $actorId, array $data = []): Alumni
    {
        $existing = Alumni::query()->where('student_id', $student->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $item = $this->eligibleItem($student);

        if ($item === null) {
            throw new LogicException('This student has no approved completed/graduated outcome and cannot be added to Alumni.');
        }

        $latestEnrollment = $student->enrollments()->latest('id')->first();

        $alumni = Alumni::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'status' => Alumni::STATUS_ACTIVE,
            'alumni_reference_number' => $data['alumni_reference_number'] ?? null,
            'graduation_date' => $data['graduation_date'] ?? $item->approved_at?->toDateString(),
            'completion_academic_year_id' => $item?->placement?->academic_year_id,
            'completed_course_id' => $latestEnrollment?->course_id,
            'completed_batch_id' => $latestEnrollment?->batch_id,
            'crm_contact_id' => $student->crm_contact_id,
            'created_by' => $actorId,
        ]);

        $this->audit->record(
            $student->institute_id,
            $actorId,
            'alumni.created',
            $alumni->id,
            null,
            [
                'student_id' => $student->id,
                'status' => $alumni->status,
                'graduation_date' => $alumni->graduation_date?->toDateString(),
                'completion_academic_year_id' => $alumni->completion_academic_year_id,
                'completed_course_id' => $alumni->completed_course_id,
                'completed_batch_id' => $alumni->completed_batch_id,
            ],
        );

        return $alumni;
    }

    /**
     * Update only the alumni/career fields. Academic provenance is read-only.
     */
    public function updateProfile(Alumni $alumni, array $data, int $actorId): Alumni
    {
        $old = $alumni->only(self::PROFILE_FIELDS);
        $changes = Arr::only($data, self::PROFILE_FIELDS);

        $alumni->fill($changes);
        $alumni->updated_by = $actorId;
        $alumni->save();

        $this->audit->record(
            $alumni->institute_id,
            $actorId,
            'alumni.updated',
            $alumni->id,
            $old,
            $alumni->only(self::PROFILE_FIELDS),
        );

        return $alumni;
    }

    public function setStatus(Alumni $alumni, string $status, int $actorId): Alumni
    {
        if (! in_array($status, Alumni::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid alumni status.');
        }

        $old = ['status' => $alumni->status];

        $alumni->fill(['status' => $status, 'updated_by' => $actorId]);
        $alumni->save();

        $this->audit->record(
            $alumni->institute_id,
            $actorId,
            'alumni.status_changed',
            $alumni->id,
            $old,
            ['status' => $status],
        );

        return $alumni;
    }

    public function delete(Alumni $alumni, int $actorId): void
    {
        $this->audit->record(
            $alumni->institute_id,
            $actorId,
            'alumni.deleted',
            $alumni->id,
            $alumni->only(self::PROFILE_FIELDS),
            null,
        );

        $alumni->delete();
    }

    /**
     * Tenant + branch isolated directory query with filters.
     */
    public function directoryQuery(array $filters = []): Builder
    {
        return Alumni::query()
            ->inScope()
            ->with(['student', 'completionAcademicYear', 'completedCourse', 'completedBatch', 'crmContact'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('alumni_reference_number', 'like', "%{$search}%")
                        ->orWhereHas('student', function (Builder $query) use ($search) {
                            $query->where('full_name', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('reg_no', 'like', "%{$search}%")
                                ->orWhere('student_id_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['completion_academic_year_id'] ?? null, fn (Builder $query, int $yearId) => $query->where('completion_academic_year_id', $yearId))
            ->when($filters['completed_course_id'] ?? null, fn (Builder $query, int $courseId) => $query->where('completed_course_id', $courseId))
            ->when($filters['completed_batch_id'] ?? null, fn (Builder $query, int $batchId) => $query->where('completed_batch_id', $batchId))
            ->when($filters['current_occupation'] ?? null, function (Builder $query, string $occupation) {
                $query->where('current_occupation', 'like', "%{$occupation}%");
            })
            ->when($filters['employer'] ?? null, function (Builder $query, string $employer) {
                $query->where('employer', 'like', "%{$employer}%");
            })
            ->when($filters['graduation_year'] ?? null, function (Builder $query, string $year) {
                $query->whereYear('graduation_date', (int) $year);
            })
            ->latest('id');
    }

    /**
     * Student search for the "Add Alumni" flow: returns students that do NOT
     * yet have an alumni profile, with their eligibility summary attached.
     *
     * @return Collection<int, array{student: Student, eligibility: array}>
     */
    public function searchEligibleStudents(string $q, int $limit = 20): Collection
    {
        $students = Student::query()
            ->whereDoesntHave('alumniProfile')
            ->where(function (Builder $query) use ($q) {
                $query->where('full_name', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('reg_no', 'like', "%{$q}%")
                    ->orWhere('student_id_number', 'like', "%{$q}%");
            })
            ->latest('id')
            ->limit($limit)
            ->get();

        return $students->map(fn (Student $student) => [
            'student' => $student,
            'eligibility' => $this->eligibility($student),
        ]);
    }

    /**
     * Aggregate report for the whole institute (or one branch), isolated by
     * tenant + branch. Returns label/total rows for each breakdown.
     *
     * @return array{
     *     totals: array{total: int, active: int, inactive: int},
     *     by_course: Collection,
     *     by_batch: Collection,
     *     by_academic_year: Collection,
     *     by_graduation_year: Collection,
     *     by_occupation: Collection,
     *     top_employers: Collection,
     * }
     */
    public function reportAggregates(int $instituteId, ?int $branchId = null): array
    {
        $base = fn () => Alumni::query()->inScope()
            ->when($branchId, fn (Builder $query) => $query->whereHas(
                'student',
                fn (Builder $student) => $student->where('branch_id', $branchId)
            ));

        $totals = $base()
            ->selectRaw('count(*) as total, sum(case when status = ? then 1 else 0 end) as active, sum(case when status = ? then 1 else 0 end) as inactive', [Alumni::STATUS_ACTIVE, Alumni::STATUS_INACTIVE])
            ->first();

        $byCourse = $base()
            ->join('courses', 'courses.id', '=', 'alumni.completed_course_id')
            ->select('courses.name as label', DB::raw('count(*) as total'))
            ->whereNotNull('alumni.completed_course_id')
            ->groupBy('courses.id', 'courses.name')
            ->orderByDesc('total')
            ->get();

        $byBatch = $base()
            ->join('batches', 'batches.id', '=', 'alumni.completed_batch_id')
            ->select('batches.name as label', DB::raw('count(*) as total'))
            ->whereNotNull('alumni.completed_batch_id')
            ->groupBy('batches.id', 'batches.name')
            ->orderByDesc('total')
            ->get();

        $byAcademicYear = $base()
            ->join('academic_years', 'academic_years.id', '=', 'alumni.completion_academic_year_id')
            ->select('academic_years.name as label', DB::raw('count(*) as total'))
            ->whereNotNull('alumni.completion_academic_year_id')
            ->groupBy('academic_years.id', 'academic_years.name')
            ->orderByDesc('total')
            ->get();

        $byGraduationYear = $base()
            ->selectRaw('year(graduation_date) as label, count(*) as total')
            ->whereNotNull('graduation_date')
            ->groupByRaw('year(graduation_date)')
            ->orderByDesc('label')
            ->get();

        // ONLY_FULL_GROUP_BY (Laravel sets 'strict' => true) rejects grouping
        // by a function expression over a nullable column, so we group by the
        // SELECT alias instead (same result, MariaDB accepts it).
        $byOccupation = $base()
            ->selectRaw('coalesce(nullif(trim(current_occupation), ""), "Not specified") as label, count(*) as total')
            ->groupByRaw('label')
            ->orderByDesc('total')
            ->get();

        $topEmployers = $base()
            ->selectRaw('coalesce(nullif(trim(employer), ""), "Not specified") as label, count(*) as total')
            ->whereNotNull('employer')
            ->groupByRaw('label')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'totals' => [
                'total' => (int) ($totals->total ?? 0),
                'active' => (int) ($totals->active ?? 0),
                'inactive' => (int) ($totals->inactive ?? 0),
            ],
            'by_course' => $byCourse,
            'by_batch' => $byBatch,
            'by_academic_year' => $byAcademicYear,
            'by_graduation_year' => $byGraduationYear,
            'by_occupation' => $byOccupation,
            'top_employers' => $topEmployers,
        ];
    }

    /**
     * The latest approved completed/graduated decision item for the student —
     * the existing canonical eligibility rule (same as certificate requests).
     */
    private function eligibleItem(Student $student): ?PromotionDecisionItem
    {
        return PromotionDecisionItem::query()
            ->where('student_id', $student->id)
            ->whereIn('decision', self::TERMINAL_OUTCOMES)
            ->whereHas('decision', function (Builder $query) {
                $query->where('status', PromotionDecision::STATUS_APPROVED)
                    ->whereHas('result', fn (Builder $result) => $result->where('status', AcademicFinalResult::STATUS_PUBLISHED));
            })
            ->with(['placement.academicYear', 'decision.result.scheme.academicYear'])
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->first();
    }
}
