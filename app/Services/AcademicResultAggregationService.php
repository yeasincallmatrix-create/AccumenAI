<?php

namespace App\Services;

use App\Models\AcademicAssessment;
use App\Models\AcademicGroup;
use App\Models\AcademicResultAggregationItem;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicStudentMark;
use App\Models\AcademicYear;
use App\Models\AssessmentSubject;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Institute;
use App\Models\StudentAcademicPlacement;
use App\Models\Subject;
use App\Support\BranchContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Multiple-assessment aggregation with manually configured weightage.
 *
 * A scheme is configuration only. It says which assessments participate and
 * each assessment's weight (stored once per scheme, never per assessment
 * master). Aggregation only READS existing marks; it never creates, edits or
 * deletes marks or assessments and never overwrites configured weights.
 *
 * Authoritative rules (backend only — never in Blade/JS):
 *
 *   ENTERED      the actual obtained marks are summed per subject and used.
 *   ABSENT       the assessment is excluded and the weights of the remaining
 *                ENTERED assessments are re-normalized for that subject. The
 *                absent status is preserved (a later result-policy layer may
 *                turn absence into a fail; Step 8 does not).
 *   NOT ENTERED  the whole subject aggregate is incomplete/pending and no
 *                numeric aggregate is produced and weights are NOT
 *                re-normalized (data-entry state, not student performance).
 *   ZERO         counts as a real 0 and participates in the calculation.
 *
 * Calculation:
 *   per assessment subject percentage = obtained / total_full * 100
 *   effective weight (when re-normalized) = weight / sum(entered weights) * 100
 *   aggregate = SUM( percentage * effective_weight / 100 )
 *
 * Precision:
 *   internal per-assessment percentage rounded to 4 dp
 *   effective weights kept at full float, contribution rounded to 4 dp
 *   final aggregate rounded to 2 dp (PHP_ROUND_HALF_UP)
 *   display uses the same fixed 2-dp rounding
 */
class AcademicResultAggregationService
{
    public const INTERNAL_PRECISION = 4;

    public const DISPLAY_PRECISION = 2;

    /** Aggregated subject status constants (independent of per-assessment status). */
    public const SUBJECT_AGGREGATE_COMPUTED = 'computed';

    public const SUBJECT_AGGREGATE_INCOMPLETE = 'incomplete';

    public const SUBJECT_AGGREGATE_ABSENT_ONLY = 'absent_only';

    public const SUBJECT_AGGREGATE_NOT_ELIGIBLE = 'not_eligible';

    // ------------------------------------------------------------- Eligibility

