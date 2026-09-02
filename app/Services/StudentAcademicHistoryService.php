<?php

namespace App\Services;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultRow;
use App\Models\AcademicFinalResultStudent;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds the read model for the Student Academic History page.
 *
 * Everything shown comes from frozen snapshots (published final results and
 * approved promotion decisions) — never from live marks — so the page is a
 * stable record of what the student achieved each year. Tenant + branch
 * isolation is preserved by reaching every unscoped snapshot table through a
 * tenant-scoped parent (AcademicFinalResult / PromotionDecision).
 */
class StudentAcademicHistoryService
{
    /**
     * All academic years that carry a placement of this student (for the
     * filter dropdown), ordered with the most recent first.
     */
    public function academicYears(Student $student): Collection
    {
        return $student->academicPlacements()
            ->with('academicYear')
            ->get()
            ->pluck('academicYear')
            ->filter()
            ->unique('id')
            ->sortByDesc(fn ($year) => $year->start_date?->toDateString() ?? $year->id)
            ->values();
    }

    /**
     * Timeline payload for the student, ordered oldest year → newest year.
     *
     * Each timeline entry:
     *   placement    StudentAcademicPlacement
     *   isCurrent    bool — the latest placement in the returned set
     *   snapshot     AcademicFinalResultStudent|null (published GPA snapshot)
     *   result       AcademicFinalResult|null (published result behind snapshot)
     *   rows         Collection<AcademicFinalResultRow> (snapshot's per-subject rows)
     *   inProgress   bool — a non-published result cycle is underway for this context
     *   hasPromotion bool
     *   promotion    PromotionDecisionItem|null (approved verdict only)
     */
    public function forStudent(Student $student, ?int $academicYearId = null): array
    {
        $academicYears = $this->academicYears($student);

        $placements = $this->placements($student, $academicYearId);

        if ($placements->isEmpty()) {
            return [
                'student' => $student,
                'timeline' => collect(),
                'academicYears' => $academicYears,
            ];
        }

        $placementIds = $placements->map(fn ($placement) => (int) $placement->id)->all();

        $snapshots = $this->publishedSnapshots($placementIds);
        $rowsByPlacement = $this->snapshotRows($snapshots);
        $inProgressContexts = $this->inProgressContexts($placements);
        $promotions = $this->decidedPromotions($placementIds);

        $lastIndex = $placements->count() - 1;

        $timeline = $placements->map(function (StudentAcademicPlacement $placement, int $index) use ($snapshots, $rowsByPlacement, $inProgressContexts, $promotions, $lastIndex) {
            $placementId = (int) $placement->id;
            $snapshot = $snapshots->get($placementId);

            return [
                'placement' => $placement,
                'isCurrent' => $index === $lastIndex,
                'snapshot' => $snapshot,
                'result' => $snapshot?->result,
                'rows' => $rowsByPlacement->get($placementId, collect()),
                'inProgress' => ! $snapshot && $inProgressContexts->contains($this->contextKey($placement)),
                'hasPromotion' => $promotions->has($placementId),
                'promotion' => $promotions->get($placementId),
            ];
        })->values();

        return [
            'student' => $student,
            'timeline' => $timeline,
            'academicYears' => $academicYears,
        ];
    }

    private function placements(Student $student, ?int $academicYearId = null): Collection
    {
        return $student->academicPlacements()
            ->with(['academicYear', 'classGrade', 'academicGroup', 'selections.subject'])
            ->when($academicYearId, fn (Builder $query) => $query->where('academic_year_id', $academicYearId))
            ->get()
            ->sort(function (StudentAcademicPlacement $a, StudentAcademicPlacement $b) {
                $aKey = $a->academicYear?->start_date?->toDateString() ?? '9999-12-31';
                $bKey = $b->academicYear?->start_date?->toDateString() ?? '9999-12-31';
                $cmp = strcmp($aKey, $bKey);

                return $cmp !== 0 ? $cmp : ($a->id <=> $b->id);
            })
            ->values();
    }

