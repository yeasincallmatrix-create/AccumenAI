<?php

namespace App\Http\Controllers;

use App\Models\AcademicFinalResult;
use App\Models\AcademicYear;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PromotionDecision;
use App\Models\PromotionPolicy;
use App\Models\PromotionPolicyRule;
use App\Models\User;
use App\Services\AcademicSubjectService;
use App\Services\PromotionDecisionExportService;
use App\Services\PromotionEvaluationService;
use App\Services\PromotionLifecycleService;
use App\Services\PromotionPolicyService;
use App\Support\CsvStream;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Institute-facing academic promotion / progression (Step 11).
 *
 * Security model mirrors AcademicFinalResultController:
 *   - institute identity comes ONLY from the authenticated user / workspace;
 *     forged institute/branch/year/class/group ids are never trusted.
 *   - policies / decisions are tenant + branch scoped by their global scopes;
 *   - the promotion source is ONLY a PUBLISHED academic_final_results row
 *     (service-enforced, never a live recalculation);
 *   - a promotion never updates or deletes the source placement; the next-year
 *     placement is a new row created by PromotionPlacementService.
 *   - the whole route group is gated behind permission:promotion.manage.
 */
class AcademicPromotionController extends Controller
{
    public function __construct(
        private readonly AcademicSubjectService $subjects,
        private readonly PromotionPolicyService $policies,
        private readonly PromotionLifecycleService $lifecycle,
        private readonly PromotionEvaluationService $evaluator,
        private readonly PromotionDecisionExportService $decisionExporter
    ) {}

    // ------------------------------------------------------------- Policies

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = PromotionPolicy::query()
            ->with(['academicYear', 'classGrade', 'academicGroup', 'activeRules', 'decisions'])
            ->withCount(['rules', 'decisions'])
            ->orderByDesc('id');

        if (filled($request->query('academic_year_id'))) {
            $query->where('academic_year_id', (int) $request->query('academic_year_id'));
        }

        if (filled($request->query('class_grade_id'))) {
            $query->where('class_grade_id', (int) $request->query('class_grade_id'));
        }

        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        $policies = $query->get();

