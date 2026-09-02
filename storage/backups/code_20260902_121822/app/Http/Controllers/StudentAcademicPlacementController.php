<?php

namespace App\Http\Controllers;

use App\Models\AcademicFinalResultRow;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicGroup;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PromotionDecisionItem;
use App\Models\PromotionPolicy;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\User;
use App\Services\AcademicSubjectService;
use App\Services\BatchAuditService;
use App\Services\StudentAcademicPlacementService;
use App\Support\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Student academic placements + subject selection (Education Engine).
 *
 * Security model:
 *   - Institute identity comes ONLY from the authenticated user / active
 *     workspace membership (see resolveInstitute()) — institute_id is never
 *     read from request input.
 *   - Students are resolved through the tenant + branch scopes of the Student
 *     model, so a branch-restricted user can only reach placements of students
 *     in their own branch.
 *   - Class/grades must belong to the institute's effective (country + override)
 *     structure; groups must belong to the selected class; academic years are
 *     institute-owned.
 *   - The whole route group is gated behind permission:education.manage.
 */
class StudentAcademicPlacementController extends Controller
{
    public function __construct(
        private readonly AcademicSubjectService $subjects,
        private readonly StudentAcademicPlacementService $service,
        private readonly BatchAuditService $audit
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $query = StudentAcademicPlacement::query()
            ->with(['student.branch', 'academicYear', 'classGrade', 'academicGroup'])
            ->inScope()
            ->withCount('selections');

        if (filled($request->query('academic_year_id'))) {
            $query->where('academic_year_id', (int) $request->query('academic_year_id'));
        }

        if (filled($request->query('class_grade_id'))) {
            $query->where('class_grade_id', (int) $request->query('class_grade_id'));
        }

        if (filled($request->query('academic_group_id'))) {
            $query->where('academic_group_id', (int) $request->query('academic_group_id'));
        }

        if (filled($request->query('branch_id'))) {
            $query->whereHas('student', fn ($student) => $student->where('branch_id', (int) $request->query('branch_id')));
        }

        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        if (filled($request->query('q'))) {
            $term = '%'.trim((string) $request->query('q')).'%';
            $query->whereHas('student', fn ($student) => $student
                ->where('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhere('student_id_number', 'like', $term)
                ->orWhere('reg_no', 'like', $term));
        }

        $placements = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.academic-placements.index', [
            'institute' => $institute,
            'placements' => $placements,
            'academicYears' => AcademicYear::query()->orderByDesc('code')->get(),
            'classes' => $this->subjects->effectiveClasses($institute),
            'groups' => $this->groupOptions($request),
            'branches' => Branch::query()->where('institute_id', $institute->id)->orderBy('name')->get(),
            'q' => $request->query('q'),
            'academicYearId' => $request->query('academic_year_id'),
            'classGradeId' => $request->query('class_grade_id'),
            'academicGroupId' => $request->query('academic_group_id'),
            'branchId' => $request->query('branch_id'),
            'status' => $request->query('status'),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $student = null;
        if (filled($request->query('student')) && ctype_digit((string) $request->query('student'))) {
            $student = Student::query()->find((int) $request->query('student'));
        }

        return view('institute.academic-placements.form', [
            'institute' => $institute,
            'placement' => null,
            'students' => Student::query()->with('branch')->orderBy('first_name')->orderBy('last_name')->get(),
            'preselectedStudent' => $student,
            'academicYears' => AcademicYear::query()->orderByDesc('code')->get(),
            'classes' => $this->subjects->effectiveClasses($institute),
            'classGrade' => null,
            'academicGroup' => null,
            'subjectPayload' => null,
            'selectedSubjectIds' => [],
        ]);
    }

    /**
     * AJAX curriculum for a class/group. Returns the rendered subject grid from
     * the same _subjects partial as the server-rendered form, so the UI has a
     * single source of truth for the selection controls.
     */
    public function subjects(Request $request): JsonResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $data = $request->validate([
            'class_grade_id' => ['required', 'integer'],
            'academic_group_id' => ['nullable', 'integer'],
            'selected_ids' => ['nullable', 'array'],
            'selected_ids.*' => ['integer'],
        ]);

        $classGrade = $this->classWithinInstitute($institute, (int) $data['class_grade_id']);
        abort_if($classGrade === null, 422, 'Invalid class / grade.');

        $academicGroup = null;
        if (filled($data['academic_group_id'] ?? null)) {
            $academicGroup = $classGrade->groups()->where('status', true)->find((int) $data['academic_group_id']);
            abort_if($academicGroup === null, 422, 'Invalid group / stream.');
        }

        $selected = array_map('intval', $data['selected_ids'] ?? []);

        $html = view('institute.academic-placements._subjects', [
            'payload' => $this->service->selectionData($institute, $classGrade, $academicGroup),
            'selectedSubjectIds' => $selected,
        ])->render();

        return response()->json(['html' => $html]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        $data = $this->validated($request);
        $student = Student::query()->findOrFail((int) $data['student_id']);
        $year = AcademicYear::query()->findOrFail((int) $data['academic_year_id']);
        $classGrade = $this->classWithinInstitute($institute, (int) $data['class_grade_id']);
        abort_if($classGrade === null, 422, 'Invalid class / grade.');
        $academicGroup = $this->groupWithinClass($classGrade, $data['academic_group_id'] ?? null);

        $placement = $this->service->storePlacement(
            $institute,
            $student,
            $year,
            $classGrade,
            $academicGroup,
            $data['subject_ids'] ?? [],
            $data['status'],
            $data['notes'] ?? null
        );

        return redirect()
            ->route('settings.academic.placements.show', $placement)
            ->with('status', 'Academic placement for '.$student->full_name.' saved.');
    }

    public function show(Request $request, StudentAcademicPlacement $placement): View
    {
        $this->assertPlacementVisible($placement);

        $placement->load(['student.branch', 'academicYear', 'classGrade', 'academicGroup', 'selections.subject', 'selections.selectionGroup']);

        return view('institute.academic-placements.show', [
            'institute' => $this->requireInstitute($request),
            'placement' => $placement,
        ]);
    }

    public function edit(Request $request, StudentAcademicPlacement $placement): View
    {
        $this->assertPlacementVisible($placement);

        $institute = $this->requireInstitute($request);

        return view('institute.academic-placements.form', [
            'institute' => $institute,
            'placement' => $placement,
            'students' => Student::query()->with('branch')->orderBy('first_name')->orderBy('last_name')->get(),
            'preselectedStudent' => $placement->student,
            'academicYears' => AcademicYear::query()->orderByDesc('code')->get(),
            'classes' => $this->subjects->effectiveClasses($institute),
            'classGrade' => $placement->classGrade,
            'academicGroup' => $placement->academicGroup,
            'subjectPayload' => $placement->classGrade !== null
                ? $this->service->selectionData($institute, $placement->classGrade, $placement->academicGroup)
                : null,
            'selectedSubjectIds' => $placement->selections()->pluck('subject_id')->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function update(Request $request, StudentAcademicPlacement $placement): RedirectResponse
    {
        $this->assertPlacementVisible($placement);
        $institute = $this->requireInstitute($request);

        $data = $this->validated($request, $placement);
        $year = AcademicYear::query()->findOrFail((int) $data['academic_year_id']);
        $classGrade = $this->classWithinInstitute($institute, (int) $data['class_grade_id']);
        abort_if($classGrade === null, 422, 'Invalid class / grade.');
        $academicGroup = $this->groupWithinClass($classGrade, $data['academic_group_id'] ?? null);

        $this->service->updatePlacement(
            $institute,
            $placement,
            $year,
            $classGrade,
            $academicGroup,
            $data['subject_ids'] ?? [],
            $data['status'],
            $data['notes'] ?? null
        );

        return redirect()
            ->route('settings.academic.placements.show', $placement)
            ->with('status', 'Academic placement updated.');
    }

    public function destroy(Request $request, StudentAcademicPlacement $placement): RedirectResponse
    {
        $this->assertPlacementVisible($placement);

        if ($this->placementHasHistory($placement)) {
            return redirect()
                ->route('settings.academic.placements.index')
                ->withErrors(['placement' => 'This placement is part of recorded final-result or promotion history and cannot be removed.']);
        }

        $placement->delete();

        return redirect()
            ->route('settings.academic.placements.index')
            ->with('status', 'Academic placement removed.');
    }

    /**
     * P3-1 — Archive (soft-delete) a placement for withdrawn/transferred students.
     * Historical marks/results snapshots remain untouched.
     */
    public function archive(Request $request, StudentAcademicPlacement $placement): RedirectResponse
    {
        $this->assertPlacementVisible($placement);

        $institute = $this->requireInstitute($request);
        // Ensure placement belongs to institute via student + year
        if ((int) $placement->institute_id !== (int) $institute->id) {
            abort(403, 'Placement does not belong to this institute.');
        }

        try {
            $this->service->archivePlacement($placement, (int) $request->user()->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('settings.academic.placements.show', $placement->getKey())
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('settings.academic.placements.index')
            ->with('status', 'Academic placement archived.');
    }

    // ------------------------------------------------------------- Academic Years

    public function storeAcademicYear(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', Rule::unique('academic_years', 'code')->where('institute_id', $institute->id)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_current' => ['nullable', 'boolean'],
        ]);

        $isCurrent = (bool) ($data['is_current'] ?? false);
        $actorId = (int) $request->user()->id;
        $yearId = null;

        DB::transaction(function () use ($institute, $data, $isCurrent, &$yearId) {
            $year = AcademicYear::create([
                'institute_id' => $institute->id,
                'name' => trim($data['name']),
                'code' => trim($data['code']),
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'is_current' => false,
                'status' => true,
            ]);

            $yearId = $year->id;

            $this->setCurrentYear($institute, $year, $isCurrent);
        });

        $this->audit->record($institute->id, $actorId, 'academic_year_created', $yearId, null, [
            'name' => trim($data['name']),
            'code' => trim($data['code']),
            'is_current' => $isCurrent,
        ], 'academic-sessions');

        return redirect()
            ->route('settings.academic.placements.index')
            ->with('status', 'Academic year added.');
    }

    public function updateAcademicYear(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', Rule::unique('academic_years', 'code')->where('institute_id', $institute->id)->ignore($academicYear->id)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_current' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        $isCurrent = (bool) ($data['is_current'] ?? false);
        $actorId = (int) $request->user()->id;

        $old = [
            'name' => $academicYear->name,
            'code' => $academicYear->code,
            'start_date' => $academicYear->start_date?->toDateString(),
            'end_date' => $academicYear->end_date?->toDateString(),
            'is_current' => $academicYear->is_current,
            'status' => $academicYear->status,
        ];

        DB::transaction(function () use ($institute, $academicYear, $data, $isCurrent) {
            $academicYear->update([
                'name' => trim($data['name']),
                'code' => trim($data['code']),
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'is_current' => false,
                'status' => (bool) ($data['status'] ?? true),
            ]);

            // Closing a session also ends its "current" status.
            if (! (bool) ($data['status'] ?? true)) {
                $academicYear->update(['is_current' => false]);
            }

            // A closed session can never be (re)marked as current.
            if ((bool) ($data['status'] ?? true)) {
                $this->setCurrentYear($institute, $academicYear, $isCurrent);
            }
        });

        $new = [
            'name' => $academicYear->name,
            'code' => $academicYear->code,
            'start_date' => $academicYear->start_date?->toDateString(),
            'end_date' => $academicYear->end_date?->toDateString(),
            'is_current' => $academicYear->is_current,
            'status' => $academicYear->status,
        ];

        $this->audit->record($institute->id, $actorId, 'academic_year_updated', $academicYear->id, $old, $new, 'academic-sessions');

        return redirect()
            ->route('settings.academic.placements.index')
            ->with('status', 'Academic year updated.');
    }

    public function destroyAcademicYear(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->requireInstitute($request);

        if ($this->academicYearHasHistory($academicYear)) {
            return redirect()
                ->route('settings.academic.placements.index')
                ->withErrors(['academic_year' => 'This academic year has placements or recorded results/promotion history and cannot be removed.']);
        }

        $old = [
            'name' => $academicYear->name,
            'code' => $academicYear->code,
            'start_date' => $academicYear->start_date?->toDateString(),
            'end_date' => $academicYear->end_date?->toDateString(),
            'is_current' => $academicYear->is_current,
            'status' => $academicYear->status,
        ];

        $academicYear->delete();

        $this->audit->record((int) $academicYear->institute_id, (int) $request->user()->id, 'academic_year_deleted', $academicYear->id, $old, null, 'academic-sessions');

        return redirect()
            ->route('settings.academic.placements.index')
            ->with('status', 'Academic year removed.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * Academic groups/streams actually present in placements visible in the
     * current tenant + branch scope, optionally narrowed by the selected year
     * and class. Only groups that exist in real placements are offered, so the
     * dropdown never suggests a group that has no students in scope.
     *
     * @return Collection<int, AcademicGroup>
     */
    private function groupOptions(Request $request): Collection
    {
        return StudentAcademicPlacement::query()
            ->inScope()
            ->when(filled($request->query('academic_year_id')), fn ($q) => $q->where('academic_year_id', (int) $request->query('academic_year_id')))
            ->when(filled($request->query('class_grade_id')), fn ($q) => $q->where('class_grade_id', (int) $request->query('class_grade_id')))
            ->whereNotNull('academic_group_id')
            ->with('academicGroup')
            ->get(['academic_group_id'])
            ->map(fn (StudentAcademicPlacement $placement) => $placement->academicGroup)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Enforce zero-or-one current academic year per institute.
     *
     * When the selected year is marked current, every other current year of
     * the same institute is atomically unset first, then the selected year is
     * marked current. Unmarking (is_current = false) never touches other
     * years. Other institutes are never affected.
     */
    private function setCurrentYear(Institute $institute, AcademicYear $year, bool $isCurrent): void
    {
        if ($isCurrent) {
            AcademicYear::query()
                ->where('institute_id', $institute->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->update(['is_current' => false]);
        }

        $year->update(['is_current' => $isCurrent]);
    }

    /**
     * A placement that fed a final-result snapshot (academic_final_result_students /
     * academic_final_result_rows) or promotion history (promotion_decision_items —
     * either as the source placement or as the approved next-year target) must
     * never disappear; deleting it would cascade historical records.
     */
    private function placementHasHistory(StudentAcademicPlacement $placement): bool
    {
        return AcademicFinalResultStudent::query()->where('placement_id', $placement->id)->exists()
            || AcademicFinalResultRow::query()->where('placement_id', $placement->id)->exists()
            || PromotionDecisionItem::query()
                ->where('placement_id', $placement->id)
                ->orWhere('next_placement_id', $placement->id)
                ->exists();
    }

    /**
     * An academic year that holds placements, final-result configuration
     * (aggregation schemes) or promotion configuration (policies) is part of
     * the historical record and cannot be cascade-deleted.
     */
    private function academicYearHasHistory(AcademicYear $academicYear): bool
    {
        return $academicYear->placements()->exists()
            || AcademicResultAggregationScheme::query()->where('academic_year_id', $academicYear->id)->exists()
            || PromotionPolicy::query()->where('academic_year_id', $academicYear->id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?StudentAcademicPlacement $placement = null): array
    {
        return $request->validate([
            'student_id' => [
                'required', 'integer',
                Rule::in(Student::query()->pluck('id')->all()),
            ],
            'academic_year_id' => [
                'required', 'integer',
                Rule::in(AcademicYear::query()->pluck('id')->all()),
            ],
            'class_grade_id' => ['required', 'integer'],
            'academic_group_id' => ['nullable', 'integer'],
            'status' => ['required', Rule::in(StudentAcademicPlacement::STATUSES)],
            'notes' => ['nullable', 'string', 'max:500'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer'],
        ]);
    }

    /**
     * A class is valid for the institute when it appears in the institute's
     * effective (country + overrides) class list.
     */
    private function classWithinInstitute(Institute $institute, int $classGradeId): ?ClassGrade
    {
        foreach ($this->subjects->effectiveClasses($institute) as $entry) {
            if ((int) $entry['class_grade']->id === $classGradeId) {
                return $entry['class_grade'];
            }
        }

        return null;
    }

    private function groupWithinClass(ClassGrade $classGrade, int|string|null $groupId): ?AcademicGroup
    {
        if (! filled($groupId)) {
            return null;
        }

        $group = $classGrade->groups()->where('status', true)->find((int) $groupId);
        abort_if($group === null, 422, 'Invalid group / stream.');

        return $group;
    }

    /**
     * Branch-restricted users may only reach placements of students in their
     * branch. The Student model is BranchScoped, so a cross-branch placement
     * has no visible student → 403.
     */
    private function assertPlacementVisible(StudentAcademicPlacement $placement): void
    {
        abort_if($placement->student === null, 403, 'This academic placement is not accessible.');
    }

    private function requireInstitute(Request $request): Institute
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        return $institute;
    }

    private function resolveInstitute(Request $request): ?Institute
    {
        $user = $request->user();

        if ($user instanceof InstituteUser) {
            return Institute::query()->find($user->institute_id);
        }

        if ($user instanceof User) {
            $membership = Workspace::membership();

            return $membership !== null ? Institute::query()->find($membership->institution_id) : null;
        }

        return null;
    }
}