    /**
     * Latest published GPA snapshots, keyed by placement_id. Only results that
     * are visible in the current tenant + branch context are considered.
     */
    private function publishedSnapshots(array $placementIds): Collection
    {
        return AcademicFinalResultStudent::query()
            ->whereIn('placement_id', $placementIds)
            ->whereHas('result', fn (Builder $query) => $query->where('status', AcademicFinalResult::STATUS_PUBLISHED))
            ->with(['result.scheme.academicYear'])
            ->orderBy('result_id')
            ->get()
            ->keyBy('placement_id');
    }

    /**
     * Per-placement subject rows of the exact snapshot chosen per placement.
     */
    private function snapshotRows(Collection $snapshots): Collection
    {
        $rows = AcademicFinalResultRow::query()
            ->whereIn('result_id', $snapshots->pluck('result_id')->unique()->values()->all())
            ->whereHas('result')
            ->with('subject')
            ->orderBy('id')
            ->get();

        return $rows
            ->groupBy('placement_id')
            ->map(function (Collection $placementRows) use ($snapshots) {
                $placementId = (int) $placementRows->first()->placement_id;
                $resultId = $snapshots->get($placementId)?->result_id;

                return $resultId !== null
                    ? $placementRows->where('result_id', (int) $resultId)->values()
                    : collect();
            });
    }

    /**
     * Context keys (year:class:group) that have a non-published final result in
     * the current tenant + branch scope, i.e. a result cycle that is underway.
     */
    private function inProgressContexts(Collection $placements): Collection
    {
        $contexts = $placements
            ->map(fn ($placement) => $this->contextKey($placement))
            ->unique()
            ->values();

        if ($contexts->isEmpty()) {
            return collect();
        }

        return AcademicFinalResult::query()
            ->whereIn('status', AcademicFinalResult::ACTIVE_STATUSES)
            ->whereHas('scheme', function (Builder $query) use ($contexts) {
                $query->where(function (Builder $inner) use ($contexts) {
                    foreach ($contexts as $context) {
                        [$yearId, $classId, $groupId] = explode(':', $context);
                        $inner->orWhere(function (Builder $match) use ($yearId, $classId, $groupId) {
                            $match->where('academic_year_id', (int) $yearId)
                                ->where('class_grade_id', (int) $classId);

                            if ($groupId === '') {
                                $match->whereNull('academic_group_id');
                            } else {
                                $match->where('academic_group_id', (int) $groupId);
                            }
                        });
                    }
                });
            })
            ->with('scheme')
            ->get()
            ->map(fn (AcademicFinalResult $result) => $this->schemeContextKey($result))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Approved promotion verdict per placement (pending/review items are
     * deliberately hidden — they are work-in-progress, not history).
     */
    private function decidedPromotions(array $placementIds): Collection
    {
        $items = PromotionDecisionItem::query()
            ->whereIn('placement_id', $placementIds)
            ->whereHas('decision', fn (Builder $query) => $query->where('status', PromotionDecision::STATUS_APPROVED))
            ->with(['decision', 'targetClassGrade', 'targetAcademicGroup', 'nextPlacement.academicYear'])
            ->orderBy('id')
            ->get();

        return $items
            ->groupBy('placement_id')
            ->map(fn (Collection $group) => $group->last());
    }

    private function contextKey(StudentAcademicPlacement $placement): string
    {
        return (string) $placement->academic_year_id
            .':'.(string) $placement->class_grade_id
            .':'.(string) ($placement->academic_group_id ?? '');
    }

    private function schemeContextKey(AcademicFinalResult $result): string
    {
        $scheme = $result->scheme;

        return $scheme !== null
            ? (string) $scheme->academic_year_id
                .':'.(string) $scheme->class_grade_id
                .':'.(string) ($scheme->academic_group_id ?? '')
            : '';
    }
}
