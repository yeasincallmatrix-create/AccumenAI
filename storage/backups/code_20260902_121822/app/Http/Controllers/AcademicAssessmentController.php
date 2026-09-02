<?php

namespace App\Http\Controllers;

use App\Models\AcademicAssessment;
use App\Models\AcademicYear;
use App\Models\AssessmentSubject;
use App\Models\AssessmentType;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Component;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use App\Services\AcademicAssessmentService;
use App\Services\AcademicSubjectService;
use App\Support\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Academic assessment (exam) configuration — Education Engine.
 *
 * Security model (mirrors StudentAcademicPlacementController):
 *   - Institute identity comes ONLY from the authenticated user / workspace
 *     (resolveInstitute); institute_id is never read from request input.
 *   - branch_id is never read from input; it comes from the acting user's
 *     assigned branch (null = whole-institute assessment).
 *   - Tenant + branch visibility is enforced by the AcademicAssessment global
 *     scopes, so implicit route-model binding already 404s cross-tenant and
 *     cross-branch records.
 *   - The whole route group is gated behind permission:education.manage.
 */
class AcademicAssessmentController extends Controller
{
    public function __construct(
        private readonly AcademicSubjectService $subjects,
        private readonly AcademicAssessmentService $service
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = AcademicAssessment::query()
            ->with(['academicYear', 'classGrade', 'academicGroup', 'assessmentType', 'branch'])
            ->withCount('subjects');

        if (filled($request->query('academic_year_id'))) {
            $query->where('academic_year_id', (int) $request->query('academic_year_id'));
        }

        if (filled($request->query('class_grade_id'))) {
            $query->where('class_grade_id', (int) $request->query('class_grade_id'));
        }

        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        $assessments = $query->orderBy('display_order')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.academic-assessments.index', [
            'institute' => $institute,
            'assessments' => $assessments,
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

        return view('institute.academic-assessments.form', [
            'institute' => $institute,
            'assessment' => null,
            'academicYears' => AcademicYear::query()->orderByDesc('code')->get(),
            'classes' => $this->subjects->effectiveClasses($institute),
            'assessmentTypes' => AssessmentType::query()->availableFor($institute)->get(),
            'components' => Component::query()->availableFor($institute)->get(),
            'classGrade' => null,
            'academicGroup' => null,
            'selectedSubjects' => [],
        ]);
    }

