<?php

namespace App\Services;

use App\Models\AcademicFinalResultStudent;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\StudentAcademicPlacement;
use Illuminate\Support\Collection;

/**
 * Step 25 — Promotion decision CSV export.
 *
 * Exports one promotion decision strictly from its frozen data:
 *   - per-student verdicts + reasons = materialized promotion_decision_items
 *     (written once at decision creation, immutable until approval seals them);
 *   - GPA / failed-count / incomplete-count metrics = the PUBLISHED result's
 *     snapshot tables (academic_final_result_students + rows) — never live
 *     marks, never a recalculation. Metrics are derived through
 *     PromotionEvaluationService::inputForStudent, exactly as the decision
 *     page shows them, so the exported sheet always matches the on-screen
 *     committee review.
 *
 * Tenant + branch isolation, mirroring AcademicResultExportService:
 *   - the decision reaches this service tenant + branch scoped (route binding);
 *   - every placement/student is re-resolved through the scoped
 *     StudentAcademicPlacement query, and any item whose placement (or its
 *     student) is not reachable in the acting user's scope is skipped — it
 *     never leaks into the export.
 *
 * Strictly read-only: no rows are ever written.
 */
class PromotionDecisionExportService
{
    const VERDICT_LABELS = [
        PromotionDecisionItem::DECISION_PROMOTED => 'Promoted',
        PromotionDecisionItem::DECISION_NOT_PROMOTED => 'Not Promoted',
        PromotionDecisionItem::DECISION_CONDITIONAL => 'Conditional',
        PromotionDecisionItem::DECISION_REPEAT => 'Repeat',
        PromotionDecisionItem::DECISION_COMPLETED => 'Completed',
        PromotionDecisionItem::DECISION_GRADUATED => 'Graduated',
        PromotionDecisionItem::DECISION_PENDING => 'Pending',
    ];

    public function __construct(
        private readonly PromotionEvaluationService $evaluator
    ) {}

    /**
     * @return array{
     *     filename: string,
     *     headers: array<int, string>,
     *     rows: \Generator<int, array<int, string>>,
     * }
     */
    public function export(PromotionDecision $decision): array
    {
        $decision->load([
            'policy.academicYear',
            'policy.classGrade',
            'policy.academicGroup',
            'result.scheme.academicYear',
            'result.scheme.classGrade',
            'result.scheme.academicGroup',
        ]);

        $result = $decision->result;
        $snapshots = $result?->students()->get()->keyBy('placement_id');
        $rowsByPlacement = $result?->rows()->get()->groupBy('placement_id') ?? collect();

        $items = $decision->items()
            ->with(['placement', 'targetClassGrade', 'targetAcademicGroup', 'nextPlacement.academicYear'])
            ->orderBy('id')
            ->get();

        $placements = $this->scopedPlacements($items);

        return [
            'filename' => sprintf(
                'promotion-decision-%s-%s.csv',
                str()->slug($result?->name ?: 'decision'),
                $decision->approved_at?->format('Y-m-d') ?? $decision->created_at?->format('Y-m-d') ?? 'decision',
            ),
            'headers' => [
                '#',
                'Student',
                'Student ID',
                'Registration Number',
                'Source Academic Year',
                'Source Class / Grade',
                'Source Group / Stream',
                'GPA',
                'Subjects Failed',
                'Subjects Incomplete',
                'Verdict',
                'Reasons',
                'Placement Needed',
                'Target Class / Grade',
                'Target Group / Stream',
                'Next-Year Placement',
            ],
            'rows' => $this->rows($items, $placements, $snapshots, $rowsByPlacement),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, PromotionDecisionItem>  $items
     * @param  Collection<int, StudentAcademicPlacement>  $placements
     * @param  Collection<int, AcademicFinalResultStudent>|null  $snapshots
     * @param  Collection<int, Collection>  $rowsByPlacement
     * @return \Generator<int, array<int, string>>
     */
    private function rows($items, $placements, $snapshots, $rowsByPlacement): \Generator
    {
        $sequence = 0;

        foreach ($items as $item) {
            $placement = $placements->get((int) $item->placement_id);

            // An item whose placement (or its student) is not reachable in the
            // acting user's tenant/branch scope is excluded entirely.
            if ($placement === null || $placement->student === null) {
                continue;
            }

            $student = $placement->student;
            $sequence++;

            $snapshot = $snapshots?->get((int) $placement->id);
            $metrics = $snapshot !== null
                ? $this->evaluator->inputForStudent($snapshot, $rowsByPlacement->get((int) $placement->id)?->all() ?? [])
                : [];

            $next = $item->nextPlacement;

            yield [
                (string) $sequence,
                $student->full_name,
                $student->student_id_number ?? '',
                $student->reg_no ?? '',
                $placement->academicYear?->name ?? ('Year #'.$placement->academic_year_id),
                $placement->classGrade?->name ?? ('Class #'.$placement->class_grade_id),
                $placement->academicGroup?->name ?? '—',
                $this->gpa($snapshot, $metrics),
                (string) ($metrics['failed_count'] ?? 0),
                (string) ($metrics['incomplete_count'] ?? 0),
                self::VERDICT_LABELS[$item->decision] ?? ucfirst((string) $item->decision),
                $this->reasons($item),
                $item->needsPlacement() ? 'Yes' : 'No',
                $item->targetClassGrade?->name ?? '—',
                $item->targetAcademicGroup?->name ?? '—',
                $next !== null
                    ? trim(($next->academicYear?->name ?? '').' · '.($item->targetClassGrade?->name ?? ''), ' ·')
                    : '—',
            ];
        }
    }

    /**
     * Re-resolve placements through the tenant + branch scoped model so only
     * placements whose students are visible to the acting user are returned.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, PromotionDecisionItem>  $items
     * @return Collection<int, StudentAcademicPlacement>
     */
    private function scopedPlacements($items): Collection
    {
        $ids = $items
            ->map(fn (PromotionDecisionItem $item) => (int) $item->placement_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return StudentAcademicPlacement::query()
            ->with(['student', 'classGrade', 'academicGroup', 'academicYear'])
            ->whereIn('id', $ids)
            ->whereHas('student')
            ->get()
            ->keyBy('id');
    }

    private function gpa(?AcademicFinalResultStudent $snapshot, array $metrics): string
    {
        if ($snapshot === null) {
            return '—';
        }

        if (is_numeric($snapshot->gpa)) {
            return number_format((float) $snapshot->gpa, 2);
        }

        return ($metrics['gpa_status'] ?? null) === AcademicFinalResultStudent::GPA_COMPUTED ? '—' : 'Unavailable';
    }

    private function reasons(PromotionDecisionItem $item): string
    {
        $reasons = $item->reasons;

        if (! is_array($reasons) || $reasons === []) {
            return '—';
        }

        return implode('; ', array_map(
            static fn ($reason): string => (string) $reason,
            $reasons
        ));
    }
}
