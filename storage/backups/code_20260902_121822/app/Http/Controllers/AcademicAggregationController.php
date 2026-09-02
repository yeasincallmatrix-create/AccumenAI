<?php

namespace App\Http\Controllers;

use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicResultAggregationService;
use App\Services\AcademicSubjectService;
use App\Support\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Aggregation scheme configuration + derived aggregation preview.
 *
 * Security model mirrors AcademicAssessmentController:
 *   - Institute identity comes ONLY from the authenticated user / workspace.
 *   - branch_id is NEVER read from input; it comes from the acting user's
 *     assigned branch (null = whole-institute scheme).
 *   - Institute + branch visibility is enforced by the scheme's global scopes,
 *     so implicit route-model binding already 404s cross-tenant and
 *     cross-branch records.
 *   - Every item assessment must belong to the scheme's year + class + group
 *     context and to the current institute (AcademicAssessment global scopes),
 *     so cross-tenant / wrong-context IDs are rejected server-side.
 *   - The whole route group is gated behind permission:education.manage.
 */
class AcademicAggregationController extends Controller
{
    public function __construct(
        private readonly AcademicResultAggregationService $aggregations,
        private readonly AcademicSubjectService $subjects
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = AcademicResultAggregationScheme::query()
            ->with(['academicYear', 'classGrade', 'academicGroup', 'branch'])
            ->withCount('items');

        if (filled($request->query('academic_year_id'))) {
            $query->where('academic_year_id', (int) $request->query('academic_year_id'));
        }

        if (filled($request->query('class_grade_id'))) {
            $query->where('class_grade_id', (int) $request->query('class_grade_id'));
        }

        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        $schemes = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.academic-aggregations.index', [
            'institute' => $institute,
            'schemes' => $schemes,
            'academicYears' => AcademicYear::query()->orderByDesc('code')->get(),
            'classes' => $this->subjects->effectiveClasses($institute),
            'academicYearId' => $request->query('academic_year_id'),
            'classGradeId' => $request->query('class_grade_id'),
            'status' => $request->query('status'),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.academic-aggregations.form', [
            'institute' => $institute,
            'scheme' => null,
            'academicYears' => AcademicYear::query()->orderByDesc('code')->get(),
            'classes' => $this->subjects->effectiveClasses($institute),
        ]);
    }

    /**
     * AJAX: assessments available for a year/class/group context (used by the
     * dynamic assessment table in the scheme form).
     */
    public function assessments(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'class_grade_id' => ['required', 'integer'],
            'academic_group_id' => ['nullable', 'integer'],
        ]);

        $year = AcademicYear::query()
            ->where('institute_id', $institute->id)
            ->find((int) $data['academic_year_id']);
        abort_if($year === null, 422, 'Invalid academic year.');

        $classGrade = $this->classWithinInstitute($institute, (int) $data['class_grade_id']);
        abort_if($classGrade === null, 422, 'Invalid class / grade.');

        $group = null;
        if (filled($data['academic_group_id'] ?? null)) {
            $group = $classGrade->groups()->where('status', true)->find((int) $data['academic_group_id']);
            abort_if($group === null, 422, 'Invalid group / stream.');
        }

        return response()->json([
            'assessments' => $this->aggregations->assessmentsForContext($institute, $year, $classGrade, $group)->map(
                fn ($assessment) => [
                    'id' => (int) $assessment->id,
                    'name' => $assessment->name,
                    'type' => $assessment->assessmentType?->name,
                    'subjects_count' => $assessment->subjects()->count(),
                ]
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $this->validated($request);

        $scheme = $this->aggregations->store(
            $institute,
            $this->actingBranch($request),
            $this->creatorId($request),
            $data,
            $this->itemPayload($request)
        );

        return redirect()
            ->route('settings.academic.aggregations.show', $scheme)
            ->with('status', 'Scheme "'.$scheme->name.'" saved.');
    }

    public function show(Request $request, AcademicResultAggregationScheme $scheme): View
    {
        $this->requireInstitute($request);

        $scheme->load(['academicYear', 'classGrade', 'academicGroup', 'branch', 'creator', 'items.assessment.assessmentType']);

        $subjectId = $request->query('subject_id');
        $subjectId = $subjectId !== null && in_array((int) $subjectId, $this->aggregations->coveredSubjectIds($scheme), true)
            ? (int) $subjectId
            : null;

        return view('institute.academic-aggregations.show', [
            'scheme' => $scheme,
            'coveredSubjects' => Subject::query()->whereIn('id', $this->aggregations->coveredSubjectIds($scheme))->get(),
            'selectedSubjectId' => $subjectId,
            'preview' => $subjectId !== null ? $this->aggregations->preview($scheme, $subjectId) : null,
        ]);
    }

    public function edit(Request $request, AcademicResultAggregationScheme $scheme): View
    {
        $this->requireInstitute($request);

        $scheme->load(['academicYear', 'classGrade', 'academicGroup', 'items.assessment.assessmentType']);

        return view('institute.academic-aggregations.form', [
            'institute' => $scheme->institute,
            'scheme' => $scheme,
            'academicYears' => AcademicYear::query()->orderByDesc('code')->get(),
            'classes' => $this->subjects->effectiveClasses($scheme->institute),
            'preselectedItems' => $scheme->items->map(fn ($item) => [
                'assessment_id' => (int) $item->academic_assessment_id,
                'name' => $item->assessment?->name,
                'weight' => (float) $item->weight,
            ])->all(),
        ]);
    }

    public function update(Request $request, AcademicResultAggregationScheme $scheme): RedirectResponse
    {
        $this->requireInstitute($request);

        $scheme = $this->aggregations->update($scheme, $this->validated($request), $this->itemPayload($request));

        return redirect()
            ->route('settings.academic.aggregations.show', $scheme)
            ->with('status', 'Scheme updated.');
    }

    public function destroy(Request $request, AcademicResultAggregationScheme $scheme): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->aggregations->destroy($scheme);

        return redirect()
            ->route('settings.academic.aggregations.index')
            ->with('status', 'Aggregation scheme removed.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'class_grade_id' => ['required', 'integer'],
            'academic_group_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::in(AcademicResultAggregationScheme::STATUSES)],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * Extract the nested assessment + weight rows submitted by the form.
     *
     * @return array<int, array<string, mixed>>
     */
    private function itemPayload(Request $request): array
    {
        $items = $request->input('items') ?? [];
        $payload = [];

        foreach ($items as $index => $row) {
            if (! isset($row['assessment_id']) || $row['assessment_id'] === '' || $row['assessment_id'] === null) {
                continue;
            }

            $payload[] = [
                'assessment_id' => (int) $row['assessment_id'],
                'weight' => (float) ($row['weight'] ?? 0),
                'display_order' => $index + 1,
                'status' => ($row['active'] ?? '1') === '0' ? 'inactive' : 'active',
            ];
        }

        return $payload;
    }

    private function actingBranch(Request $request): ?Branch
    {
        $user = $request->user();

        if ($user instanceof InstituteUser && $user->branch_id !== null) {
            return Branch::query()->find($user->branch_id);
        }

        return null;
    }

    private function creatorId(Request $request): ?int
    {
        $user = $request->user();

        return $user instanceof InstituteUser ? (int) $user->id : null;
    }

    private function classWithinInstitute(Institute $institute, int $classGradeId): ?ClassGrade
    {
        foreach ($this->subjects->effectiveClasses($institute) as $entry) {
            if ((int) $entry['class_grade']->id === $classGradeId) {
                return $entry['class_grade'];
            }
        }

        return null;
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
