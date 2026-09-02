<?php

namespace App\Http\Controllers;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicFinalResultRow;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicResultAggregationScheme;
use App\Models\GradeScale;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\StudentAcademicPlacement;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicFinalResultLifecycleService;
use App\Services\AcademicFinalResultPreflightService;
use App\Services\AcademicResultExportService;
use App\Support\CsvStream;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Institute-facing final-result lifecycle (Step 10).
 *
 * Security model mirrors AcademicGradingController:
 *   - institute identity comes ONLY from the authenticated user / workspace
 *     (forged input is ignored); requireInstitute() aborts with 404.
 *   - schemes / policies / results are tenant + branch scoped by their global
 *     scopes, so implicit route-model binding already 404s cross-tenant and
 *     cross-branch records. The branch of a created result is copied from the
 *     policy (which inherits it from the scheme) — never from request input.
 *   - the grade-scale policy override must belong to the acting institute
 *     (GradeScale has no tenant scope; checked explicitly here).
 *   - route group gated by permission:education.manage.
 */
class AcademicFinalResultController extends Controller
{
    public function __construct(
        private readonly AcademicFinalResultLifecycleService $lifecycle,
        private readonly AcademicResultExportService $resultExporter,
        private readonly AcademicFinalResultPreflightService $preflight,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $schemes = AcademicResultAggregationScheme::query()
            ->with(['academicYear', 'classGrade', 'academicGroup', 'branch'])
            ->withCount('items')
            ->orderByDesc('id')
            ->get();

        $policies = AcademicFinalResultPolicy::query()
            ->whereIn('scheme_id', $schemes->pluck('id')->all())
            ->orderByDesc('id')
            ->get()
            ->keyBy('scheme_id');

        $activeResults = [];
        if ($policies->isNotEmpty()) {
            $activeResults = AcademicFinalResult::query()
                ->whereIn('policy_id', $policies->keys()->all())
                ->whereIn('status', AcademicFinalResult::ACTIVE_STATUSES)
                ->get()
                ->keyBy('policy_id')
                ->all();
        }

        return view('institute.academic-final-results.index', [
            'institute' => $institute,
            'schemes' => $schemes,
            'policies' => $policies,
            'activeResults' => $activeResults,
        ]);
    }

    public function policy(Request $request, AcademicResultAggregationScheme $scheme): View
    {
        $this->requireInstitute($request);

        $scheme->load(['academicYear', 'classGrade', 'academicGroup', 'branch']);

        $policy = $this->lifecycle->policyForScheme($scheme->institute, $scheme);
        $activeResult = $this->lifecycle->activeResult($policy);
        $history = $policy->results()->withCount(['rows', 'students'])->get();

        return view('institute.academic-final-results.policy', [
            'institute' => $scheme->institute,
            'scheme' => $scheme,
            'policy' => $policy,
            'activeResult' => $activeResult,
            // Only run the (read-only) Step-29 pre-flight when a new cycle can
            // actually be started; the verdict blocks the start form below.
            'preflight' => $activeResult === null ? $this->preflight->preflight($scheme) : null,
            'history' => $history,
            'scaleOverrides' => $this->instituteScales($policy),
        ]);
    }

    public function updatePolicy(Request $request, AcademicFinalResultPolicy $policy): RedirectResponse
    {
        $this->requireInstitute($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'absent_renormalization' => ['nullable', 'boolean'],
            'require_approval' => ['nullable', 'boolean'],
            'grade_scale_id' => ['nullable', 'integer'],
        ]);

        $gradeScaleId = $data['grade_scale_id'] ?? null;
        if (filled($gradeScaleId)) {
            $owned = GradeScale::query()->where('id', (int) $gradeScaleId)->where('institute_id', $policy->institute_id)->where('status', true)->first();
            abort_if($owned === null, 422, 'Invalid or inactive grade-scale override.');
        } else {
            $gradeScaleId = null;
        }

        $policy->update([
            'name' => trim($data['name']),
            'absent_renormalization' => (bool) ($data['absent_renormalization'] ?? true),
            'require_approval' => (bool) ($data['require_approval'] ?? true),
            'grade_scale_id' => $gradeScaleId,
        ]);