    /**
     * Students placed in the scheme's context (year + class + group),
     * additionally restricted to the acting branch when one is set.
     *
     * @return Collection<int, StudentAcademicPlacement>
     */
    public function eligiblePlacements(AcademicResultAggregationScheme $scheme): Collection
    {
        return StudentAcademicPlacement::query()
            // P3-1 — Exclude archived/soft-deleted placements from active workflows (SoftDeletes global scope)
            ->where('status', StudentAcademicPlacement::STATUS_ACTIVE)
            ->where('academic_year_id', $scheme->academic_year_id)
            ->where('class_grade_id', $scheme->class_grade_id)
            ->when($scheme->academic_group_id, fn ($q) => $q->where('academic_group_id', $scheme->academic_group_id))
            ->when(BranchContext::enabled(), fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('branch_id', BranchContext::id())))
            ->with(['student', 'selections'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Distinct subjects actually covered by the scheme's assessments. Optional
     * subjects only appear here if at least one scheme assessment includes
     * them; per-student selection still gates who gets an aggregate.
     *
     * @return array<int, int> sorted subject ids
     */
    public function coveredSubjectIds(AcademicResultAggregationScheme $scheme): array
    {
        $assessmentIds = $scheme->items()
            ->where('status', 'active')
            ->pluck('academic_assessment_id');

        $ids = AssessmentSubject::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $ids;
    }

    /**
     * Assessments that can be added to a scheme: those matching the scheme's
     * year + class + group context (academic_assessments are already tenant
     * and branch scoped by their own global scopes).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AcademicAssessment>
     */
    public function assessmentsForContext(Institute $institute, AcademicYear $year, ClassGrade $classGrade, ?AcademicGroup $group): \Illuminate\Database\Eloquent\Collection
    {
        return AcademicAssessment::query()
            ->where('academic_year_id', $year->id)
            ->where('class_grade_id', $classGrade->id)
            ->when($group?->id, fn ($q) => $q->where('academic_group_id', $group->id))
            ->with(['assessmentType', 'branch'])
            ->orderBy('display_order')->orderBy('id')
            ->get();
    }

    // ------------------------------------------------------------- Persistence

    /**
     * @param  array<int, array<string, mixed>>  $items  indexed items with assessment_id + weight
     */
    public function store(
        Institute $institute,
        ?Branch $branch,
        ?int $createdBy,
        array $data,
        array $items
    ): AcademicResultAggregationScheme {
        $year = $this->requireInstituteYear($institute, (int) $data['academic_year_id']);
        $classGrade = $this->requireClassWithinInstitute($institute, (int) $data['class_grade_id']);
        $group = $this->requireGroupWithinClass($classGrade, $data['academic_group_id'] ?? null);
        $validated = $this->validateItems($year, $classGrade, $group, $items);
        $this->assertTotalWeightForStatus($validated, $data['status'] ?? 'draft');

        return DB::transaction(function () use ($institute, $branch, $createdBy, $data, $year, $classGrade, $group, $validated) {
            $scheme = AcademicResultAggregationScheme::create([
                'institute_id' => $institute->id,
                'branch_id' => $branch?->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $classGrade->id,
                'academic_group_id' => $group?->id,
                'name' => trim($data['name']),
                'status' => $data['status'],
                'display_order' => isset($data['display_order']) && is_numeric($data['display_order'])
                    ? (int) $data['display_order']
                    : 0,
                'created_by' => $createdBy,
            ]);

            $this->syncItems($scheme, $validated);

            return $scheme->refresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function update(AcademicResultAggregationScheme $scheme, array $data, array $items): AcademicResultAggregationScheme
    {
        // C2 — Aggregation Mutability After Lock guard (identical to destroy):
        // block re-weighting a scheme that is already snapshotted in a locked/published final result.
        $hasHistorical = DB::table('academic_final_results')
            ->where('scheme_id', $scheme->id)
            ->whereIn('status', ['locked', 'published'])
            ->exists();
        if ($hasHistorical) {
            throw ValidationException::withMessages(['scheme' => 'Cannot update aggregation scheme because it is used in a locked or published final result. Historical configuration must remain readable.']);
        }

        // Also block if any assessment inside the scheme is locked (mirrors destroy guard).
        $lockedAssessmentExists = DB::table('academic_result_aggregation_items')
            ->join('academic_assessments', 'academic_assessments.id', '=', 'academic_result_aggregation_items.academic_assessment_id')
            ->where('academic_result_aggregation_items.scheme_id', $scheme->id)
            ->whereNotNull('academic_assessments.locked_at')
            ->exists();
        if ($lockedAssessmentExists) {
            throw ValidationException::withMessages(['scheme' => 'Cannot update aggregation scheme that contains locked assessments.']);
        }

        $year = $this->requireInstituteYear($scheme->institute, (int) $data['academic_year_id']);
        $classGrade = $this->requireClassWithinInstitute($scheme->institute, (int) $data['class_grade_id']);
        $group = $this->requireGroupWithinClass($classGrade, $data['academic_group_id'] ?? null);
        $validated = $this->validateItems($year, $classGrade, $group, $items);
        $this->assertTotalWeightForStatus($validated, $data['status'] ?? $scheme->status);

        DB::transaction(function () use ($scheme, $data, $year, $classGrade, $group, $validated) {
            $scheme->update([
                'academic_year_id' => $year->id,
                'class_grade_id' => $classGrade->id,
                'academic_group_id' => $group?->id,
                'name' => trim($data['name']),
                'status' => $data['status'],
                'display_order' => isset($data['display_order']) && is_numeric($data['display_order'])
                    ? (int) $data['display_order']
                    : 0,
            ]);

            $scheme->items()->delete();
            $this->syncItems($scheme, $validated);
        });

        return $scheme->refresh();
    }

    public function destroy(AcademicResultAggregationScheme $scheme): void
    {
        // Historical protection: block if referenced by locked/published final results
        $hasHistorical = DB::table('academic_final_results')
            ->where('scheme_id', $scheme->id)
            ->whereIn('status', ['locked', 'published'])
            ->exists();
        if ($hasHistorical) {
            throw ValidationException::withMessages(['scheme' => 'Cannot delete aggregation scheme that is referenced by locked or published final results. Historical configuration must remain readable.']);
        }
        // Also block if any assessment in scheme is locked (assessment.locked_at not null)
        $lockedAssessmentExists = DB::table('academic_result_aggregation_items')
            ->join('academic_assessments', 'academic_assessments.id', '=', 'academic_result_aggregation_items.academic_assessment_id')
            ->where('academic_result_aggregation_items.scheme_id', $scheme->id)
            ->whereNotNull('academic_assessments.locked_at')
            ->exists();
        if ($lockedAssessmentExists) {
            throw ValidationException::withMessages(['scheme' => 'Cannot delete aggregation scheme that contains locked assessments.']);
        }

        DB::transaction(function () use ($scheme) {
            // Lock scheme row to prevent concurrent delete vs finalize
            AcademicResultAggregationScheme::whereKey($scheme->id)->lockForUpdate()->firstOrFail();
            $scheme->delete();
        });

        // Audit
        try {
            DB::table('audit_logs')->insert([
                'institute_id' => $scheme->institute_id,
                'module' => 'academic_aggregations',
                'action' => 'aggregation_scheme_deleted',
                'record_id' => $scheme->id,
                'actor_id' => auth()->id() ?? null,
                'payload' => json_encode(['scheme_id' => $scheme->id, 'name' => $scheme->name]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {}
    }

    // ------------------------------------------------------------- Weight validation

    /**
     * Validate individual weights and the configured total. Each weight must
     * be 0..100. The route/controller decides whether an incomplete total is
     * acceptable for drafting; the calculation layer refuses to aggregate a
     * scheme whose active total is not exactly 100%.
     *
     * @throws ValidationException
     */
    public function assertItemWeights(array $items): void
    {
        foreach ($items as $index => $item) {
            $weight = (float) ($item['weight'] ?? 0);
            if ($weight < 0 || $weight > 100) {
                throw ValidationException::withMessages([
                    "items.$index.weight" => 'Weight must be between 0 and 100%.',
                ]);
            }
            // Reject non-numeric and ensure decimal handling via float comparison
            if (!is_numeric($item['weight'] ?? 0)) {
                throw ValidationException::withMessages([
                    "items.$index.weight" => 'Weight must be a numeric value.',
                ]);
            }
        }
    }

    /**
     * Central weight-total validation — exact 2-decimal check after DECIMAL(5,2).
     * DRAFT may be incomplete (any total allowed), but active/archived schemes must be exactly 100.00.
     * Zero-weight items are rejected for active schemes unless business explicitly requires them.
     */
    public function assertTotalWeightForStatus(array $items, string $status): void
    {
        $status = strtolower($status);
        if ($status === 'draft') {
            // Draft may be incomplete — only individual 0..100 already validated, total can be anything
            return;
        }
        // P2-3 — Exact 2-decimal total after DECIMAL migration (no tolerance).
        $total = round(array_sum(array_map(fn ($i) => (float) ($i['weight'] ?? 0), $items)), 2);
        if ($total !== 100.0) {
            throw ValidationException::withMessages([
                'items' => "Total weight must equal exactly 100% for active schemes (current total: ".number_format($total, 2)."%). Draft schemes may be incomplete.",
            ]);
        }
        // Reject zero-weight items for active schemes (would produce aggregate 0 silently)
        foreach ($items as $index => $item) {
            if ((float) ($item['weight'] ?? 0) == 0.0) {
                throw ValidationException::withMessages([
                    "items.$index.weight" => 'Weight must be greater than 0% for active schemes. Use Draft status for incomplete configuration.',
                ]);
            }
        }
    }

    // ------------------------------------------------------------- Aggregation

    /**
     * Aggregated score for one student placement in one subject.
     *
     * @param  bool  $renormalizeAbsent  TRUE (default): the weights of the
     *                                   remaining ENTERED assessments are re-scaled to 100% when an
     *                                   ABSENT assessment drops out. FALSE: configured weights are
     *                                   kept as-is, so a missing (absent) assessment lowers the
     *                                   achievable total. The result policy (Step 10) chooses; the
     *                                   default preserves the long-standing behavior.
     * @return array<string, mixed>
     */
    public function subjectAggregate(AcademicResultAggregationScheme $scheme, StudentAcademicPlacement $placement, int $subjectId, bool $renormalizeAbsent = true): array
    {
        $selected = $placement->selections->contains('subject_id', $subjectId);

        if (! $selected) {
            return [
                'status' => self::SUBJECT_AGGREGATE_NOT_ELIGIBLE,
                'entries' => [],
                'aggregate' => null,
                'effective_total' => null,
                'incomplete_reason' => 'Subject not in the student\'s selection.',
            ];
        }

        $items = $scheme->items()
            ->with(['assessment.subjects.subject', 'assessment.subjects.components.component', 'assessment.academicYear', 'assessment.classGrade'])
            ->where('status', 'active')
            ->get()
            ->filter(function (AcademicResultAggregationItem $item) use ($subjectId) {
                return $item->assessment->subjects->contains('subject_id', $subjectId);
            })
            ->values();

        if ($items->isEmpty()) {
            return [
                'status' => self::SUBJECT_AGGREGATE_NOT_ELIGIBLE,
                'entries' => [],
                'aggregate' => null,
                'effective_total' => null,
                'incomplete_reason' => 'No assessment in this scheme covers the subject.',
            ];
        }

        $entries = [];
        $enteredWeights = [];
        $notEntered = [];

        foreach ($items as $item) {
            $subjectConfig = $item->assessment->subjects->firstWhere('subject_id', $subjectId);

            if ($subjectConfig === null) {
                continue;
            }

            $full = (float) $subjectConfig->components->sum('full_mark');
            $marks = AcademicStudentMark::query()
                ->where('assessment_subject_id', $subjectConfig->id)
                ->where('academic_placement_id', $placement->id)
                ->get();

            $status = $this->assessmentStatus($marks, $full);

            $entry = [
                'item' => $item,
                'assessment' => $item->assessment,
                'subject' => $subjectConfig->subject,
                'original_weight' => (float) $item->weight,
                'effective_weight' => null,
                'status' => $status,
                'full' => $full,
                'obtained' => null,
                'percentage' => null,
                'attempted' => $marks->where('status', AcademicStudentMark::STATUS_ENTERED)->count() > 0,
            ];

            if ($status === AcademicStudentMark::STATUS_ENTERED) {
                $obtained = (float) $marks->where('status', AcademicStudentMark::STATUS_ENTERED)->sum('obtained_mark');
                $pct = $full > 0 ? round($obtained / $full * 100, self::INTERNAL_PRECISION) : 0.0;
                $entry['obtained'] = $obtained;
                $entry['percentage'] = $pct;
                $enteredWeights[] = (float) $item->weight;
            } elseif ($status === AcademicStudentMark::STATUS_ABSENT) {
                // excluded below; absent status preserved for later result policy
            } else {
                $notEntered[] = $item->assessment->name;
            }

            $entries[] = $entry;
        }

        if ($notEntered !== []) {
            return [
                'status' => self::SUBJECT_AGGREGATE_INCOMPLETE,
                'entries' => $entries,
                'aggregate' => null,
                'effective_total' => null,
                'incomplete_reason' => 'Marks not entered yet: '.implode(', ', array_values(array_unique($notEntered))).'.',
            ];
        }

        // Only entered assessments participate; absent ones are excluded.
        $enteredOnly = array_values(array_filter($entries, fn ($e) => $e['status'] === AcademicStudentMark::STATUS_ENTERED));

        if ($enteredOnly === []) {
            return [
                'status' => self::SUBJECT_AGGREGATE_ABSENT_ONLY,
                'entries' => $entries,
                'aggregate' => null,
                'effective_total' => null,
                'incomplete_reason' => 'Student is absent in every assessment covering this subject.',
            ];
        }

        $enteredWeightSum = array_sum($enteredWeights);
        $effectiveTotal = $enteredWeightSum > 0 ? round($enteredWeightSum, self::INTERNAL_PRECISION) : null;

        $aggregate = 0.0;
        foreach ($entries as &$entry) {
            $entry['effective_weight'] = round(
                $renormalizeAbsent ? $entry['original_weight'] / $enteredWeightSum * 100 : $entry['original_weight'],
                self::INTERNAL_PRECISION
            );
            if ($entry['status'] === AcademicStudentMark::STATUS_ENTERED && $entry['percentage'] !== null) {
                $aggregate += round($entry['percentage'] * $entry['effective_weight'] / 100, self::INTERNAL_PRECISION);
            }
        }
        unset($entry);

        return [
            'status' => self::SUBJECT_AGGREGATE_COMPUTED,
            'entries' => $entries,
            'aggregate' => round($aggregate, self::DISPLAY_PRECISION),
            'effective_total' => $effectiveTotal,
            'incomplete_reason' => null,
        ];
    }

    /**
     * Full preview: every eligible placement × every covered subject, computed
     * from the actual stored marks.
     *
     * @return array{subject: array<string, mixed>|null, rows: array<int, array<string, mixed>>, weights_valid: bool, total_weight: float}
     */
    public function preview(AcademicResultAggregationScheme $scheme, int $subjectId): array
    {
        $subject = Subject::query()->find($subjectId);
        $subjectAllowed = in_array($subjectId, $this->coveredSubjectIds($scheme), true);

        $rows = [];
        $placements = $this->eligiblePlacements($scheme);

        foreach ($placements as $placement) {
            $rows[] = [
                'placement' => $placement,
                'student' => $placement->student,
                'selected' => $placement->selections->contains('subject_id', $subjectId),
                'aggregate' => $subjectAllowed ? $this->subjectAggregate($scheme, $placement, $subjectId) : null,
            ];
        }

        return [
            'subject' => $subjectAllowed ? $subject : null,
            'rows' => $rows,
            'weights_valid' => $scheme->weightIsValid(),
            'total_weight' => $scheme->totalWeight(),
        ];
    }

    // ------------------------------------------------------------- Internals

    /**
     * Aggregate per-assessment status from the stored marks. Three distinct
     * states: ENTERED (at least one component actually entered, so even an
     * all-zero entry participates), ABSENT (rows exist but every row is an
     * explicit absent), NOT_ENTERED (no rows at all / data-entry pending).
     */
    private function assessmentStatus(Collection $marks): string
    {
        if ($marks->isEmpty()) {
            return 'not_entered';
        }

        if ($marks->where('status', AcademicStudentMark::STATUS_ENTERED)->isNotEmpty()) {
            return AcademicStudentMark::STATUS_ENTERED;
        }

        return AcademicStudentMark::STATUS_ABSENT;
    }

    /**
     * Validate items (assessments valid for the scheme context, no duplicates)
     * and return a normalized structure.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function validateItems(AcademicYear $year, ClassGrade $classGrade, ?AcademicGroup $group, array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Select at least one assessment for this scheme.']);
        }

        $seen = [];
        $normalized = [];

        foreach ($items as $index => $item) {
            $assessmentId = (int) ($item['assessment_id'] ?? 0);
            $weight = (float) ($item['weight'] ?? 0);

            if ($weight < 0 || $weight > 100) {
                throw ValidationException::withMessages([
                    "items.$index.weight" => 'Weight must be between 0 and 100%.',
                ]);
            }

            if (isset($seen[$assessmentId])) {
                throw ValidationException::withMessages([
                    "items.$index.assessment_id" => 'Each assessment can only appear once in a scheme.',
                ]);
            }
            $seen[$assessmentId] = true;

            $assessment = $this->requireAssessmentInContext($year, $classGrade, $group, $assessmentId);

            $normalized[] = [
                'academic_assessment_id' => $assessment->id,
                'weight' => $weight,
                'display_order' => (int) ($item['display_order'] ?? 0),
                'status' => ($item['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            ];
        }

        return $normalized;
    }

    private function syncItems(AcademicResultAggregationScheme $scheme, array $items): void
    {
        foreach ($items as $index => $item) {
            AcademicResultAggregationItem::create([
                'scheme_id' => $scheme->id,
                'academic_assessment_id' => $item['academic_assessment_id'],
                'weight' => $item['weight'],
                'display_order' => $item['display_order'] ?: $index + 1,
                'status' => $item['status'],
            ]);
        }
    }

    private function requireAssessmentInContext(AcademicYear $year, ClassGrade $classGrade, ?AcademicGroup $group, int $assessmentId): AcademicAssessment
    {
        $assessment = AcademicAssessment::query()
            ->where('academic_year_id', $year->id)
            ->where('class_grade_id', $classGrade->id)
            ->when($group?->id, fn ($q) => $q->where('academic_group_id', $group->id))
            ->find($assessmentId);

        if ($assessment === null) {
            throw ValidationException::withMessages([
                'items' => 'One of the selected assessments is not valid for this academic context.',
            ]);
        }

        return $assessment;
    }

    private function requireInstituteYear(Institute $institute, int $yearId): AcademicYear
    {
        $year = AcademicYear::query()->where('institute_id', $institute->id)->find($yearId);

        if ($year === null) {
            throw ValidationException::withMessages(['academic_year_id' => 'Invalid academic year for this institute.']);
        }

        return $year;
    }

    private function requireClassWithinInstitute(Institute $institute, int $classGradeId): ClassGrade
    {
        $service = app(AcademicSubjectService::class);

        foreach ($service->effectiveClasses($institute) as $entry) {
            if ((int) $entry['class_grade']->id === $classGradeId) {
                return $entry['class_grade'];
            }
        }

        throw ValidationException::withMessages(['class_grade_id' => 'Invalid class / grade for this institute.']);
    }

    private function requireGroupWithinClass(ClassGrade $classGrade, int|string|null $groupId): ?AcademicGroup
    {
        if (! filled($groupId)) {
            return null;
        }

        $group = $classGrade->groups()->where('status', true)->find((int) $groupId);

        if ($group === null) {
            throw ValidationException::withMessages(['academic_group_id' => 'Invalid group / stream.']);
        }

        return $group;
    }
}