    /**
     * AJAX: selectable subjects for a class/group (used by the dynamic subject
     * table in the form).
     */
    public function subjects(Request $request, AcademicAssessment $assessment): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'class_grade_id' => ['required', 'integer'],
            'academic_group_id' => ['nullable', 'integer'],
        ]);

        $classGrade = $this->classWithinInstitute($institute, (int) $data['class_grade_id']);
        abort_if($classGrade === null, 422, 'Invalid class / grade.');

        $academicGroup = null;
        if (filled($data['academic_group_id'] ?? null)) {
            $academicGroup = $classGrade->groups()->where('status', true)->find((int) $data['academic_group_id']);
            abort_if($academicGroup === null, 422, 'Invalid group / stream.');
        }

        return response()->json([
            'subjects' => $this->service->subjectsForSelection($institute, $classGrade, $academicGroup),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $this->validated($request);

        $assessment = $this->service->store(
            $institute,
            $this->actingBranch($request),
            $this->creatorId($request),
            $data,
            $this->subjectPayload($request)
        );

        return redirect()
            ->route('settings.academic.assessments.show', $assessment)
            ->with('status', 'Assessment "'.$assessment->name.'" saved.');
    }

    public function show(Request $request, AcademicAssessment $assessment): View
    {
        $institute = $this->requireInstitute($request);

        $assessment->load([
            'academicYear',
            'classGrade',
            'academicGroup',
            'assessmentType',
            'branch',
            'creator',
            'lockedBy',
            'subjects.subject',
            'subjects.components.component',
        ]);

        return view('institute.academic-assessments.show', [
            'institute' => $institute,
            'assessment' => $assessment,
        ]);
    }

    public function edit(Request $request, AcademicAssessment $assessment): View
    {
        $institute = $this->requireInstitute($request);

        $assessment->load(['subjects.subject', 'subjects.components.component']);

        return view('institute.academic-assessments.form', [
            'institute' => $institute,
            'assessment' => $assessment,
            'academicYears' => AcademicYear::query()->orderByDesc('code')->get(),
            'classes' => $this->subjects->effectiveClasses($institute),
            'assessmentTypes' => AssessmentType::query()->availableFor($institute)->get(),
            'components' => Component::query()->availableFor($institute)->get(),
            'classGrade' => $assessment->classGrade,
            'academicGroup' => $assessment->academicGroup,
            'selectedSubjects' => $assessment->subjects->map(fn (AssessmentSubject $subject) => [
                'subject_id' => $subject->subject_id,
                'name' => $subject->subject?->name,
                'pass_rule' => $subject->pass_rule,
                'components' => $subject->components->map(fn ($component) => [
                    'component_id' => $component->component_id,
                    'name' => $component->component?->name,
                    'full_mark' => $component->full_mark,
                    'pass_mark' => $component->pass_mark,
                    'mandatory_pass' => (bool) $component->mandatory_pass,
                ])->all(),
            ])->all(),
        ]);
    }

    public function update(Request $request, AcademicAssessment $assessment): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $this->validated($request, $assessment);

        $assessment = $this->service->update(
            $institute,
            $assessment,
            $data,
            $this->subjectPayload($request),
            $this->creatorId($request)
        );

        return redirect()
            ->route('settings.academic.assessments.show', $assessment)
            ->with('status', 'Assessment updated.');
    }

    public function destroy(Request $request, AcademicAssessment $assessment): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->service->destroy($assessment, $this->creatorId($request));

        return redirect()
            ->route('settings.academic.assessments.index')
            ->with('status', 'Assessment removed.');
    }

    /**
     * Freeze an assessment so marks, configuration edits and deletion are
     * refused until an explicitly permission-gated unlock. The route group is
     * gated behind permission:education.manage (the unlock gate is identical).
     */
    public function lock(Request $request, AcademicAssessment $assessment): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->service->lock($assessment, $this->creatorId($request));

        return redirect()
            ->route('settings.academic.assessments.show', $assessment)
            ->with('status', 'Assessment locked. Marks and configuration are frozen.');
    }

    public function unlock(Request $request, AcademicAssessment $assessment): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->service->unlock($assessment, $this->creatorId($request));

        return redirect()
            ->route('settings.academic.assessments.show', $assessment)
            ->with('status', 'Assessment unlocked. Marks and configuration can be changed again.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AcademicAssessment $assessment = null): array
    {
        return $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'class_grade_id' => ['required', 'integer'],
            'academic_group_id' => ['nullable', 'integer'],
            'assessment_type_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'exam_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(AcademicAssessment::STATUSES)],
            'display_order' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
            'subjects' => ['nullable', 'array'],
            'subjects.*.subject_id' => ['required', 'integer'],
            'subjects.*.pass_rule' => ['nullable', Rule::in(AssessmentSubject::PASS_RULES)],
            'subjects.*.components' => ['nullable', 'array'],
            'subjects.*.components.*.component_id' => ['required', 'integer'],
            'subjects.*.components.*.full_mark' => ['required', 'numeric', 'min:0'],
            'subjects.*.components.*.pass_mark' => ['nullable', 'numeric', 'min:0'],
            'subjects.*.components.*.mandatory_pass' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Extract the nested subject/component payload from the request, keeping
     * only rows that were actually submitted.
     *
     * @return array<int, array<string, mixed>>
     */
    private function subjectPayload(Request $request): array
    {
        $subjects = $request->input('subjects') ?? [];
        $payload = [];

        foreach ($subjects as $row) {
            $components = [];
            foreach (Arr::wrap($row['components'] ?? []) as $component) {
                $components[] = [
                    'component_id' => (int) $component['component_id'],
                    'full_mark' => (float) $component['full_mark'],
                    'pass_mark' => (float) ($component['pass_mark'] ?? 0),
                    'mandatory_pass' => (bool) ($component['mandatory_pass'] ?? false),
                ];
            }

            $payload[] = [
                'subject_id' => (int) $row['subject_id'],
                'pass_rule' => in_array($row['pass_rule'] ?? null, AssessmentSubject::PASS_RULES, true)
                    ? $row['pass_rule']
                    : AssessmentSubject::PASS_RULE_TOTAL_ONLY,
                'components' => $components,
            ];
        }

        return $payload;
    }

    /**
     * The acting user's assigned branch (null = whole-institute access).
     * Never taken from request input.
     */
    private function actingBranch(Request $request): ?Branch
    {
        $user = $request->user();

        if ($user instanceof InstituteUser && $user->branch_id !== null) {
            return Branch::query()->find($user->branch_id);
        }

        return null;
    }

    /**
     * created_by references institute_users; platform users acting through a
     * workspace leave it empty.
     */
    private function creatorId(Request $request): ?int
    {
        $user = $request->user();

        return $user instanceof InstituteUser ? (int) $user->id : null;
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