        return redirect()
            ->route('settings.academic.final-results.policy', $policy->scheme_id)
            ->with('status', 'Final-result policy updated.');
    }

    public function storeResult(Request $request, AcademicFinalResultPolicy $policy): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $result = $this->lifecycle->createResult($institute, $policy, trim($data['name']), $this->creatorId($request));

        return redirect()
            ->route('settings.academic.final-results.show', $result)
            ->with('status', 'Final-result cycle "'.$result->name.'" started — ready for review.');
    }

    public function show(Request $request, AcademicFinalResult $result): View
    {
        $this->requireInstitute($request);

        $result->load(['policy', 'scheme.academicYear', 'scheme.classGrade', 'scheme.academicGroup', 'scheme.branch']);

        return view('institute.academic-final-results.show', [
            'institute' => $result->institute,
            'result' => $result,
            'finalized' => $result->hasSnapshot(),
            'render' => $result->hasSnapshot() ? $this->renderSnapshot($result) : $this->renderPreview($result),
        ]);
    }

    public function approve(Request $request, AcademicFinalResult $result): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->lifecycle->approve($result, $this->creatorId($request));

        return back()->with('status', 'Final result approved.');
    }

    /**
     * Official, printable report card (Step 13) for a single student that is
     * part of the result's snapshot.
     *
     * Report cards are only ever produced from a PUBLISHED final result and
     * read exclusively from the frozen snapshot tables — never from live marks
     * or the derived preview. The snapshot tables are reached through the
     * tenant + branch scoped AcademicFinalResult parent, and membership in the
     * snapshot is verified, so cross-tenant / cross-branch / IDOR access to
     * another student's report card returns 404.
     */
    public function report(Request $request, AcademicFinalResult $result, StudentAcademicPlacement $placement): View
    {
        $this->requireInstitute($request);

        abort_if($result->status !== AcademicFinalResult::STATUS_PUBLISHED, 404, 'Only published final results have official report cards.');

        $snapshot = AcademicFinalResultStudent::query()
            ->where('result_id', $result->id)
            ->where('placement_id', $placement->id)
            ->first();

        abort_if($snapshot === null, 404, 'This student is not part of the published result snapshot.');

        $rows = AcademicFinalResultRow::query()
            ->where('result_id', $result->id)
            ->where('placement_id', $placement->id)
            ->with('subject')
            ->orderBy('id')
            ->get();

        $result->load(['scheme.academicYear', 'scheme.classGrade', 'scheme.academicGroup', 'scheme.branch']);
        $placement->load(['student', 'academicYear', 'classGrade', 'academicGroup']);

        // Student is tenant + branch scoped, so a null relation means the
        // placement's student is not reachable in the acting user's scope.
        abort_if($placement->student === null, 404);

        return view('institute.academic-final-results.report-card', [
            'institute' => $result->institute,
            'result' => $result,
            'placement' => $placement,
            'snapshot' => $snapshot,
            'rows' => $rows,
            'promotion' => $this->approvedPromotionVerdict($placement),
        ]);
    }

    /**
     * Official, printable class/group result sheet (Step 15) covering every
     * student that belongs to the selected PUBLISHED final result's frozen
     * snapshot.
     *
     * Produced purely from the snapshot tables (academic_final_result_students
     * and academic_final_result_rows) for that single result_id — never from
     * live marks, the derived preview, or later curriculum/grading/subject
     * configuration. Subject columns are the subjects of this exact result's
     * rows. The result is tenant + branch scoped, so route binding and the
     * nested loads keep the sheet isolated to the acting user's context.
     */
    public function resultSheet(Request $request, AcademicFinalResult $result): View
    {
        $this->requireInstitute($request);

        abort_if($result->status !== AcademicFinalResult::STATUS_PUBLISHED, 404, 'Only published final results have an official result sheet.');

        $result->load(['policy', 'scheme.academicYear', 'scheme.classGrade', 'scheme.academicGroup', 'scheme.branch']);

        $snapshots = $result->students;
        $placementIds = $snapshots->pluck('placement_id')->unique()->values()->all();

        $rowsByPlacement = $result->rows->groupBy('placement_id');

        $subjectIds = $rowsByPlacement->flatten(1)->pluck('subject_id')->unique()->values()->all();
        $subjects = Subject::query()->whereIn('id', $subjectIds)->orderBy('id')->get()->keyBy('id');

        $placements = StudentAcademicPlacement::query()
            ->with('student')
            ->whereIn('id', $placementIds)
            ->get()
            ->keyBy('id');

        $promotions = $this->approvedPromotionVerdicts($placementIds);

        $sheetRows = [];
        foreach ($snapshots as $snapshot) {
            $student = $placements->get((int) $snapshot->placement_id)?->student;

            // A student whose record is not reachable in the acting user's
            // institute/branch scope is excluded from the sheet entirely.
            if ($student === null) {
                continue;
            }

            $cells = [];
            foreach ($rowsByPlacement->get((int) $snapshot->placement_id, collect()) as $row) {
                $cells[$row->subject_id] = $row;
            }

            $sheetRows[] = [
                'placement_id' => (int) $snapshot->placement_id,
                'student' => $student,
                'cells' => $cells,
                'gpa' => $snapshot->gpa,
                'gpa_status' => $snapshot->gpa_status,
                'gpa_reason' => $snapshot->gpa_reason,
                'passed_count' => $snapshot->passed_count,
                'failed_count' => $snapshot->failed_count,
                'promotion' => $promotions->get((int) $snapshot->placement_id),
            ];
        }

        $rows = collect($sheetRows)->sortBy('placement_id')->values();

        abort_if($rows->isEmpty(), 404, 'No published snapshot students are available for this result.');

        return view('institute.academic-final-results.result-sheet', [
            'institute' => $result->institute,
            'result' => $result,
            'subjects' => $subjects,
            'rows' => $rows,
        ]);
    }

    public function sendToReview(Request $request, AcademicFinalResult $result): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->lifecycle->sendBackToReview($result, $this->creatorId($request));

        return back()->with('status', 'Final result sent back to review.');
    }

    public function lock(Request $request, AcademicFinalResult $result): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->lifecycle->lock($result, $this->creatorId($request));

        return back()->with('status', 'Final result locked. Numbers are now frozen for this cycle.');
    }

    public function publish(Request $request, AcademicFinalResult $result): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->lifecycle->publish($result, $this->creatorId($request));

        return back()->with('status', 'Final result published.');
    }

    /**
     * CSV download (Step 21) of a PUBLISHED final result, read exclusively
     * from the frozen snapshot tables — never from live marks or the derived
     * preview. The `{result}` route binding resolves through the tenant +
     * branch scoped AcademicFinalResult model, so cross-tenant / cross-branch
     * results already 404, and non-published results are rejected here.
     */
    public function export(Request $request, AcademicFinalResult $result)
    {
        $this->requireInstitute($request);

        abort_if($result->status !== AcademicFinalResult::STATUS_PUBLISHED, 404, 'Only published final results can be exported.');

        $export = $this->resultExporter->export($result);

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    // ------------------------------------------------------------- Internals

    /**
     * Live Step-9 preview, normalized to a straight table structure for the
     * show view (subject columns in covered order).
     *
     * @return array<string, mixed>
     */
    private function renderPreview(AcademicFinalResult $result): array
    {
        $preview = $this->lifecycle->preview($result);

        $rows = [];
        foreach ($preview['rows'] as $previewRow) {
            $cells = [];
            foreach ($previewRow['subjects'] as $entry) {
                $cells[] = [
                    'subject_id' => (int) $entry['subject']->id,
                    'subject' => $entry['subject']->name,
                    'status' => $entry['result']['status'],
                    'aggregate' => $entry['result']['aggregate'],
                    'grade' => $entry['result']['grade'],
                    'grade_point' => $entry['result']['grade_point'],
                    'subject_status' => $entry['result']['subject_status'],
                    'reason' => $entry['result']['incomplete_reason'],
                    'gpa_included' => (bool) ($entry['result']['gpa']['included'] ?? false),
                ];
            }

            $rows[] = [
                'placement_id' => (int) $previewRow['placement']->id,
                'student' => $previewRow['student'],
                'cells' => $cells,
                'gpa' => $previewRow['gpa']['value'],
                'gpa_status' => $previewRow['gpa']['status'],
                'gpa_reason' => $previewRow['gpa']['reason'],
            ];
        }

        return [
            'subjects' => $preview['subjects'],
            'rows' => $rows,
            'weights_valid' => $preview['weights_valid'],
            'total_weight' => $preview['total_weight'],
        ];
    }

    /**
     * Frozen snapshot render (locked / published results). Purely read from the
     * materialized rows; never re-computed.
     *
     * @return array<string, mixed>
     */
    private function renderSnapshot(AcademicFinalResult $result): array
    {
        $rowsByPlacement = $result->rows->groupBy('placement_id');
        $subjects = Subject::query()->whereIn('id', $result->rows->pluck('subject_id')->unique())->get()->keyBy('id');
        $students = $result->students->keyBy('placement_id');
        $placements = StudentAcademicPlacement::query()
            ->with(['student'])
            ->whereIn('id', $result->students->pluck('placement_id')->unique())
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($rowsByPlacement as $placementId => $subjectRows) {
            $cells = [];
            foreach ($subjectRows as $subjectRow) {
                $cells[] = [
                    'subject_id' => (int) $subjectRow->subject_id,
                    'subject' => $subjects->get($subjectRow->subject_id)?->name ?? ('Subject #'.$subjectRow->subject_id),
                    'status' => $subjectRow->status,
                    'aggregate' => $subjectRow->aggregate,
                    'grade' => $subjectRow->grade,
                    'grade_point' => $subjectRow->grade_point,
                    'subject_status' => $subjectRow->subject_status,
                    'reason' => $subjectRow->incomplete_reason,
                    'gpa_included' => (bool) $subjectRow->gpa_included,
                ];
            }
            $cells = collect($cells)->sortBy('subject_id')->values()->all();

            $studentRow = $students->get((int) $placementId);

            $rows[] = [
                'placement_id' => (int) $placementId,
                'student' => $placements->get((int) $placementId)?->student,
                'cells' => $cells,
                'gpa' => $studentRow?->gpa,
                'gpa_status' => $studentRow?->gpa_status,
                'gpa_reason' => $studentRow?->gpa_reason,
            ];
        }

        return [
            'subjects' => $subjects->sortBy('id')->values(),
            'rows' => collect($rows)->sortBy('placement_id')->values()->all(),
            'weights_valid' => true,
            'total_weight' => null,
        ];
    }

    /**
     * Institute-owned grade scales usable as a policy override.
     *
     * @return Collection<int, GradeScale>
     */
    private function instituteScales(AcademicFinalResultPolicy $policy): Collection
    {
        return GradeScale::query()
            ->with(['rows', 'academicLevel'])
            ->where('institute_id', $policy->institute_id)
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Approved promotion verdict behind the placement, or null. Pending/review
     * decisions are deliberately hidden — only frozen, approved outcomes appear
     * on the official report card.
     */
    private function approvedPromotionVerdict(StudentAcademicPlacement $placement): ?PromotionDecisionItem
    {
        return PromotionDecisionItem::query()
            ->where('placement_id', $placement->id)
            ->whereHas('decision', fn ($query) => $query->where('status', PromotionDecision::STATUS_APPROVED))
            ->with(['decision', 'targetClassGrade', 'targetAcademicGroup', 'nextPlacement.academicYear'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Latest approved promotion verdict per placement, for many placements at
     * once (result sheet scale). Pending/review decisions are never included.
     */
    private function approvedPromotionVerdicts(array $placementIds): Collection
    {
        if ($placementIds === []) {
            return collect();
        }

        return PromotionDecisionItem::query()
            ->whereIn('placement_id', $placementIds)
            ->whereHas('decision', fn ($query) => $query->where('status', PromotionDecision::STATUS_APPROVED))
            ->with(['decision', 'targetClassGrade', 'targetAcademicGroup', 'nextPlacement.academicYear'])
            ->orderBy('id')
            ->get()
            ->groupBy('placement_id')
            ->map(fn (Collection $group) => $group->last())
            ->keyBy(fn (PromotionDecisionItem $item) => (int) $item->placement_id);
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