        return view('institute.academic-promotions.index', [
            'institute' => $institute,
            'policies' => $policies,
            'academicYears' => AcademicYear::query()->orderByDesc('code')->get(),
            'classes' => $this->subjects->effectiveClasses($institute),
            'academicYearId' => $request->query('academic_year_id'),
            'classGradeId' => $request->query('class_grade_id'),
            'status' => $request->query('status'),
        ]);
    }

    public function createPolicy(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.academic-promotions.policy-form', [
            'institute' => $institute,
            'policy' => null,
            'academicYears' => AcademicYear::query()->orderByDesc('code')->get(),
            'classes' => $this->subjects->effectiveClasses($institute),
        ]);
    }

    public function storePolicy(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'academic_year_id' => ['required', 'integer'],
            'class_grade_id' => ['required', 'integer'],
            'academic_group_id' => ['nullable', 'integer'],
            'status' => ['required', 'in:'.implode(',', PromotionPolicy::STATUSES)],
        ]);

        $policy = $this->policies->storePolicy($institute, $data, $this->creatorId($request));

        return redirect()
            ->route('settings.academic.promotions.policies.show', $policy)
            ->with('status', 'Promotion policy "'.$policy->name.'" created.');
    }

    public function showPolicy(Request $request, PromotionPolicy $policy): View
    {
        $this->requireInstitute($request);

        $policy->load(['academicYear', 'classGrade', 'academicGroup', 'rules', 'decisions']);
        $activeDecision = $policy->decisions->firstWhere(fn ($d) => in_array($d->status, PromotionDecision::ACTIVE_STATUSES, true));

        return view('institute.academic-promotions.policy', [
            'institute' => $policy->institute,
            'policy' => $policy,
            'activeDecision' => $activeDecision,
            'publishedResults' => $this->publishedResultsForPolicy($policy),
        ]);
    }

    public function editPolicy(Request $request, PromotionPolicy $policy): View
    {
        $this->requireInstitute($request);

        return view('institute.academic-promotions.policy-form', [
            'institute' => $policy->institute,
            'policy' => $policy,
            'academicYears' => AcademicYear::query()->orderByDesc('code')->get(),
            'classes' => $this->subjects->effectiveClasses($policy->institute),
        ]);
    }

    public function updatePolicy(Request $request, PromotionPolicy $policy): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'academic_year_id' => ['required', 'integer'],
            'class_grade_id' => ['required', 'integer'],
            'academic_group_id' => ['nullable', 'integer'],
            'status' => ['required', 'in:'.implode(',', PromotionPolicy::STATUSES)],
        ]);

        $this->policies->updatePolicy($institute, $policy, $data);

        return redirect()
            ->route('settings.academic.promotions.policies.show', $policy)
            ->with('status', 'Promotion policy updated.');
    }

    public function setPolicyStatus(Request $request, PromotionPolicy $policy): RedirectResponse
    {
        $this->requireInstitute($request);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', PromotionPolicy::STATUSES)],
        ]);

        $this->policies->setStatus($policy, $data['status']);

        return back()->with('status', 'Promotion policy status set to "'.$data['status'].'".');
    }

    // ------------------------------------------------------------- Rules

    public function storeRule(Request $request, PromotionPolicy $policy): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->policies->storeRule($policy, $this->validatedRule($request));

        return back()->with('status', 'Promotion rule added.');
    }

    public function updateRule(Request $request, PromotionPolicyRule $rule): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->policies->updateRule($institute, $rule, $this->validatedRule($request));

        return back()->with('status', 'Promotion rule updated.');
    }

    public function destroyRule(Request $request, PromotionPolicyRule $rule): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->policies->destroyRule($institute, $rule);

        return back()->with('status', 'Promotion rule removed.');
    }

    // ------------------------------------------------------------- Decisions

    public function storeDecision(Request $request, PromotionPolicy $policy): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'result_id' => ['required', 'integer'],
        ]);

        $result = AcademicFinalResult::query()->find((int) $data['result_id']);
        abort_if($result === null, 422, 'Invalid final result.');

        $decision = $this->lifecycle->createDecision($institute, $policy, $result, $this->creatorId($request));

        return redirect()
            ->route('settings.academic.promotions.decisions.show', $decision)
            ->with('status', 'Promotion decision generated from the published result.');
    }

    public function showDecision(Request $request, PromotionDecision $decision): View
    {
        $this->requireInstitute($request);

        $decision->load([
            'policy.academicYear',
            'policy.classGrade',
            'policy.academicGroup',
            'result.scheme.academicYear',
            'result.scheme.classGrade',
            'result.scheme.academicGroup',
            'items.placement.student',
            'items.placement.classGrade',
            'items.placement.academicGroup',
            'items.targetClassGrade',
            'items.targetAcademicGroup',
            'items.nextPlacement',
        ]);

        $metrics = $this->metricsByPlacement($decision->result);

        return view('institute.academic-promotions.decision', [
            'institute' => $decision->institute,
            'decision' => $decision,
            'metrics' => $metrics,
            'academicYears' => AcademicYear::query()->orderByDesc('code')->get(),
            'classes' => $this->subjects->effectiveClasses($decision->institute),
            'classGroupsMap' => $this->classGroupsMap($decision->institute),
        ]);
    }

    public function reviewDecision(Request $request, PromotionDecision $decision): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->lifecycle->review($decision, $this->creatorId($request));

        return back()->with('status', 'Promotion decision moved to review.');
    }

    public function sendBackToReview(Request $request, PromotionDecision $decision): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->lifecycle->sendBackToReview($decision, $this->creatorId($request));

        return back()->with('status', 'Promotion decision sent back to pending.');
    }

    public function approveDecision(Request $request, PromotionDecision $decision): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'target_year_id' => ['required', 'integer'],
            'targets' => ['nullable', 'array'],
            'targets.*.class_grade_id' => ['nullable', 'integer'],
            'targets.*.academic_group_id' => ['nullable', 'integer'],
        ]);

        $targetYear = AcademicYear::query()->where('institute_id', $institute->id)->find((int) $data['target_year_id']);
        abort_if($targetYear === null, 422, 'Invalid target academic year.');

        $this->lifecycle->approve($institute, $decision, $targetYear, $data['targets'] ?? [], $this->creatorId($request));

        return redirect()
            ->route('settings.academic.promotions.decisions.show', $decision)
            ->with('status', 'Promotion decision approved. Next-year placements created.');
    }

    public function cancelDecision(Request $request, PromotionDecision $decision): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->lifecycle->cancelDecision($decision, $this->creatorId($request));

        return back()->with('status', 'Promotion decision cancelled. Next-year placements removed where applicable.');
    }

    /**
     * CSV download (Step 25) of a promotion decision, read exclusively from
     * the frozen verdicts and the published result snapshot — never from live
     * marks or a recalculation. The {decision} route binding resolves through
     * the tenant + branch scoped PromotionDecision model, so cross-tenant /
     * cross-branch decisions already 404, and the decision's result must still
     * be the PUBLISHED snapshot it was generated from.
     */
    public function export(Request $request, PromotionDecision $decision)
    {
        $this->requireInstitute($request);

        abort_if($decision->result?->status !== AcademicFinalResult::STATUS_PUBLISHED, 404, 'Only decisions backed by a published final result can be exported.');

        $export = $this->decisionExporter->export($decision);

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    /**
     * Printable promotion sheet (Step 25): the official year-end promotion
     * register — per-student verdict, GPA / failed / incomplete metrics,
     * reasons, target and next-year placement — laid out for print. Read-only
     * and sourced from the same frozen verdicts + published snapshot.
     */
    public function sheet(Request $request, PromotionDecision $decision): View
    {
        $this->requireInstitute($request);

        $decision->load([
            'policy.academicYear',
            'policy.classGrade',
            'policy.academicGroup',
            'result.scheme.academicYear',
            'result.scheme.classGrade',
            'result.scheme.academicGroup',
            'items.placement.student',
            'items.placement.classGrade',
            'items.placement.academicGroup',
            'items.targetClassGrade',
            'items.targetAcademicGroup',
            'items.nextPlacement',
        ]);

        return view('institute.academic-promotions.sheet', [
            'institute' => $decision->institute,
            'decision' => $decision,
            'metrics' => $this->metricsByPlacement($decision->result),
        ]);
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return array<string, mixed>
     */
    private function validatedRule(Request $request): array
    {
        return $request->validate([
            'rule_type' => ['required', 'string', 'in:'.implode(',', PromotionPolicyRule::RULE_TYPES)],
            'field' => ['nullable', 'string'],
            'operator' => ['nullable', 'string', 'in:'.implode(',', PromotionPolicyRule::OPERATORS)],
            'value' => ['nullable', 'string'],
            'pass_action' => ['required', 'string', 'in:'.implode(',', PromotionPolicyRule::ACTIONS)],
            'fail_action' => ['required', 'string', 'in:'.implode(',', PromotionPolicyRule::ACTIONS)],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Published results whose scheme context matches the policy context.
     *
     * @return Collection<int, AcademicFinalResult>
     */
    private function publishedResultsForPolicy(PromotionPolicy $policy): Collection
    {
        return AcademicFinalResult::query()
            ->with(['scheme.academicYear', 'scheme.classGrade', 'scheme.academicGroup'])
            ->where('status', AcademicFinalResult::STATUS_PUBLISHED)
            ->whereHas('scheme', fn ($scheme) => $scheme
                ->where('academic_year_id', $policy->academic_year_id)
                ->where('class_grade_id', $policy->class_grade_id)
                ->where(fn ($q) => $policy->academic_group_id === null
                    ? $q->whereNull('academic_group_id')
                    : $q->where('academic_group_id', $policy->academic_group_id)))
            ->orderByDesc('id')
            ->get();
    }

    /**
     * placement_id → snapshot metrics for display on the decision page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function metricsByPlacement(AcademicFinalResult $result): array
    {
        $rowsByPlacement = $result->rows->groupBy('placement_id');

        $metrics = [];
        foreach ($result->students as $student) {
            $placementId = (int) $student->placement_id;
            $metrics[$placementId] = $this->evaluator->inputForStudent($student, $rowsByPlacement->get($placementId)?->all() ?? []);
        }

        return $metrics;
    }

    /**
     * class_id → active groups, for the per-item target class/group selects.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function classGroupsMap(Institute $institute): array
    {
        $map = [];
        foreach ($this->subjects->effectiveClasses($institute) as $entry) {
            $classGrade = $entry['class_grade'];
            $map[(int) $classGrade->id] = $classGrade->groups()
                ->where('status', true)
                ->orderBy('display_order')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn ($group) => ['id' => (int) $group->id, 'name' => $group->name])
                ->all();
        }

        return $map;
    }

    private function creatorId(Request $request): ?int
    {
        $user = $request->user();

        return $user instanceof InstituteUser ? (int) $user->id : null;
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
